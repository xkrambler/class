/*

	AJAX One Click Multiple File Uploader - Requires common.js

	Parameters:
		url       Server URL to request.
		ajax      Create URL request with ajax=value.
		post      Server POST parameters {key:value, ...}.
		input     Bind to an existing input.
		name      File data name (default: files).
		multiple  Allow multiple selection.
		chunked   Boolean or chunk size, to enable chunked file upload for sizes above file size/post length max sizes.
		browse    Automatically browse files.
		drag      Id, element or array of both with draggable zones.

	Events:
		All events has 2 parameters, caller and an object with parameters.
		onfiles      Each time file is added or file list is changed.
		onstart      Upload is going to start.
		oncomplete   Files successfully uploaded.
		onprogress   Files are uploading.
		oncancel     Upload is cancelled.
		onerror      An error has occurred.
		ondragover   When dragging over a zone.
		ondragleave  When drag leaves a zone.
		ondrop       When files are dropped.

	Public Methods:
		get()/set(values)     Get/Set any setup value.
		isBrowserSupported()  Check if browser supports ajax uploading.
		browse([options])     Launch browse.
		submit()              Submit files.
		abort()               Abort upload.
		input(input)          Get/Set input.
		files([files])        Get/Set files.
		file([file])          Get/Set first file.
		filesAdd(files)       Add files.
		fileDel(index)        Delete file by index.
		fileDelByName(name)   Delete file by file name.
		reset()               Reset file list.
		post(post)            Get/Set POST data.
		chunked([chunked])    Get/Set is chunked or chunked size.
		preventDrop(element)  Prevent file drop in any element.
		drag(elements)        Setup event listeners for drag any elements.
		undrag()              Remove any event listeners for drag.

	Private Methods:
		getChunk(blob, start, end, callback)
		inputEvent(event)
		progress(response)
		ondragover(event)
		ondragleave(event)
		ondrop(event)

	Example:
		uploader=new xUploader({
			"ajax":"upload", // "url":"otherurl.php?upload=1"
			//"input":gid("files_id"),
			//"name":"files",
			//"chunked":data.chunk_size,
			"chunked":true,
			"multiple":true,
			"post":{"name":"value"},
			//"drag":["drag1","drag2"],
			"onfiles":function(uploader, o){
				var h=(o.files.length>0?"":"File list cleaned.");
				if (o.files) for (var i=0; i<o.files.length; i++) {
					var file=o.files[i];
					h+=file.name+"<br>\n";
				}
				//gidset("files", h);
			},
			"ondragover":function(uploader, o){
				classAdd(o.zone, "dragzone_over");
			},
			"ondragleave":function(uploader, o){
				classDel(o.zone, "dragzone_over");
			},
			"ondrop":function(uploader, o){
				classAdd(o.zone, "dragzone_drop");
				setTimeout(function(){ classDel(o.zone, "dragzone_drop"); }, 500);
			},
			"onstart":function(uploader, files){
				newwait("Uploading... <span id='progress'></span>");
				//setTimeout(function(){ uploader.abort(); }, 1000);
			},
			"onprogress":function(uploader, r){
				gidset("progress", r.progress+"%"+(r.file?" ("+r.index+"/"+r.count+") "+r.file.name:""));
			},
			"oncomplete":function(uploader, r){
				newwait_close();
				if (r.data.ok) newok("Upload successful: "+r.text);
			},
			"oncancel":function(uploader){
				newwait_close();
			},
			"onabort":function(uploader, r){
				newwarn("Upload aborted.");
			},
			"onerror":function(uploader, r){
				newerror("Upload error"+(r.data && r.data.err?": "+r.data.err:"")+": "+r.text);
				//alert(adump(r));
			},
			"browse":true
		});

*/
function xUploader(o) {
	var self=this;

	// get values
	self.get=function(){
		return self.o;
	};

	// set and verify values
	self.set=function(values){

		// set values
		for (var k in values)
			self.o[k]=values[k];

		// verify values
		if (!self.o.input) {
			self.o.input=document.createElement("input");
			self.o.input.name=self.o.name;
			self.o.input.type='file';
			self.o.input.size='1';
			self.o.input.runat='server';
			self.o.input.style.display="none";
			document.body.appendChild(self.o.input); // requerido IOS
		}
		if (self.o.multiple) self.o.input.setAttribute('multiple', '');
		var i=self.o.input.name.indexOf("[]");
		if (self.o.input.multiple && i == -1) self.o.input.name+='[]';
		if (!self.o.input.multiple && i != -1) self.o.input.name=self.o.input.name.substring(0, i);

	};

	// check if browser is supported
	self.isBrowserSupported=function(){
		return (FormData && self.o.input && XMLHttpRequest);
	};

	// get chunk
	self.getChunk=function(blob, start, end, callback){
		var chunk;
		if (blob.webkitSlice) chunk=blob.webkitSlice(start, end);
		else if (blob.mozSlice) chunk=blob.mozSlice(start, end);
		else chunk=blob.slice(start, end);
		callback(chunk);
	};

	// prepare Form Data
	self.send=function(){

		// checks
		if (!self.isBrowserSupported() || typeof(self.data.files) == "undefined") return false;

		// form data
		var fd=new FormData();

		// post additional fields
		for (var k in self.o.post) fd.append(k, self.o.post[k]);

		// delete requested post fields for next request
		if (self.o.postunique) for (var i in self.o.postunique) delete self.o.post[self.o.postunique[i]];

		// files
		self.data.count=self.o.files.length;
		if (self.data.count > 0 && self.chunked()) {
			// read next file chunk
			for (var i=0; i < self.o.files.length; i++) {
				if (!self.data.files[i].sent) {
					self.data.index=i+1;
					self.data.file=self.o.files[i];
					var start=self.data.files[i].start;
					var end=start+self.chunked();
					var ended=(end >= self.o.files[i].size);
					var status=(ended?(start?"end":"complete"):(start?"resume":"start"))+"."+(i+1)+"."+self.o.files.length+"."+self.o.files[i].size;
					// mark ended files and next start
					if (ended) {
						end=self.o.files[i].size;
						self.data.files[i].sent=true;
					}
					self.data.files[i].start=end;
					self.data.files[i].sending=end-start;
					// send chunk
					(function() {
						// get file chunk
						self.getChunk(self.o.files[i], start, end, function(filedata){
							// create a file field with the chunk, and mimetype with chunk status
							fd.append(self.o.input.name, new Blob([filedata], {type:self.o.files[i].type+"//"+status}), self.o.files[i].name);
							self.ajax(fd);
						});
					})();
					break;
				}
			}
		} else {
			// normal file send
			for (var i=0; i < self.o.files.length; i++)
				fd.append(self.o.input.name, self.o.files[i]);
			// ajax request
			self.ajax(fd);
		}

	};

	// get/set chunk size or false/NaN if disabled
	self.chunked=function(chunked){
		if (typeof(chunked) != "undefined") self.o.chunked=chunked;
		if (self.o.chunked === true) return 1024*1024;
		else if (self.o.chunked) return parseInt(self.o.chunked);
		return false;
	};

	// get progress
	self.progress=function(r){
		var loaded=0, total=0;
		if (self.o.chunked) {
			for (var i=0; i < self.o.files.length; i++) {
				loaded+=self.data.files[i].start;
				if (!self.data.files[i].sent) loaded+=self.data.files[i].sending;
				total+=self.o.files[i].size;
			}
			if (typeof(r) != "undefined" && r.loaded && r.total) {
				for (var i=0; i < self.o.files.length; i++)
					if (!self.data.files[i].sent)
						loaded+=self.data.files[i].sending*r.loaded/r.total;
			}
		} else {
			if (typeof(r) != "undefined" && r.loaded) {
				loaded=r.loaded;
				total=(r.total?r.total:r.loaded);
			}
		}
		if (loaded > total) loaded=total;
		var d={
			"progress":Math.round(loaded*100/total),
			"loaded":loaded,
			"total":total,
			"index":false,
			"count":self.data.count
		};
		if (self.chunked()) {
			d.index=self.data.index;
			if (self.data.file) d.file={
				"name":self.data.file.name,
				"type":self.data.file.type,
				"size":self.data.file.size
			};
		}
		return d;
	}

	// AJAX request and events
	self.ajax=function(fd){
		self.xhr=new XMLHttpRequest();
		self.xhr.upload.addEventListener("progress", function(r){
			var o=self.progress(r);
			o.response=r;
			if (!self.o.chunked && self.o.onprogress) self.o.onprogress(self, o);
		}, false);
		self.xhr.addEventListener("load", function(r){
			//alert(adump(r));
			var o=self.progress(r);
			o.response=r;
			if (self.o.chunked && self.o.onprogress) self.o.onprogress(self, o);
			if (self.o.oncomplete) {
				// get response and parse JSON
				o.text=r.target.responseText;
				try { o.data=JSON.parse(r.target.responseText); } catch(e) { o.data=null; }
				// if chunked, check if all files are sent, if not, continue sending chunks
				if (self.chunked()) {
					if (!o.data) o.data={};
					if (!o.data.ok) {
						o.data.chunked=true;
						if (!o.data.err) o.data.err="Chunked upload error";
						if (self.o.oncancel) self.o.oncancel(self, o);
						if (self.o.onerror) self.o.onerror(self, o);
						return;
					}
					for (var i=0; i < self.o.files.length; i++) {
						if (!self.data.files[i].sent) {
							self.send(); // continue with next chunk
							return;
						}
					}
				}
				// no more chunks or not chunked, upload completed
				self.o.oncomplete(self, o);
				if (!o.data.ok && self.o.onerror) self.o.onerror(self, o);
			}
		}, false);
		self.xhr.addEventListener("error", function(r){
			if (self.o.oncancel) self.o.oncancel(self, r);
			if (self.o.onerror) self.o.onerror(self, r);
		}, false);
		self.xhr.addEventListener("abort", function(r){
			if (self.o.oncancel) self.o.oncancel(self, r);
			if (self.o.onabort) self.o.onabort(self, r);
		}, false);
		self.xhr.open("POST", (self.o.ajax?alink({"ajax":self.o.ajax}):self.o.url));
		self.xhr.send(fd);
	};

	// abort request
	self.abort=function(){
		self.xhr.abort();
	};

	// submit files
	self.submit=function(){

		// if autosubmit, if done, remove
		delete self.o.autosubmit;

		// check if browser is supported
		if (!self.isBrowserSupported()) return false;

		// restart submit data and initialize file submit counters
		if (!self.o.files) self.o.files=[];
		self.data.files=[];
		for (var i=0; i < self.o.files.length; i++)
			self.data.files[i]={"start":0, "sending":0, "sent":false};

		// run onstart callback, when file browsing closed
		if (self.o.onstart) self.o.onstart(self, {"files":self.o.files});

		// start to send
		self.send();

		// all ok
		return true;

	};

	// get/set input
	self.input=function(input){
		if (typeof(input)!="undefined") {
			self.o.input=input;
			self.o.input.addEventListener('change', self.inputEvent, false);
		}
		return self.o.input;
	};

	// event: input
	self.inputEvent=function(event){
		self.filesAdd(self.o.input.files, {
			"action":"input",
			"event":event,
			"callback":function(o){
				if (self.o.onselect) self.o.onselect(self, {"selected":self.o.input.files, "files":self.files()});
				if (self.o.autosubmit || self.o.submit) self.submit();
			}
		});
	};

	// add files
	self.filesAdd=function(files, o){
		var o=o||{};
		var names={};
		var scaling=(self.o.scale && window.ImageResizer);
		if (self.o.scale && !window.ImageResizer) console.warn("No ImageResizer library loaded for image scaling.");
		var async_scaling=false;
		if (!self.o.repeat)
			for (var i=0; i < self.o.files.length; i++)
				names[self.o.files[i].name]=true;
		for (var i=0; i < files.length; i++)
			if (!names[files[i].name])
				names[files[i].name]=false;
		for (var i=0; i < files.length; i++) {
			if (!names[files[i].name]) {
				if (scaling) {
					if (self.o.onscale && !async_scaling) {
						async_scaling=true;
						self.o.onscale(self);
					}
					(function(self, file, file_index){
						// resize image with ImageResizer
						ImageResizer.resizeImage(file, {
							debug:            true,
							//resize:           false,
							//convertToJpg:     true,
							//convertToJpgBgColor: "#FFFFFF"
							renameFile:       false,
							upscale:          false,
							pngToJpg:         false,
							sharpen:          (self.o.scale.s || 0.15),
							maxWidth:         (self.o.scale.w || null),
							maxHeight:        (self.o.scale.h || null),
							jpgQuality:       (self.o.scale.q || 0.95),
							returnFileObject: false
						}, function(result){

							result.name=file.name;
							var result_file=new File([result], file.name, {type: result.type}); // return as file object

							names[result_file.name]=true;
							files[file_index]=result_file;
							self.o.files.push(result_file);

							var all_processed=true;
							for (var n in names)
								if (!names[n])
									all_processed=false;

							if (all_processed) {
								o.added=files;
								o.files=self.o.files;
								if (self.o.onfiles) self.o.onfiles(self, o);
								if (self.o.onscaled) self.o.onscaled(self, o);
								if (o.callback) o.callback(o);
							}

						});
					})(self, files[i], i);
				} else {
					self.o.files.push(files[i]);
				}
			}
		}
		if (!async_scaling) {
			o.added=files;
			o.files=self.o.files;
			if (self.o.onfiles) self.o.onfiles(self, o);
			if (o.callback) o.callback(o);
		}
	};

	// delete file by index
	self.fileDel=function(index){
		var file=false;
		var files=[];
		for (var i=0; i < self.o.files.length; i++) {
			if (i == index) file=self.o.files[i];
			else files.push(self.o.files[i]);
		}
		self.o.files=files;
		if (self.o.onfiles) self.o.onfiles(self, {"del":file, "index":index, "files":self.o.files});
		return file;
	};

	// delete file by file name
	self.fileDelByName=function(name){
		for (var i=0; i < self.o.files.length; i++)
			if (self.o.files[i].name == name)
				return self.fileDel(i);
		return false;
	};

	// get/set files
	self.files=function(files){
		if (isset(files)) {
			self.o.files=files;
			if (self.o.onfiles) self.o.onfiles(self, {"files":self.o.files});
		}
		return self.o.files;
	};

	// get/set first file
	self.file=function(file){
		if (isset(file)) {
			if (!self.o.files) self.o.files=[];
			self.o.files[0]=file;
			if (self.o.onfiles) self.o.onfiles(self, {"files":self.o.files});
		}
		return (self.o.files && self.o.files[0]?self.o.files[0]:false);
	};

	// reset files
	self.reset=function(){
		if (self.o.input) self.o.input.value="";
		self.files([]);
	};

	// get/set post data
	self.post=function(post){
		if (isset(post)) self.o.post=post;
		return self.o.post;
	};

	// browse files
	self.browse=function(o){
		var o=o||{};
		if (!self.o.input) return false;

		// submit event
		if (isset(o.submit)) self.o.autosubmit=o.submit;

		// launch browse
		if (document.createEvent) {
			var evt=document.createEvent("MouseEvents");
			evt.initEvent("click", true, true);
			self.o.input.dispatchEvent(evt);
		}

		return true;
	}

	// private: prevent drop for window specific event listener
	self._windowPreventDrop=function(e){ e=e || event; e.preventDefault(); }

	// prevent drop default actions
	self.preventDrop=function(element){
		if (element && element.addEventListener) {
			element.addEventListener("drop",     function(e){ e=e || event; e.preventDefault(); }, false);
			element.addEventListener("dragover", function(e){ e=e || event; e.preventDefault(); }, false);
			return true;
		} else if (!element && window.addEventListener) {
			window.addEventListener("drop",     self._windowPreventDrop, false);
			window.addEventListener("dragover", self._windowPreventDrop, false);
			return true;
		}
		return false;
	};

	// event: on drag over
	self.ondragover=function(event){
		if (self.o.ondragover) self.o.ondragover(self, {"zone":this, "id":this.id, "event":event});
		event.preventDefault();
	};

	// event: on drag leave
	self.ondragleave=function(event){
		if (self.o.ondragleave) self.o.ondragleave(self, {"zone":this, "id":this.id, "event":event});
		event.preventDefault();
	};

	// event: on drop
	self.ondrop=function(event){
		var files=event.dataTransfer.files;
		if (self.o.ondragleave) self.o.ondragleave(self, {"zone":this, "id":this.id, "event":event});
		if (self.o.ondrop) self.o.ondrop(self, {"zone":this, "id":this.id, "event":event, "files":files});
		// prevent default
		event.preventDefault();	
		event.stopPropagation();		
		// prepare upload
		self.filesAdd(files, {
			"action":"drop",
			"zone":this,
			"id":this.id,
			"event":event,
			"callback":function(){
				if (self.o.autosubmit || self.o.submit) self.submit();
			}
		});
	};

	// setup drag
	self.drag=function(elements){
		if (!window.addEventListener) return false;
		// prevent window drop default actions
		self.preventDrop();
		// set elements
		if (isset(elements)) self.o.drag=(Array.isArray(elements)?elements:[elements]);
		// set event listeners
		for (var i=0; i < self.o.drag.length; i++) {
			var zone=gid(self.o.drag[i]);
			if (zone && zone.addEventListener) {
				zone.addEventListener("dragover",  self.ondragover,  true);
				zone.addEventListener("dragleave", self.ondragleave, true);
				zone.addEventListener("drop",      self.ondrop,      true);
			}
		}
		// ok
		return true;
	};

	// unset event listeners
	self.undrag=function(){
		if (!window.addEventListener) return false;
		// unregister
		for (var i=0; i < self.o.drag.length; i++) {
			var zone=gid(self.o.drag[i]);
			if (zone && zone.removeEventListener) {
				zone.removeEventListener("dragover",  self.ondragover,  true);
				zone.removeEventListener("dragleave", self.ondragleave, true);
				zone.removeEventListener("drop",      self.ondrop,      true);
			}
		}
		// ok
		return true;
	};

	// remove registered events
	self.destroy=function(){
		self.undrag();
	};

	// default values
	self.o={
		url: location.href,
		files: [],
		post: {},
		repeat: false,
		name: 'files',
		multiple: false,
		chunked: false,
		onstart: null,
		oncomplete: null,
		onprogress: null,
		oncancel: null,
		onerror: null
	};
	self.set(o);

	// default data
	self.data={"files":[]};

	// enable input event
	if (self.o.input) self.input(self.o.input);

	// enable drags
	if (self.o.drag) self.drag(self.o.drag);

	// browse if requested
	if (self.o.browse) self.browse({"submit":true});

}
