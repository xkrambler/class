/*
	xtree class 0.1c
*/
var xtrees={};
function xtree(o) {
	if (!o) return false;
	var self=this;
	self.o=o;
	self.noevents=false;
	self.id=gid(self.o.id);
	self.tree=(self.o.tree?self.o.tree:[]);
	self.data={};

	// return if a route is checked
	self.checked=function(route, check){
		self.data[route].node.checked=!check;
		self.noevents=true;
		self.swap(route);
		self.noevents=false;
	};

	// get checkbox class by its state
	self.getCheckboxClass=function(route){
		var node=self.data[route].node;
		var partial=self.data[route].partial;
		var selectable=(typeof(node.selectable) == "undefined"?true:node.selectable);
		return "xtree_ico"+(selectable?" xtree_clickable":"")+" xtree_ico_"
			+(selectable
				?(node.checked
					?"checked"
					:(partial
						?"partial"
						:"check"
					)
				)
				:(partial
					?"disabled_partial"
					:"disabled"
				)
			)
		;
	};

	// get connection class by its state
	self.getConnectionClass=function(route){
		var node=self.data[route].node;
		return ""
			+"xtree_expcon xtree_ico_"
			+(node.nodes
				?(node.expanded
					?(node.last?"minus":"minusbottom")
					:(node.last?"plus":"plusbottom")
				)
				:(node.last?"join":"joinbottom")
			)
			+(node.nodes?"":" xtree_expcon_nodes")
		;
	};

	// swap a node
	self.swap=function(route){
		var id="tree_"+self.id.id+"_checkbox_"+route;
		var e=self.data[route];
		e.node.checked=!e.node.checked;
		if (gid(id)) gid(id).className=self.getCheckboxClass(route);
		var lp=e.par;
		if (lp) {
			for (var j=0; j < e.n; j++) {
				var id="tree_"+self.id.id+"_checkbox_"+lp.route;
				var ne=self.data[lp.route];
				self.data[lp.route].partial=(ne.partial?ne.partial:0)+(e.node.checked?1:-1);
				if (gid(id)) gid(id).className=self.getCheckboxClass(lp.route);
				lp=self.data[lp.route].par;
			}
		}
		if (!self.noevents && e.node.onchange) {
			e.node.onchange(e.node, e, self);
			self.update();
		}
		if (self.o.onchange) self.o.onchange(self, route, e.node);
	};

	// check if a route is expanded
	self.expanded=function(route, expand){
		if (!route) self.expcon();
		else {
			self.data[route].node.expanded=!expand;
			self.expcon(route);
		}
	};

	// expand swap
	self.expcon=function(route){
		if (route) {
			var id="tree_"+self.id.id+"_exp_"+route;
			var cmd="tree_"+self.id.id+"_expcon_"+route;
			var e=self.data[route];
			e.node.expanded=!e.node.expanded;
			if (gid(id)) {
				if (e.node.expanded) show(id); else hide(id);
				gid(cmd).className=self.getConnectionClass(route);
			}
		} else {
			var recexpand=function(t, expand){
				for (var i in t) {
					if (expand)
						t[i].expanded=false;
					else
						if (t[i].checked || self.data[t[i].route].partial)
							t[i].expanded=true;
					if (t[i].nodes)
						recexpand(t[i].nodes,expand);
				}
			};
			var someexpanded=false;
			for (var i in self.tree.nodes)
				if (self.tree.nodes[i].expanded)
					someexpanded=true;
			recexpand(self.tree.nodes,someexpanded);
			self.update();
		}
	};

	// evento de click
	self.onclick=function(route){
		var ni=route.substring(1).split(".");
		var n=self.tree;
		for (var i in ni)
			n=n.nodes[ni[i]];
		o.onclick(n, self);
	};

	// recursiva de cálculo
	self.calc=function(p, t, n, r){
		if (typeof(self.tree.expanded) == "undefined") self.tree.expanded=true;
		function rec(p, t, n, r){
			if (!n) self.data={};
			var nchecked=0;
			for (var i in t) {
				var node=t[i];
				var route=r+"."+i;
				var len=t.length-1;
				i=parseInt(i);
				node.route=route;
				self.data[route]={
					"route":route,
					"i":i,
					"n":n,
					"last":(i < len?false:true),
					"par":p,
					"base":t,
					//"partial":0,
					"node":node
				};
				if (node.checked) {
					var lp=p;
					for (var j=0; j<n; j++) {
						var e=self.data[lp.route];
						e.partial=(e.partial?e.partial+1:1);
						lp=self.data[lp.route].par;
					}
					nchecked++;
				}
				if (node.nodes) rec(node, node.nodes, n+1, route);
			}
		};
		rec(null, self.tree.nodes, 0, "");
	};

	// update tree
	self.update=function(){
		self.calc();
		var h=""
			+"<div class='xtree'>"
			+(typeof(self.tree.caption) == "undefined"?"":""
				+"<div>"
					+"<span class='xtree_ico xtree_ico_tree'></span>"
					+"<a class='xtree_caption' href='javascript:void(xtrees[\""+self.id.id+"\"].expcon())'>"+(self.o.render?self.o.render(self, self.tree):self.tree.caption)+"</a>"
				+"</div>"
			)
		;
		// recursiva principal
		var rec=function(p, t, n, r){
			h+="<div id='tree_"+self.id.id+"_exp_"+r+"' class='' style='display:"+(p.expanded?"block":"none")+";'>"
			h+="<ul>";
			for (var i in t) {
				var node=t[i];
				var route=r+"."+i;
				var len=t.length-1;
				var selectable=(typeof(node.selectable) == "undefined"?true:node.selectable);
				i=parseInt(i);
				h+="<li class='xtree_li"+(i < len?" xtree_ico_line":"")+(n?" xtree_li_mar":"")+"'>";
				var expcon_link="xtrees[\""+self.id.id+"\"].expcon(\""+route+"\")";
				var checkbox_link=(selectable?"xtrees[\""+self.id.id+"\"].swap(\""+route+"\")":"");
				h+="<div class='xtree_item"+(i < len?" xtree_ico_line":"")+"'>";
					h+="<div id='tree_"+self.id.id+"_expcon_"+route+"' class='"+self.getConnectionClass(route)+"'>";
					if (node.nodes)
						h+="<span class='xtree_ico xtree_ico_empty xtree_clickable' onClick='"+expcon_link+"'></span>"
					if (!o.nochecks && (!self.o.checkonlyleafs || (self.o.checkonlyleafs && !node.nodes)) && (!self.o.checkbox || self.o.checkbox(self, route, node)))
						h+="<span id='tree_"+self.id.id+"_checkbox_"+route+"' class='"+self.getCheckboxClass(route, node)+"' onClick='"+checkbox_link+";'></span>"
					if (node.ico) h+="<span class='xtree_ico' style='background-image:url("+node.ico+");'></span>";
					h+=""
						+"<a class='xtree_caption' href='javascript:void("+(o.onclick
							?'xtrees["'+self.id.id+'"].onclick("'+route+'")'
							:(node.nodes?expcon_link:(checkbox_link?checkbox_link:0))
						)+");' onkeypress='if(event.keyCode == 32){"+checkbox_link+";return(false);}'>"
							+(self.o.render?self.o.render(self, node):node.caption)
						+"</a>"
					;
					h+=(node.extra?node.extra:"");
					h+="<div style='clear: both;'></div>";
					h+="</div>";
				h+="</div>";
				if (node.nodes) rec(node, node.nodes, n+1, route);
				h+="</li>";
			}
			h+="</ul>";
			h+="</div>";
		}
		rec(self.tree, self.tree.nodes, 0, "");
		h+="</div>";
		gidset(self.id,h);
	};

	// get/set values
	self.values=function(newvalues){
		if (newvalues) {
			var setvalues=function(t){
				for (var i in t) {
					t[i].checked=(typeof(newvalues[t[i].value])!="undefined"?true:false);
					if (t[i].nodes) setvalues(t[i].nodes);
				}
			}
			setvalues(self.tree.nodes);
			self.update();
			return true;
		} else {
			var v=[];
			var getvalues=function(t){
				for (var i in t) {
					if (t[i].checked) v.push(typeof(t[i].value)!="undefined"?t[i].value:t[i].route);
					if (t[i].nodes) getvalues(t[i].nodes);
				}
			};
			getvalues(self.tree.nodes);
			return v;
		}
	};

	// defered update
	self.deferedupdate=function(){
		var s=this;
		if (s.timeout) clearTimeout(s.timeout);
		s.timeout=setTimeout(function(){
			self.update();
			delete s.timeout;
		}, 1);
	};

	// add node to route
	self.add=function(route, node){
		var base=(route?self.data[route].node:self.tree);
		if (!base.nodes) base.nodes=[];
		var i=base.nodes.push(node)-1;
		self.calc();
		self.deferedupdate();
		return base.nodes[i].route;
	};

	// delete node from route
	self.del=function(route){
		if (!self.data[route]) return false;
		self.data[route].base.splice(self.data[route].i,1);
		delete self.data[route];
		self.deferedupdate();
		return true;
	};

	// expand selected level
	self.expandedlevel=function(level, expand){
		var recexpand=function(t, l){
			for (var i in t.nodes) {
				t.nodes[i].expanded=true;
				if (l < level && t.nodes[i].nodes)
					recexpand(t.nodes[i], l+1);
			}
		};
		recexpand(self.tree, 1)
		self.deferedupdate();
	};

	// autoexpand
	self.autoexpand=function(){
		self.expcon();
	};

	// initialize
	self.init=function(){
		if (self.o.values) self.values(self.o.values);
		if (self.o.expanded) self.expandedlevel(self.o.expanded, true);
		self.update();
		if (self.o.autoexpand) self.autoexpand();
	};

	xtrees[self.id.id]=this;
	self.init();

}
