/*

	Título..: VeryTinyAJAX2 0.2c, Wrapper JavaScript simple a funciones XMLHTTP para AJAX.
	Licencia: GPLv2 (http://www.gnu.org/licenses/gpl.txt)
	Autores.: Pablo Rodríguez Rey (mr -en- xkr -punto- es)
	          https://mr.xkr.es/
	          Javier Gil Motos (cucaracha -en- inertinc -punto- org)
	          http://cucaracha.inertinc.org/

	Agradecimientos a Cucaracha, por darme interés en el desarrollo de webs usando
	AJAX y proveerme del ejemplo básico con el que está desarrollada esta librería.

	Ejemplo de usos comunes:

		PETICIÓN COMPLETA (GET ajax=eco POST data=datos):
			ajax({
				"get":{"ajax":"eco"},
				"post":{"data":"datos de ejemplo"},
				"async":function(resultado){
					alert(adump(resultado.data));
				},
				"error":function(error){
					error.show();
				}
			});

		PETICIÓN ABREVIADA EQUIVALENTE (GET ajax=eco POST data=datos):
			ajax("eco","datos de ejemplo",function(resultado){
				alert(adump(resultado.data));
			});

*/

// información de versión
function ajaxVersion() { return("VeryTinyAJAX2/0.2c"); }

// comprobar si las peticiones AJAX están soportadas por este navegador
function ajaxEnabled() {
	return (httpObject()?true:false);
}

// generar un nuevo objeto XMLHttpRequest
function httpObject() {
	var xmlhttp;
	try { xmlhttp=new ActiveXObject("Msxml2.XMLHTTP"); }
	catch (e) { try { xmlhttp=new ActiveXObject("Microsoft.XMLHTTP"); }
	catch (e) { try { xmlhttp=new XMLHttpRequest(); }
	catch (e) { xmlhttp=false; } } }
	return xmlhttp;
}

// cadena de estado de la petición
function httpStateString(state) {
	switch (state) {
	case 0: return "Uninitialized";
	case 1: return "Loading";
	case 2: return "Loaded";
	case 3: return "Interactive";
	case 4: return "Complete";
	case 5: return "Server Crashed";
	}
	return "";
}

