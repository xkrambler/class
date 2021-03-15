/* xtree class */

var xtrees={};
function xtree(o) {
	if (!o) return false;
	var a=this;
	a.o=o;
	a.noevents=false;
	a.id=gid(a.o.id);
	a.tree=(a.o.tree?a.o.tree:[]);
	if (typeof(a.tree.expanded)=="undefined") a.tree.expanded=true;
	a._extra={};
	a.base=(a.o.base?a.o.base:"images/xtree16/");
	a.imgs={
		"empty":"empty.png",
		"line":"line.png",
		"minus":"minus.png",
		"plus":"plus.png",
		"join":"join.png",
		"minusbottom":"minusbottom.png",
		"plusbottom":"plusbottom.png",
		"joinbottom":"joinbottom.png",
		"checkbox":"checkbox.png",
		"checkboxok":"checkboxok.png",
		"checkboxmed":"checkboxmed.png"
	};
	a.checked=function(route,check){
		a._extra[route].node.checked=!check;
		a.noevents=true;
		a.swap(route);
		a.noevents=false;
	};
	a.swap=function(route){
		var id="tree_"+a.id.id+"_checkbox_"+route;
		var e=a._extra[route];
		e.node.checked=!e.node.checked;
		if (gid(id))
			gid(id).src=a.base+(e.node.checked?a.imgs.checkboxok:(e.partial?a.imgs.checkboxmed:a.imgs.checkbox));
		var lp=e.par;
		if (lp) {
			for (var j=0;j<e.n;j++) {
				var id="tree_"+a.id.id+"_checkbox_"+lp.route;
				var ne=a._extra[lp.route];
				ne.partial=(ne.partial?ne.partial:0)+(e.node.checked?1:-1);
				if (gid(id))
					gid(id).src=a.base+(lp.checked?a.imgs.checkboxok:(ne.partial?a.imgs.checkboxmed:a.imgs.checkbox));
				lp=a._extra[lp.route].par;
			}
		}
		if (!a.noevents && e.node.onchange) {
			e.node.onchange(e.node,e,a);
			a.update();
		}
	};
	a.expanded=function(route,expand){
		if (!route) a.expcon();
		else {
			a._extra[route].node.expanded=!expand;
			a.expcon(route);
		}
	};
	a.expcon=function(route){
		if (route) {
			var id="tree_"+a.id.id+"_exp_"+route;
			var cmd="tree_"+a.id.id+"_expcon_"+route;
			var e=a._extra[route];
			e.node.expanded=!e.node.expanded;
			if (gid(id)) {
				if (e.node.expanded) show(id); else hide(id);
				style(cmd,{"backgroundImage":"url('"+a.base+(e.node.expanded
					?(e.last?a.imgs.minusbottom:a.imgs.minus)
					:(e.last?a.imgs.plusbottom:a.imgs.plus)
				)+"')"});
			}
		} else {
			var recexpand=function(t,expand){
				for (var i in t) {
					if (expand)
						t[i].expanded=false;
					else
						if (t[i].checked || a._extra[t[i].route].partial)
							t[i].expanded=true;
					if (t[i].nodes)
						recexpand(t[i].nodes,expand);
				}
			};
			var someexpanded=false;
			for (var i in a.tree.nodes)
				if (a.tree.nodes[i].expanded)
					someexpanded=true;
			recexpand(a.tree.nodes,someexpanded);
			a.update();
		}
	};
	// evento de click
	a.onclick=function(route){
		var ni=route.substring(1).split(".");
		var n=a.tree;
		for (var i in ni)
			n=n.nodes[ni[i]];
		o.onclick(n,a);
	};
	// recursiva de cálculo
	a.calc=function(p,t,n,r){
		//if (!this.h) this.h="";
		if (!n) a._extra={};
		var nchecked=0;
		for (var i in t) {
			var route=r+"."+i;
			i=parseInt(i);
			t[i].route=route;
			a._extra[route]={
				"route":route,
				"i":i,
				"n":n,
				"last":((i+1)<t.length?false:true),
				"par":p,
				"base":t,
				//"partial":0,
				"node":t[i]
			};
			if (t[i].checked) {
				var lp=p;
				for (var j=0;j<n;j++) {
					//if (!lp.checked) {
						var e=a._extra[lp.route];
						e.partial=(e.partial?e.partial+1:1);
					//}
					lp=a._extra[lp.route].par;
				}
				nchecked++;
			}
			//this.h+="<div style='padding-left:"+(n*20)+"px;'>"+t[i].caption+" ("+(t[i].nodes?"MAS":"")+")</div>";
			if (t[i].nodes)
				a.calc(t[i],t[i].nodes,n+1,route);
			//this.h+="<div style='padding-left:"+(n*20)+"px;'>"+t[i].caption+" ("+(t[i].nodes?"MAS":"")+")</div>";
		}
		//gidset(a.id,this.h);
	};
	a.recalc=function(){
		a.calc(null,a.tree.nodes,0,"");
	};
	a.update=function(){
		a.recalc();
		var h
			="<div class='xtree'>"
			+(typeof(a.tree.caption)=="undefined"
				?""
				:"<div><img src='"+(a.tree.ico?a.tree.ico:a.base+"tree.png")+"' align='absmiddle' width='16' height='16' alt='' />"
					+" <a href='javascript:void(xtrees[\""+a.id.id+"\"].expcon())'>"+(a.o.render?a.o.render(a, a.tree):a.tree.caption)+"</a>"
					+"</div>"
			);
		// recursiva principal
		var rec=function(p,t,n,r){
			h+="<div id='tree_"+a.id.id+"_exp_"+r+"' style='display:"+(p.expanded?"block":"none")+";'>"
			h+="<ul>";
			for (var i in t) {
				i=parseInt(i);
				var route=r+"."+i;
				if ((i+1)<t.length) {
					h+="<li class='xtree_li"+(n?" xtree_li_mar":"")+"' style='background:url("+a.base+a.imgs.line+") repeat-y left top;'>";
				} else {
					h+="<li class='xtree_li"+(n?" xtree_li_mar":"")+"'>";
				}
				var ico=a.base+(
					t[i].nodes
					?(
						t[i].expanded
						?(i+1<t.length?a.imgs.minus:a.imgs.minusbottom)
						:(i+1<t.length?a.imgs.plus:a.imgs.plusbottom)
					)
					:(i+1<t.length?a.imgs.join:a.imgs.joinbottom)
				);
				var expcon_link="javascript:void(xtrees[\""+a.id.id+"\"].expcon(\""+route+"\"));";
				var checkbox_link="javascript:void(xtrees[\""+a.id.id+"\"].swap(\""+route+"\"));";
				if (i+1<t.length)
					h+="<div style='background:url("+a.base+a.imgs.line+") repeat-y left top;'>";
				h+="<div id='tree_"+a.id.id+"_expcon_"+route+"' style='background:url("+ico+") no-repeat left top;border:0px solid #F0F;"+(t[i].nodes?"":"padding-left:18px;")+"'>";
				if (t[i].nodes)
					h+="<img src='"+a.base+a.imgs.empty+"' align='absmiddle' width='16' height='16' class='xtree_clickable' onClick='"+expcon_link+"' />";
				if (!o.nochecks && (!a.o.checkonlyleafs || (a.o.checkonlyleafs && !t[i].nodes))) {
					h+="<img id='tree_"+a.id.id+"_checkbox_"+route+"' class='xtree_clickable' src='"+a.base+(
						t[i].checked
						?a.imgs.checkboxok
						:(
							a._extra[route].partial
							?a.imgs.checkboxmed
							:a.imgs.checkbox
						)
					)+"' align='absmiddle' width='16' height='16' onClick='"+checkbox_link+"' />";
				}
				if (t[i].ico) h+="<img src='"+t[i].ico+"' align='absmiddle' width='16' height='16' />";
				h+=(o.onclick
						?"<a href='javascript:xtrees[\""+a.id.id+"\"].onclick(\""+route+"\")'>"
						:"<a href='"+(t[i].nodes?expcon_link:checkbox_link)+"'>"
					)+(a.o.render?a.o.render(a, t[i]):t[i].caption)+"</a>"
				;
				h+=(t[i].extra?t[i].extra:"");
				h+="<div style='clear: both;'></div>";
				h+="</div>";
				// 'oldDiv = Object.replaceChild(newDiv, oldDiv)';
				if (i+1<t.length)
					h+="</div>";
				if (t[i].nodes)
					rec(t[i],t[i].nodes,n+1,route);
				h+="</li>";
			}
			h+="</ul>";
			h+="</div>";
		}
		rec(a.tree,a.tree.nodes,0,"");
		h+="</div>";
		//h+="<pre>"+adump(a.tree)+"</pre>";
		gidset(a.id,h);
	};
	a.values=function(newvalues){
		if (newvalues) {
			var setvalues=function(t){
				for (var i in t) {
					t[i].checked=(typeof(newvalues[t[i].value])!="undefined"?true:false);
					if (t[i].nodes) setvalues(t[i].nodes);
				}
			}
			setvalues(a.tree.nodes);
			a.update();
			return true;
		} else {
			var v=[];
			var getvalues=function(t){
				for (var i in t) {
					if (t[i].checked) v.push(typeof(t[i].value)!="undefined"?t[i].value:t[i].route);
					if (t[i].nodes) getvalues(t[i].nodes);
				}
			};
			getvalues(a.tree.nodes);
			return v;
		}
	};
	a.deferedupdate=function(){
		var a2=this;
		if (a2.timeout)
			clearTimeout(a2.timeout);
		a2.timeout=setTimeout(function(){
			a.update();
			delete a2.timeout;
		},1);
	}
	a.add=function(route,node){
		var base=(route?a._extra[route].node:a.tree);
		if (!base.nodes) base.nodes=[];
		var i=base.nodes.push(node)-1;
		a.recalc();
		a.deferedupdate();
		return base.nodes[i].route;
	};
	a.del=function(route){
		if (!a._extra[route]) return false;
		a._extra[route].base.splice(a._extra[route].i,1);
		delete a._extra[route];
		a.deferedupdate();
		return true;
	};
	a.expandedlevel=function(level,expand){
		var recexpand=function(t,l){
			for (var i in t.nodes) {
				t.nodes[i].expanded=true;
				if (l<level && t.nodes[i].nodes)
					recexpand(t.nodes[i],l+1);
			}
		};
		recexpand(a.tree,1)
		a.deferedupdate();
	};
	a.autoexpand=function(){
		a.expcon();
	};
	if (a.o.values) a.values(a.o.values);
	if (a.o.expanded) a.expandedlevel(a.o.expanded,true);
	a.update();
	if (a.o.autoexpand) a.autoexpand();
	xtrees[a.id.id]=this;
}
