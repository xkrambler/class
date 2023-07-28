// xForm3: form client class support, v3
function xForm3(o) {
	if (!o) o={};
	var a=this;
	a.o=array_merge({
		"thumbnail":{"w":170, "h":128}
	}, o);
	a.data={};

	// xForm3 declaration
	a.xForm3=true;

	// nocache token
	a.nocache=function(){ return (new Date().getTime()); };

	// inmediate
	a.name=function(n){
		if (isset(n)) a.o.name=n;
		return (a.o.name?a.o.name:null);
	};
	a.class=function(f){ return (a.o.class?(f?a.o.class[f]:a.o.class):null); };
	a.field=function(f){ return a.o.fields[f]; };
	a.fields=function(){ return a.o.fields; };
	a.get=function(f,n,v){
		return a.o.fields[f][n];
	};
	a.set=function(f,n,v){
		if (isset(v)) a.o.fields[f][n]=v;
		return a.o.fields[f][n];
	};
	a.del=function(f){
		delete a.o.fields[f];
	};
	a.caption=function(f){ return (a.o.fields[f]?a.o.fields[f].caption:null); };
	a.id=function(f){ return a.o.name+"_"+f; };
	a.gid=function(f){ return (a.id(f)?gid(a.id(f)):null); };
	a.gidval=function(f, value){ return (a.id(f)?gidval(a.id(f), value):null); }; // DEPRECATED
	a.gidfocus=function(f){ gidfocus(a.id(f)); }; // DEPRECATED

	// get field by its identifier
	a.fieldById=function(id){
		for (var field in a.o.fields)
			if (a.id(field) == id)
				return field;
		return false;
	};

	// set focus to a field
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

	// select a text field
	a.select=function(f){
		var e=gid(a.id(f));
		if (e && e.select) e.select();
	};

	// select and set focus to correct a field
	a.correct=function(f){
		a.focus(f);
		a.select(f);
	};

	// set focus to first field
	a.focusfirst=a.focusFirst=function(){
		for (var field in a.o.fields) {
			a.focus(field);
			a.select(field);
			return true;
		}
		return false;
	};

	// set focus to first empty field
	a.focusFirstEmpty=function(){
		for (var field in a.o.fields) {
			if (!a.value(field)) {
				a.focus(field);
				return true;
			}
		}
		return false;
	};

	// get/set if a field is enabled
	a.enabled=function(f, enabled){
		return (a.attribute(f, "disabled", (enabled?false:(isset(enabled)?"":null))) != "");
	};

	// get/set if a field is disabled
	a.disabled=function(f, disabled){
		return (a.attribute(f, "disabled", (disabled?"":disabled)) == "");
	};

	// get/set if a field is read only
	a.readonly=function(f, enabled){
		return a.attribute(f, "readonly", (enabled?"":enabled));
	};

	// get/set/del a generic field attribute
	a.attribute=function(f, attribute, value){
		var e=gid(a.id(f));
		if (!e) return null;
		if (isset(value) && value !== null) {
			if (value === false) e.removeAttribute(attribute);
			else e.setAttribute(attribute, value);
		}
		return e.getAttribute(attribute);
	};

	// get all fields values from a form
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

	// get field value from a form
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

	// set combo options
	a.options={

		// clear all options
		clear:function(field){
			var combo=gid(a.id(field));
			combo.options.length=0;
		},

		// add option
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

		// fill with options
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
		},

		// option text
		text:function(field){
			var combo=gid(a.id(field));
			return (combo && combo.selectedIndex >=0?combo.options[combo.selectedIndex].text:null);
		}

	};

	// return null if empty value
	a.nullifempty=function(value){
		return (value === ""?null:value);
	};

	// locale float parser
	a.localeParseFloat=function(value){
		var lds=localeDecimalSeparator();
		return parseFloat((""+value).replace(lds, "."));
	};

	// filter value
	a.filter=function(field, value){
		var f=a.o.fields[field];
		if (f) {
			if (f.maxlength)   value=(""+value).substring(0, f.maxlength);
			if (f.nullifempty) value=a.nullifempty(value);
			if (f.integer)     value=(value?parseInt(value):0);
			if (f.number)      value=(value?parseInt(value):0);
			if (f.positive)    value=(value?Math.abs(a.localeParseFloat(value)):0);
			if (f.decimal)     value=(value?a.localeParseFloat(value):0);
		}
		return value;
	};

	// get/set file value
	a.file=function(field, value){
		var f=a.o.fields[field];
		if (!f) return null;
		var id=a.id(field);
		switch (f.type) {
		case "file":
		case "files":
		case "image":
		case "images":
			return a.files.value(field);
		}
		return null;
	};

	// get/set field value
	a.value=function(field, value){
		var f=a.o.fields[field];
		if (!f) return null;
		var id=a.id(field);
		if (f.type) {
			switch (f.type) {
			case "file":
			case "files":
			case "image":
			case "images":
				return a.filter(field, a.files.value(field, value));
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
			case "color":
				if (isset(value)) if (a.gid(field)) a.gid(field).style.backgroundColor="#"+a.colorValue(value);
				return a.colorValue(a.gid(field).style.backgroundColor.replace("#", ""));
			case "time":
			default:
				if (!gid(id)) return null;
				if (isset(value)) gidval(id, a.filter(field, value));
			}
			return a.filter(field, a.formValue(id));
		}
		return null;
	};

	// filter color value
	a.colorValue=function(c){
		var c=c.replace("#", "");
    if (c.charAt(0) == 'r') {
			c=c.replace('rgb(','').replace('rgba(','').replace(')','').split(',');
			var r=parseInt(c[0], 10).toString(16);
			var g=parseInt(c[1], 10).toString(16);
			var b=parseInt(c[2], 10).toString(16);
			r=(r.length == 1?'0'+r:r);
			g=(g.length == 1?'0'+g:g);
			b=(b.length == 1?'0'+b:b);
			c=r+g+b;
		}
		if (c.length == 3) c=c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
		return c.toUpperCase();
	};

	// get/set field placeholder
	a.placeholder=function(field, placeholder){
		var f=a.o.fields[field];
		if (!f) return null;
		var id=a.id(field);
		if (gid(id)) gid(id).setAttribute("placeholder", placeholder);
		else console.warn("xform3: id "+a.id(field)+" not found!");
		return null;
	};

	// get/set all form values
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

	// add event listener in a field or all fields (if f is callback function)
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

	// add event listener for an specific field
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

	// upload file using xUploader
	a.fileUpload=function(o, onok){
		o.field=a.o.fields[o.f];
		//console.log(o.f);
		//console.log(a.data[o.f]);
		if (a.data[o.f] && a.data[o.f].uploader) {
			a.data[o.f].uploader.upload(self, o, onok);
			return true;
		}
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
		return true;
	};

	// delete file
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

	// audio related actions
	a.audio={

		// upload audio
		"upload":function(field){
			a.fileUpload({
				"f":field,
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

		// download audio
		"download":function(field){
			location.href=alink(a.files.fileLink(field, 0, {"attachment":""}));
		},

		// delete audio
		"del":function(field){
			a.fileDel(a.files.fileLink(field, 0, {"ajax":"xform3.file.del"}), function(r){
				delete a.o.fields[field].value;
				a.data[field].deleted=true;
				a.audio.refresh(field);
			});
		},

		// refresh HTML
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

		// setup
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

	// files actions
	a.files={

		// set custom file uploader
		"uploader":function(field, uploader){
			if (!a.data[field]) a.data[field]={};
			a.data[field].uploader=uploader;
		},

		// get all files
		"get":function(field){
			return a.data[field].files;
		},

		// set all files
		"set":function(field, files){
			if (!a.data[field]) return false;
			a.data[field].files=files;
			a.files.refresh(field);
			return true;
		},

		// return a remote link to file
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

		// return current file URL
		"fileURL":function(field, index){
			if (a.data[field] && a.data[field].uploader && a.data[field].uploader.url) return a.data[field].uploader.url(self, {"f":field, "field":a.o.fields[field]});
			var url=a.data[field].files[index].url;
			if (!url) url=alink(a.files.fileLink(field, index));
			return url;
		},

		// return current file Thumbnail URL
		"fileThumbnail":function(field, index, ao){
			if (a.data[field] && a.data[field].uploader && a.data[field].uploader.tn) return a.data[field].uploader.tn(self, {"f":field, "field":a.o.fields[field]});
			if (a.data[field] && a.data[field].uploader && a.data[field].uploader.url) return a.data[field].uploader.url(self, {"f":field, "field":a.o.fields[field]});
			var tn=a.data[field].files[index].tn;
			if (!tn) tn=alink(a.files.fileLink(field, index, ao));
			return tn;
		},

		// return caption element
		"htmlImageCaption":function(o){
			var e=document.createElement("div");
			e.className="xform3_files_upload_caption";
			if (o.caption) e.innerHTML=o.caption;
			return e;
		},

		// return image button element
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

		// return file button element
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

		// add file element
		"add":function(o){
			var field=a.o.fields[o.field];

			var div=document.createElement("div");
			div.setAttribute("data-index", o.index);
			div.id=a.id(o.field)+"_item_"+o.index;

			// render depending on field type
			switch (field.type) {
			case "image":
			case "images":
				// asignar clase y botones
				div.className="xform3_files_item";
				div.setAttribute("data-id", a.id(o.field));
				div.setAttribute("data-index", o.index);
				var buttons=document.createElement("div");
				buttons.className="xform3_files_item_buttons";
				buttons.appendChild(a.files.htmlImageButton({"type":"delete", "id":a.id(o.field), "index":o.index, "action":a.files.del}));
				//buttons.appendChild(a.files.htmlImageButton({"type":"zoom", "id":a.id(o.field), "index":o.index, "action":a.files.zoom, "href":a.files.fileURL(o.field, o.index)}));
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
				// get width/height of a thumbnail (it's done this way because if in hidden field it does not calculate it properly)
				document.body.appendChild(div);
				var w=div.offsetWidth;
				var h=div.offsetHeight;
				div.parentNode.removeChild(div);
				a.data[o.field].container.appendChild(div);
				// get capture URL
				var url=a.files.fileThumbnail(o.field, o.index, {
					"w":(w?w:a.o.thumbnail.w),
					"h":(h?h:a.o.thumbnail.h)
				});
				div.style.backgroundImage="url("+url+")";
				break;

			case "file":
			case "files":
				// name=file.psd type=image/vnd.adobe.photoshop error=0 size=999 caption=file.psd
				div.className="xform3_files_file";
				div.innerHTML="<span class='xform3_files_file_icon xform3_files_file_icon_file'></span><a class='xform3_files_file_name' href='"+a.files.fileURL(o.field, o.index)+"' target='_blank'>"+o.item.name+"</a> <span class='xform3_files_file_size'>("+bytesToString(o.item.size)+")</span>";
				div.appendChild(a.files.htmlFileButton({"type":"delete", "id":a.id(o.field), "index":o.index, "action":a.files.del}));
				a.data[o.field].container.appendChild(div);
				break;

			}

			// if requested, event to send the element generated to filter
			if (a.o.fields[o.field].onelement) a.o.fields[o.field].onelement(a, div, o);

			// refresh field
			a.files.refreshField(o.field);

		},

		// refresh all files or images
		"refresh":function(field){
			var f=a.o.fields[field];

			// clean
			gidset(a.id(field),"");

			// create list
			a.data[field].container=document.createElement("ul");
			a.data[field].container.className="xform3_files_container";

			// add container to HTML field element
			gid(a.id(field)).appendChild(a.data[field].container);

			// generate file listing
			for (var index in a.data[field].files) {
				var item=a.data[field].files[index];
				if (item.deleted) continue;
				a.files.add({
					"index":index,
					"item":item,
					"field":field
				});
			}

			// prepare upload button
			var div=document.createElement("div");
			div.id=a.id(field)+"_item_upload";
			div.setAttribute("data-id", a.id(field));
			var action_upload=function(){
				var field=a.fieldById(this.getAttribute("data-id"));
				var f=a.o.fields[field];
				a.files.upload(field);
			};

			// render by field type
			if (!isset(f.upload)) {
				switch (f.type) {

				case "image": limit=1; // no break
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

				case "file": limit=1; // no break
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
			div.className="xform3_clear";
			gid(a.id(field)).appendChild(div);

			// if sortable, setup
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
						if (distance >= 10) a.cancelSortableClick=true;
					}
				});
			}

			// refresh field
			a.files.refreshField(field);

		},

		// refresh field
		"refreshField":function(field){
			var f=a.o.fields[field];

			// update numeration (if numbered)
			if (isset(f.numbered) && f.numbered && a.data[field].sortable) {
				var sortedIDs=$(a.data[field].container).sortable("toArray");
				var count=0;
				for (var i in sortedIDs) {
					var index=parseInt(gid(sortedIDs[i]).getAttribute("data-index"));
					if (!a.data[field].files[index].deleted) gidset(a.id(field)+"_number_"+index, ++count);
				}
			}

			// update visibility of upload button
			var limit=a.files.getLimit(field);
			classEnable(a.id(field)+"_item_upload", "xform3_files_item_hide", (limit && a.files.count(field) >= limit));

		},

		// delete file
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
			var ondelete=function(){
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
			};
			if (a.data[field] && a.data[field].uploader && a.data[field].uploader.del) {
				if (a.data[field].uploader.del(self, {"f":field, "field":a.o.fields[field], "index":index, "confirmed":confirmed})) ondelete();
				return;
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
				if (r.data.ok) ondelete();
			});
		},

		// zoom a photo
		"zoom":function(field, index){
			var url=a.files.fileURL(field, index);
			if (typeof(xPhotos) != "undefined") {

				// create list
				var actual=0;
				var count=0;
				var list=[];
				for (var i in a.data[field].files)
					if (!a.data[field].files[i].deleted) {
						if (index == i) actual=list.length;
						list.push({"img":a.files.fileURL(field, i)}); // "caption":(a.data[field].files[i].name?a.data[field].files[i].name:false),
					}

				// show using xPhotos
				var xphotos=new xPhotos({
					"list":list,
					"actual":actual,
					"show":true
				});

			} else if (typeof(newalert) != "undefined") {

				// show using newalert
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

		// upload file
		"upload":function(field){
			a.fileUpload({
				"f":field,
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

		// get count limit of files
		"getLimit":function(field){
			var f=a.o.fields[field];
			switch (f.type) {
			case "audio": case "file": case "image":
				return 1;
			case "files": case "images": default:
				return (a.o.fields[field].limit?a.o.fields[field].limit:false);
			}
		},

		// count files
		"count":function(field){
			var count=0;
			for (var i in a.data[field].files)
				if (!a.data[field].files[i].deleted)
					count++;
			return count;
		},

		// get/set files
		"value":function(field, value){
			if (a.data[field] && a.data[field].uploader && a.data[field].uploader.value) return a.data[field].uploader.value(value);
			var f=a.o.fields[field];
			if (f.sortable) {
				var sortedIDs=$(a.data[field].container).sortable("toArray");
				for (var i in sortedIDs) {
					var index=parseInt(gid(sortedIDs[i]).getAttribute("data-index"));
					a.data[field].files[index].orden=i;
				}
			}
			if (f.onvalues) a.data[field].files=f.onvalues(a, a.data[field].files);
			return (a.data[field]?a.data[field].files:[]);
		},

		// setup
		"init":function(field){
			var f=a.o.fields[field];
			// ensure it's an array
			a.data[field].files=array_values(f.files);
			// refresh
			a.files.refresh(field);
		}

	};

	// assign a data list for a field
	a.datalist=function(f, items){
		var datalist_id=a.id(f)+"_datalist";
		// if previously exists, remove
		if (gid(datalist_id)) gid(datalist_id).parentNode.removeChild(gid(datalist_id));
		// create datalist and render options
		var datalist=document.createElement("datalist");
		datalist.id=datalist_id;
		if (items) for (var i in items) {
			var option=document.createElement("option");
			option.value=items[i];
			datalist.appendChild(option);
		}
		document.body.appendChild(datalist);
		// assign datalist to input
		if (a.gid(f)) a.gid(f).setAttribute("list", datalist_id);
	};

	// is input date supported
	a.isInputDateSupported=function(){
		var input=document.createElement('input');
		var value='a';
		input.setAttribute('type', 'date');
		input.setAttribute('value', value);
		return (input.value !== value);
	};

	// set color
	a.setColor=function(field, color) {
		var c=color.replace("#", "").toUpperCase();
		gidset("xform3_color_picked", c);
		a.value(field, c);
	};

	// return icon, if defined
	a.icon=function(icon){
		return (typeof(kernel) == "object" && kernel.icon?kernel.icon(icon)+" ":"");
	};

	// setup
	a.init=function(o){
		var o=o||{};
		if (o.name) a.name(o.name);
		if (isset(o.chunksize)) a.o.chunksize=o.chunksize;
		for (var f in a.o.fields) {
			var id=a.id(f);
			if (!a.data[f]) a.data[f]={};
			var field=a.o.fields[f];
			(function(field, f, id){
				if (o.set && o.set[f])
					for (var n in o.set[f])
						a.set(f, n, o.set[f][n]);
				switch (field.type) {
				case "date":
					if (!a.isInputDateSupported()) {
						var value=gidval(id);
						if (value.indexOf("-") !== -1) gidval(id, a.filter(field, sqlDateSP(gidval(id))));
					}
					break;
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
				case "color":
					if (typeof(newalert) == "function" && typeof(iro) == "object" && iro.ColorPicker) {
						a.gid(f).onclick=function(){
							var buttons=[];
							buttons.push({"caption":(typeof(kernel) == "object" && kernel.icon?kernel.icon("check")+" ":"")+"Aceptar","default":true});
							if (field.colors) for (var i in field.colors) {
								var c=field.colors[i];
								(function(c){
									buttons.push({"caption":"<span style='color:#"+c+";'>◼</span>","action":function(){
										a.setColor(f, c);
										newalert_close("xform3_colorpicker");
									}});
								})(c);
							}
							newalert({
								"id":"xform3_colorpicker",
								//"title":"Seleccionar color",
								"msg":""
									+"<div class='acenter'>"
										+"<div class='iblock'>"
											+"<div id='xform3_colorpicker' style='margin:auto;min-width:300px;'></div>"
											+"<div class='acenter' id='xform3_color_picked'>&nbsp;</div>"
										+"</div>"
									+"</div>"
								,
								"buttons":buttons
							});
							a.data[f].iro_colorpicker=new iro.ColorPicker("#xform3_colorpicker", {
								"color":a.value(f)
								//"width":getWidth("color_picker")
							}).on("input:change", function(c){
							//}).on("color:change", function(c){
								a.setColor(f, c.hexString);
								return true;
							});
						};
					}
					break;
				}
			})(field, f, id);
			if (field.oninit) field.oninit(a, field, a.data[f]);
		}
	};

}
