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
	var a=this;
	a.o=o;

	// agregar objeto a la lista global
	if (!window.xEditors) window.xEditors=[];
	a.num=window.xEditors.length;
	window.xEditors[a.num]=a;
	a.me="window.xEditors["+a.num+"]";

	// guardar HTML en input
	a.store=function(){
		if (a.o.store && gid(a.o.store) && gid(a.o.id)) {
			gidval(a.o.store, (a.plain?a.plain.value:gid(a.o.id).innerHTML));
		}
	};

	// get all tools
	a.getTools=function(){
		var available=[
			{"cmd":"html","caption":"Editar código HTML"},
			{"cmd":"undo","caption":"Deshacer"},
			{"cmd":"redo","caption":"Rehacer"},
			{"cmd":"removeformat","caption":"Borrar formato"},
			{"cmd":"bold","caption":"Negrita"},
			{"cmd":"italic","caption":"Cursiva"},
			{"cmd":"underline","caption":"Subrayado"},
			{"cmd":"strike","caption":"Tachado"},
			{"cmd":"justifyleft","caption":"Justificar Izquierda"},
			{"cmd":"justifycenter","caption":"Justificar Centro"},
			{"cmd":"justifyright","caption":"Justificar Derecha"},
			//{"cmd":"justifyfull","caption":"Justificación Total"},
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
			{"cmd":"heading","caption":"Encabezado","select":[
				{"cmd":"div","caption":"&lt;DIV&gt;"},
				{"cmd":"p","caption":"&lt;P&gt;"},
				{"cmd":"h1","caption":"&lt;H1&gt;"},
				{"cmd":"h2","caption":"&lt;H2&gt;"},
				{"cmd":"h3","caption":"&lt;H3&gt;"},
				{"cmd":"h4","caption":"&lt;H4&gt;"},
				{"cmd":"h5","caption":"&lt;H5&gt;"},
				{"cmd":"h6","caption":"&lt;H6&gt;"}
			]}
		];
		var tools=[];
		for (var j in available) {
			for (var i in a.o.tools) {
				var tool=a.o.tools[i];
				if (tool.before == available[j].cmd || !j) tools.push(tool);
			}
			tools.push(available[j]);
			for (var i in a.o.tools) {
				var tool=a.o.tools[i];
				if (tool.after == available[j].cmd) tools.push(tool);
			}
		}
		return tools;
	};

	// get active tools
	a.getToolsActive=function(){
		var tools=a.getTools();
		if (a.plain) {
			return [{"cmd":"html","caption":"HTML"}];
		} else {
			if (a.o.allowed) for (var i in tools) {
				var tool=tools[i];
				var found=false;
				for (var j in a.o.allowed) {
					var cmd=a.o.allowed[j];
					if (tool.cmd==cmd) {
						found=true;
						break;
					}
				}
				if (!found) delete tools[i];
			}
			if (a.o.disallowed) for (var j in a.o.disallowed) {
				var cmd=a.o.disallowed[j];
				for (var i in tools) {
					var tool=tools[i];
					if (tool.cmd==cmd)
						delete tools[i];
				}
			}
		}
		return tools;
	};

	// seleccionar
	a.selectClick=function(tool_cmd, select) {
		a.command(select.value);
		select.value="";
	};

	// crear barra de herramientas
	a.toolboxUpdate=function(){
		var editor=gid(a.o.id);
		// si no tiene toolbox...
		a.toolbox_id="xeditor_toolbox";
		if (!a.toolbox) {
			a.toolbox_lasth=false;
			a.toolbox=document.createElement("div");
			a.toolbox.id=a.toolbox_id;
			a.toolbox.onmouseover=function(){ a.cancelBlur=true; };
			a.toolbox.onmouseout=function(){ a.cancelBlur=false; };
			// ver si se va a fijar, o se queda flotante
			if (a.o.toolbox) {
				gidset(a.o.toolbox, "");
				gid(a.o.toolbox).appendChild(a.toolbox);
			} else {
				a.toolbox_container=document.createElement("div");
				a.toolbox_container.style.position="relative";
				editor.parentNode.insertBefore(a.toolbox_container, editor);
				a.toolbox_container.appendChild(a.toolbox);
			}
		}
		// si tengo toolbox
		if (a.toolbox) {
			var tools=a.getToolsActive();
			var h="";
			h+="<div class='xeditor_toolbox "+(a.o.toolbox?"":"xeditor_toolbox_float")+"'>";
			for (var i in tools) {
				var tool=tools[i];
				if (tool.select) {
					h+="<select id='"+a.toolbox_id+"_"+tool.cmd+"'"
						+" class='xeditor_toolbox_cmd xeditor_toolbox_"+tool.cmd+"'"
						+" onChange='javascript:"+a.me+".selectClick(\""+tool.cmd+"\",this);return false;'"
						+" tabindex='-1'"
						+" title='"+tool.caption+"'"
						+">";
					h+="<option value='' selected></option>";
					for (var j in tool.select) {
						var s=tool.select[j];
						h+="<option value='"+s.cmd+"'>"+s.caption+"</option>";
					}
					h+="</select>";
				} else {
					h+="<span id='"+a.toolbox_id+"_"+tool.cmd+"' class='xeditor_toolbox_cmd xeditor_toolbox_"+tool.cmd+"'"
						+(tool.ico?" style='background-image:url("+tool.ico+");'":"")
						+" onMouseDown='javascript:"+a.me+".command(\""+tool.cmd+"\");return false;'"
						+" title='"+tool.caption+"'"
							+"><span>"+tool.caption+"</span>"
						+"</span>"
					;
				}
			}
			h+="<div class='xeditor_toolbox_clear'></div>";
			h+="</div>";
			if (!a.toolbox_lasth || h != a.toolbox_lasth) {
				a.toolbox_lasth=h;
				a.toolbox.innerHTML=h;
			}
		}
		// si no es flotante
		if (a.o.toolbox) {
			// si no lo es, cambiar la clase
			var activo=(editor === document.activeElement);
			classEnable(a.o.toolbox, "xeditor_toolbox_enabled", activo);
			classEnable(a.o.toolbox, "xeditor_toolbox_disabled", !activo);
		} else {
			// si es flotante, actualizar clase
			classAdd(a.toolbox, "xeditor_toolbox_enabled");
		}
	};

	// terminar edición
	a.end=function(){
		// desestablecer editable
		var editor=gid(a.o.id);
		editor.removeAttribute("contentEditable");
		// cerrar herramientas
		if (a.o.toolbox) gidset(a.o.toolbox, "");
		// suprimir eventos
		editor.onkeyup=null;
		editor.onmouseup=null;
		editor.onfocus=null;
		editor.onblur=null;
	};

	// convetir un post en editable
	a.editable=function() {

		// establecer editable
		var editor=gid(a.o.id);
		editor.contentEditable='true';

		// eventos
		editor.oninput=function(){
			if (a.o.oninput) a.o.oninput(a, editor.innerHTML);
		};

		// verificar tags HTML sean correctos
		editor.onkeyup=a.check;
		editor.onmouseup=a.check;

		// crear barra de herramientas en la parte superior al enfocar
		editor.onfocus=function(){
			a.toolboxUpdate();
			document.execCommand("defaultParagraphSeparator", false, "br");
			document.execCommand("enableObjectResizing", false, "true")
		};

		// destruir barra de herramientas al perder el foco
		editor.onblur=function(){
			a.close();
			if (a.o.onblur) a.o.onblur(a, editor.innerHTML);
		};
	
		// estilo
		classAdd(editor, "xeditor_editing");
	
		// guardar valor inicial
		a.store();

		// acciones automáticas
		if (a.o.focus) gid(editor).focus();
		if (a.o.toolbox) a.toolboxUpdate();

	};

	// cerrar edición
	a.close=function(){
		a.toolboxUpdate();
		a.store();
		// si es flotante, quitar barra de herramientas
		if (!a.o.toolbox) {
			if (!a.cancelBlur) {
				a.toolbox.parentNode.removeChild(a.toolbox);
				if (a.toolbox_container) {
					a.toolbox_container.parentNode.removeChild(a.toolbox_container);
					delete a.toolbox_container
				}
				delete a.toolbox;
			}
		}
	};

	// parseo de tags indeseables -que no elimina los atributos-
	a.check=function(){
		if (a.plain) return;
		var editor=gid(a.o.id);
		var org=editor.innerHTML;
		if (a.o.sanitize) {
			var msg=strip_tags(org, "<p><br><br/><span><div><img><a><b><i><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6>");
			if (msg != org) editor.innerHTML=msg;
		}
		// debug
		/*var sel=window.getSelection();
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
		gidset("debug",h);*/
		a.store();
	};

	// editar html
	a.edithtml=function(nofocus){
		var editor=gid(a.o.id);
		a.toolboxUpdate();
		if (a.plain) {
			classDel(editor, "xeditor_coding");
			editor.contentEditable='true';
			editor.innerHTML=a.plain.value;
			delete a.plain;
			if (!nofocus) editor.focus();
		} else {
			classAdd(editor, "xeditor_coding");
			editor.contentEditable='false';
			a.plain=newElement("textarea", {
				"class":"xeditor_code",
				"value":trim(editor.innerHTML),
				"properties":{
					oninput:function(){
						if (a.o.oninput) a.o.oninput(a, editor.innerHTML);
					},
					onblur:function(){
						a.edithtml(true);
						a.close();
					}
				}
			});
			editor.appendChild(a.plain);
			a.plain.focus();
			a.plain.setSelectionRange(0, 0);
		}
	};

	// comando de edición
	a.command=function(cmd){
		var editor=gid(a.o.id);
		// obtener HTML seleccionado
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
		// guardar posición del cursor
		function saveRangePosition() {
			try {
				var getNodeIndex=function(n){ var i=0; while (n=n.previousSibling) i++; return i; }
				var range=window.getSelection().getRangeAt(0);
				var sC=range.startContainer,eC=range.endContainer;
				A=[]; while (sC!==editor) { A.push(getNodeIndex(sC)); sC=sC.parentNode; }
				B=[]; while (eC!==editor) { B.push(getNodeIndex(eC)); eC=eC.parentNode; }
				return {"sC":A, "sO":range.startOffset, "eC":B, "eO":range.endOffset};
			} catch (e) {
				return false;
			}
		}
		var rp=saveRangePosition();
		// restaurar posición del cursor
		function restoreRangePosition(rp) {
			editor.focus();
			if (!rp) return;
			var sel=window.getSelection(),range=sel.getRangeAt(0);
			var x,C,sC=editor,eC=editor;
			C=rp.sC; x=C.length; while(x--) sC=sC.childNodes[C[x]];
			C=rp.eC; x=C.length; while(x--) eC=eC.childNodes[C[x]];
			range.setStart(sC,rp.sO);
			range.setEnd(eC,rp.eO);
			sel.removeAllRanges();
			sel.addRange(range)
		}
		function insertImage(o) {
			var element=document.createElement("img");
			element.src=o.url;
			if (o.alt) element.alt=o.alt;
			if (o.title) element.title=o.title;
			a.insert(element.outerHTML);
		}
		function insertLink(o) {
			var element=document.createElement("a");
			element.href=o.href;
			element.innerHTML=o.href;
			a.insert(element.outerHTML);
		}
		// lanzar comando
		switch (cmd) {
		case "html": a.edithtml(); break;
		case "link":
			if (a.o.onlink) {
				a.o.onlink(a); // TODO: parametros
			} else {
				var url=prompt("Indique URL", (selectionHTML.indexOf("::")!=-1?selectionHTML:""));
				if (url) {
					if (selectionLen) {
						document.execCommand("createlink", false, url);
					} else {
						insertLink({"href":url});
					}
				}
			}
			break;
		case "strike":
			a.insert("<s>"+selectionHTML+"</s>");
			break;
		case "removeformat":
			a.insert(nl2br(selectionText));
			break;
		case "unorderedlist":
			//a.insert("<ul><li>"+selectionHTML+"</li></ul>");
			document.execCommand("insertunorderedlist", false, null);
			break;
		case "orderedlist":
			//a.insert("<ol><li>"+selectionHTML+"</li></ol>");
			document.execCommand("insertorderedlist", false, null);
			break;
		case "div": document.execCommand("formatBlock", false, "<div>"); break;
		case "p": document.execCommand("formatBlock", false, "<p>"); break;
		case "h1": document.execCommand("formatBlock", false, "<h1>"); break;
		case "h2": document.execCommand("formatBlock", false, "<h2>"); break;
		case "h3": document.execCommand("formatBlock", false, "<h3>"); break;
		case "h4": document.execCommand("formatBlock", false, "<h4>"); break;
		case "h5": document.execCommand("formatBlock", false, "<h5>"); break;
		case "h6": document.execCommand("formatBlock", false, "<h6>"); break;
		case "fontsize-2": if (selectionHTML) a.insert("<span style='font-size:0.5em;'>"+selectionHTML+"</span>"); break;
		case "fontsize-1": if (selectionHTML) a.insert("<span style='font-size:0.8em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+1": if (selectionHTML) a.insert("<span style='font-size:1.2em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+2": if (selectionHTML) a.insert("<span style='font-size:1.5em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+3": if (selectionHTML) a.insert("<span style='font-size:1.8em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+4": if (selectionHTML) a.insert("<span style='font-size:2.2em;'>"+selectionHTML+"</span>"); break;
		case "fontsize+5": if (selectionHTML) a.insert("<span style='font-size:3em;'>"+selectionHTML+"</span>"); break;
		/*case "test":
			a.insert("asd<b>asd</b>");
			break;*/
		case "image":
			// si hay newalert, lanzar ventana avanzada
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
							if (o.src) insertImage(o);
						}},
						{"caption":"Cancelar"}
					],
					"onclose":function(){
						restoreRangePosition(rp);
					}
				});
				gidval("xeditor_url", "");
				gidval("xeditor_alt", strip_tags(selectionHTML));
				gidfocus("xeditor_url");
			// en caso contrario, prompt simple
			} else {
				var url=prompt("Indique URL de la imagen", "");
				if (url) insertImage({"src":url});
			}
			break;
		default:
			var cmd_found=false;
			if (a.o.tools)
				for (var i in a.o.tools)
					if (a.o.tools[i].cmd==cmd) {
						cmd_found=true;
						var html=a.o.tools[i].action(a, o, a.o.tools[i]);
						if (typeof html == "string") a.insert(html);
						break;
					}
			if (!cmd_found) document.execCommand(cmd, false, null);

		}
		a.check();
	};

	// insertar HTML
	a.insert=function(html){
		document.execCommand("insertHTML", false, html);
	};

	// establecer foco
	a.focus=function(){
		gid(a.o.id).focus();
	};

	// devolver contenido
	a.value=function(){
		var value=(a.plain?a.plain.value:trim(gid(a.o.id).innerHTML));
		if (value.length >= 4 && value.substring(value.length-4) == "<br>") value=value.substring(0, value.length-4); // quita el último <br> agregado
		return value;
	};

	// inicializar
	if (a.o.id) a.editable();

}
