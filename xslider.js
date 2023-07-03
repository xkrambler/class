/*

	xSlider, simple div slider. Example:

	<div class='xslider' id='slider' style='width:1000px;height:512px;'>
		<div class='xslide' style='background:url(index1.jpg)'></div>
		<div class='xslide' style='background:url(index2.jpg)'></div>
		<div class='xslide' style='background:url(index3.jpg)'></div>
	</div>
	
	<script>
		var slider=new xslider({
			"id":"slider"
		});
	</script>
	
	Other options:
		"duration":3000, // duration between transitions (in miliseconds) - if 0, no automatic transitions are done
		"durationSmall":1000, // duration between transition when leaving from paused mode (in miliseconds)
		"transition":1000, // transition time (in miliseconds) - if 0, instant transitions
		"effect":"alpha left left-alpha left-alpha-exp right right-alpha right-exp9 up up-alpha up-alpha-out5", // do the next effects
		"effect":["left","right"], // random choose one effect 
		"slides":[ // automatically create DIV slides
			{"img":"index1.jpg","effect":"up"},
			{"img":"index2.jpg","effect":"alpha"},
			{"img":"index2.jpg","effect":"down"}
		],
		"start":0, // start always in this slide ("random" allowed)
		"nopause":true, // no pause when mouse over a slide
		"nolinks":true, // no render previous and next links
	
	Events:
		"onstartslide":function(xslider, index, xslide) // at the start of the slide transition
		"onslide":function(xslider, index, xslide) // at the end of the slide transition
		"onclick":function(xslider, index) // slider click

*/