// parsear cadena para ser enviada por GET/POST
function gescape(torg) {
	var d=""+torg;
	try { var d=d.replace(/%/gi,"%25"); } catch(e) {}
	try { var d=d.replace(/\"/gi,"%22"); } catch(e) {}
	try { var d=d.replace(/\\/gi,"%5C"); } catch(e) {}
	try { var d=d.replace(/\?/gi,"%3F"); } catch(e) {}
	try { var d=d.replace(/&/gi,"%26"); } catch(e) {}
	try { var d=d.replace(/=/gi,"%3D"); } catch(e) {}
	try { var d=d.replace(/\+/gi,"%2B"); } catch(e) {}
	try { var d=d.replace(/ /gi,"%20"); } catch(e) {}
	return(d);
}

// hard escape: codifica aparte de los especiales,
// también aquellos cuyo ASCII sea <32 o >127
function hescape(t) {
	var s=gescape(t);
	var r="";
	var c;
	for (var i=0;i<s.length;i++) {
		c=s.charCodeAt(i);
		r+=(c<32 || c>127?"%"+c.toString(16):s.charAt(i));
	}
	return r;
}

// función auxiliar para crear cadenas PHP sin caracteres de control
function jescape(torg) {
	var d=""+torg;
	try { var d=d.replace(/\\/gi,"\\\\"); } catch(e) {}
	try { var d=d.replace(/\"/gi,"\\\""); } catch(e) {}
	try { var d=d.replace(/\n/gi,"\\n"); } catch(e) {}
	try { var d=d.replace(/\t/gi,"\\t"); } catch(e) {}
	try { var d=d.replace(/\f/gi,"\\f"); } catch(e) {}
	try { var d=d.replace(/\r/gi,"\\r"); } catch(e) {}
	return(d);
}

// convertir variable JavaScript a JSON
function json(a, level) {
	if (JSON && JSON.stringify) return JSON.stringify(a);
	if (!level) level=0;
	if (a==null) return 'null';
	switch (typeof(a)) {
	case 'object':
		var s="";
		for (var i in a) s+=(s?",":"")+(a.length?'':'"'+jescape(i)+'":')+json(a[i],level+1);
		return (a.length?"[":"{")+s+(a.length?"]":"}");
	case 'boolean': return (a?'true':'false');
	case 'number': return a;
	case 'string': default: return '"'+jescape(a)+'"';
	}
	return null;
}

// petición AJAX
function ajax(data, realdata, func1, func2) {

	// si no está soportado, cancelar petición
	if (!ajaxEnabled()) return false;

	// convertir llamada abreviada en significado real: llamada en plano
	if (typeof(data)=="object" && typeof(realdata)=="function") {
		// configuración por defecto:
		ajax({
			"post":data, // datos que se enviarán por post
			"async":realdata, // función de retorno asíncrona
			"showerrors":false, // se mostrarán errores típicos
			"plain":true // la petición es AJAX o es de texto plano/XML
		});
		return;
	}

	// convertir llamada abreviada en significado real: llamada abreviada
	if (typeof(data)=="string" && typeof(func1)=="function") {
		// configuraciones por defecto
		if (typeof(func2)=="function") {
			ajax({
				"ajax":data, // comando ajax
				"data":realdata, // datos que se enviarán
				"showerrors":true, // se mostrarán errores típicos
				"always":func1, // ejecutar esta función siempre (al terminar o al ocurrir un error)
				"async":func2 // función de retorno asíncrona
			});
		} else {
			ajax({
				"ajax":data, // comando ajax
				"data":realdata, // datos que se enviarán
				"async":func1, // función de retorno asíncrona
				"showerrors":true, // se mostrarán errores típicos
				"plain":(func2?true:false) // la petición es AJAX o es de texto plano/XML
			});
		}
		return;
	}

	// preparar datos
	var http=httpObject();
	var async=(data.sync?false:true);
	var func=(async?data.async:data.sync);
	var always=data.always;
	var post="";
	var url=(typeof(data.url) == "undefined"?location.href:data.url);
	var urlalmp=url.indexOf("#");
	var urlalm="";
	if (urlalmp!=-1) {
		urlalm=url.substring(urlalmp);
		url=url.substring(0, urlalmp);
	}

	// control de eventos HTTP
	function events(http) {

		// objeto resultado
		function result() {

			// estado de la petición y cadena de estado
			try { this.state=http.readyState; } catch(e) { this.state=5; }
			this.stateString=httpStateString(this.state);

			// indicar si se ha completado la operación
			this.complete=(http.readyState == 4?true:false);

			// código de protocolo del servidor
			try { this.status=http.status; } catch(e) { this.status=null; }

			// si el estado es OK, devolver datos
			if (this.status==200) {
				this.http=http;
				this.text=http.responseText; // datos recibidos en texto plano
				this.xml=http.responseXML; // datos recibidos en XML
				this.data=null; // ausencia de datos por defecto
				try { eval("this.data="+http.responseText); }
				catch(e) { this.error=true; } // datos recibidos en JSON son preparados
			}

		}

		// comprobar que la respuesta del servidor es la 200 (HTTP OK)
		var error=false;
		if (http.readyState==4) {
			try { error=(http.status!=200); }
			catch(e) { error=true; }
		}

		// crear objeto resultado
		var r=new result();

		// devolver evento
		if (error) {
			if (data.error || data.showerrors) {
				function result_error() {
					this.status=r.status;
					this.error=(r.status
						?"Se ha encontrado el error "+r.status+" en el servidor."
						:"El servidor no responde a la petición!\nPruebe dentro de unos instantes."
					);
					this.show=function(){
						if (typeof(newerror)=="function") newerror(this.error);
						else alert(this.error);
					}
				}
				var re=new result_error();
				switch (typeof(data.error)) {
				case "boolean": re.show(); break;
				case "string":
					if (typeof(newerror)=="function") newerror(data.error);
					else alert(data.error);
					break;
				case "function": data.error(re); break;
				}
				if (always) always(re);
				if (data.showerrors && re.status) re.show();
			}
		} else {
			if (r.complete) {
				if (data.showerrors && r.error && !data.plain) {
					var alertmsg="Error en buffer de salida: No se puede procesar la petición AJAX";
					if (typeof(newerror)=="function") newerror("<b>"+alertmsg+"</b><hr/>"+r.text);
					else alert(alertmsg+"\n\n"+r.text);
				}
				if (always) always(r);
				func(r);
			} else {
				if (data.events)
					data.events(r);
			}
		}
	
	}

	// si se especifica acción, incluir parámetro GET ajax=acción
	if (data.ajax) url+=(url.indexOf("?")<0?"?":"&")+"ajax="+data.ajax;

	// si se especifican parámetros GET adicionales, incluirlos
	if (data.get) {
		if (typeof(data.get) == "object") {
			for (var i in data.get)
				url+=(url.indexOf("?")<0?"?":"&")+gescape(i)+"="+gescape(data.get[i]);
		} else {
			url+=(url.indexOf("?")<0?"?":"&")+data.get;
		}
	}

	// si se especifican datos por POST, incluirlos
	if (data.post) {
		if (typeof(data.post) == "object") {
			for (var i in data.post)
				post+=(post?"&":"")+gescape(i)+"="+gescape(data.post[i]);
		} else {
			post=data.post;
		}
	}

	// si hay datos prefijados, añadir al POST en formato JSON
	if (data.data) post+=(post?"&":"")+"data="+gescape(json(data.data));

	// petición HTTP
	url+=urlalm;
	http.open((post?"POST":"GET"), url, async);
	if (async) http.onreadystatechange=function(){ events(http); };
	if (data.progress) http.onprogress=data.progress;
	if (data.uploadProgress && http.upload) http.upload.onprogress=data.uploadProgress;
	http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
	//try { http.setRequestHeader("Content-Length", (post?post.length:0)); } catch(e) {}
	http.send(post?post:null);
	if (!async) events(http);

	// completado correctamente
	return true;

}
