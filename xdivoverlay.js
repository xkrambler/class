// xdivoverlay.js by mr.xkr v0.1 rev.1
function xdivOverlay(o) {
	var a=this;
	a.o=o=(o?o:{});
	a.o.attach=o.attach=gid(o.attach);
	a.div=null;
	a.visible=false;
	a.cancelHide=false;
	a.install=function(){
		if (this.installed) return;
		this.installed=true;
		mouseup(function(){
			if (a.cancelHide) {
				a.cancelHide=false;
				return;
			}
			a.hide(true);
		});
	};
	a.show=function(){
		if (a.visible && a.div) {
			visible(a.div);
			return;
		}
		a.install();
		var creatediv=(!a.div?true:false);
		if (a.o.attach) {
			a.o.x=getLeft(a.o.attach);
			a.o.y=getTop(a.o.attach)+getHeight(a.o.attach);
			if (a.o.fixw) a.o.w=getWidth(a.o.attach);
		}
		if (creatediv) a.div=document.createElement("div");
		a.div.className="xdivoverlay"+(a.o.attach?" xdivoverlay_"+a.o.attach.id:"");
		style(a.div,{
			"display":"block",
			"position":"absolute",
			"left":a.o.x+"px",
			"top":a.o.y+"px"
		});
		if (a.o.w) style(a.div,{"width":a.o.w+"px"});
		if (a.o.h) style(a.div,{"height":a.o.h+"px"});
		if (creatediv) gidset(a.div,"");
		show(a.div);
		visible(a.div);
		a.visible=true;
		a.div.onmousedown=function(){
			a.hideCancel();
			a.cancelHide=true;
		};
		if (creatediv) document.body.appendChild(a.div)
		if (o.ontext)
			o.ontext(a,gidval(a.o.attach));
		a.hideCancel();
		// reposicionar, si con el tamaño excede el ancho de la ventana
		var rw=getWidth(a.div);
		if (a.o.x+rw>windowWidth()) {
			a.o.x=windowWidth()-rw;
			style(a.div,{"left":a.o.x+"px"});
		}
	};
	a.hideCancel=function(){
		if (a.hideTimer) {
			clearTimeout(a.hideTimer);
			delete a.hideTimer;
		}
	};
	a.hide=function(local){
		if (local && a.cancelHide) return;
		a.hideCancel();
		a.hideTimer=setTimeout(function(){
			if (a.div) {
				hide(a.div);
			}
			a.visible=false;
		},1);
	};
	a.deattach=function(){
		if (a.div) hide(a.div);
		if (a.o.attach) {
			a.o.attach.onmousedown=null;
			a.o.attach.onfocus=null;
			a.o.attach.onblur=null;
			a.o.attach.onkeyup=null;
			delete a.o.attach;
		}
	};
	a.attach=function(where){
		a.deattach();
		a.o.attach=gid(where);
		a.o.attach.onmousedown=function(){ a.cancelHide=true; }
		a.o.attach.onfocus=function(){ a.focused=true; a.show(); hidden(a.div); }
		a.o.attach.onblur=function(){ a.focused=false; a.hide(true); }
		if (o.ontext)
			a.o.attach.onkeyup=function(e) {
				if (!e) var e=window.event;
				//if (gidval(a.o.attach)) visible(a.div); else hidden(a.div);
				o.ontext(a,gidval(a.o.attach),e);
			}
	};
	a.destroy=function(){
		if (a.div && gid(a.div))
		gid(a.div).parentNode.removeChild(gid(a.div));
		delete a.div;
	};
	if (a.o.attach) a.attach(a.o.attach);
}
