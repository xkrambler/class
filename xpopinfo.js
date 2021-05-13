// crea un popup de información
function xpopinfo(o) {
	var a=this;
	o.attach=gid(o.attach);
	o.div=gid(o.div);
	if (!o.position) o.position="";
	a.o=o;
	a.isie=function(){ return (navigator.userAgent.indexOf("MSIE")!=-1); };
	a.create=function(){
		if (!a.div || !gid(a.div)) {
			a.div=document.createElement("div");
			a.div.className="xpopinfo";
			a.div.innerHTML
				="<div class='xpopinfo_t'>"
					+"<div class='xpopinfo_tl'><div class='xpopinfo_tr'><div class='xpopinfo_ti'></div></div></div>"
				+"</div>"
				+"<div class='xpopinfo_lb'><div class='xpopinfo_li'><div class='xpopinfo_rb'><div class='xpopinfo_ri'>"
					+"<div class='xpopinfo_c'><div class='xpopinfo_body'>"
						+a.o.msg
					+"</div></div>"
				+"</div></div></div></div>"
				+"<div class='xpopinfo_b'>"
					+"<div class='xpopinfo_bl'><div class='xpopinfo_br'><div class='xpopinfo_bi'></div></div></div>"
				+"</div>"
			;
			if (a.o.autohide)
				a.div.addEventListener("click",function(event) { a.hide(); },true);
			document.body.appendChild(a.div);
		}
		a.resize();
	};
	a.position=function(position){
		if (!a.div || !gid(a.div)) return;
		var ref=(a.o.attach || a.o.div);
		if (ref) {
			var l=getLeft(ref);
			var t=getTop(ref);
			var w=getWidth(ref);
			var h=getHeight(ref);
			var nw=164;
			var window_width=windowWidth();
			var recomended_width=parseInt(window_width/3);
			a.div.className="xpopinfo xpopinfo_"+position;
			switch (position) {
			case "top":
				l-=32;
				var w=getWidth(a.div);
				h=getHeight(a.div)+getBorderHeight(a.div)+getPaddingHeight(a.div);
				t-=h;
				break;
			case "bottom":
				l-=32;
				t+=h;
				break;
			case "left":
				var laux=l;
				t-=(32+8);
				w=parseInt(l);
				if (w<nw) w=nw;
				if (w>recomended_width) w=recomended_width;
				l-=w;
				if (l<0) l=0;
				if (w-l>laux) w=laux;
				style(a.div,{"width":w+"px"});
				break;
			case "right":
				t-=(32+8);
				l+=w;
				var w=(window_width-l);
				if (w<nw) w=nw;
				if (w>recomended_width) w=recomended_width;
				style(a.div,{"width":w+"px"});
				break;
			}
			style(a.div,{"left":l+"px","top":t+"px"});
		}
	};
	a.resize=function(){
		if (!a.div || !gid(a.div)) return;
		var ref=(a.o.attach || a.o.div);
		if (ref) {
			var l=getLeft(ref);
			var t=getTop(ref);
			var w=getWidth(ref);
			var h=getHeight(ref);
			var nw=164;
			var scroll_left=scrollLeft();
			var scroll_top=scrollTop();
			var window_width=windowWidth();
			var window_height=windowHeight();
			var recomended_width=parseInt(window_width/3);
			var position=""+a.o.position;
			if (!position || position=="auto") {
				position="top";
			}
			a.position(position);
			//var dl=getLeft(a.div);
			//var dt=getTop(a.div);
			//var dw=getWidth(a.div)+getBorderWidth(a.div)+getPaddingWidth(a.div);
			//var dh=getHeight(a.div)+getBorderHeight(a.div)+getPaddingHeight(a.div);
			// reposicionamiento en pruebas
			//if (dt+dh-scroll_top>window_height && position=="bottom") a.position("top");
			//else if (dt+dh-scroll_top>window_height && position=="top") a.position("bottom");
			//else if (scroll_left>dl && position=="left") a.position("right");
		}
	};
	a.hide=function(){
		hide(a.div);
	};
	a.destroy=function(){
		a.div.parentNode.removeChild(a.div);
	};
	a.show=function(){
		a.create();
		show(a.div);
		a.resize();
	};
	a.init=function(){
		if (!a.o.msg) a.o.msg="";
		if (a.o.attach) {
			if (a.o.attach.title) a.o.msg=a.o.attach.title;
			if (a.isie()) {
				a.o.attach_onfocus=a.o.attach.onfocus;
				a.o.attach_onblur=a.o.attach.onblur;
				a.o.attach.onfocus=function(){ if (a.o.attach_onfocus) a.o.attach_onfocus(); a.show(); };
				a.o.attach.onblur=function(){ if (a.o.attach_onblur) a.o.attach_onblur(); a.hide(); };
			} else {
				a.o.attach.addEventListener("focus",function(event) { a.show(); },true);
				a.o.attach.addEventListener("blur",function(event) { a.hide(); },true);
			}
		}
		resize(function(){ a.resize(); })
		scroll(function(){ a.resize(); })
	};
	a.init();
	if (o.div) a.show();
}
