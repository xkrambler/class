/*

	Class xtabs v0.1b, JavaScript basic tabs.
	By Pablo Rodríguez Rey (at mr @ xkr dot(.) es) 21/11/2010.

	Example HTML:
		<div id='tabs'>
			<ul>
				<li>Tab 1</li>
				<li>Tab 2</li>
			</ul>
			<div data-xname='tab_name_1'>
				Content of first tab.
			</div>
			<div data-xname='tab_name_2'>
				Content of second tab.
			</div>
		</div>

	Example JS:
		var tabs=new xtabs("tabs");
		tabs.go(1); // go second tab
		tabs.go("tab_name_1"); // go first tab
		alert(tabs.actual); // get current tab name
		alert(tabs.tab); // get current tab index
		tabs.onclick=function(pos,tab){ alert(pos+" "+tab); }; // event called on tab change

	Construction:
		var tabs=new xtabs(id, options);
			id
				String identifier of the tab container.
			options (optional)
				Object with the options.

	Options:
		restore (Boolean)
			Enabled if last tab is going to be saved and restored using a cookie.
		vertical (Boolean)
			Tabs in vertical (true) or horizontal (false) by default.
		cookieName (String)
			Set restore cookie name.
		start
			First tab.
		width (style String)
			Set content style width.
		height (style String)
			Set content style height.
		onclick(Integer tab_index, String name, self)
			Event function called when tab is changed.

	Variables:
		.actual (String)
			Returns current tab name.
		.tab (Integer)
			Returns current tab index.

	Functions:
		.go(Integer index / String name) (Boolean)
			Go tab number 0..(n-1) or with attribute data-xname="name".
		.getIndex(Integer index / String name) (Integer)
			Get tab index by name.
		.getTab(Integer index / String name) (Element/Boolean)
			Get tab element.
		.getContent(Integer index / String name) (Element/Boolean)
			Get content element of a tab.
		.visible(Integer index / String name) (Boolean)
			Get tab visibility of a tab.
		.visible(Integer index / String name, Boolean visible) (Boolean)
			Set tab visibility of a tab.
		.hasClass(Element element, String className) (Boolean)
			Helper to check if an element has a class applied.
		.refresh()
			Refresh active tab.

*/
function xtabs(id, o) {
	var self=this;

	self.xnames={};
	self.actual="";
	self.tab=0;
	self.o=(o?o:{});
	self.onclick=(self.o.onclick?self.o.onclick:null);
	self.cookieName=location.href+"#"+id;

	self.go=function(rn) {

		var n=self.getIndex(rn);

		for (i=0;;i++) {
			var t=gid(self.id+"_t"+i);
			var c=gid(self.ids[i]);
			if (!t) break;
			t.className=(t.style.backgroundImage?"xtab_icon ":"")+(i==n?"xtab_active":"");
			if (selectionEnabled) selectionEnabled(t,false);
			var tabhide=(t.getAttribute("data-hide")=="1"?true:false);
			t.style.display=(tabhide?"none":(self.o.vertical?"block":"inline"));
			if (c) {
				c.className="xtab";
				c.style.display=(i==n?"block":"none");
				if (i==n) self.actual=(c.getAttribute("data-xname")?c.getAttribute("data-xname"):(c.getAttribute("xname")?c.getAttribute("xname"):c));
			}
		}

		self.tab=n;
		if (n>=0 && rn!=-1) {
			if (self.o.restore) setCookie(self.cookieName, self.tab);
			if (self.onclick) self.onclick(self.tab, self.actual, self);
		}

	};

	self.getIndex=function(n){
		for (var i=0;;i++) {
			var t=gid(self.id+"_t"+i);
			var c=gid(self.ids[i]);
			if (!t || !c) break;
			if (n === i || (typeof(n)=="string" && (c.getAttribute("data-xname")==n || c.getAttribute("xname")==n))) return i;
		}
		return false;
	};

	self.getContent=function(n){
		var i=self.getIndex(n);
		if (i === false) return false;
		return gid(self.ids[i]);
	};

	self.getTab=function(n){
		var i=self.getIndex(n);
		if (i === false) return false;
		return gid(self.id+"_t"+i);
	};

	self.visible=function(n, is_visible){
		var t=self.getTab(n);
		if (!t) return false;
		if (isset(is_visible)) {
			t.setAttribute("data-hide", (is_visible?"":"1"));
			self.refresh();
		}
		return (t.getAttribute("data-hide")?false:true);
	};

	self.hasClass=function(element, c) {
		var e=element.className.split(" ");
		for (var i=0; i<e.length; i++)
			if (e[i]==c)
				return true;
		return false;
	};

	self.refresh=function() {
		self.go(self.tab);
	};

	self._init=function(){

		var lastTab=getCookie(self.cookieName);
		self.id=id;
		self.ids=[];
		gid(id).className="xtabs";
		var ul=gid(self.id).getElementsByTagName("ul")[0];
		ul.className=(self.o.vertical?"xtabs_vertical":"xtabs_horizontal");
		var tabs=ul.getElementsByTagName("li");
		for (i=0;i<tabs.length;i++) {
			if (tabs[i].parentNode.parentNode.id != self.id) continue;
			tabs[i].id=self.id+"_t"+i;
			tabs[i].setAttribute("data-index", i);
			tabs[i].setAttribute("tabindex", "0");
			tabs[i].onkeypress=function(e){
				if (!e) var e=window.event;
				if (e.keyCode == 13 || e.keyCode == 32) {
					var i=this.getAttribute("data-index");
					self.go(parseInt(i));
				}
			};
			tabs[i].onmousedown=function(){
				var i=this.getAttribute("data-index");
				self.go(parseInt(i));
			};
		}

		self.go(-1); // first set, needed for content calculations

		var tabs=gid(id).getElementsByTagName("div");
		for (i=0; i<tabs.length; i++) {
			if (tabs[i].parentNode.id!=self.id) continue;
			tabs[i].className="xtab";
			if (!tabs[i].id) tabs[i].id=self.id+"_c"+i;
			self.ids.push(tabs[i].id);
			var xname=(tabs[i].getAttribute("data-xname")?tabs[i].getAttribute("data-xname"):tabs[i].getAttribute("xname"));
			if (xname) self.xnames[xname]=self.ids.length-1;
			if (self.o.width) tabs[i].style.width=self.o.width;
			if (self.o.height) tabs[i].style.height=self.o.height;
			if (self.o.vertical) tabs[i].style.marginLeft=(getWidth(ul)-1)+"px";
		}

		if (self.o.restore && (typeof(self.o.start)=="undefined" || self.o.start === false) && lastTab) {
			self.go(lastTab?parseInt(lastTab):0);
		} else {
			self.go(self.o.start?self.o.start:0);
		}

	};

	self._init();

}
