/*
	xpop v0.2b by mr.xkr

	Examples:
		xpop({"class":"xxxxxx","background":"#CFC","ico":"images/ico16/ok.png","msg":"User saved!"});
		xpop("<span class='fa fa-times'></span> A typical toast");
		xpopok("Saved ok!");
		xpopwarn("This is a warning!");
		xpoperr("This is an error!");
*/

var xpops={};

// crea un mensaje popup centrado en la parte superior de la página
function xpop(o) {
	if (typeof(o)=="string") var o={"msg":o};
	if (!this.c) this.c=0;
	this.c++;
	o.c=this.c;
	if (!o.id) o.id="";
	if (!isset(o.timeout) || o.timeout<1) o.timeout=2500;
	xpop_destroy(o.id);
	o.e=document.createElement("div");
	o.e.className="xpop noselect"+(o.class?" "+o.class:"");
	o.e.onclick=function(){
		if (o.onclick) o.onclick(o.id, o);
		else xpop_close(o.id);
	};
	/*o.e.innerHTML
		="<table class='xpop_table'>"
		+"<tr><td><div class='xpop_dialog'"
			+" style='"
				+(o.color?"color:"+o.color+";":"")
				+(o.background?"background:"+o.background+";":"")
			+"'>"
				+"<span class='xpop_caption"+(o.ico?" xpop_icon":"")+"' "+(o.ico!==true?" style='background-image:url("+o.ico+");'":"")+">"
					+o.msg
				+"</span>"
			+"</div></td></tr>"
		+"</table>"
	;*/
	o.e.innerHTML=""
		+"<div class='xpop_dialog'"
			+" style='"
				+(o.color?"color:"+o.color+";":"")
				+(o.background?"background:"+o.background+";":"")
			+"'>"
			+"<span class='xpop_caption"+(o.ico?" xpop_icon":"")+"' "+(o.ico!==true?" style='background-image:url("+o.ico+");'":"")+">"
				+o.msg
			+"</span>"
		+"</div>"
	;
	document.body.appendChild(o.e);
	if (isset(o.timeout) && o.timeout>0) {
		window.requestAnimationFrame(function(){
			window.requestAnimationFrame(function(){
				if (xpops[o.id] && xpops[o.id].c==o.c) {
					classAdd(o.e, "xpop_in");
					xpops[o.id].closeTimer=setTimeout(function(){
						xpop_close(o.id);
					}, o.timeout);
				}
			});
		});
	}
	xpops[o.id]=o;
	return o.id;
}

// destruye un popup previo
function xpop_destroy(id) {
	if (xpops[id]) {
		if (xpops[id].closeTimer) {
			clearTimeout(xpops[id].closeTimer);
			delete xpops[id].closeTimer;
		}
		if (xpops[id].e) xpops[id].e.parentNode.removeChild(xpops[id].e);
		if (xpops[id].onclose) xpops[id].onclose(id, xpops[id]);
		delete xpops[id];
	}
}

// cierra un popup por su identificador
function xpop_close(id) {
	if (!id) var id="";
	if (!xpops[id]) return false;
	var s=document.createElement('p').style, supportsTransitions='transition' in s;
	if (supportsTransitions) {
		var e=xpops[id].e;
		classDel(e, "xpop_in");
		classAdd(e, "xpop_out");
		e.addEventListener("transitionend", function(){ xpop_destroy(id); }, true);
	} else {
		xpop_destroy(id);
	}
	return true;
}

// diversos mensajes comunes
function xpopok(msg, onclose)   { xpop({"class":"xpop_ok",   "ico":true, "msg":msg, "onclose":onclose}); }
function xpoperr(msg, onclose)  { xpop({"class":"xpop_err",  "ico":true, "msg":msg, "onclose":onclose}); }
function xpopwarn(msg, onclose) { xpop({"class":"xpop_warn", "ico":true, "msg":msg, "onclose":onclose}); }