function xslider(o) {
	var a=this;
	if (isset(o.duration) && !o.duration) o.noduration=true;
	if (!o.duration || o.duration<0) o.duration=3000;
	if (!o.durationSmall || o.durationSmall<0) o.durationSmall=1000;
	if (isset(o.transition) && !o.transition) o.transition=1;
	if (!o.transition || o.transition<0) o.transition=1000;
	if (!isset(o.start)) {
		if (!isset(o.cookie) || o.cookie===true) o.cookie="xslider_"+gid(o.id).id;
		var start=parseInt(getCookie(o.cookie+"_start"));
		o.start=(start?start:0);
	}
	if (!o.base) o.base="";
	if (!o.prev) o.prev={};
	if (!o.next) o.next={};
	if (!o.go) o.go={};
	if (!o.effect) o.effect="left-alpha-exp4";
	if (!o.prev.effect) o.prev.effect="right-alpha-exp9";
	if (!o.next.effect) o.next.effect="left-alpha-exp9";
	if (!o.go.effect) o.go.effect="alpha";
	if (!o.prev.transition) o.prev.transition=500;
	if (!o.next.transition) o.next.transition=500;
	if (!o.go.transition) o.go.transition=250;
	a.o=o;
	a._setAlpha=function(e,p){
		try {
			if (isie()) {
				if (p>.95) e.style.filter="";
				else e.style.filter="alpha(opacity="+parseInt(p*100)+")";
			}
		} catch(e) {}
		e.style.opacity=p;
	};
	a.transitionEffect=function(d1,d2,p,effect){
		if (!a.d[d1]) return;
		var w=a.d[d1].offsetWidth;
		var h=a.d[d1].offsetHeight;
		var mod_alpha=false;
		var e=effect.split("-");
		if (effect) {
			var in_array=function(a,e){
				for (var i in a)
					if (a[i]==e)
						return true;
				return false;
			};
			if (in_array(e,"alpha")) mod_alpha=true;
			if (in_array(e,"exp") || in_array(e,"exp2")) var p=1-((1-p)*(1-p));
			else if (in_array(e,"exp3")) var p=1-((1-p)*(1-p)*(1-p));
			else if (in_array(e,"exp4")) var p=1-((1-p)*(1-p)*(1-p)*(1-p));
			else if (in_array(e,"exp5")) var p=1-((1-p)*(1-p)*(1-p)*(1-p)*(1-p));
			else if (in_array(e,"exp9")) var p=1-((1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p));
			if (in_array(e,"out") || in_array(e,"out2")) var p=p*p;
			else if (in_array(e,"out3")) var p=p*p*p;
			else if (in_array(e,"out4")) var p=p*p*p*p;
			else if (in_array(e,"out5")) var p=p*p*p*p*p;
			else if (in_array(e,"out9")) var p=p*p*p*p*p*p*p*p*p;
			if (in_array(e,"inout") || in_array(e,"inout2")) var p=(p<=0.5?p*p*2:1-((1-p)*(1-p)*2));
			else if (in_array(e,"inout3")) var p=(p<=0.5?p*p*p*4:1-((1-p)*(1-p)*(1-p)*4));
			else if (in_array(e,"inout4")) var p=(p<=0.5?p*p*p*p*8:1-((1-p)*(1-p)*(1-p)*(1-p)*8));
			else if (in_array(e,"inout5")) var p=(p<=0.5?p*p*p*p*p*16:1-((1-p)*(1-p)*(1-p)*(1-p)*(1-p)*16));
			else if (in_array(e,"inout9")) var p=(p<=0.5?p*p*p*p*p*p*p*p*p*256:1-((1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*(1-p)*256));
			if (p<0.0001) p=0;
			if (p>0.9999) p=1;
		} else {
			p=1; // si no hay efecto, solo hay 1 frame de transición
		}
		a.d[d1].style.visibility=(p==1?"hidden":"visible");
		a.d[d2].style.visibility="visible";
		switch (e[0]) {
		case "up":
			a.d[d1].style.top="-"+parseInt(p*h)+"px";
			a.d[d2].style.top=parseInt((1-p)*h)+"px";
			a.d[d1].style.left="0px";
			a.d[d2].style.left="0px";
			break;
		case "down":
			a.d[d1].style.top=parseInt(p*h)+"px";
			a.d[d2].style.top="-"+parseInt((1-p)*h)+"px";
			a.d[d1].style.left="0px";
			a.d[d2].style.left="0px";
			break;
		case "left":
			a.d[d1].style.left="-"+parseInt(p*w)+"px";
			a.d[d2].style.left=parseInt((1-p)*w)+"px";
			a.d[d1].style.top="0px";
			a.d[d2].style.top="0px";
			break;
		case "right":
			a.d[d1].style.left=parseInt(p*w)+"px";
			a.d[d2].style.left="-"+parseInt((1-p)*w)+"px";
			a.d[d1].style.top="0px";
			a.d[d2].style.top="0px";
			break;
		case "alpha":
		default:
			a.d[d1].style.left="0px";
			a.d[d2].style.left="0px";
			a.d[d1].style.top="0px";
			a.d[d2].style.top="0px";
		}
		a._setAlpha(a.d[d1],(mod_alpha?1-p:1));
		a._setAlpha(a.d[d2],(mod_alpha?p:1));
		//gidset("depuracion",e[0]+"<br>"+a.d[d1].style.top+"<br>"+a.d[d2].style.top)
		return (p==1?true:false);
	};
	a.getNext=function(reverse){
		if (reverse) {
			var next=a.o.start-1;
			if (next<0) next=a.o.slides.length-1;
			return next;
		} else {
			return (a.o.start+1)%a.o.slides.length;
		}
	};
	a.autoheightCheck=function(){
		if (!a.o.autoheight) return;
		var h=0;
		for (var i in a.d)
			if (a.d[i].offsetHeight>h)
				h=a.d[i].offsetHeight;
		if (h<a.o.minheight) h=a.o.minheight;
		a.o.id.style.height=h+"px";
		setTimeout(a.autoheightCheck,40);
	},
	a.finishTransition=function(){
		if (a.doTransitionTimer) {
			clearTimeout(a.doTransitionTimer);
			a.doTransition(array_merge(a.lastTransitionOptions,{"end":true}));
			delete a.doTransitionTimer;
		}
	},
	a.doTransition=function(options){
		if (!options.end) a.lastTransitionOptions=options;
		var next=(isset(options.next)?options.next:a.getNext(false));
		var p=0;
		var transition=(options.transition?options.transition:a.o.transition);
		if (options.start && a.timer) {
			if (a.doTransitionTimer) clearTimeout(a.doTransitionTimer);
			p=1;
		}
		options.start=false;
		if (!a.timer) {
			if (a.o.onstartslide) a.o.onstartslide(a, next, a.slide(next));
			a.timer=new Date().getTime();
			if (o.cookie) setCookie(o.cookie+"_start",next);
			var effects="";
			a.doTransitionEffect=(options.effect?options.effect:(a.o.slides[next].effect?a.o.slides[next].effect:(a.o.effect?a.o.effect:"")));
			if (a.doTransitionEffect.indexOf && a.doTransitionEffect.indexOf(" ")!=-1) {
				var effects=a.doTransitionEffect.split(" ");
				a.doTransitionEffect=effects[next%effects.length];
			} else if (typeof(a.doTransitionEffect)=="object" && typeof(a.doTransitionEffect.length)=='number')
				a.doTransitionEffect=a.doTransitionEffect[parseInt(Math.random()*a.doTransitionEffect.length)];
		} else {
			p=((new Date().getTime())-a.timer)/transition; if (p>1 || options.end) p=1;
		}
		if (a.transitionEffect(a.o.start,next,p,a.doTransitionEffect)) p=1;
		if (p<1) a.doTransitionTimer=setTimeout(function(){ a.doTransition(options); },1);
		else {
			a.timer=0;
			a.o.start=next;
			if (a.o.onslide) a.o.onslide(a, next, a.slide(next));
			if (a.o.duration && !options.end) a.startTimer();
		}
	};
	a.endTimer=function(){
		if (a.timeout) {
			a.timeout=clearTimeout(this.timeout);
			delete a.timeout;
		}
	};
	a.startTimer=function(options){
		if (a.o.slides.length <= 1 || a.o.noduration) return;
		var options=(options?options:{});
		a.endTimer();
		a.timeout=setTimeout(function(){ a.doTransition({"start":true}); },(options.duration?options.duration:a.o.duration));
	};
	a.actual=function(){
		return a.o.start;
	};
	a.slide=function(i){
		var i=(isset(i)?i:a.o.start);
		return a.d[i];
	};
	a.slides=function(){
		return a.o.slides;
	};
	a.num=function(){
		return a.o.slides.length;
	};
	a._prev_click=function(e){ a.prev(); e.preventDefault(); e.stopPropagation(); return false; };
	a.prev=function(){
		a.finishTransition();
		a.endTimer();
		a.doTransition({"start":true,"next":a.getNext(true),"effect":a.o.prev.effect,"transition":a.o.prev.transition});
	};
	a._next_click=function(e){ a.next(); e.preventDefault(); e.stopPropagation(); return false; };
	a.next=function(){
		a.finishTransition();
		a.endTimer();
		a.doTransition({"start":true,"next":a.getNext(false),"effect":a.o.next.effect,"transition":a.o.next.transition});
	};
	a.go=function(next,options){
		a.finishTransition();
		a.endTimer();
		a.doTransition({"start":true,"next":next,"effect":(options && options.effect?options.effect:a.o.go.effect),"transition":a.o.go.transition});
	};
	a.start=function(){
		a.startTimer();
	};
	a.stop=function(){
		a.finishTransition();
		a.endTimer();
	};
	a.init=function(){
		a.o.id=gid(a.o.id);
		if (!a.o.id) return false;
		if (!a.o.slides) a.o.slides=[];
		if (!a.o.id.style.position) a.o.id.style.position="relative";
		a.o.id.style.overflow="hidden";
		a.d=[];
		// touch support
		if (!a.o.notouch && window.addEventListener) {
			a.o.id.addEventListener("touchstart",function(e){
				if (e.changedTouches.length == 1) {
					var touchobj=e.changedTouches[0];
					a._startX=touchobj.pageX;
					a._startY=touchobj.pageY;
					if (a.d[a.o.start]) a.d[a.o.start].style.transform="";
					//e.preventDefault();
				}
			});
			a.o.id.addEventListener("touchmove",function(e){
				if (e.changedTouches.length == 1) {
					var touchobj=e.changedTouches[0];
					var dist=Math.floor((touchobj.pageX - a._startX)/4);
					if (Math.abs(dist) > 5) e.preventDefault();
					if (a.d[a.o.start]) a.d[a.o.start].style.transform="translate("+dist+"px,0)";
				}
			});
			a.o.id.addEventListener("touchend",function(e){
				if (e.changedTouches.length == 1) {
					var touchobj=e.changedTouches[0];
					if (a.d[a.o.start]) a.d[a.o.start].style.transform="";
					var dist=(touchobj.pageX - a._startX);
					if (Math.abs(dist) > 50) {
						if (dist < 0) a.next();
						else a.prev();
					}
					delete a._startX;
					delete a._startY;
					//e.preventDefault();
				}
			});
		}
		// get slider divs
		if (a.o.id.getElementsByClassName) var divs=a.o.id.getElementsByClassName("xslide");
		else {
			var divs=[];
			var div=document.getElementsByTagName("div");
			for (var i=0; i<div.length; i++) {
				var c=div[i].className.split(" ");
				for (var j in c)
					if (c[j] == "xslide")
						if (div[i].parentNode == a.o.id)
							divs.push(div[i]);
			}
		}
		var max=(divs.length>a.o.slides.length?divs.length:a.o.slides.length);
		if (a.o.start=="random") a.o.start=Math.floor(Math.random()*max);
		a.o.start=(a.o.start % max);
		for (var i=0; i<max; i++) {
			if (!a.o.slides[i]) a.o.slides[i]={};
			a.d[i]=(i<divs.length?divs[i]:a.d[i]=document.createElement("div"));
			style(a.d[i], {
				"position":"absolute",
				"display":"block", // if displayed block, images inside are preloaded
				"visibility":"visible",
				"top":"0px",
				"left":(i==a.o.start?"0px":"100%"), // only visible first
				"opacity":(i==a.o.start?"1":"0")
			});
			if (a.o.slides[i] && a.o.slides[i].img)
				style(a.d[i],{"backgroundImage":"url("+a.o.base+a.o.slides[i].img+")"});
			if (i >= divs.length) a.o.id.appendChild(a.d[i]);
			if (a.o.onadd) a.o.onadd(a, i, divs[i]);
		}
		if (!a.o.nolinks && a.o.slides.length>1) {
			var links={
				"prevlink":{"onclick":a._prev_click},
				"nextlink":{"onclick":a._next_click}
			};
			for (var link in links) {
				var l=links[link];
				a[link]=document.createElement("div");
				a[link].className="xslider_"+link;
				a[link].onclick=l.onclick;
				a.o.id.appendChild(a[link]);
				selectionEnabled(a[link],false);
			}
		}
		if (a.o.onclick) {
			a.o.id.style.cursor="pointer";
			a.o.id.onclick=function(){
				a.o.onclick(a, a.o.start);
			};
		}
		if (!a.o.noduration) {
			a.startTimer();
			if (!a.o.nopause) {
				mouseenter(a.o.id,function(event){ a.endTimer(); });
				mouseleave(a.o.id,function(event){ a.startTimer({"duration":a.o.durationSmall}); });
			}
		}
		if (a.o.onstartslide) a.o.onstartslide(a, a.o.start);
		if (a.o.autoheight) a.autoheightCheck();
	};
	a.init();
}
