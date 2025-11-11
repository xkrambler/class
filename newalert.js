/*

	newalert 0.1b
	Creates modal dialogs using JS/HTML.
	* requires: common.js

	Examples:
		newalert("Hello world!");
		newwait("Please, wait while processing...");
		newwait_close();
		newalert({
			"ico":"images/ico48/ok.png",
			"title":"Modal Dialog",
			"msg":"This is an example dialog",
			"buttons":[
				{"caption":"Simple Wait Test","ico":"images/ico16/clock.png","action":function(newalert_id){
					newsimplewait();
					setTimeout(function(){ newwait_close(newalert_id); },1000);
				}},
				{"caption":"OK","ico":"images/ico16/ok.png","action":function(id, o){ newok("Test OK Dialog"); }},
				{"caption":"ERROR","ico":"images/ico16/error.png","action":function(id, o){ newerror("Test Error Dialog"); }},
				{"caption":"Close","ico":"images/ico16/cancel.png","action":function(id, o){ newalert_close(id) }},
				{"caption":"Alternate Close","ico":"images/ico16/cancel.png"}
			]
		});

*/

var _newalert=_newalert || {}; // setup
if (typeof _newalert.id == "undefined") _newalert.id="newalert_"; // base id
if (typeof _newalert.mobile == "undefined") _newalert.mobile=1000; // less than these pixels is considered mobile
var newalerts={};
var newalert_texts={
	"en":{
		"accept":"Accept",
		"cancel":"Cancel",
		"close":"Close",
		"wait":"Processing, please wait a moment..."
	},
	"es":{
		"accept":"Aceptar",
		"cancel":"Cancelar",
		"close":"Cerrar",
		"wait":"Proceso en curso, por favor espere..."
	}
};

function newalert_T(id) {
	var lang=document.documentElement.lang;
	if (!newalert_texts[lang]) lang="es";
	return (newalert_texts[lang][id]?newalert_texts[lang][id]:"["+id+"]");
}

function newalert_ismobile() {
	if (isset(_newalert.mobile)) {
		switch (_newalert.mobile) {
		case false: case true: return _newalert.mobile;
		case "auto": default:
		}
	}
	return (windowWidth() < _newalert.mobile); // auto
}

function newalert_open(id) {
	return newalerts[id];
}

function newalert_exec_button(id, i) {
	var activeElement=document.activeElement
	newalerts[id].buttons[i].action(id, newalerts[id].buttons[i]);
	if (activeElement != document.activeElement) newalerts[id].return_focus=false; // prevents action custom focus modification
}

function newalert_body_click(id, e) {
	if (!e.stopPropagation) return;
	e.stopPropagation();
	if (newalerts[id] && newalerts[id].onclick) newalerts[id].onclick(id, e);
}

function newalert_back_close(id, e) {
	if (!e.stopPropagation) return;
	if (!newalerts[id]) return;
	if (!newalerts[id].noclose) newalert_close(id);
	e.stopPropagation();
}

function newalert_change(o) {
	switch (o.action) {
	case "buttons_hide": hide(_newalert.id+o.id+"_cmds"); break;
	case "buttons_hide": hide(_newalert.id+o.id+"_cmds"); break;
	case "buttons_show": show(_newalert.id+o.id+"_cmds"); break;
	}
}

function newalert_resize(o) {
	var o=o||{};
	if (!o.noresize) {
		_newalert.last_ismobile=newalert_ismobile();
		var body_noscroll_windows=0;
		var body_full_windows=0;
		for (var id in newalerts) {
			var newalert=newalerts[id];
			if (!newalert.nomobile) body_noscroll_windows++;
			if (newalert.full) body_full_windows++;
		}
		var body_noscroll=(body_noscroll_windows && _newalert.last_ismobile);
		classEnable(document.body, "newalert_window_body_mobile", body_noscroll);
		classEnable(document.body, "newalert_window_body_desktop", !body_noscroll);
		for (var id in newalerts) {
			var newalert=newalerts[id];
			var back=gid(_newalert.id+id+"_back");
			var frame=gid(_newalert.id+id+"_frame");
			if (back) back.className=newalerts[id].backClass();
			if (frame) {
				frame.style.overflow="";
				frame.style.maxWidth="";
				frame.style.maxHeight="";
				var borderw=getWidth(_newalert.id+id+"_table")-getWidth(_newalert.id+id+"_content");
				var borderh=getHeight(_newalert.id+id+"_table")-getHeight(_newalert.id+id+"_content");
				var maxw=getWidth(_newalert.id+id)-borderw;
				var maxh=getHeight(_newalert.id+id)-borderh;
				frame.style.overflow="auto";
				if (maxw > 0) frame.style.maxWidth=maxw+"px";
				if (maxh > 0) frame.style.maxHeight=maxh+"px";
			}
		}
	}
}

