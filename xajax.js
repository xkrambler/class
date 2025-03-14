/*

	verytinyAJAX3 v0.3a
	Simple XMLHTTP wrapper to implement simple AJAX requests and webservice calls.
	(cc)2020 by Pablo Rodríguez Rey (mr -at- xkr -dot- es)

	Examples:

		new xajax({
			url:location.href,
			ajax:"test",
			data:{"hola":"que tal","integer":32,"float":32.97,"bool":true,"null":null},
			async:function(r){
				console.log("async");
				console.log(r);
			},
			always:function(){
				console.log("always!");
			},
			complete:function(r){
				console.log("complete");
				console.log(r);
			}
		});

		ajax("ajax", data, function(){ console.log("always!"); }, function(r){
			console.log("complete");
			console.log(r);
		});

*/
function xajax(o){
	const self=this;
	self.o=o;
	self.err="";
	self.states=["Uninitialized", "Loading", "Loaded", "Interactive", "Complete", "Server Crashed"];

	// get/set last error
	self.error=function(err){
		if (self.isset(err)) {
			self.err=err;
			return false;
		}
		return self.err;
	};

	// check if a variable is set
	self.isset=function(v){
		return (typeof(v) !== "undefined");
	};

	// get XMLHttpRequest object
	self.http=function() {
		var h=null;
		try { h=new ActiveXObject("Msxml2.XMLHTTP"); }
		catch (e) { try { h=new ActiveXObject("Microsoft.XMLHTTP"); }
		catch (e) { try { h=new XMLHttpRequest(); }
		catch (e) { h=false; } } }
		return h;
	};

	// return state by number
	self.state=function(state){
		return self.states[parseInt(state)];
	};

	// aux function to escape JSON strings
	self.jescape=function(s) {
		var r=""+s;
		try { r=r.replace(/\\/gi,"\\\\"); } catch(e) {}
		try { r=r.replace(/\"/gi,"\\\""); } catch(e) {}
		try { r=r.replace(/\n/gi,"\\n"); } catch(e) {}
		try { r=r.replace(/\t/gi,"\\t"); } catch(e) {}
		try { r=r.replace(/\f/gi,"\\f"); } catch(e) {}
		try { r=r.replace(/\r/gi,"\\r"); } catch(e) {}
		return r;
	}

	// JSON.stringify implementation
	self.json=function(a, level) {
		if (self.isset(JSON) && JSON && JSON.stringify) return JSON.stringify(a); // native
		if (!level) level=0;
		if (a === null) return 'null';
		switch (typeof(a)) {
		case 'object':
			var s="";
			for (var i in a) s+=(s?",":"")+(a.length?'':'"'+self.jescape(i)+'":')+self.json(a[i], level+1);
			return (Array.isArray(a)?"[":"{")+s+(a.length?"]":"}");
		case 'boolean': return (a?'true':'false');
		case 'number': return a;
		case 'string': default: return '"'+self.jescape(a)+'"';
		}
		return null;
	};

	// parse array from JSON
	self.data=function(json) {
		var r;
		try { 
			if (self.isset(JSON) && JSON && JSON.parse) r=JSON.parse(json); // native mode
			else eval("r="+json); // decode using eval method (insecure)
		} catch(e) {
			r=null; // error decoding
		};
		return r;
	};

	// url get parameters decode
	self.urlParam=function(url){
		var p=url.indexOf('?');
		var a={};
		if (p != -1) {
			var pairs=url.substring(p+1).split('&');
			for (var i=0; i < pairs.length; i++) {
				var pair=pairs[i].split('=');
				a[decodeURIComponent(pair[0])]=decodeURIComponent(typeof(pair[1]) !== "undefined"?pair[1]:"");
			}
		}
		return a;
	};

	// build query string
	self.param=function(a){
		var s=[];
		var add=function(k, v) {
			v=(typeof(v) === 'function'?v():v);
			s.push(encodeURIComponent(k)+(v === null || v === undefined || v === ''?'':'='+encodeURIComponent(v)));
		};
		var buildParams=function(prefix, obj) {
			var i, len, key;
			if (prefix) {
				if (Array.isArray(obj)) {
					for (i=0, len=obj.length; i < len; i++)
						buildParams(prefix+'['+(typeof obj[i] === 'object' && obj[i]?i:'')+']', obj[i]);
				} else if (String(obj) === '[object Object]') {
					for (key in obj)
						buildParams(prefix+'['+key+']', obj[key]);
				} else {
					add(prefix, obj);
				}
			} else if (Array.isArray(obj)) {
				for (i=0, len=obj.length; i < len; i++)
					add(obj[i].name, obj[i].value);
			} else {
				for (key in obj)
					buildParams(key, obj[key]);
			}
			return s;
		};
		return buildParams('', a).join('&');
	};

	// alter URL
	self.alink=function(a, url) {
		var marker="";
		// save marker
		var i=url.indexOf("#");
		if (i !== -1) {
			marker=url.substring(i);
			url=url.substring(0, i);
		}
		// get current parameters
		var param=self.urlParam(url);
		// remove parameters from URL
		var i=url.indexOf("?");
		if (i !== -1) url=url.substring(0, i);
		// set parameters
		if (a) for (var k in a) {
			var v=a[k];
			if (v === null) delete param[k];
			param[k]=v;
		}
		// build query string
		var qs=self.param(param);
		// return altered URL
		return url+(qs.length?"?"+qs:"")+(marker.length?"#"+marker:"");
	};

	// onreadystatechange event controller
	self.onreadystatechange=function(http, o){

		// event information
		var r={
			status:null,
			state:null,
			complete:false,
			text:null,
			xml:null,
			data:null
		};

		// get status/state information
		try { r.status=http.status; } catch(e) { r.status=false; }
		try { r.state=http.readyState; } catch(e) { r.state=false; }

		// set if is completed
		r.complete=(r.state == 4);

		// set requested options
		r.request=o;

		// parse data
		r.data=self.data(http.responseText); // from JSON to JS
		r.text=http.responseText; // plain text
		r.xml=http.responseXML; // XML response

		// set HTTP and self objects
		r.http=http;
		r.self=self;

		// state change events
		if (o.event) o.event(r);

		// always/async/complete/error events
		if (r.complete) {
			if (o.always) o.always(r);
			if (o.async) o.async(r);
			if (o.complete && r.status >= 200 && r.status < 300) o.complete(r);
			else if (o.error) o.error(r);
		}

		// return synchronous information
		return r;

	};

	// AJAX request
	self.ajax=function(options){

		// copy and merge options for this request
		var o={};
		if (self.o)
			for (var k in self.o)
				o[k]=self.o[k]
		if (options)
			for (var k in options)
				o[k]=options[k];

		// clear last error
		self.error("");

		// create XMLHttpRequest object
		var http=self.http();
		if (!http) return self.error("Cannot create XMLHttpRequest object.");

		// mimetype
		var mime=o.mime;

		// prepare GET
		var get={};
		if (o.ajax) get.ajax=o.ajax;
		if (o.get)
			for (var k in o.get)
				get[k]=o.get[k];

		// prepare PUT
		if (o.put) {
			o.method="PUT";
			o.post=o.put;
		}

		// prepare POST
		var post=(o.post?o.post:false);
		var fd=(o.formdata?o.formdata:false);

		// JSON post/custom
		if (self.isset(o.json)) {
			mime="application/json; charset=UTF-8";
			post=self.json(o.json);
		} else {

			// if post is object, convert
			if (fd || post || self.isset(o.data)) {

				// if no FormData defined but is an array post or data
				if (!fd && (typeof(post) == "object" || typeof(o.data) == "object")) {

					// instance FormData
					fd=new FormData();

					// iterate data
					for (var k in post) fd.append(k, post[k]);

					// data JSON
					if (self.isset(o.data)) {
						// separate Files/Blobs to FormData
						for (var k in o.data) if (o.data[k] instanceof Blob || o.data[k] instanceof File) {
							fd.append(k, o.data[k]);
						}
						// data as JSON
						fd.append("data", self.json(o.data));
					}

				}

			}

		}

		// prepare final parameters
		var async=(o.sync?false:true);
		var method=(o.method?o.method:(fd || post?"POST":"GET"));
		var url=self.alink(get, (o.url?o.url:location.href));
		var headers=o.headers || {};
		if (mime) headers['Content-Type']=mime;

		// timeout event
		http.ontimeout=function(e){
			if (data.timeout) {
				var d={"timeout":e};
				if (always) always(d);
				data.error(d);
			}
		};

		// do request
		try {
			http.open(method, url, async, (self.isset(o.user)?o.user:null), (self.isset(o.pass)?o.pass:null));
			if (!fd && typeof(post) == "string") http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			if (headers) for (var k in headers) if (typeof(headers[k]) === "string") http.setRequestHeader(k, headers[k]);
			if (async) http.onreadystatechange=function(){ self.onreadystatechange(http, o); };
			if (o.progress) http.onprogress=o.progress;
			if (o.timeout) http.timeout=o.timeout;
			if (o.uploadprogress && http.upload) http.upload.onprogress=o.uploadprogress;
			http.send(fd?fd:(post?post:null));
			if (!async) return self.onreadystatechange(http, o);
		} catch(e) {
			return self.error(".ajax(): "+e);
		}

		// ok
		return true;

	};

	// if complete at startup, request ajax
	if (self.o && (
		self.o.complete
		|| self.o.sync
		|| self.o.async
		|| self.o.always
	)) self.ajax();

}

// abbreviated call
function ajax(a, b, c, d){
	if (typeof(a) == "string") {
		var a={
			"ajax":a,
			"data":b,
			"error":function(r){
				console.warn("ajax("+a+") "+r.status+": "+r.text);
				var error=(r.status
					?"Se ha encontrado el error "+r.status+" en el servidor."
					:"El servidor no responde a la petición!\nPruebe dentro de unos instantes."
				);
				if (typeof(newerror) == "function") newerror(error);
				else alert(error);
			},
			"always":c,
			"complete":(d?d:c)
		};
		if (!d) delete a.always;
	}
	return new xajax(a);
}
