/*
	xForm3: clase soporte cliente de formularios, versión 3
*/
function xForm3(o) {
	if (!o) o={};
	var a=this;
	a.o=array_copy(o);
	a.data={};

	// nocache token
	a.nocache=function(){ return (new Date().getTime()); };

	// inmediatos
	a.name=function(n){
		if (isset(n)) a.o.name=n;
		return (a.o.name?a.o.name:null);
	};
	a.class=function(f){ return (a.o.class?(f?a.o.class[f]:a.o.class):null); };
	a.field=function(f){ return a.o.fields[f]; };
	a.fields=function(){ return a.o.fields; };
	a.set=function(f,n,v){
		if (isset(v)) a.o.fields[f][n]=v;
		return a.o.fields[f][n];
	};
	a.caption=function(f){ return (a.o.fields[f]?a.o.fields[f].caption:null); };
	a.id=function(f){ return a.o.name+"_"+f; };
	a.gid=function(f){ return (a.id(f)?gid(a.id(f)):null); };
	a.gidval=function(f, value){ return (a.id(f)?gidval(a.id(f), value):null); }; // DEPRECATED
	a.gidfocus=function(f){ gidfocus(a.id(f)); }; // DEPRECATED

	// obtiene el campo por su identificador
	a.fieldById=function(id){
		for (var field in a.o.fields)
			if (a.id(field) == id)
				return field;
		return false;
	};

	// establece el foco a un campo
	a.focus=function(f){
		var field=a.field(f);
		var e=false;
		switch (field?field.type:"") {
		case "datetime":
			e=gid(a.id(f)+":d");
			break;
		default:
			e=a.gid(f);
		}
		if (e && e.focus) e.focus();
	};

	// establece la selección en un campo
	a.select=function(f){
		var e=gid(a.id(f));
		if (e && e.select) e.select();
	};

	// establece el foco y selección a un campo para corregir
	a.correct=function(f){
		a.focus(f);
		a.select(f);
	};

	// establece el foco al primer campo
	a.focusfirst=function(){
		for (var field in a.o.fields) {
			a.focus(field);
			a.select(field);
			return true;
		}
		return false;
	};

	// obtiene/establece estado de un campo
	a.enabled=function(f, enabled){
		var e=gid(a.id(f));
		if (!e) return null;
		if (enabled) e.removeAttribute("disabled");
		else e.setAttribute("disabled", "");
	};

	// devuelve todos los campos y datos de un formulario en formato JSON
	a.form=function(form){
		var form=gid(form);
		var values={};
		for (i=0; i < form.length; i++) {
			if (!form[i].name) continue;
			if (form[i].type == "radio" && !form[i].checked) continue;
			values[form[i].name]=a.formValue(form[i]);
		}
		return values;
	};

	// devuelve un valor de un campo
	a.formValue=function(id){
		var e=gid(id);
		if (!e) return null;
		switch (e.type) {
		case "checkbox":
			return a.value(id);
		case "select-multiple":
			var d=[];
			for (var i=0; i < e.length; i++)
				if (e.options[i].selected)
					d.push(e.options[i].value);
			return d;
		case "date":
			return (e.value?e.value:null);
		case "radio":
		case "button":
		case "select-one":
		case "text":
		case "number":
		case "textarea":
		default:
			return e.value;
		}
	};

	// establecer opciones de un campo
	a.options={
		clear:function(field){
			var combo=gid(a.id(field));
			combo.options.length=0;
		},
		add:function(field, caption, value){
			var combo=gid(a.id(field));
			var option=new Option(caption, (typeof(value) == "undefined"?caption:value));
			try {
				try { combo.add(option, null); } catch(e) { combo.add(option); }
			} catch(e) {
				return false;
			}
			return option;
		},
		fill:function(field, options){
			var f=a.o.fields[field];
			if (isset(options)) {
				a.options.clear(field);
				a.o.fields[field].options={};
				for (var i in options) {
					if (typeof(options[i]) == "object") {
						a.options.add(field, options[i].caption, options[i].value);
						a.o.fields[field].options[options[i].value]=options[i].caption;
					} else {
						a.options.add(field, options[i], i);
						a.o.fields[field].options[i]=options[i];
					}
				}
			}
			return a.o.fields[field].options;
		}
	};

	// return null if empty value
	a.nullifempty=function(value){
		return (value === ""?null:value);
	};

	// filtrar valor
	a.filter=function(field, value){
		var f=a.o.fields[field];
		if (f) {
			if (f.nullifempty) value=a.nullifempty(value);
			if (f.integer)  value=(value?parseInt(value):0);
			if (f.number)   value=(value?parseInt(value):0);
			if (f.positive) value=(value?Math.abs(parseFloat(value)):0);
			if (f.decimal)  value=(value?parseFloat(value):0);
		}
		return value;
	};

	// obtener/establecer el valor de un campo
	a.value=function(field, value){
		var f=a.o.fields[field];
		if (!f) return null;
		var id=a.id(field);
		if (f.type) {
			switch (f.type) {
			case "files":
			case "images":
				return a.filter(field, a.files.value(field));
			case "html":
			case "div":
				if (!gid(id)) return null;
				if (isset(value)) gidset(id, a.filter(field, value));
				return a.filter(field, gidget(id));
			case "checkbox":
				if (!gid(id)) return null;
				if (f.values) {
					if (isset(value)) gid(id).checked=(value == f.values[1]?true:false);
					return f.values[(gid(id).checked?1:0)];
				}
				if (isset(value)) gid(id).checked=(value?true:false);
				return gid(id).checked;
			case "radio":
				if (isset(value))
					for (var i=0, e=false; e=gid(id+"-"+i); i++)
						if ((""+e.value) == (""+value))
							e.checked=true;
				for (var i=0, e=false; e=gid(id+"-"+i); i++)
					if (e.checked)
						return e.value;
				return null;
			case "date":
				if (!gid(id)) return null;
				if (!a.isInputDateSupported()) {
					if (isset(value)) gidval(id, a.filter(field, sqlDateSP(value)));
					else return spDateSQL(gidval(id));
				}
				if (isset(value)) gidval(id, a.filter(field, value));
				return a.nullifempty(gidval(id));
			case "datetime":
				if (!gid(id+":d") || !gid(id+":t")) return null;
				if (isset(value)) {
					if (typeof(value) != "string") value="";
					gidval(id+":d", value.substring(0, 10));
					gidval(id+":t", value.substring(11, 16));
				}
				var d=gidval(id+":d"); if (d.length != 10) d="";
				var t=gidval(id+":t"); if (t.length != 5) t="";
				return a.nullifempty(a.filter(field, trim(d && t?d+" "+t+":00":"")));
			case "time":
			default:
				if (!gid(id)) return null;
				if (isset(value)) gidval(id, a.filter(field, value));
			}
			return a.filter(field, a.formValue(id));
		}
		return null;
	};

	// obtener/establecer el placeholder de un campo
	a.placeholder=function(field, placeholder){
		var f=a.o.fields[field];
		if (!f) return null;
		var id=a.id(field);
		if (gid(id)) gid(id).setAttribute("placeholder", placeholder);
		else console.warn("xform3: id "+a.id(field)+" not found!");
		return null;
	};

	// obtiene/establece los valores del formulario
	a.values=function(values){
		if (isset(values))
			for (var f in values)
				if (a.o.fields[f] && a.o.fields[f].type)
					a.value(f, values[f]);
		var values={};
		for (var f in a.o.fields)
			if (a.o.fields[f].type)
				values[f]=a.value(f);
		return values;
	};

	// crea event listener en un campo o en todos los campos (si f=callback)
	a.addEventListener=function(event, f, callback){
		if (typeof(f) == "function") {
			var callback=f;
			for (var f in a.o.fields)
				if (!a.addEventListenerField(event, f, callback))
					return false;
		} else
			if (!Array.isArray(f)) f=[f];
			for (var i in f)
				if (!a.addEventListenerField(event, f[i], callback))
					return false;
		return true;
	};

	// crea event listener en el campo especificado
	a.addEventListenerField=function(event, f, callback){
		var field=a.o.fields[f];
		var field_id=a.id(f);
		if (field && field_id) {
			switch (field.type) {
			case "radio":
				if (field.options)
					var num=0;
					for (var i in field.options)
						(function(){
							var e=gid(field_id+"-"+(num++));
							if (e) e.addEventListener(event, function(event){
								a.event={
									"f":f,
									"field":field,
									"id":this.id,
									"num":num,
									"element":this,
									"value":a.value(f)
								};
								callback(a, event, a.event);
							}, false);
						})();
				break;
			default:
				(function(){
					var e=gid(a.id(f));
					if (e) e.addEventListener(event, function(event){
						a.event={
							"f":f,
							"field":field,
							"id":this.id,
							"element":this,
							"value":a.value(f)
						};
						callback(a, event, a.event);
					}, false);
				})();
			}
			return true;
		}
		return false;
	};

	// subir fichero
	a.fileUpload=function(o, onok){
		if (typeof(xUploader) == "undefined") {
			console.warn("xUploader no activo, subida cancelada.");
			return false;
		}
		a.files.xuploader=new xUploader(array_merge({
			"name":"xform3_upload",
			"chunked":(isset(a.o.chunksize)?a.o.chunksize:false), // default not chunked
			"multiple":false,
			"onstart":function(uploader, files){
				newwait("<div class='xform3_upload_progress' id='xform3_upload_progress'>Subiendo...</div>");
			},
			"onprogress":function(uploader, r){
				var files=uploader.files();
				var index=(r.index?(r.index-1):false);
				var h="";
				for (var i in files) {
					h+="<li id='xform3_upload_file_"+i+"' class='xform3_upload_li"+(parseInt(i) === index?" xform3_upload_li_actual":"")+"'>"
						+"<span class='xform3_upload_file_name'>"+files[i].name+"</span>"
						+"<span class='xform3_upload_file_size_p'>(<span class='xform3_upload_file_size'>"+sizeString(files[i].size)+"</span>)</span>"
						+"</li>"
					;
				}
				gidset("xform3_upload_progress",""
					+"<div class='xform3_upload_caption'><span>Subiendo "
						+(r.index?r.index+" de ":"")
						+r.count+" fichero"+(r.count==1?"":"s")+"...</span> "+sizeString(r.total)+"</div>"
					+"<div class='xform3_upload_files' id='xform3_upload_scroll'>"
						+"<ul class='xform3_upload_files_list'>"+h+"</ul>"
					+"</div>"
					+"<div class='xform3_upload_progress_bar_background'>"
						+"<div class='xform3_upload_progress_bar' style='width:"+r.progress+"%;'></div>"
						+"<div class='xform3_upload_progress_text'>"+r.progress+"%</div>"
					+"</div>"
				);
				if (index) gid("xform3_upload_scroll").scrollTop=gid("xform3_upload_file_"+index).offsetTop;
			},
			"oncomplete":function(uploader, r){
				newwait_close();
				if (!r.data) newalert(adump(r));
				if (r.data.err) newerror(r.data.err);
				if (r.data.ok) if (onok) onok(uploader, r);
			},
			"oncancel":function(uploader){
				newwait_close();
				newwarn("Subida cancelada.");
			},
			"onabort":function(uploader, r){
			},
			"onerror":function(uploader, r){
				newwait_close();
				newerror("Error en subida"+(r.data && r.data.err?": "+r.data.err:"")+": "+r.text);
				//alert(adump(r));
			},
			"browse":true
		}, o));
	};

	// borrar fichero
	a.fileDel=function(action, onok){
		newsimplewait();
		ajax(alink(action), {}, function(){
			newwait_close();
		},function(r){
			if (r.data.err) newerror(r.data.err);
			if (r.data.ok) onok(r);
		});
		return true;
	};

	// acciones de tipo audio
	a.audio={

		// subir audio
		"upload":function(field){
			a.fileUpload({
				"url":alink(a.files.fileLink(field, 0, {"ajax":"xform3.files.upload"}))
			}, function(uploader, r){
				delete a.data[field].deleted;
				var file=uploader.file();
				a.data[field].uploaded=true;
				a.data[field].type=file.type;
				a.data[field].name=file.name;
				a.data[field].size=file.size;
				a.audio.refresh(field);
			});
		},

		// descargar audio
		"download":function(field){
			location.href=alink(a.files.fileLink(field, 0, {"attachment":""}));
		},

		// borrar audio
		"del":function(field){
			a.fileDel(a.files.fileLink(field, 0, {"ajax":"xform3.file.del"}), function(r){
				delete a.o.fields[field].value;
				a.data[field].deleted=true;
				a.audio.refresh(field);
			});
		},

		// actualizar HTML
		"refresh":function(f){
			var field=a.o.fields[f];
			var d=a.data[f];
			gidset(d.file, (d.uploaded && !d.deleted
				?"<span class='fa fa-file-audio-o'></span> "+d.name+" ("+bytesToString(d.size)+")"
				:"<span class='fa fa-file-o'></span> Sin audio"
			));
			gidset(d.audio_div, "");
			var audio=document.createElement("audio");
			audio.style.minWidth="300px";
			audio.setAttribute("controls", "");
			if (d.uploaded && !d.deleted) {
				var source=document.createElement("source");
				source.type=(d.type?d.type:"");
				if (!source.type || source.type == "application/octet-stream") source.type='audio/mpeg';
				source.src=alink({"ajax":"xform3.file.get","ssid":a.o.ssid,"name":a.o.name,"field":f,"nocache":a.nocache()});
				audio.appendChild(source);
			}
			d.audio_div.appendChild(audio);
		},

		// inicializar
		"init":function(f){
			var field=a.o.fields[f];
			var container=document.createElement("div");
			container.className="xform3_audio_container";
			var upload_cmd=document.createElement("button");
			upload_cmd.className="cmd";
			upload_cmd.innerHTML="Subir";
			upload_cmd.onclick=function(){ a.audio.upload(f); };
			var download_cmd=document.createElement("button");
			download_cmd.className="cmd";
			download_cmd.innerHTML="Descargar";
			download_cmd.onclick=function(){ a.audio.download(f); };
			var delete_cmd=document.createElement("button");
			delete_cmd.className="cmd";
			delete_cmd.innerHTML="Borrar";
			delete_cmd.onclick=function(){ a.audio.del(f); };
			var audio_div=document.createElement("div");
			audio_div.className="xform3_audio_div";
			var file=document.createElement("div");
			file.className="txt";
			file.innerHTML="&nbsp;";
			container.appendChild(upload_cmd);
			container.appendChild(download_cmd);
			container.appendChild(delete_cmd);
			container.appendChild(audio_div);
			gidset(a.id(f), "");
			gid(a.id(f)).appendChild(file);
			gid(a.id(f)).appendChild(container);
			var d={
				"upload_cmd":upload_cmd,
				"download_cmd":download_cmd,
				"delete_cmd":delete_cmd,
				"audio_div":audio_div,
				"file":file,
			};
			if (field.files && field.files[0]) {
				d.uploaded=true;
				d.name=field.files[0].name;
				d.type=field.files[0].type;
				d.size=field.files[0].size;
			}
			a.data[f]=d;
			a.audio.refresh(f);
		}

	};

	// acciones de tipo files
	a.files={

		"set":function(field, files){
			a.data[field].files=files;
		},

		// devuelve parámetros de acceso al fichero por el servidor
		"fileLink":function(field, index, ao){
			var ao=ao||{};
			return array_merge({
				"ajax":"xform3.file.get",
				"ssid":a.o.ssid,
				"name":a.o.name,
				"field":field,
				"index":index,
				"nocache":a.nocache()
			}, ao);
		},

		// devuelve URL del fichero
		"fileURL":function(field, index){
			var url=a.data[field].files[index].url;
			if (!url) url=alink(a.files.fileLink(field, index));
			return url;
		},

		// devuelve imagen de captura
		"fileThumbnail":function(field, index, ao){
			var tn=a.data[field].files[index].tn;
			if (!tn) tn=alink(a.files.fileLink(field, index, ao));
			return tn;
		},

		// generar etiqueta
		"htmlImageCaption":function(o){
			var e=document.createElement("div");
			e.className="xform3_files_upload_caption";
			if (o.caption) e.innerHTML=o.caption;
			return e;
		},

		// generar botón (para imagen)
		"htmlImageButton":function(o){
			var e=document.createElement(o.href?"a":"div");
			e.className="xform3_files_item_button xform3_files_item_button_"+o.type;
			if (o.href) e.href=o.href;
			e.setAttribute("data-id", o.id);
			e.setAttribute("data-index", o.index);
			e.onclick=function(event){
				// obtener campo por identificador
				var index=parseInt(this.getAttribute("data-index"));
				var field=a.fieldById(this.getAttribute("data-id"));
				event.stopPropagation();
				return o.action(field, index);
			};
			if (o.caption) e.innerHTML=o.caption;
			return e;
		},

		// generar botón (para fichero)
		"htmlFileButton":function(o){
			var span=document.createElement("span");
			span.className="xform3_files_file_button xform3_files_item_button_"+o.type;
			span.setAttribute("data-id", o.id);
			span.setAttribute("data-index", o.index);
			span.onclick=function(){
				// obtener campo por identificador
				var index=parseInt(this.getAttribute("data-index"));
				var field=a.fieldById(this.getAttribute("data-id"));
				o.action(field, index);
			};
			span.title="Borrar";
			return span;
		},

		// añadir fichero
		"add":function(o){
			var field=a.o.fields[o.field];

			//location.href=url;
			var div=document.createElement("div");
			div.setAttribute("data-index", o.index);
			div.id=a.id(o.field)+"_item_"+o.index;

			// renderizar dependiendo del tipo
			switch (field.type) {
			case "image":
			case "images":
				// asignar clase y botones
				div.className="xform3_files_item";
				div.setAttribute("data-id", a.id(o.field));
				div.setAttribute("data-index", o.index);
				var buttons=document.createElement("div");
				buttons.className="xform3_files_item_buttons";
				buttons.appendChild(a.files.htmlImageButton({"type":"delete","id":a.id(o.field),"index":o.index,"action":a.files.del}));
				//buttons.appendChild(a.files.htmlImageButton({"type":"zoom","id":a.id(o.field),"index":o.index,"action":a.files.zoom,"href":a.files.fileURL(o.field, o.index)}));
				div.onclick=function(){
					var index=parseInt(this.getAttribute("data-index"));
					var field=a.fieldById(this.getAttribute("data-id"));
					if (a.data[field].sortable) {
						if (a.cancelSortableClick) {
							delete a.cancelSortableClick;
							return;
						}
					}
					a.files.zoom(field, index);
				};
				div.appendChild(buttons);
				if (o.item.caption) {
					div.title=o.item.caption;
					div.appendChild(a.files.htmlImageCaption({"caption":o.item.caption}));
				}
				if (field.numbered) {
					var number=document.createElement("div");
					number.id=a.id(o.field)+"_number_"+o.index;
					number.className="xform3_files_item_number";
					number.innerHTML=(parseInt(o.index)+1);
					div.appendChild(number);
				}
				// obtener ancho/alto de una captura (se hace así por si está en pestañas, obtener igualmente resolución)
				document.body.appendChild(div);
				var w=div.offsetWidth;
				var h=div.offsetHeight;
				div.parentNode.removeChild(div);
				a.data[o.field].container.appendChild(div);
				// URL de captura
				var url=a.files.fileThumbnail(o.field, o.index, {
					"w":(w?w:170),
					"h":(h?h:128)
				});
				div.style.backgroundImage="url("+url+")";
				break;

			case "file":
			case "files":
				// name=inmuebles_ver.psd type=image/vnd.adobe.photoshop error=0 size=81062 caption=inmuebles_ver.psd
				div.className="xform3_files_file";
				div.innerHTML="<span class='xform3_files_file_icon xform3_files_file_icon_file'></span><a class='xform3_files_file_name' href='"+a.files.fileURL(o.field, o.index)+"' target='_blank'>"+o.item.name+"</a> <span class='xform3_files_file_size'>("+bytesToString(o.item.size)+")</span>";
				div.appendChild(a.files.htmlFileButton({"type":"delete","id":a.id(o.field),"index":o.index,"action":a.files.del}));
				a.data[o.field].container.appendChild(div);
				break;

			}

			// si se solicita, evento para enviar el elemento generado a tratar
			if (a.o.fields[o.field].onelement)
				a.o.fields[o.field].onelement(a, div, o);

			// evento de actualización de campo
			a.files.refreshField(o.field);

		},

		// actualizar lista de ficheros/imágenes
		"refresh":function(field){
			var f=a.o.fields[field];

			// limpiar
			gidset(a.id(field),"");

			// crear lista
			a.data[field].container=document.createElement("ul");
			a.data[field].container.className="xform3_files_container";

			// añadir contenedor
			gid(a.id(field)).appendChild(a.data[field].container);

			// generar el listado de ficheros
			for (var index in a.data[field].files) {
				var item=a.data[field].files[index];
				if (item.deleted) continue;
				a.files.add({
					"index":index,
					"item":item,
					"field":field
				});
			}

			// añadir botón de subida
			var div=document.createElement("div");
			div.id=a.id(field)+"_item_upload";
			div.setAttribute("data-id", a.id(field));
			var action_upload=function(){
				var field=a.fieldById(this.getAttribute("data-id"));
				var f=a.o.fields[field];
				a.files.upload(field);
			};

			// renderizar dependiendo del tipo
			if (!isset(f.upload)) {
				switch (f.type) {
				case "image":
					limit=1;
				case "images":
					var limit=a.files.getLimit(field);
					div.className="xform3_files_item"
						+" xform3_files_upload"
						+(f.type == "images"?" xform3_files_upload_images":" xform3_files_upload_files")
					;
					div.onclick=action_upload;
					div.title=(limit == 1?"Subir imágen":"Subir imágenes");
					div.appendChild(a.files.htmlImageCaption({"caption":div.title}));
					gid(a.id(field)).appendChild(div);
					break;

				case "file":
					limit=1;
				case "files":
				default:
					div.className="xform3_files_file xform3_files_file_upload";
					var span=document.createElement("span");
					span.className="a xform3_files_file_upload_caption";
					span.setAttribute("data-id", a.id(field));
					span.onclick=action_upload;
					span.title=(limit == 1?"Subir archivo":"Subir archivos");
					span.innerHTML="<span class='xform3_files_file_icon xform3_files_file_icon_upload'></span>"+span.title;
					div.appendChild(span);
					gid(a.id(field)).appendChild(div);
					break;

				}
			}

			// clear
			var div=document.createElement("div");
			div.className="clear";
			gid(a.id(field)).appendChild(div);

			// si es ordenable, lanzar
			if (f.sortable) {
				a.data[field].sortable=$(a.data[field].container).sortable({
					cancel:".xform3_files_upload",
					//handle: ".admin_slide_nipZone",
					//placeholder: "admin_slide_placeholder",
					opacity: 0.8,
					deactivate: function(){
						a.files.refreshField(field);
					},
					stop: function(event, ui){
						var distance=(Math.sqrt(
							  Math.pow(ui.position.top - ui.originalPosition.top, 2)
							+ Math.pow(ui.position.left - ui.originalPosition.left, 2)
						));
						if (distance>=10) a.cancelSortableClick=true;
					}
				});
			}

			// actualizar campo
			a.files.refreshField(field);

		},

		// actualizar campo
		"refreshField":function(field){
			var f=a.o.fields[field];

			// actualizar numeración (si numerado)
			if (isset(f.numbered) && f.numbered && a.data[field].sortable) {
				var sortedIDs=$(a.data[field].container).sortable("toArray");
				var count=0;
				for (var i in sortedIDs) {
					var index=parseInt(gid(sortedIDs[i]).getAttribute("data-index"));
					if (!a.data[field].files[index].deleted) gidset(a.id(field)+"_number_"+index, ++count);
				}
			}

			// actualizar visibilidad de botón de subir
			var limit=a.files.getLimit(field);
			classEnable(a.id(field)+"_item_upload", "xform3_files_item_hide", (limit && a.files.count(field) >= limit));

		},

		// borrar fichero
		"del":function(field, index, confirmed){
			var f=a.o.fields[field];
			var item_id=a.id(field)+"_item_"+index;
			if (f.askdelete && !confirmed) {
				newalert({
					"id":"xform3_files_del",
					"title":"Confirme borrado",
					"msg":"¿Está seguro de querer eliminar este elemento?",
					"buttons":[
						{"caption":"Borrar","action":function(id){
							newalert_close(id);
							a.files.del(field, index, true);
						}},
						{"caption":"Cancelar"}
					]
				});
				return;
			}
			var s=document.createElement('p').style, supportsTransitions='transition' in s;
			switch (f.type) {
			case "images": classAdd(item_id, "xform3_files_item_deleting"); break;
			case "files": default: supportsTransitions=false; break;
			}
			ajax("xform3.file.del",{
				"ssid":a.o.ssid,
				"name":a.o.name,
				"field":field,
				"index":index,
				"nocache":a.nocache()
			},function(){
				newwait_close();
			},function(r){
				if (r.data.err) newerror(r.data.err);
				if (r.data.ok) {
					a.data[field].files[index].deleted=true;
					if (gid(item_id)) {
						if (supportsTransitions) {
							gid(item_id).addEventListener("transitionend",function(){
								a.files.refreshField(field);
								if (gid(item_id)) gid(item_id).parentNode.removeChild(gid(item_id));
							},true);
						} else {
							a.files.refreshField(field);
							classDel(item_id, "xform3_files_item_deleting");
							gid(item_id).parentNode.removeChild(gid(item_id));
						}
					}
				}
			});
		},

		// ampliar una fotografía
		"zoom":function(field, index){
			var url=a.files.fileURL(field, index);
			if (typeof(xPhotos) != "undefined") {

				// construir lista
				var actual=0;
				var count=0;
				var list=[];
				for (var i in a.data[field].files)
					if (!a.data[field].files[i].deleted) {
						if (index == i) actual=list.length;
						list.push({"img":a.files.fileURL(field, i)}); // "caption":(a.data[field].files[i].name?a.data[field].files[i].name:false),
					}

				// mostrar xPhotos
				var xphotos=new xPhotos({
					"list":list,
					"actual":actual,
					"show":true
				});

			} else if (typeof(newalert) != "undefined") {

				// newalert
				newalert({
					"id":"xform3_image_zoom",
					"full":true,
					"msg":""
						+"<div class='xform3_image_zoom_container' onClick='javascript:newalert_close(\"xform3_image_zoom\");'>"
							+"<img class='xform3_image_zoom_image' src='"+url+"' />"
						+"</div>"
				});

			} else {

				// fallback: window
				windowOpen(url, 0.8, 0.7);

			}
			return false;
		},

		// subir fichero
		"upload":function(field){
			a.fileUpload({
				"url":alink({
					"ajax":"xform3.files.upload",
					"ssid":a.o.ssid,
					"name":a.o.name,
					"field":field,
					"nocache":a.nocache()
				}),
				"multiple":(a.files.getLimit(field) == 1?false:true)
			}, function(uploader, r){
				var files=uploader.files();
				for (var i in files) {
					var item=files[i];
					if (!a.data[field].files) a.data[field].files=[];
					var index=a.data[field].files.length;
					var limit=a.files.getLimit(field);
					if (limit && (index+1 > limit)) index=limit-1;
					a.data[field].files[index]=item;
					a.files.add({
						"index":index,
						"item":item,
						"field":field
					});
				}
			});
		},

		"getLimit":function(field){
			var f=a.o.fields[field];
			switch (f.type) {
			case "audio": case "file": case "image":
				return 1;
			case "files": case "images": default:
				return (a.o.fields[field].limit?a.o.fields[field].limit:false);
			}
		},

		// conteo
		"count":function(field){
			var count=0;
			for (var i in a.data[field].files)
				if (!a.data[field].files[i].deleted)
					count++;
			return count;
		},

		// get/set valores
		"value":function(field){
			var f=a.o.fields[field];
			if (f.sortable) {
				var sortedIDs=$(a.data[field].container).sortable("toArray");
				for (var i in sortedIDs) {
					var index=parseInt(gid(sortedIDs[i]).getAttribute("data-index"));
					a.data[field].files[index].orden=i;
				}
			}
			if (f.onvalues)
				a.data[field].files=f.onvalues(a, a.data[field].files);
			return a.data[field].files;
		},

		// inicializar
		"init":function(field){
			var f=a.o.fields[field];
			// asegurarse de que es un array
			a.data[field].files=array_values(f.files);
			// actualizar
			a.files.refresh(field);
		}

	};

	// asignar una lista de datos a un campo
	a.datalist=function(f, items){
		var datalist_id=a.id(f)+"_datalist";
		// si ya existía, suprimir antiguo
		if (gid(datalist_id)) gid(datalist_id).parentNode.removeChild(gid(datalist_id));
		// crear datalist y asignar items
		var datalist=document.createElement("datalist");
		datalist.id=datalist_id;
		for (var i in items) {
			var option=document.createElement("option");
			option.value=items[i];
			datalist.appendChild(option);
		}
		document.body.appendChild(datalist);
		// asignar datalist al input
		a.gid(f).setAttribute("list", datalist_id);
	};

	// input date supported
	a.isInputDateSupported=function(){
		var input=document.createElement('input');
		var value='a';
		input.setAttribute('type', 'date');
		input.setAttribute('value', value);
		return (input.value !== value);
	};

	// inicializar
	a.init=function(o){
		var o=o||{};
		if (o.name) a.name(o.name);
		if (isset(o.chunksize)) a.o.chunksize=o.chunksize;
		for (var f in a.o.fields) {
			var id=a.id(f);
			if (!a.data[f]) a.data[f]={};
			var field=a.o.fields[f];
			if (o.set && o.set[f])
				for (var n in o.set[f])
					a.set(f, n, o.set[f][n]);
			switch (field.type) {
			case "datetime":
				(function(id){
					gid(id+":d").onfocus=function(){
						this.setAttribute("data-lastvalue", this.value);
					};
					gid(id+":d").oninput=function(){
						if (
							!this.getAttribute("data-lastvalue")
							&& gid(id+":t")
							&& !gid(id+":t").value
							&& this.value
						) gid(id+":t").value="00:00";
					};
				})(id);
				break;
			case "number":
			case "text":
				if (gid(id)) {
					if (field.integer)  gInputInt(id);
					if (field.number)   gInputInt(id, true);
					if (field.positive) gInputFloat(id);
					if (field.decimal)  gInputFloat(id, true);
				}
				break;
			case "audio":
				a.audio.init(f);
				break;
			case "file":
			case "image":
			case "files":
			case "images":
				a.files.init(f);
				break;
			}
			if (field.oninit) field.oninit(a, field, a.data[f]);
		}
	};

}
