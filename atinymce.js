/*

	aTinyMCE, funciones para mejorar la experiencia de integración con AJAX y TinyMCE
	Ejemplo de uso básico, siendo contenido un textarea:

	atinymce.init("contenido",{
		"onsave":function(id,contenido){
			newwait();
			ajax("contenido.set",{
				"contenido":contenido // atinymce.get('contenido')
			},function(){
				newwait_close();
			},function(r){
				newalert_close();
				if (r.data.err) newerror(r.data.err);
				if (r.data.ok) {
					atinymce.unload("contenido");
					show("contenedor_cmd_edit");
					gidset("contenedor",contenido);
				}
			});
		},
		"onfile":atinymce.filerFileAdd,
		"onpicture":atinymce.filerImageAdd
	});

*/
var atinymce={
	"tinymceloaded":false,
	"init":function(id,o){
		
		// verificar parámetros
		if (!isset(o)) var o={};
		if (!isset(o.onfile)) o.onfile=atinymce.filerFileAdd;
		if (!isset(o.onpicture)) o.onpicture=atinymce.filerImageAdd;
		if (!isset(o.path_files)) o.path_files=(isset(o.path)?o.path:"ficheros/");
		if (!isset(o.path_images)) o.path_images=(isset(o.path)?o.path:"imagenes/");
		this.o=o;
		
		// activar tinyMCE
		tinyMCE.init({
			
			"elements":id,
			"mode":"exact",
			"theme":"advanced",
			"language":"es",
			"plugins":"autolink,lists,pagebreak,style,layer,table,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template,wordcount,advlist",

			// Theme options
			theme_advanced_buttons1 :""
				+(o.onsave?"asave,":"")
				+(o.onfile?"afile,":"")
				+(o.onpicture?"apicture,":"")
				+"|,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,formatselect,fontselect,fontsizeselect,|,fullscreen", // styleselect,
			theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
			theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl",
			theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak,restoredraft",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			theme_advanced_statusbar_location : "bottom",
			theme_advanced_resizing : true,

			// Example content CSS (should be your site CSS)
			content_css : (o.css?o.css:"kernel.css"),

			// Drop lists for link/image/media/template dialogs
			/*template_external_list_url : "lists/template_list.js",
			external_link_list_url : "lists/link_list.js",
			external_image_list_url : "lists/image_list.js",
			media_external_list_url : "lists/media_list.js",*/

			// función de guardado
			"setup":function(ed) {
				if (o.onsave) {
					ed.addButton('asave',{
						title: 'Guardar',
						image: 'images/tinymce.asave.png',
						onclick: function() {
							o.onsave(id,atinymce.get(id));
						}
					});
				}
				if (o.onfile) {
					ed.addButton('afile',{
						title: 'Adjuntar fichero',
						image: 'images/tinymce.file.png',
						onclick: function() {
							o.onfile(id);
						}
					});
				}
				if (o.onpicture) {
					ed.addButton('apicture',{
						title: 'Insertar imagen',
						image: 'images/tinymce.picture.png',
						onclick: function() {
							o.onpicture(id);
						}
					});
				}
			},

			// Style formats
			/*style_formats : [
				{title : 'Bold text', inline : 'b'},
				{title : 'Red text', inline : 'span', styles : {color : '#ff0000'}},
				{title : 'Red header', block : 'h1', styles : {color : '#ff0000'}}
				//,{title : 'Example 1', inline : 'span', classes : 'example1'},
				//{title : 'Example 2', inline : 'span', classes : 'example2'},
				//{title : 'Table styles'},
				//{title : 'Table row 1', selector : 'tr', classes : 'tablerow1'}
			]*/
			
		});

	},
	"unload":function(id){
		tinyMCE.execCommand('mceFocus', false, id);
		tinyMCE.execCommand('mceRemoveControl', false, id);
	},
	"insert":function(id,html) { this.editor(id).selection.setContent(html); },
	"get":function(id) { return tinyMCE.get(id).getContent(); },
	"update":function(id) { tinyMCE.get(id).save(); },
	"editor":function(id) { return tinyMCE.get(id); },
	"filerImageAdd":function(id) {
		filer.open({
			"title":"Seleccionar Imagen",
			"mode":"",
			"path":atinymce.o.path_images,
			"vista":"imagenes",
			"onopen":function(f){
				if (f) {
					atinymce.insert(id,"<img src='"+f.path+f.file+"' />");
					filer.close();
					/*newwait("Escalando imagen para publicación web...");
					ajax("image.resize",{
						"image":f.path+f.file
					},function(){
						newwait_close();
					},function(r){
						if (r.data.err) newerror(r.data.err);
						if (r.data.ok) {
							atinymce.insert(id,"<img src='"+f.path+f.file+"' />");
							filer.close();
						}
					});*/
				} else {
					newwarn("Por favor, seleccione alguna imagen.");
				}
			}
		});
	},
	"filerFileAdd":function(id) {
		filer.open({
			"title":"Seleccionar Fichero",
			"mode":"",
			"path":atinymce.o.path_files,
			"vista":"",
			"onopen":function(f){
				atinymce.insert(id,"<a href='"+f.path+f.file+"'>"+f.file+"</a>");
				filer.close();
			}
		});
	}
};
