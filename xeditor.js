/*

	xeditor: makes HTML-editable a div container
	* requires common.js

	Examples:
		xEditor({
			"id":"div_identifier", // required
			"store":"input_identifier",
			"toolbox":"toolbox_identifier",
			"allowed":["undo","redo","bold","italic","underline","link","unlink"],
			"disallowed":["unlink"]
		});

*/
function xEditor(o) {
	var self=this;

	// available tools
	self.availableTools=[
		{"cmd":"html","caption":"Editar código HTML"},
		{"cmd":"undo","caption":"Deshacer"},
		{"cmd":"redo","caption":"Rehacer"},
		{"cmd":"removeformat","caption":"Borrar formato"},
		{"cmd":"bold","caption":"Negrita"},
		{"cmd":"italic","caption":"Cursiva"},
		{"cmd":"underline","caption":"Subrayado"},
		{"cmd":"strike","caption":"Tachado"},
		{"cmd":"highlighter","caption":"Resaltar"},
		{"cmd":"justifyleft","caption":"Justificar Izquierda"},
		{"cmd":"justifycenter","caption":"Justificar Centro"},
		{"cmd":"justifyright","caption":"Justificar Derecha"},
		{"cmd":"justifyfull","caption":"Justificación Completa"},
		{"cmd":"unorderedlist","caption":"Lista No Ordenada"},
		{"cmd":"orderedlist","caption":"Lista Ordenada"},
		{"cmd":"image","caption":"Insertar imagen"},
		{"cmd":"link","caption":"Enlazar"},
		{"cmd":"unlink","caption":"Desenlazar"},
		{"cmd":"fontsize","caption":"Tamaño de letra","select":[
			{"cmd":"fontsize-2","caption":"-2"},
			{"cmd":"fontsize-1","caption":"-1"},
			{"cmd":"fontsize+1","caption":"+1"},
			{"cmd":"fontsize+2","caption":"+2"},
			{"cmd":"fontsize+3","caption":"+3"},
			{"cmd":"fontsize+4","caption":"+4"},
			{"cmd":"fontsize+5","caption":"+5"}
		]},
		{"cmd":"heading","caption":"Bloques","select":[
			{"cmd":"div","caption":"&lt;div&gt;"},
			{"cmd":"p","caption":"&lt;p&gt;"},
			{"cmd":"h1","caption":"&lt;h1&gt;"},
			{"cmd":"h2","caption":"&lt;h2&gt;"},
			{"cmd":"h3","caption":"&lt;h3&gt;"},
			{"cmd":"h4","caption":"&lt;h4&gt;"},
			{"cmd":"h5","caption":"&lt;h5&gt;"},
			{"cmd":"h6","caption":"&lt;h6&gt;"},
			{"cmd":"blockquote","caption":"&lt;blockquote&gt;"}
		]}
	];

	// add me to global object list
	if (!window.xEditors) window.xEditors=[];
	self.num=window.xEditors.length;
	window.xEditors[self.num]=self;
	self.me="window.xEditors["+self.num+"]";

	// save HTML on defined input
	self.store=function(){
		if (self.o.store && gid(self.o.store) && self.editor) {
			gidval(self.o.store, (self.plain?self.plain.value:self.editor.innerHTML));
		}
	};

	// get enabled tools
	self.getTools=function(){
		var tools=[];
		for (var j in self.availableTools) {
			for (var i in self.o.tools) {
				var tool=self.o.tools[i];
				if (tool.before == self.availableTools[j].cmd || !j) tools.push(tool);
			}
			tools.push(self.availableTools[j]);
			for (var i in self.o.tools) {
				var tool=self.o.tools[i];
				if (tool.after == self.availableTools[j].cmd) tools.push(tool);
			}
		}
		return tools;
	};

	// get active tools
	self.getToolsActive=function(){
		var tools=self.getTools();
		if (self.plain) {
			return [{"cmd":"html","caption":"HTML"}];
		} else {
			if (self.o.allowed) for (var i in tools) {
				var tool=tools[i];
				var found=false;
				for (var j in self.o.allowed) {
					var cmd=self.o.allowed[j];
					if (tool.cmd == cmd) {
						found=true;
						break;
					}
				}
				if (!found) delete tools[i];
			}
			if (self.o.disallowed) for (var j in self.o.disallowed) {
				var cmd=self.o.disallowed[j];
				for (var i in tools) {
					var tool=tools[i];
					if (tool.cmd == cmd)
						delete tools[i];
				}
			}
		}
		return tools;
	};

	// update tool box
	self.toolboxUpdate=function(){
		self.toolbox_id="xeditor_toolbox";

		// if not defined, create it
		if (!self.toolbox) {
			self.toolbox_lasth=false;
			self.toolbox=document.createElement("div");
			self.toolbox.id=self.toolbox_id;
			self.toolbox.onmousedown=function(){ self.cancelBlur=true; };
			self.toolbox.onmouseup=function(){ self.cancelBlur=false; };
			// fixed/floating toolbox
			if (self.o.toolbox) {
				gidset(self.o.toolbox, "");
				gid(self.o.toolbox).appendChild(self.toolbox);
			} else {
				self.toolbox_container=document.createElement("div");
				self.toolbox_container.style.position="relative";
				self.editor.parentNode.insertBefore(self.toolbox_container, self.editor);
				self.toolbox_container.appendChild(self.toolbox);
			}
		}

		// render toolbox
		if (self.toolbox) {
			var tools=self.getToolsActive();
			var h="";
			h+="<div class='xeditor_toolbox"+(self.o.toolbox?"":" xeditor_toolbox_float")+(self.o.toolbar?" xeditor_toolbox_bar":"")+"'>";
			for (var i in tools) {
				var tool=tools[i];
				h+="<span id='"+self.toolbox_id+"_"+tool.cmd+"' class='xeditor_toolbox_cmd xeditor_toolbox_"+tool.cmd+"'"
					+(tool.ico?" style='background-image:url("+tool.ico+");'":"")
					+" onMouseDown='"+self.me+".command(\""+tool.cmd+"\");return false;'"
					+" title='"+tool.caption+"'>"
						+"<span class='xeditor_toolbox_cmd_caption'>"+tool.caption+"</span>"
				;
				if (tool.select) {
					h+="<span class='xeditor_toolbox_floatmenu'>"
						h+="<span class='xeditor_toolbox_select_grab'>"
							h+="<span class='xeditor_toolbox_select'>"
							for (var j in tool.select) {
								var s=tool.select[j];
								h+="<span class='xeditor_toolbox_select_item' onclick='"+self.me+".command(\""+s.cmd+"\");'>"
										+s.caption
									+"</span>"
								;
							}
							h+="</span>";
						h+="</span>";
					h+="</span>";
				}
				h+="</span>";
			}
			h+="<div class='xeditor_toolbox_clear'></div>";
			h+="</div>";
			if (!self.toolbox_lasth || h != self.toolbox_lasth) {
				self.toolbox_lasth=h;
				self.toolbox.innerHTML=h;
			}
		}

		// if not floating...
		if (self.o.toolbox) {
			// ...change class on editor enabled
			var activo=(self.editor === document.activeElement);
			classEnable(self.o.toolbox, "xeditor_toolbox_enabled", activo);
			classEnable(self.o.toolbox, "xeditor_toolbox_disabled", !activo);
		} else {
			// ...always add class
			classAdd(self.toolbox, "xeditor_toolbox_enabled");
		}

	};

	// terminate edition
	self.end=function(){
		// remove content as editable
		self.editor.removeAttribute("contentEditable");
		// close tool box
		if (self.o.toolbox) gidset(self.o.toolbox, "");
		// supress editable events
		self.editor.oninput=null;
		self.editor.onblur=null;
		self.editor.onkeyup=null;
		self.editor.onfocus=null;
		self.editor.onmouseup=null;
	};

	// convert in editable
	self.editable=function() {
		self.editor=gid(self.o.id);

		// set content editable
		self.editor.contentEditable='true';

		// prevent editing but keep arrows, select & copy functions
		if (self.o.readonly) {
			self.editor.oncut=
			self.editor.onpaste=function(e){ return !self.o.readonly; };
			self.editor.onbeforeinput=function(e){ e.preventDefault(); };
			self.editor.onkeydown=function(e){ if (!e.ctrlKey && !e.metaKey && !e.altKey && e.key.length === 1) e.preventDefault(); };
		}

		// events
		self.editor.oninput=function(){
			if (self.o.oninput) self.o.oninput(self, self.editor.innerHTML);
		};

		// check on editing
		self.editor.onkeyup=self.check;
		self.editor.onmouseup=self.check;

		// on focus, update tool box an setup editable parameters
		self.editor.onfocus=function(){
			self.toolboxUpdate();
			document.execCommand("defaultParagraphSeparator", false, "br");
			document.execCommand("enableObjectResizing", false, "true")
		};

		// close tool box when losing focus
		self.editor.onblur=function(){
			self.close();
			if (self.o.onblur) self.o.onblur(self, self.editor.innerHTML);
		};
	
		// set editor
		classAdd(self.editor, "xeditor_editing");
	
		// store initial value
		self.store();

		// automations
		if (self.o.focus) self.editor.focus();
		if (self.o.toolbox) self.toolboxUpdate();

	};

	// terminate edition
	self.close=function(){

		// last tool box update
		self.toolboxUpdate();

		// store content
		self.store();

		// remove class
		classDel(self.editor, "xeditor_editing");

		// if floating tool box, remove it
		if (!self.o.toolbox) {
			if (!self.cancelBlur) {
				self.toolbox.parentNode.removeChild(self.toolbox);
				if (self.toolbox_container) {
					self.toolbox_container.parentNode.removeChild(self.toolbox_container);
					delete self.toolbox_container
				}
				delete self.toolbox;
			}
		}

	};

	// check and sanitize tags (but no attribute deletion)
	self.check=function(){
		if (self.plain) return;
		var org=self.editor.innerHTML;
		if (self.o.sanitize) {
			var msg=strip_tags(org, "<p><br><br/><span><div><img><a><b><i><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6>");
			if (msg != org) self.editor.innerHTML=msg;
		}
		// debug
		/*
		var sel=window.getSelection();
		var range=sel.getRangeAt(0);
		//range.setStart(sel.anchorNode, sel.anchorOffset);
		//range.setEnd(sel.focusNode, sel.focusOffset);
		var h="";
		h+="sel="+sel+"<br>";
		h+="sel.anchorNode="+sel.anchorNode+"<br>";
		h+="sel.anchorOffset="+sel.anchorOffset+"<br>";
		if (sel.anchorNode.tagName) h+="sel.anchorNode.tagName="+sel.anchorNode.tagName+"<br>";
		h+="sel.focusNode="+sel.focusNode+"<br>";
		h+="sel.focusOffset="+sel.focusOffset+"<br>";
		h+="range="+range+"<br>";
		gidset("debug",h);
		*/
		// update content
		self.store();
	};

	// swap HTML/text editor
	self.edithtml=function(nofocus){
		self.toolboxUpdate();
		if (self.plain) {
			classDel(self.editor, "xeditor_coding");
			self.editor.contentEditable='true';
			self.editor.innerHTML=self.plain.value;
			delete self.plain;
			if (!nofocus) self.editor.focus();
		} else {
			classAdd(self.editor, "xeditor_coding");
			self.editor.contentEditable='false';
			self.plain=newElement("textarea", {
				"class":"xeditor_code",
				"value":trim(self.editor.innerHTML),
				"properties":{
					oninput:function(){
						if (self.o.oninput) self.o.oninput(self, self.editor.innerHTML);
					},
					onblur:function(){
						self.edithtml(true);
						self.close();
					}
				}
			});
			self.editor.appendChild(self.plain);
			self.plain.focus();
			self.plain.setSelectionRange(0, 0);
			self.toolboxUpdate();
		}
	};

	// get current editor selection
	self.getRangePosition=function() {
		try {
			var getNodeIndex=function(n){ var i=0; while (n=n.previousSibling) i++; return i; }
			var range=window.getSelection().getRangeAt(0);
			var sC=range.startContainer,eC=range.endContainer;
			A=[]; while (sC !== self.editor) { A.push(getNodeIndex(sC)); sC=sC.parentNode; }
			B=[]; while (eC !== self.editor) { B.push(getNodeIndex(eC)); eC=eC.parentNode; }
			return {"sC":A, "sO":range.startOffset, "eC":B, "eO":range.endOffset};
		} catch (e) {
			return false;
		}
	};

	// set current editor selection
	self.setRangePosition=function(rp) {
		try {
			self.editor.focus();
			if (!rp) return;
			var sel=window.getSelection(),range=sel.getRangeAt(0);
			var x, C, sC=self.editor, eC=self.editor;
			C=rp.sC; x=C.length; while(x--) sC=sC.childNodes[C[x]];
			C=rp.eC; x=C.length; while(x--) eC=eC.childNodes[C[x]];
			range.setStart(sC,rp.sO);
			range.setEnd(eC,rp.eO);
			sel.removeAllRanges();
			sel.addRange(range);
			return true;
		} catch (e) {
			return false;
		}
	};

	self.insertImage=function(o) {
		var element=document.createElement("img");
		element.src=o.url;
		if (o.alt) element.alt=o.alt;
		if (o.title) element.title=o.title;
		self.insert(element.outerHTML);
	};

	self.insertLink=function(o) {
		var element=document.createElement("a");
		element.href=o.href;
		element.innerHTML=o.href;
		self.insert(element.outerHTML);
	};

	// edit command
	self.command=function(cmd){

		// get selected HTML
		var selection=window.getSelection();
		var range=selection.getRangeAt(0);
		var selectionText=selection.toString();
		var selectionLen=(selection.focusOffset-selection.anchorOffset);
		var selectionHTML="";
		if (typeof window.getSelection != "undefined") {
			var sel=window.getSelection();
			if (sel.rangeCount) {
				var container=document.createElement("div");
				for (var i=0, len=sel.rangeCount; i<len; ++i)
					container.appendChild(sel.getRangeAt(i).cloneContents());
				selectionHTML=container.innerHTML;
			}
		} else if (typeof document.selection != "undefined") {
			if (document.selection.type == "Text")
				selectionHTML=document.selection.createRange().htmlText;
		}
		var rp=self.getRangePosition();

		// do command
		switch (cmd) {
		case "html": self.edithtml(); break;
		case "link":
			if (self.o.onlink) {
				self.o.onlink(self); // TODO: parameters
			} else {
				var url=prompt("Indique URL", (selectionHTML.indexOf("::")!=-1?selectionHTML:""));
				if (url) {
					if (selectionLen) {
						document.execCommand("createlink", false, url);
					} else {
						self.insertLink({"href":url});
					}
				}
			}
			break;
		case "strike": self.insert("<s>"+selectionHTML+"</s>"); break;
		case "highlighter": if (selectionHTML) self.insert("<span style='color:#000;background-color:#FFFF00;'>"+selectionHTML+"</span>"); break;
		case "removeformat": if (selectionText) self.insert(nl2br(selectionText)); break;
		case "unorderedlist": document.execCommand("insertunorderedlist", false, null); break;
		case "orderedlist": document.execCommand("insertorderedlist", false, null); break;
		case "div": document.execCommand("formatBlock", false, "<div>"); break;
		case "p": document.execCommand("formatBlock", false, "<p>"); break;
		case "h1": document.execCommand("formatBlock", false, "<h1>"); break;
		case "h2": document.execCommand("formatBlock", false, "<h2>"); break;
		case "h3": document.execCommand("formatBlock", false, "<h3>"); break;
		case "h4": document.execCommand("formatBlock", false, "<h4>"); break;
		case "h5": document.execCommand("formatBlock", false, "<h5>"); break;
		case "h6": document.execCommand("formatBlock", false, "<h6>"); break;
		case "blockquote": document.execCommand("formatBlock", false, "<blockquote>"); break;
		case "fontsize-2": if (selectionHTML) self.insert("<span style='font-size:0.5em;'>"+selectionHTML+"</span>"); break;
		case "fontsize-1": if (selectionHTML) self.insert("<span style='font-size:0.8em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+1": if (selectionHTML) self.insert("<span style='font-size:1.2em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+2": if (selectionHTML) self.insert("<span style='font-size:1.5em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+3": if (selectionHTML) self.insert("<span style='font-size:1.8em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+4": if (selectionHTML) self.insert("<span style='font-size:2.2em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+5": if (selectionHTML) self.insert("<span style='font-size:3em;'>"+selectionHTML+"</span>"); break;
		case "image":
			// if newalert available, do advanced image insert
			if (typeof(newalert) != "undefined") {
				newalert({
					"title":"Insertar imagen",
					"msg":""
						+"<div class='xeditor_field'>"
							+"<div class='xeditor_field_caption'>URL de la imagen:</div>"
							+"<div class='xeditor_field_input'><input id='xeditor_src' class='txt' type='text' value='' style='width:400px;' /></div>"
						+"</div>"
						+"<div class='xeditor_field'>"
							+"<div class='xeditor_field_caption'>Texto alternativo:</div>"
							+"<div class='xeditor_field_input'><input id='xeditor_alt' class='txt' type='text' value='' style='width:400px;' /></div>"
						+"</div>"
						+"<div class='xeditor_field'>"
							+"<div class='xeditor_field_caption'>Título:</div>"
							+"<div class='xeditor_field_input'><input id='xeditor_title' class='txt' type='text' value='' style='width:400px;' /></div>"
						+"</div>"
					,
					"buttons":[
						{"caption":"Insertar","action":function(){
							var o={
								"src":trim(gidval("xeditor_src")),
								"alt":gidval("xeditor_alt"),
								"title":gidval("xeditor_title")
							};
							newalert_close();
							if (o.src) self.insertImage(o);
						}},
						{"caption":"Cancelar"}
					],
					"onclose":function(){
						self.setRangePosition(rp);
					}
				});
				gidval("xeditor_src", "");
				gidval("xeditor_alt", strip_tags(selectionHTML));
				gidfocus("xeditor_src");
			// else, simple prompt
			} else {
				var url=prompt("Indique URL de la imagen", "");
				if (url) self.insertImage({"src":url});
			}
			break;
		default:
			var cmd_found=false;
			if (self.o.tools)
				for (var i in self.o.tools)
					if (self.o.tools[i].cmd == cmd) {
						cmd_found=true;
						var html=self.o.tools[i].action(self, o, self.o.tools[i]);
						if (typeof html == "string") self.insert(html);
						break;
					}
			if (!cmd_found) document.execCommand(cmd, false, null);
		}

		// sanitize
		self.check();
	
	};

	// insert HTML in caret/selection
	self.insert=function(html){
		var rp=self.getRangePosition();
		document.execCommand("insertHTML", false, html);
		self.setRangePosition(rp);
	};

	// set focus to editor
	self.focus=function(){
		self.editor.focus();
	};

	// return content
	self.value=function(){
		var value=(self.plain?self.plain.value:trim(self.editor.innerHTML));
		if (value.length >= 4 && value.substring(value.length-4) == "<br>") value=value.substring(0, value.length-4); // supress last <br>
		return value;
	};

	// init
	self.init=function(o){
		self.o=o;
		if (self.o.id) self.editable();
	};

	// initialize
	self.init(o);

}
