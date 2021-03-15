/*
	Ejemplo de uso:
	filer.open({
		"title":"Pruebas para seleccionar fichero",
		"mode":"", // default(newalert)/xwin/div
		"div":"contenedor", // para modo div
		"xwin":{ // para modo xwin
			"top":100,
			"left":100,
		},
		"path":"",
		"vista":"imagenes",
		"onopen":function(f){
			alert(adump(f));
		},
		"onclose":function(f){
			alert("close!");
		}
	});
*/
var filer={
	"ext":null,
	"o":{},
	"path":"",
	"dir":[],
	"selected":[],
	"seleccionado":null,
	"vista":"",
	"vistanext":function(){
		switch (this.vista) {
		case "": this.vista="imagenes"; break;
		default: this.vista="";
		}
		this.redraw();
	},
	"back":function(){
	},
	"up":function(){
	},
	"nuevacarpeta":function(){
		newalert({
			"id":"newfolder",
			"ico":"newfolder",
			"msg"
				:"<div>Indique nombre de nueva carpeta:</div>"
				+"<div><input id='filer_newfolder' value='Nueva carpeta' class='txt' type='text' style='width:200px;' /></div>"
			,
			"buttons":[
				{"caption":"Crear","ico":"images/ico16/foldernew.png","action":function(){
					newwait();
					ajax("filer:newfolder",{
						"filer":(filer.o.filer?filer.o.filer:""),
						"path":filer.path,
						"folder":gidval("filer_newfolder")
					},function(){
						newwait_close();
					},function(r){
						newalert_close("newfolder");
						if (r.data.err) newerror(r.data.err);
						if (r.data.ok) {
							newok("Nueva carpeta creada correctamente.");
							filer.refresh();
						}
					});
				}},
				{"caption":"Cancelar","ico":"images/ico16/cancel.png"}
			]
		});
		gid("filer_newfolder").focus();
		gid("filer_newfolder").select();
	},
	"ira":function(p){
		if (filer.o.oncd)
			if (!filer.o.oncd(p))
				return false;
		if (this.path!=p) {
			this.path=p;
			this.refresh();
		}
	},
	"open":function(o){
		if (!this.installedhooks) {
			this.installedhooks=true;
			keydown(function(e){
				if (e.keyCode==17) filer.controlKey=true;
				if (e.shiftKey) filer.shiftKey=true;
			});
			keyup(function(e){
				if (e.keyCode==17) filer.controlKey=false;
				filer.upShift=false;
				filer.shiftKey=false;
			});
		}
		this.uploader_url=location.href;
		var i=this.uploader_url.indexOf("?");
		if (i!=-1) this.uploader_url=this.uploader_url.substring(0,i);
		this.uploader_url+="?ajax=filer:uploader";
		this.o=o;
		this.path=(o.path?o.path:"");
		this.vista=(o.vista?o.vista:"");
		var h
			="<div class='filer'>"
				+"<div class='filer_head'>"
					+"<div class='filer_address'>"
						+"<table><tr>"
							+"<td width='99%'><select id='filer_path' class='cmb' style='width:99%;' onChange='javascript:filer.ira(this.value)' onKeyUp='javascript:filer.ira(this.value)'>"
							+"</select></td>"
							+"<td width='1%'><div class='filer_tools'>"
								+"<a href='javascript:filer.subir()' style=\"background-image:url('images/ico16/up.png')\"><span>Subir</span></a>"
								+"<a href='javascript:filer.nuevacarpeta()' style=\"background-image:url('images/ico16/foldernew.png')\"><span>N.Carpeta</span></a>"
								+"<a href='javascript:filer.refresh()' style=\"background-image:url('images/ico16/refresh.png')\"><span>Recargar</span></a>"
								+"<a href='javascript:filer.vistanext()' style=\"background-image:url('images/ico16/search.png')\"><span>Vista</span></a>"
							+"</div></td>"
						+"</tr></table>"
					+"</div>"
					+"<div class='filer_thead'>"
						+"<div class='filer_row'>"
							+"<span class='filer_scroll filer_th'></span>"
							+"<span class='filer_fecha filer_th'>Fecha</span>"
							+"<span class='filer_size filer_th'>Tama&ntilde;o</span>"
							+"<span class='filer_file filer_th'>Nombre del fichero</span>"
						+"</div>"
					+"</div>"
				+"</div>"
				+"<div id='filer_dir' class='filer_body'>"
				+"</div>"
				+"<div class='filer_foot'>"
					+"<div class='filer_selectors'>"
						+"<table>"
																	
							+"<tr>"
								+"<th width='1%'>Seleccionado:</th>"
								+"<td width='97%'>"
									+"<input id='filer_seleccionado' class='txt' value='' style='width:99%;' onChange='javascript:filer.filename(this.value);' onKeyUp='javascript:filer.filename(this.value);' />"
								+"</>"
								+"<td width='1%'>"
									+"<a class='cmd' href='javascript:filer.borrar()' style='padding-left:8px;padding-right:6px;'><span class='icon' style=\"background-image:url('images/ico16/delete.png');padding-left:16px;\"></span></a>"
								+"</td>"
								+"<td width='1%'><a class='cmd' id='filer_cmd_seleccionar' href='javascript:filer.seleccionar()' disabled>"
									+"<span class='icon' style=\"background-image:url('"
										+(filer.o.select && filer.o.select.ico?filer.o.select.ico:"images/ico16/ok.png")
									+"')\">"
										+(filer.o.select && filer.o.select.caption?filer.o.select.caption:"Aceptar")
									+"</span>"
								+"</a></td>"
							+"</tr>"
							
							+"<tr>"
								+"<th width='1%'>Formatos:</th>"
								+"<td width='98%' colspan='2'>"
									+"<select id='filer_formatos' class='cmb' style='width:99%;' onChange='javascript:filer.filter(this.value)' onKeyUp='javascript:filer.filter(this.value)'>"
										+"<option value='' selected>Todos los archivos</option>"
									+"</select>"
								+"</td>"
								+"<td width='1%'><a class='cmd' href='javascript:filer.close();'>"
									+"<span class='icon' style=\"background-image:url('images/ico16/cancel.png')\">Cancelar</span>"
								+"</a></td>"
							+"</tr>"

							+"<tr id='file_upload'>"
								+"<th width='1%'>Subir:</th>"
								+"<td width='98%' colspan='2'>"
									+"<iframe id='file_uploader' src='"+this.uploader_url+"&path="+gescape(this.path)+"' style='width:100%;height:25px;' frameborder='0'></iframe>"
								+"</td>"
								+"<td width='1%'><a class='cmd' href='javascript:filer.upload();'>"
									+"<span class='icon' style=\"background-image:url('images/ico16/up.png')\">Subir</span>"
								+"</a></td>"
							+"</tr>"
							
						+"</table>"
					+"</div>"
				+"</div>"
			+"</div>"
		;
		
		var title=(o.title?o.title:"Seleccionar archivo");
		switch (this.o.mode) {
		case "div":
			gidset(gid(this.o.div),h);
			break;
			
		case "xwin":
			var opciones={
				"caption":title,
				"width":580,"height":600,
				"minwidth":500,"minheight":300,
				"center":true,
				"body":h
			}
			this.o.xwin=xwinNew(array_merge(opciones,(o.xwin?o.xwin:{})));
			break;
		
		default:
			newalert({
				"id":"filer",
				"ico":"",
				"title":title,
				"msg":"<div style='width:550px;height:500px;padding-bottom:40px;'><div style='width:550px;height:500px;'>"+h+"</div></div>",
				"buttons":[]
			});
		}
		
		this.refresh();
		
	},
	"close":function(){
		switch (this.o.mode) {
		case "div": break;
		case "xwin": xwinClose(this.o.xwin); break;
		default: newalert_close("filer");
		}
		if (this.o.onclose)
			this.o.onclose(this);
	},
	"upload":function(){
		if (!gid("file_uploader").contentWindow.upload()) {
			newwarn("Por favor, especifique un fichero.");
		}
	},
	"uploaded":function(o){
		if (o && o.ok) {
			newok("Fichero "+o.file+" de "+o.sizet+" bytes subido correctamente.");
			filer.refresh();
		} else {
			if (o) {
				parent.newwarn("Acceso denegado en escritura a esta carpeta."+adump(o));
			} else {
				parent.newwarn("Error en operacioacute;n");
			}
		}
		gid("file_uploader").src=this.uploader_url+"&path="+gescape(this.path)+"&nocache="+(new Date().getTime())
	},
	"redraw":function(){
		// crear listado de ficheros
		var h="";
		var num=0;
		for (var i in filer.dir) {
			num++;
			if (num>filer.count) break;
			var dir=filer.dir[i];
			var url=dir.path+filer.path+dir.file;
			url="javascript:void(0)"; // sobreescribir
			switch (filer.vista) {
			case "imagenes":
				h+="<a href='"+url+"'"
							+" onClick='javascript:filer.marcar("+i+",event);return false;'"
							+" class='filer_image "+(filer.selected[i]?"filer_selected":"")+"' title='"+dir.file+"'"
							+" style='background-image:url(\""+dir.imagen+"\");'"
						+">"
						+"<div>"+dir.file+"</div>"
					+"</a>";
				break;
			default:
				h+="<a href='"+url+"'"
							+" onClick='javascript:filer.marcar("+i+",event);return false;'"
							+" class='filer_row "+(filer.selected[i]?"filer_selected":"")+" filer_entry' title='"+dir.file+"'"
						+">"
						+"<span class='filer_fecha'>"+dir.mtimet+"</span>"
						+"<span class='filer_size'>"+(dir.sizet.length?dir.sizet:"&nbsp;")+"</span>"
						+"<span class='filer_file' style=\"background-image: url('"+dir.ico+"');\">"+dir.file+"</span>"
					+"</a>"
				;
			}
		}
		// rellenar extensiones (si hay diferentes)
		if (filer.exts) {
			comboClear('filer_formatos');
			var i=0;
			for (var e in filer.exts) {
				comboAdd('filer_formatos',filer.exts[e],e,(!i && filer.ext===null || filer.ext==e?true:false));
				i++;
			}
		}
		// rellenar arbol de carpetas
		comboClear('filer_path');
		var dpath=filer.path;
		var i=0;
		while (true) {
			comboAdd('filer_path',"/"+dpath,dpath,(!i?true:false));
			if (!dpath) break;
			dpath=filer.previo(dpath)
			i++;
		}
		// actualizar ruta de subida de ficheros
		if (gid("file_uploader").contentWindow) {
			try {
				gid("file_uploader").contentWindow.document.getElementById("path").value=filer.path;
			} catch(e) {}
		}
		// mostrar directorio y subir scroll al inicio
		gidset("filer_dir",h);
	},
	"refresh":function(){
		newsimplewait();
		ajax("filer:list",{
			"filer":(this.o.filer?this.o.filer:""),
			"path":this.path,
			"ext":filer.ext
		},function(){
			newwait_close();
		},function(r){
			if (r.data.err) {
				if (newerror) newerror(r.data.err);
				else alert(r.data.err);
			}
			if (r.data.ok) {
				// inicializar variables base
				delete filer.lastmark;
				filer.selected={};
				// actualizar datos
				filer.root=r.data.root;
				filer.path=r.data.path;
				filer.dir=r.data.dir;
				filer.count=r.data.count;
				filer.exts=r.data.exts;
				filer.ext=r.data.ext;
				// actualizar vista
				filer.redraw();
				filer.redraw();
				// establecer visibilidad del cuadro de upload
				gid("file_upload").style.visibility=(r.data.upload?"visible":"hidden");
				// subir scroll al inicio
				gid("filer_dir").scrollTop="0px";
			}
		});
		
	},
	"filename":function(filename){
		if (filename) {
			gid("filer_cmd_seleccionar").removeAttribute("disabled");
		} else {
			gid("filer_cmd_seleccionar").setAttribute("disabled","");
		}
		this.seleccionado={
			"file":filename,
			"path":this.root+this.path,
			"manual":true
		}
	},
	"double":function(id){
		if (this.dir[id].dir) {
			this.ira(this.path+this.dir[id].file+"/");
			this.desmarcar();
		} else {
			this.seleccionar();
		}
	},
	"desmarcar":function(){
		this.seleccionado=null;
		gid("filer_cmd_seleccionar").setAttribute("disabled","");
		gidval("filer_seleccionado","");
	},
	"marcar":function(id,e){
		// marcaje
		if (!filer.controlKey && !filer.upShift) filer.selected={};
		if (filer.shiftKey && isset(this.lastmark)) {
			this.desmarcar(); // desmarcar el actualmente seleccionado
			var markEnabled=false;
			var start_mark=this.lastmark;
			var end_mark=id;
			var step=(start_mark<end_mark?1:-1);
			if (step<0) {
				start_mark=id;
				end_mark=this.lastmark;
			}
			for (var i=(step>0?start_mark:end_mark);i>=start_mark && i<=end_mark;i+=step) {
				if (!filer.controlKey && !filer.upShift && i==this.lastmark) markEnabled=true;
				if (markEnabled) {
					if (filer.selected[i]) delete filer.selected[i];
					else filer.selected[i]=true;
				}
				if ((filer.controlKey || filer.upShift) && i==this.lastmark) markEnabled=true;
				if (i==id) markEnabled=false;
			}
			filer.upShift=true;
			gidval("filer_seleccionado","");
		} else {
			// si ya estaba marcado antes, lanzar doble click
			if (this.seleccionado && this.seleccionado.file==this.dir[id].file)
				//if ((new Date()).getTime()-this.lastClickTimer.getTime()<1000)
					this.double(id);
			// guardar último momento de click para temporizar el doble click
			//this.lastClickTimer=new Date();
			//this.desmarcar(); // desmarcar el actualmente seleccionado
			// marcar
			if (filer.selected[id]) delete filer.selected[id];
			else filer.selected[id]=true;
			// seleccionado
			this.seleccionado=array_copy(this.dir[id]);
			this.seleccionado.path+=this.path,
			gid("filer_cmd_seleccionar").removeAttribute("disabled");
			gidval("filer_seleccionado",this.dir[id].file);
		}
		// actualizar listado
		this.redraw();
		// último marcado
		this.lastmark=id;
		// click válido
		return true;
	},
	"seleccionar":function(){
		this.o.onopen(this.seleccionado);
	},
	"subir":function(){
		this.ira(this.previo(this.path));
	},
	"borrar":function(){
		var items=[];
		if (filer.selected) for (var i in filer.selected)
			if (filer.selected[i])
				items.push(filer.dir[i].file);
		if (!items.length) {
			newwarn("Por favor, primero seleccione un archivo/carpeta.");
			return;
		}
		newalert({
			"id":"delete",
			"ico":"warn",
			"msg":"¿Est&aacute; seguro de querer eliminar "
				+(items.length==1
					?(filer.seleccionado.dir?"la carpeta <b>"+filer.seleccionado.file+"</b>":"el fichero <b>"+filer.seleccionado.file+"</b>")
					:"estos <b>"+items.length+"</b> elementos"
				)+"?",
			"buttons":[
				{"caption":"Borrar","ico":"images/ico16/delete.png","action":function(){
					newwait();
					ajax("filer:delete",{
						"filer":(filer.o.filer?filer.o.filer:""),
						"path":filer.path,
						"items":items
					},function(){
						newwait_close();
					},function(r){
						newalert_close("delete");
						if (r.data.err) newerror(r.data.err);
						if (r.data.ok) {
							newok(
								items.length==1
								?(filer.seleccionado.dir?"Carpeta borrada correctamente":"Fichero borrado correctamente.")
								:"<b>"+items.length+"</b> elementos borrados correctamente"
							);
							filer.desmarcar();
							filer.refresh();
						}
					});
				}},
				{"caption":"Cancelar","ico":"images/ico16/cancel.png"}
			]
		});
		
	},
	"previo":function(p){
		var dpath="";
		for (var i=p.length-2;i>=0;i--)
			if (p[i]=="/") {
				dpath=p.substring(0,i+1);
				break;
			}
		return dpath;
	},
	"filter":function(ext){
		if (this.ext!=ext) {
			this.ext=ext;
			this.refresh();
		}
	}
};
