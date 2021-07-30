/*

	xphotos 0.2
	License: GPLv3 (https://www.gnu.org/licenses/gpl-3.0.txt)
	Pablo Rodríguez Rey (mr -at- xkr -dot- es) ~ http://mr.xkr.es/
	Requires: common.js

	Use freely this library under the terms of the GPLv3 license,
	but please, leave the authoring, thanks!

	Usage example:
		var xphotos=new xPhotos({
			"list":[
				{
					"caption":"Custom image caption", // can be a function
					"img":"images/image1.jpg"
				},
				{
					"tn":"images/image2-thumbnail.jpg",
					"img":"images/image2.jpg"
				}
			],
			"caption":"Caption by default",
			"actual":1, // show image 0..n
			"onchange":function(xphotos, actual){
				//alert(actual);
			},
			//"onfirst":function(xphotos, actual){},
			//"onlast":function(xphotos, actual){},
			//"onprev":function(xphotos, actual){},
			//"onnext":function(xphotos, actual){},
			//"onhide":function(xphotos){},
			//"ondestroy":function(xphotos){},
			"onclick":function(xphotos){
				xphotos.hide();
			},
			//"thumbnails":true,
			//"nopreloadnext":true,
			"show":true
		});

*/
function xPhotos(o) {
	var self=this;
	self.o=o;
	self._list=[];
	// revisión de parámetros
	if (!self.o.list) return false;
	if (!self.o.margin) self.o.margin=10;
	if (!self.o.maxScaleWidth) self.o.maxScaleWidth=1;
	if (!self.o.maxScaleHeight) self.o.maxScaleHeight=1;
	
	// mostrar capturas
	self.showThumbnails=function() {
		if (!self._body) return;
		if (self._body_tfoot_td && !self._thumbnails) {
			self._thumbnails=document.createElement("div");
			self._thumbnails.className="xphotos_thumbnails";
			self._thumbnails.addEventListener("click",function(e){
				e.stopPropagation();
				e.preventDefault();
			});
			// crear thumbnails
			for (var i in self.o.list) {
				var src=(self.o.list[i].tn?self.o.list[i].tn:self.o.list[i].img);
				// crear thumbnail
				var tn=document.createElement("div");
				self._list[i].tn=tn;
				tn.setAttribute("data-index", i);
				tn.className="xphotos_thumbnail";
				// crear imagen de thumbnail
				var img=document.createElement("img");
				img.className="xphotos_thumbnail_img";
				img.style.display="none";
				img.src=src;
				// asignar
				tn.appendChild(img);
				self._thumbnails.appendChild(tn);
				// eventos
				img.addEventListener("load",function(e){
					this.style.display="";
				});
				tn.addEventListener("mousedown",function(e){
					e.stopPropagation();
					e.preventDefault();
					var index=parseInt(this.getAttribute("data-index"));
					self.setActual(index);
				});
				tn.addEventListener("click",function(e){
					e.stopPropagation();
					e.preventDefault();
				});
			}
			// clear
			var clear=document.createElement("div");
			clear.style.clear="both";
			// asignaciones
			self._thumbnails.appendChild(clear);
			self._body_tfoot_td.appendChild(self._thumbnails);
		}
	};
	
	// mostrar caption
	self.showCaption=function(caption) {
		if (!caption) var caption=(self.o.list[self.o.actual].caption?self.o.list[self.o.actual].caption:self.o.caption);
		if (typeof(caption) == "function") var caption=caption(self, self.o.list[self.o.actual], self.o.actual);
		if (self._caption) {
			self._caption.innerHTML=(caption?caption:"");
			classEnable(self._caption, "xphotos_caption_visible", caption);
		}
	};

	// mostrar
	self.show=function(index){
		if (typeof(index) != "undefined") self.setActual(index);
		if (!self._body) {
			// fondo
			self._back=document.createElement("div");
			self._back.className="xphotos_back";
			document.body.appendChild(self._back);
			// contenedor
			self._body=document.createElement("div");
			self._body.className="xphotos_body";
			// tabla distribuidora
			self._body_table=document.createElement("table");
			self._body_table.className="xphotos_table";
			self._body_table.height="100%"; // browser compat
			self._body_tbody=document.createElement("tbody");
			self._body_tbody_tr=document.createElement("tr");
			self._body_tbody_td=document.createElement("td");
			self._body_tbody_td.className="xphotos_frame_td";
			self._body_tfoot=document.createElement("tfoot");
			self._body_tfoot_tr=document.createElement("tr");
			self._body_tfoot_td=document.createElement("td");
			self._body_tfoot_td.colSpan=3;
			self._body_tfoot_td.height=1;
			// caption
			self._caption=document.createElement("div");
			self._caption.className="xphotos_caption";
			self._body_tfoot_td.appendChild(self._caption);
			// frame
			self._frame=document.createElement("div");
			self._frame.className="xphotos_frame xphotos_frame_init";
			// waiting
			self._waiting=document.createElement("div");
			self._waiting.className="xphotos_waiting";
			self._waiting.innerHTML="<table class='xphotos_waiting_table'><tr><td><div class='xphotos_waiting_inner'></div></td></tr></table>";
			// imagen
			self._img=document.createElement("img");
			self._img.className="xphotos_img";
			// prev/next
			self._body_prev=document.createElement("td");
			self._body_prev.className="xphotos_prev";
			self._body_next=document.createElement("td");
			self._body_next.className="xphotos_next";
			// enlaces
			self._frame.appendChild(self._waiting);
			self._frame.appendChild(self._img);
			self._frame.setAttribute("tabindex", "-1"); // focusable
			self._body_tbody_td.appendChild(self._frame);
			if (window.addEventListener) self._body_tbody_tr.appendChild(self._body_prev);
			self._body_tbody_tr.appendChild(self._body_tbody_td);
			if (window.addEventListener) self._body_tbody_tr.appendChild(self._body_next);
			self._body_tbody.appendChild(self._body_tbody_tr);
			self._body_table.appendChild(self._body_tbody);
			self._body_tfoot_tr.appendChild(self._body_tfoot_td);
			self._body_tfoot.appendChild(self._body_tfoot_tr);
			self._body_table.appendChild(self._body_tfoot);
			self._body.appendChild(self._body_table);
			document.body.appendChild(self._body);
			self._frame.focus(); // focus to frame for events
			// soporte para navegadores modernos
			if (window.addEventListener) {
				self._body.addEventListener("mousedown",function(e){
					e.stopPropagation();
					e.preventDefault();
				});
				self._body.addEventListener("touchstart",function(e){
					if (e.changedTouches.length == 1) {
						var touchobj=e.changedTouches[0];
						self._startX=touchobj.pageX;
						self._startY=touchobj.pageY;
						//e.preventDefault();
					}
				});
				self._body.addEventListener("touchmove",function(e){
					if (e.changedTouches.length == 1) {
						var touchobj=e.changedTouches[0];
						var dist=Math.floor((touchobj.pageX - self._startX)/5);
						if (Math.abs(dist) > 5) e.preventDefault();
						self._frame.style.transform="translate("+dist+"px,0)";
						//e.preventDefault();
					}
				});
				self._body.addEventListener("touchend",function(e){
					if (e.changedTouches.length == 1) {
						var touchobj=e.changedTouches[0];
						self._frame.style.transform="";
						var dist=(touchobj.pageX - self._startX);
						if (Math.abs(dist) > 50) {
							if (dist > 0) self.next();
							else self.prev();
						}
						delete self._startX;
						delete self._startY;
						//e.preventDefault();
					}
				});
				self._body.addEventListener("click", function(e){
					self.hide();
				});
				self._frame.addEventListener("click", function(e){
					e.stopPropagation();
					if (self.o.onclick) self.o.onclick(self);
					else self.hide();
				});
				self._event_cancel=function(e){
					e.stopPropagation();
					e.preventDefault();
				};
				self._event_prev=function(e){
					self._event_cancel(e);
					self.prev();
				};
				self._event_next=function(e){
					self._event_cancel(e);
					self.next();
				};
				self._body_prev.addEventListener("touchstart", self._event_prev);
				self._body_prev.addEventListener("mousedown", self._event_prev);
				self._body_next.addEventListener("touchstart", self._event_next);
				self._body_next.addEventListener("mousedown", self._event_next);
				self._body_prev.addEventListener("click", self._event_cancel);
				self._body_next.addEventListener("click", self._event_cancel);
				self._frame.addEventListener("keydown",function(e){
					if (!self._body) return;
					switch (e.keyCode) {
					case 36: self.first(); break; // start
					case 35: self.last(); break; // end
					case 33: case 37: self.prev(); break; // repag, up
					case 34: case 39: self.next(); break; // avpag, down
					case 13: case 27: self.hide(); break; // enter, escape
					default: return;
					}
					e.stopPropagation();
					e.preventDefault();
				});
				// resize
				resize(self.resize);
			} else {
				// fallback para navegadores antiguos
				self._body.onclick=function(){
					self.destroy();
				}
			}
			// asignación
			self.update();
			// precarga de la siguiente imagen
			self.preloadNext();
		}
	};
	
	// ocultar y destruir
	self.hide=function(){
		var s=document.createElement('p').style, supportsAnimation='animation' in s;
		if (supportsAnimation) {
			classAdd(self._back, "xphotos_back_hide");
			classAdd(self._body, "xphotos_body_hide");
			gid(self._back).addEventListener("animationend",function(){
				self._back.parentNode.removeChild(self._back);
				delete self._back;
			});
			gid(self._body).addEventListener("animationend",function(){
				if (self._body) {
					self._body.parentNode.removeChild(self._body);
					delete self._body;
				}
			});
		} else {
			self.destroy();
		}
		if (self.o.onhide) self.o.onhide(self);
	};

	// destruir elementos
	self.destroy=function(){
		if (self._back) self._back.parentNode.removeChild(self._back);
		if (self._body) self._body.parentNode.removeChild(self._body);
		if (self._caption) self._caption.parentNode.removeChild(self._caption);
		delete self._back;
		delete self._body;
		delete self._caption;
		if (self.o.ondestroy) self.o.ondestroy(self);
	};

	// evento de escalado
	self.resize=function(){
		if (!self._body) return;
		self._img.style.display="none";
		var w=self._body_tbody_td.clientWidth;
		var h=self._body_tbody_td.clientHeight;
		self._waiting.style.maxWidth=self._img.style.maxWidth=parseInt(w*self.o.maxScaleWidth-self.o.margin*2)+"px";
		self._waiting.style.maxHeight=self._img.style.maxHeight=parseInt(h*self.o.maxScaleHeight-self.o.margin*2)+"px";
		self._img.style.display="block";
	};

	// actualización de imagen
	self.update=function(){
		if (!self._body) return;
		// verificar rango
		if (self.o.actual < 0) self.o.actual=0;
		if (self.o.actual > self.o.list.length-1) self.o.actual=self.o.list.length-1;
		// eventos
		if (window.addEventListener) {
			// inicializar
			for (var i in self.o.list) {
				if (!self._list[i]) self._list[i]={};
			}
			// mostrar caption
			self.showCaption();
			// mostrar capturas
			if (self.o.thumbnails)
				self.showThumbnails();
		}
		// actualizar cuadro de espera
		if (self._img.width && self._img.height) {
			self._waiting.style.width=self._img.width+"px";
			self._waiting.style.height=self._img.height+"px";
			self._waiting.style.display="block";
			self._waiting.style.position="absolute";
		}
		// cambiar estilos
		classAdd(self._img, "xphotos_img_loading");
		classDel(self._img, "xphotos_img_loaded");
		// crear imagen para hacer precarga
		if (window.addEventListener) {
			var img=new Image();
			img.src=self.getImage(self.o.actual);
			img.onload=function(){
				// actualizar estado del thumbnail
				if (isset(self._update_last)) classDel(self._list[self._update_last].tn, "xphotos_thumbnail_actual");
				self._update_last=self.o.actual;
				classAdd(self._list[self.o.actual].tn, "xphotos_thumbnail_actual");
				// actualizar estado de las clases
				classDel(self._frame, "xphotos_frame_init");
				classDel(self._img, "xphotos_img_loading");
				classAdd(self._img, "xphotos_img_loaded");
				// asignar
				self._img.src=img.src;
				self._waiting.style.display="none";
				self._img.style.display="block";
				self._img.style.visibility="visible";
				// actualizar dimensiones
				self.resize();
			};
		} else {
			// actualizar estado de las clases
			classDel(self._frame, "xphotos_frame_init");
			classDel(self._img, "xphotos_img_loading");
			classAdd(self._img, "xphotos_img_loaded");
			// asignar
			self._img.src=self.getImage(self.o.actual);
			self._waiting.style.display="none";
			self._img.style.display="block";
			self._img.style.visibility="visible";
		}
		// actualizar dimensiones
		self.resize();
	};

	// obtener índice actual
	self.getActual=function(){
		return self.o.actual;
	};

	// establecer índice actual
	self.setActual=function(index){
		self.o.actual=index;
		self.update();
		self.preloadNext();
		if (self.o.onchange) self.o.onchange(self, self.o.actual);
	};

	// obtener siguiente fotografía
	self.getImage=function(index){
		return self.o.list[index].img;
	};

	// obtener siguiente fotografía
	self.getNext=function(){
		return (self.o.actual+1) % self.o.list.length;
	};

	// obtener siguiente fotografía
	self.getPrev=function(){
		self.o.actual--;
		if (self.o.actual<0) self.o.actual=self.o.list.length-1;
		return self.o.actual;
	};

	// hacer precarga de la siguiente fotografía
	self.preloadNext=function(){
		if (self.o.nopreloadnext) return;
		self._preload_next=new Image();
		self._preload_next.src=self.getImage(self.getNext());
	}

	// primera fotografía
	self.first=function(){
		self.setActual(0);
		if (self.o.onfirst) self.o.onfirst(self, self.o.actual);
	};

	// última fotografía
	self.last=function(){
		self.setActual(self.o.list.length - 1);
		if (self.o.nolast) self.o.nolast(self, self.o.actual);
	};

	// anterior fotografía
	self.prev=function(){
		self.setActual(self.getPrev());
		if (self.o.onprev) self.o.onprev(self, self.o.actual);
	};

	// siguiente fotografía
	self.next=function(){
		self.setActual(self.getNext());
		if (self.o.onnext) self.o.onnext(self, self.o.actual);
	};

	// mostrar, si especificado al inicio
	if (self.o.show) self.show();

}
