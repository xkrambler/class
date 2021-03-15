/*

	Helper para crear una ventana de envío de e-mails.
	Realiza una petición AJAX al servidor con los datos seleccionados.

	Ejemplo de uso:

		mailerOpen({
			"from":"Usuario origen <origen@servidor.org>",
			"to":"Destino <destino@servidor.org>",
			"cc":"",
			"bcc":"",
			"subject":"Asunto del mensaje",
			"text":"Ahí va un <b>mensaje</b> de prueba :)",
			"html":true,
			"onsend":function(o,r){
				newok("Solicitud enviada.");
			}
		});

*/
function mailerOpen(o) {
	var self=this;
	self.o=o;

	if (!self.o.prefix) self.o.prefix="mailer";
	var ids="from reply to cc bcc subject text";

	self.close=function(){
		newalert_close(self.o.prefix);
	};

	self.open=function(){
		newalert({
			"id":self.o.prefix,
			"ico":"",	
			"title":(self.o.title?self.o.title:"Mailer"),
			"msg":"<table class='ftable'>"
				+"<tr><th class='nowrap'>Emisor:</th><td><input id='mailer_from' class='txt' type='text' value='' style='width:100%;' /></td></tr>"
				+"<tr><th class='nowrap'>Responder a:</th><td><input id='mailer_reply' class='txt' type='text' value='' style='width:100%;' /></td></tr>"
				+"<tr><th class='nowrap'>Receptor:</th><td><input id='mailer_to' class='txt' type='text' value='' style='width:100%;' /></td></tr>"
				+"<tr><th class='nowrap'>CC:</th><td><input id='mailer_cc' class='txt' type='text' value='' style='width:100%;' /></td></tr>"
				+"<tr><th class='nowrap'>CCO:</th><td><input id='mailer_bcc' class='txt' type='text' value='' style='width:100%;' /></td></tr>"
				+"<tr><th class='nowrap'>Asunto:</th><td><input id='mailer_subject' class='txt' type='text' value='' style='width:100%;' /></td></tr>"
				+"<tr><th class='nowrap'>Mensaje:</th><td><textarea id='mailer_text' class='txt' style='width:500px;height:400px;'></textarea></td></tr>"
				+"</table>"
				,
			"buttons":[
				{"caption":"Enviar","ico":"images/ico16/email.png","action":function(){
					newwait();
					var datos=gpreids(self.o.prefix, ids);
					if (tinyMCE && self.o.html) {
						delete datos.text;
						datos.html=tinyMCE.get('mailer_text').getContent();
					}
					ajax("mailer:send",datos,function(){
						newwait_close();
					},function(r){
						if (r.data.err) {
							if (self.o.onsenderr) self.o.onsenderr(self.o, r);
							else newerror(r.data.err);
						}
						if (r.data.ok) {
							self.close();
							if (self.o.onsendok) self.o.onsendok(self.o, r);
							else newok("Mensaje enviado correctamente.");
						}
					});
				}},
				{"caption":(self.o.oncontinue?"Continuar sin enviar":""),"ico":"images/ico16/next.png","action":function(){
					self.close();
					self.o.oncontinue(o);
				}},
				{"caption":"Cancelar","ico":"images/ico16/cancel.png","action":function(){
					if (self.o.oncancel)
						self.o.oncancel(self.o);
					newalert_close("mailer");
				}}
			]
		});

		spreids(self.o.prefix, ids, {
			"from":(self.o.from?self.o.from:""),
			"reply":(self.o.reply?self.o.reply:""),
			"to":(self.o.to?self.o.to:""),
			"cc":(self.o.cc?self.o.cc:""),
			"bcc":(self.o.bcc?self.o.bcc:""),
			"subject":(self.o.subject?self.o.subject:""),
			"text":(self.o.text?self.o.text:"")
		});

		if (self.o.html) {
			if (typeof(tinyMCE) == "undefined") {
				newwarn("No se puede usar el editor HTML porque no está disponible tinyMCE.");
				self.o.html=false;
			} else {
				tinyMCE.init({

					// setup
					"mode":"exact",
					"elements":"mailer_text",
					"theme":"advanced",
					"language":"es",
					"plugins":"autolink,lists,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template,wordcount,advlist,autosave",
					"convert_urls":false,
					"relative_urls":false,

					// theme options
					theme_advanced_buttons1 : "newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
					theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
					theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
					theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak,restoredraft",
					theme_advanced_toolbar_location : "top",
					theme_advanced_toolbar_align : "left",
					theme_advanced_statusbar_location : "bottom",
					theme_advanced_resizing : true,

					// example content CSS (should be your site CSS)
					content_css : "index.css"

					// drop lists for link/image/media/template dialogs
					//template_external_list_url : "lists/template_list.js",
					//external_link_list_url : "lists/link_list.js",
					//external_image_list_url : "lists/image_list.js",
					//media_external_list_url : "lists/media_list.js",

					// style formats
					/*style_formats : [
						{title : 'Bold text', inline : 'b'},
						{title : 'Red text', inline : 'span', styles : {color : '#ff0000'}},
						{title : 'Red header', block : 'h1', styles : {color : '#ff0000'}},
						{title : 'Example 1', inline : 'span', classes : 'example1'},
						{title : 'Example 2', inline : 'span', classes : 'example2'},
						{title : 'Table styles'},
						{title : 'Table row 1', selector : 'tr', classes : 'tablerow1'}
					]*/

				});
			}
		}

	};

	self.open();

}