function newalert(o) {
	var index_default=0;
	if (typeof(o) == "string") o={"msg":o};
	var id=(o.id?o.id:"");
	var msg=(o.msg?o.msg:"");
	var buttons=(o.buttons?o.buttons:null);
	if (newalerts[id]) newalert_remove(id, true);
	newalerts[id]=o;
	if (buttons == null) buttons=[{"caption":newalert_T("accept")}];
	newalerts[id].backClass=function(){
		var use_mobile=(!this.nomobile && newalert_ismobile());
		return (use_mobile?"newalert_mobile":(this.full?"newalert_full":"newalert_desktop"));
	};
	newalerts[id].return_focus=document.activeElement;
	newalerts[id].return_function=o.func;
	newalerts[id].buttons=[];
	newalerts[id].active=true;
	var b=document.createElement("div");
	b.setAttribute("class", "newalert_background"+(o.class?" "+o.class:""));
	b.setAttribute("id", _newalert.id+id+"_bg");
	b.style.display="block";
	var d=document.createElement("div");
	d.setAttribute("class", "newalert_container"+(o.class?" "+o.class:"")+(msg?" newalert_msg":" newalert_nomsg"));
	d.setAttribute("id", _newalert.id+id);
	var hasicon=(o.ico || o.icoclass);
	var cols=(hasicon?"colspan='2'":"");
	s="<table id='"+_newalert.id+id+"_back' class='"+newalerts[id].backClass()+"' cellpadding='0' cellspacing='0' width='100%' height='100%'><tr><td align='center' valign='middle' onclick='newalert_back_close(\""+id+"\", event);'>";
	if (o.body) s+=o.body;
	else {
		s+="<table id='"+_newalert.id+id+"_table'"
				+" class='"+(o.className?o.className:"newalert")+"'"
				+" cellpadding='0' cellspacing='0'"
				+(o.width?" width='"+o.width+"'":"")
				+(o.height?" height='"+o.width+"'":"")
				+(o.style?" style='"+o.style+"'":"")
			+" onclick='newalert_body_click(\""+id+"\", event);'"
		+">"
			+(o.title == null?"":"<tr height='1'><th "+cols+" class='newalert_title'>"+(o.title?o.title:"")+"</th></tr>")
			+"<tr height='100%'>"
				+(hasicon
					?"<td class='newalert_icon' id='"+_newalert.id+id+"_icon'>"
					+(o.icoclass
						?"<div class='"+o.icoclass+"'></div>"
						:"<img class='newalert_icon_img' src='"+o.ico+"' alt='' />"
					)
					+"</td>"
					:""
				)
				+"<td class='newalert_body "+(msg?"newalert_body_msg":"newalert_body_nomsg")+"'>"
					+"<div class='newalert_frame' id='"+_newalert.id+id+"_frame'>"
						+"<div class='newalert_content' id='"+_newalert.id+id+"_content'>"
							+(typeof(msg) == "string"?msg:"")
						+"</div>"
					+"</div>"
				+"</td>"
			+"</tr>"
		;
		if (buttons.length) {
			s+="<tr>"
					+"<td "+cols+" id='"+_newalert.id+id+"_cmds_td'>"
						+"<div id='"+_newalert.id+id+"_cmds' "+cols+" class='newalert_cmds'>"
			;
			xforeach(buttons, function(button, i){
				if (button) {
					if (!button.id) button.id=_newalert.id+id+"_cmd_"+i;
					if (button.default) index_default=i;
					if (button.action || !button.href) {
						button.onclick=(button.action
							?(typeof(button.action) == "function"
								?"newalert_exec_button(\""+id+"\","+i+");"
								:button.action
							)
							:"newalert_close(\""+id+"\");"
						);
					}
					buttons[i]=button;
					if (button.caption) s+="<span class='newalert_cmd'>"+newalertButton(button)+"</span>";
				}
			});
			newalerts[id].buttons=buttons;
			s+"</div></td></tr>";
		}
		s+="</table>";
	}
	s+="</td></tr></table>";
	d.innerHTML=s;
	document.body.appendChild(b);
	document.body.appendChild(d);
	try { if (msg instanceof HTMLElement) gid(_newalert.id+id+"_content").appendChild(msg); } catch(e) { console.error(e); }
	if (o.notransition || !window.requestAnimationFrame) {
		classAdd(_newalert.id+id+"_bg", "newalert_background_transition_none");
		classAdd(_newalert.id+id, "newalert_container_transition_none");
	} else {
		window.requestAnimationFrame(function(){
			classAdd(_newalert.id+id+"_bg", "newalert_background_transition_in");
			classAdd(_newalert.id+id, "newalert_container_transition_in");
		})
	}
	_newalert.openWindows++;
	// resize events
	if (!isset(_newalert.last_ismobile)) {
		_newalert.last_ismobile=newalert_ismobile();
		window.addEventListener("resize", function(){ newalert_resize({"auto":true}); }, false);
	}
	newalert_resize({"forced":true});
	gid(_newalert.id+id).addEventListener("transitionstart", function(){ newalert_resize({"forced":true}); }, true);
	gid(_newalert.id+id).addEventListener("transitionend", function(){ newalert_resize({"forced":true}); }, true);
	// focus first button
	try { gid(buttons[index_default].id).focus(); } catch(e) {}
	// return window information
	return {
		"id":id,
		"o":o,
		"close":function(){ newalert_close(id); }
	};
}

