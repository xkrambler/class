function smoothScroll(){
	var a=this;
		
	// We do not want this script to be applied in browsers that do not support those
	// That means no smoothscroll on IE9 and below.
	if(document.querySelectorAll === void 0 || window.pageYOffset === void 0 || history.pushState === void 0) { return; }

	// Get the top position of an element in the document
	var getTop = function(element) {
	    // return value of html.getBoundingClientRect().top ... IE : 0, other browsers : -pageYOffset
	    if(element.nodeName === 'HTML') return -window.pageYOffset
	    return element.getBoundingClientRect().top + window.pageYOffset;
	}
	// ease in out function thanks to:
	// http://blog.greweb.fr/2012/02/bezier-curve-based-easing-functions-from-concept-to-implementation/
	var easeInOutCubic = function (t) { return t<.5 ? 4*t*t*t : (t-1)*(2*t-2)*(2*t-2)+1 }

	// calculate the scroll position we should be in
	// given the start and end point of the scroll
	// the time elapsed from the beginning of the scroll
	// and the total duration of the scroll (default 500ms)
	var position = function(start, end, elapsed, duration) {
	  if (elapsed > duration) return end;
	  return start + (end - start) * easeInOutCubic(elapsed / duration); // <-- you can change the easing funtion there
	  // return start + (end - start) * (elapsed / duration); // <-- this would give a linear scroll
	}

	// we use requestAnimationFrame to be called by the browser before every repaint
	// if the first argument is an element then scroll to the top of this element
	// if the first argument is numeric then scroll to this location
	// if the callback exist, it is called when the scrolling is finished
	a.smoothScroll = function(base, el, duration, callback){
	    duration = duration || 500;
	    var start = window.pageYOffset;

	    if (typeof el === 'number') {
	      var end = parseInt(el);
	    } else {
	      var end = getTop(el);
	    }

	    var clock = Date.now();
	    var requestAnimationFrame = window.requestAnimationFrame ||
	        window.mozRequestAnimationFrame || window.webkitRequestAnimationFrame ||
	        function(fn){window.setTimeout(fn, 15);};

	    var step = function(){
	        var elapsed = Date.now() - clock;
	        //window.scroll(0, position(start, end, elapsed, duration));
	        base.scrollTop=position(start, end, elapsed, duration);
	        if (elapsed > duration) {
	            if (typeof callback === 'function') {
	                callback(el);
	            }
	        } else {
	            requestAnimationFrame(step);
	        }
	    }
	    step();
	};

};


var ss=new smoothScroll();


var xani={
	"timer_refresh":10,
	"scrollTo":function(o){
		var a=this;
		if (!o) return;
		if (typeof(o)=="string") var o={"id":o};
		o.scroll=(o.scroll?gid(o.scroll):document.documentElement);
		if (o.waitfor) {
			var check=function(){
				if (gid(o.waitfor)) {
					delete a._timeout;
					o.id=o.waitfor;
					delete o.waitfor;
					xani.scrollTo(o);
				} else {
					a._timeout=setTimeout(check, xani.timer_refresh);
				}
			}
			check();
			return;
		}
		if (!o.id || !(o.id=gid(o.id))) return;
		if (o.view) {
			o.id.scrollIntoView();
			return;
		}
		if (typeof(o.duration)=="undefined") o.duration=750;//ms
		var top=getTop(o.id)-getTop(o.scroll);
		var requestAnimationFrame
			= window.requestAnimationFrame
			|| window.mozRequestAnimationFrame
			|| window.webkitRequestAnimationFrame
			|| function(fn){ a._timeout=setTimeout(fn, xani.timer_refresh); }
		;
		var maxScroll=(o.scroll.scrollHeight?(o.scroll.scrollHeight-o.scroll.offsetHeight):false);
		a.finish=function(){
			if (a._timeout) {
				clearTimeout(a._timeout);
				delete a._timeout;
			}
			o.id.style.position="relative";
			if (gid(o.id)) o.scroll.scrollTop=top;
		};
		if (o.duration) {
			var start=new Date().getTime();
			var origen=o.scroll.scrollTop;
			var d=(top-parseInt(o.scroll.scrollTop));
			if (maxScroll && d>maxScroll) d=maxScroll;
			var ani=function(){
				var p=((new Date().getTime())-start)/o.duration;
				var np=origen+(d*(1-(1-p)*(1-p)*(1-p)*(1-p)));
				if (Math.abs(np-top)<2) p=1;
				if (p>=1) {
					a.finish();
				} else {
					o.scroll.scrollTop=np;
					a._timeout=requestAnimationFrame(ani);
				}
			}
			ani();
		} else {
			o.scroll.scrollTop=top;
		}
	}
};
