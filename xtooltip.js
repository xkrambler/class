// crea un popup de información
var xtooltip={

	"hide":function(){
		hide(xtooltip.div);
	},

	"destroy":function(){
		xtooltip.timedDestroyClear();
		xtooltip.timerClear();
		if (xtooltip.div) {
			xtooltip.div.parentNode.removeChild(xtooltip.div);
			delete xtooltip.div;
		}
	},

	"timer":function(){
		xtooltip.resize();
		xtooltip._timer=setTimeout(xtooltip.timer, 1);
	},

	"timerClear":function(){
		if (xtooltip._timer) {
			clearTimeout(xtooltip._timer);
			delete xtooltip._timer;
		}
	},

	"timedDestroy":function(){
		xtooltip.timedDestroyClear();
		xtooltip._timerDestroy=setTimeout(xtooltip.destroy, 250);
	},

	"timedDestroyClear":function(){
		if (xtooltip._timerDestroy) {
			clearTimeout(xtooltip._timerDestroy);
			delete xtooltip._timerDestroy;
		}
	},

	"resize":function(){
		if (xtooltip.div && xtooltip.o.div) {
			var l=getLeft(xtooltip.o.div);
			var t=getTop(xtooltip.o.div);
			var h=getHeight(xtooltip.o.div);
			var position=xtooltip.o.position;
			if (!position || position == "auto") position=((t+h/2) > windowHeight()/2?"top":"bottom");

			var th=getHeight(xtooltip.div);

			xtooltip.div.className="xtooltip xtooltip_"+position;
			switch (position) {
			case "top":
				l-=32;
				t-=getHeight(xtooltip.div)+getBorderHeight(xtooltip.div)+getPaddingHeight(xtooltip.div);
				break;
			case "bottom":
				l-=32;
				t+=h;
				break;
			case "left":
				t=t-32-12;
				l-=getWidth(xtooltip.div);
				break;
			case "right":
				t=t-32-12;
				l+=getWidth(xtooltip.o.div);
				break;
			}
			if (l<0) l=0;
			if (t<0) t=0;
			style(xtooltip.div, {"left":l+"px", "top":t+"px"});
		}
	},

	"show":function(o){
		o.div=gid(o.div);
		if (!o.msg) o.msg=o.div.alt;
		if (!o.msg) o.msg=o.div.title;
		if (!o.position) o.position="";
		xtooltip.o=o;
		var isie=function(){ return (navigator.userAgent.indexOf("MSIE")!=-1); };

		var mh=(windowHeight()/2.5);

		xtooltip.destroy();

		xtooltip.div=document.createElement("div");
		xtooltip.div.className="xtooltip";
		xtooltip.div.innerHTML
			="<div class='xtooltip_t'>"
				+"<div class='xtooltip_tl'><div class='xtooltip_tr'><div class='xtooltip_ti'></div></div></div>"
			+"</div>"
			+"<div class='xtooltip_lb'><div class='xtooltip_li'><div class='xtooltip_rb'><div class='xtooltip_ri'>"
				+"<div class='xtooltip_c'><div class='xtooltip_body"+(o.ico?" xtooltip_body_ico":"")+"' style='max-height:"+mh+"px;"+(o.ico?"background-image:url("+o.ico+")":"")+"'>"
					+xtooltip.o.msg
				+"</div></div>"
			+"</div></div></div></div>"
			+"<div class='xtooltip_b'>"
				+"<div class='xtooltip_bl'><div class='xtooltip_br'><div class='xtooltip_bi'></div></div></div>"
			+"</div>"
		;
		document.body.appendChild(xtooltip.div);
		show(xtooltip.div);

		xtooltip.resize();
		xtooltip.timer();

	}

};

resize(function(){ xtooltip.resize(); })
scroll(function(){ xtooltip.resize(); })