function newalertButton(button) {
	var button_element=(button.href?"a":"button");
	var b=document.createElement(button.href?"a":"button");
	b.id=button.id;
	b.className="cmd"+(button.class?" "+button.class:"");
	b.innerHTML=""
		+(button.ico?"<span class='icon' style='background-image:url(\""+button.ico+"\")'>":"")
		+(button.ico?"</span>":"")
		+button.caption
	;
	xforeach(["href", "target", "title", "onclick", "onmouseover", "onmousedown", "onmouseup", "onkeydown", "onkeyup"], function(attr){
		if (button[attr]) b.setAttribute(attr, button[attr]);
	});
	if (typeof button.disabled != "undefined") b.disabled=button.disabled;
	return b.outerHTML;
}

function newwait(o) {
	var o=(typeof o == "string"?{"msg":o}:o||{});
	o.id=(o.id != null?o.id:"wait");
	o.nomobile=true;
	o.noclose=true;
	o.msg=(o.msg?o.msg:newalert_T("wait"));
	if (!isset(o.icoclass)) o.icoclass="newalert_ico newalert_ico_busy";
	if (!isset(o.buttons)) o.buttons=[];
	return newalert(o);
}

function newsimplewait() {
	return newalert({
		"id":"wait",
		"noclose":true,
		"nomobile":true,
		"noshadow":true,
		"body":"<div class='newalert_simplewait'></div>"
	});
}

function newalert_gen(kind, o, action) {
	if (typeof o == "string") o={"msg":o};
	_newalert.genc=(_newalert.genc?_newalert.genc:0)+1;
	return newalert(array_merge({
		"id":"newalert_gen_"+kind+_newalert.genc,
		"icoclass":"newalert_ico newalert_ico_"+kind,
		"buttons":[{"caption":newalert_T("accept")}],
		"onclose":function(){ if (action) action(); }
	}, o));
}

function newok(o, action) { return newalert_gen("ok", o, action); }
function newwarn(o, action) { return newalert_gen("warn", o, action); }
function newerror(o, action) { return newalert_gen("error", o, action); }

function newalert_remove(id, notransition) {
	if (!id) var id="";
	if (!newalerts[id]) return false;
	newalerts[id].active=false;
	if (newalerts[id].notransition) notransition=true;
	if (gid(_newalert.id+id)) {
		var s=document.createElement('p').style, supportsTransitions='transition' in s;
		if (supportsTransitions && !notransition && window.requestAnimationFrame) {
			window.requestAnimationFrame(function(){
				if (gid(_newalert.id+id+"_bg")) {
					classDel(_newalert.id+id+"_bg", "newalert_background_transition_in");
					classAdd(_newalert.id+id+"_bg", "newalert_background_transition_out");
					gid(_newalert.id+id+"_bg").addEventListener("transitionend", function(){
						if (gid(_newalert.id+id+"_bg")) {
							gid(_newalert.id+id+"_bg").parentNode.removeChild(gid(_newalert.id+id+"_bg"));
							newalert_resize({"forced":true});
						}
					}, true);
				}
				if (gid(_newalert.id+id)) {
					classDel(_newalert.id+id, "newalert_container_transition_in");
					classAdd(_newalert.id+id, "newalert_container_transition_out");
					gid(_newalert.id+id).addEventListener("transitionend", function(){
						if (gid(_newalert.id+id)) {
							delete newalerts[id];
							if (gid(_newalert.id+id)) gid(_newalert.id+id).parentNode.removeChild(gid(_newalert.id+id));
							newalert_resize({"forced":true});
						}
					}, true);
				}
			});
		} else {
			delete newalerts[id];
			if (gid(_newalert.id+id)) gid(_newalert.id+id).parentNode.removeChild(gid(_newalert.id+id));
			if (gid(_newalert.id+id+"_bg")) gid(_newalert.id+id+"_bg").parentNode.removeChild(gid(_newalert.id+id+"_bg"));
			newalert_resize({"forced":true});
		}
	}
	return true;
}

function newalert_close(id) {
	if (!id) var id="";
	if (newalerts[id]) {
		try { if (newalerts[id].return_focus) newalerts[id].return_focus.focus(); } catch(e) {};
		if (newalerts[id].onclose) newalerts[id].onclose(id, newalerts[id]);
		try { if (newalerts[id].return_function) newalerts[id].return_function(id); } catch(e) {};
	}
	return newalert_remove(id);
}

function newwait_close(id) {
	return newalert_close(id?id:'wait');
}
