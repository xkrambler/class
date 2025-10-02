var xRegion={
	
	"coordsToXYString":function(coords,aprox){
		var s="";
		for (var i in coords)
			s+=(s?",":"")
				+(aprox?Math.round(coords[i].x):coords[i].x)+","
				+(aprox?Math.round(coords[i].y):coords[i].y);
		return s;
	},
	
	"coordsFromXYString":function(xystring){
		var lista=[];
		var c=xystring.split(",");
		for (var i in c) {
			if (!(i%2)) x=c[i];
			else lista.push({"x":parseFloat(x),"y":parseFloat(c[i])});
		}
		return lista;
	},

	"hexToRGB":function(hex) {
		var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
		return result ? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16)
		} : null;
	},

	"map":function(o){

		// init
		var a=this;
		a.o=o;
		a.data={
			measure:{
				points:{}
			}
		};

		// ver si está deshabilitado temporalmente
		if (isset(o.disabled)) a.disabled=o.disabled;

		a.isie7=function(){ return (navigator.userAgent.indexOf("MSIE 7")!=-1); }
		a.isie78=function(){ return (navigator.userAgent.indexOf("MSIE 7")!=-1 || navigator.userAgent.indexOf("MSIE 8")!=-1); }
		a.hasCanvasEvents=function(){ return !a.isie7(); }

		// centrar mapa
		a.center=function(){
			a.tmp.centerMap=true;
			a.redraw();
		}

		// return results
		a.get=function(){
			return {
				"points":o.points,
				"regions":o.regions,
				"deleted":a.deleted
			}
		};

		// set edit mode
		a.setEditable=function(enabled){
			a.cancelRegion();
			o.editable=enabled;
			a.redraw();
			a.setCursor();
		};

		// cursor
		a.setCursor=function(cursor){
			if (a.o.cursor) {
				o.canvas.style.cursor=a.o.cursor;
			} else {
				switch (cursor) {
				default:
					if (a.tmp.startMove) {
						o.canvas.style.cursor="move";
					} else {
						//if (o.editable) {
							if (a.tmp.startRegion) o.canvas.style.cursor="crosshair";
							else o.canvas.style.cursor="default";
						//} else {
						//	o.canvas.style.cursor="url(cursor_hand.png),move";
						//}
					}
				}
			}
		},

		// adjust scale limits
		a.setScale=function(newScale){
			if (newScale == "image") {
				o.canvas.width=a.img.width;
				o.canvas.height=a.img.height;
				newScale=1;
			}
			if (newScale == "fit") {
				var rw=o.canvas.width/a.img.width*(o.fitscale?o.fitscale:1);
				var rh=o.canvas.height/a.img.height*(o.fitscale?o.fitscale:1);
				newScale=(rw>rh?rh:rw);
			}
			a.scale=newScale;
			if (a.scale<a.minScale) a.scale=a.minScale;
			if (a.scale>a.maxScale) a.scale=a.maxScale;
		};

		// dibuja texto con borde
		a.fillTextBorder=function(caption, x, y, color, style){
			var style=style || a.styles.xregion_caption;
			var size=parseInt(a.scale*style.fontSize);
			if (size > style.fontSizeMax) size=style.fontSizeMax;
			a.c.font=size+"px "+style.fontFamily;
			a.c.fillStyle=style.borderColor;
			var size=style.border;
			a.c.fillText(caption, x-size, y);
			a.c.fillText(caption, x-size, y-size);
			a.c.fillText(caption, x-size, y+size);
			a.c.fillText(caption, x+size, y);
			a.c.fillText(caption, x+size, y-size);
			a.c.fillText(caption, x+size, y+size);
			a.c.fillText(caption, x, y-size);
			a.c.fillText(caption, x, y+size);
			a.c.fillStyle=(color?color:style.color);
			a.c.fillText(caption, x, y);
			var r=a.c.measureText(caption);
			r.height=r.fontBoundingBoxAscent+r.fontBoundingBoxDescent;
			r.textHeight=r.actualBoundingBoxAscent+r.actualBoundingBoxDescent;
			return r;
		};

		// redraw canvas
		a.redraw=function(notDelayed){
			if (this._redrawTimeout) clearTimeout(this._redrawTimeout);
			var _redraw=function(){

				// si todavía no se ha cargado la imágen, esperar
				if (!a.img.width || !a.img.height) { a.redraw(); return; }

				// resize
				o.canvas.width=o.id.clientWidth;
				o.canvas.height=o.id.clientHeight;

				// primero centramos el mapa
				if (a.tmp.centerMap) {
					a.setScale(a.scale);
					a.dx=-((a.img.width/2)*a.scale-o.canvas.width/2);
					a.dy=-((a.img.height/2)*a.scale-o.canvas.height/2);
					delete a.tmp.centerMap;
				}

				// vemos si hay que centrar alguna región
				if (a.tmp.centerRegion) {
					if (a.tmp.regions[a.tmp.centerRegion]) {
						var rect=a.tmp.regions[a.tmp.centerRegion].rect;
						if (isset(rect)) {
							var rw=a.img.width/(rect.right-rect.left);
							var rh=a.img.height/(rect.bottom-rect.top);
							a.setScale((rw>rh?rh:rw)/2);
							var x=(rect.left+rect.right)/2;
							var y=(rect.top+rect.bottom)/2;
							a.dx=-(x*a.scale-o.canvas.width/2);
							a.dy=-(y*a.scale-o.canvas.height/2);
						}
					}
					delete a.tmp.centerRegion;
				}

				// vemos si hay que centrar algun punto
				if (a.tmp.centerPoint) {
					if (o.points[a.tmp.centerPoint]) {
						var p=o.points[a.tmp.centerPoint];
						a.dx=-(p.x*a.scale-o.canvas.width/2);
						a.dy=-(p.y*a.scale-o.canvas.height/2);
					}
					delete a.tmp.centerPoint;
				}

				// real width
				var w=a.img.width*a.scale;
				var h=a.img.height*a.scale;

				// scroll control
				if (o.strict) {
					var mdx=(w-o.canvas.width);
					var mdy=(h-o.canvas.height);
					if (a.dx>0) a.dx=0;
					if (a.dy>0) a.dy=0;
					if (mdx>0) { if (-a.dx>mdx) a.dx=-mdx; } else a.dx=-mdx/2;
					if (mdy>0) { if (-a.dy>mdy) a.dy=-mdy; } else a.dy=-mdy/2;
				} else {
					if (a.dx>o.canvas.width/2) a.dx=o.canvas.width/2;
					if (a.dx<-(w-o.canvas.width/2)) a.dx=-(w-o.canvas.width/2);
					if (a.dy>o.canvas.height/2) a.dy=o.canvas.height/2;
					if (a.dy<-(h-o.canvas.height/2)) a.dy=-(h-o.canvas.height/2);
				}

				// background
				a.c.fillStyle=(a.o.backgroundColor?a.o.backgroundColor:a.styles.xregion_background.color);
				a.c.fillRect(0,0,o.canvas.width,o.canvas.height);

				// map
				var rx=a.dx*1, ry=a.dy*1;
				if (a.isie7()) var rx=(a.dx>0?a.dx/1.1:a.dx), ry=(a.dy>0?a.dy/1.1:a.dy);
				a.c.drawImage(a.img, rx, ry, a.img.width*a.scale, a.img.height*a.scale);

				// ver si se está en modo edición
				var isEditing=(o.editable && !o.disableAutoHide && (a.tmp.startRegion || (a.clicked && a.clicked.region) || a.tmp.startMoveRegion || a.tmp.startMoveCoord));
				var activeRegion=(a.tmp.startRegion?a.tmp.startRegion:((a.clicked && a.clicked.region) || a.tmp.startMoveRegion || a.tmp.startMoveCoord?a.clicked.region:""));

				// dibujar rectángulo clickeables
				for (var region in a.tmp.regions) {
					if (isEditing && activeRegion!=region) continue;
					if (o.regions[region].readonly || !o.editable) continue;
					var rect=a.tmp.regions[region].rect;
					//a.c.beginPath();
					var lineWidth=(a.clicked && a.clicked.region==region?a.styles.xregion_region_rect_active.size:a.styles.xregion_region_rect.size);
					if (lineWidth) {
						a.c.lineWidth=lineWidth;
						a.c.strokeStyle=(a.clicked && a.clicked.region==region?a.styles.xregion_region_rect_active.color:a.styles.xregion_region_rect.color);
						a.c.strokeRect(a.dx+rect.left*a.scale, a.dy+rect.top*a.scale, (rect.right-rect.left)*a.scale, (rect.bottom-rect.top)*a.scale);
					}
				}

				// dibujar polígonos
				for (var region in o.regions) {
					if (isEditing && activeRegion!=region) continue;
					// obtener coordenadas
					var coords=o.regions[region].coords;
					if (isset(a.tmp.regions[region].rect)) {
						var line_coords=array_copy(coords);
						// no cerrar si es la región que se está creando
						if (region!=a.tmp.startRegion)
							line_coords.push({"x":coords[0].x,"y":coords[0].y});
						// dibujar polígonos
						var drawPolyRegion=function(){
							a.c.beginPath();
							for (var i in line_coords) {
								var p=line_coords[i];
								if (lp) {
									var rx=a.dx+p.x*a.scale,ry=a.dy+p.y*a.scale;
									a.c.lineTo(rx,ry);
								} else {
									a.c.moveTo(a.dx+p.x*a.scale,a.dy+p.y*a.scale);
								}
								//a.c.closePath();
								var lp=p;
							}
							a.c.stroke();
						};
						// poligono1: background lines
						a.c.lineWidth=a.styles.xregion_building_backrect.size;
						a.c.strokeStyle=(
							o.regions[region].borderColor
							?o.regions[region].borderColor
							:a.styles.xregion_building_backrect.color
						);
						drawPolyRegion();
						a.c.lineWidth=(a.clicked && a.clicked.region==region
							?a.styles.xregion_building_active.size
							:(a.hover && a.hover.region==region
								?a.styles.xregion_building_hover.size
								:a.styles.xregion_building.size
							)
						);
						a.c.strokeStyle=(
							o.regions[region].color
							?o.regions[region].color
							:(a.clicked && a.clicked.region==region
								?a.styles.xregion_building_active.color
								:(a.hover && a.hover.region==region
									?a.styles.xregion_building_hover.color
									:a.styles.xregion_building.color
								)
							)
						);
						if (o.regions[region].fillColor) {
							a.c.fillStyle=o.regions[region].fillColor;
							a.c.fill();
						}
						drawPolyRegion();
						// dibujar puntos de agarre
						if (!o.regions[region].readonly && o.editable) {
							for (var i in coords) {
								var p=coords[i];
								var rx=a.dx+p.x*a.scale,ry=a.dy+p.y*a.scale;
								a.c.lineWidth=(a.clicked && a.clicked.region==region && a.clicked.coord==i?a.styles.xregion_coord_active.size:a.styles.xregion_coord.size);
								a.c.strokeStyle=(a.clicked && a.clicked.region==region && a.clicked.coord==i?a.styles.xregion_coord_active.color:a.styles.xregion_coord.color);
								a.c.strokeRect(rx-a.grabSize, ry-a.grabSize, a.grabSize<<1, a.grabSize<<1);
							}
						}
					}
				}

				// estilo de textos
				a.c.textAlign="center";

				// dibujar nombres de las regiones
				if (a.c.fillText && !isEditing) {
					a.c.textBaseline="middle";
					for (var region in a.tmp.regions) {
						if (isset(o.regions[region].caption)) {
							var visibleCaption=true;
							if (isset(a.o.showCaption)) visibleCaption=false;
							if (a.o.showCaption=="hover" && a.hover && a.hover.region==region) visibleCaption=true;
							if (visibleCaption) {
								var rect=a.tmp.regions[region].rect;
								if (isset(rect)) {
									var region=o.regions[region];
									var tx=a.dx+parseInt((rect.left+rect.right)/2)*a.scale, ty=a.dy+parseInt((rect.top+rect.bottom)/2)*a.scale;
									a.fillTextBorder(region.caption, tx, ty, (region.color_caption?region.color_caption:region.color), a.styles.xregion_building_caption);
								}
							}
						}
					}
				}

				// dibujar puntos
				if (!isEditing) {
					var selected=(a.clicked && a.clicked.point == i?i:null);
					if (!o.onDrawPoints || o.onDrawPoints(a, o.points, selected)) {
						for (var i in o.points) {
							var p=o.points[i];
							var rx=a.dx+p.x*a.scale,ry=a.dy+p.y*a.scale;
							var selected=(a.clicked && a.clicked.point == i);
							if (!o.onDrawPoint || o.onDrawPoint(a,rx,ry,i,p,selected)) {
								// dibujar punto
								a.c.beginPath();
								a.c.lineWidth=(selected?a.styles.xregion_point_active.size*a.scale:a.styles.xregion_point.size*a.scale);
								a.c.strokeStyle=(selected?a.styles.xregion_point_active.color:(p.color?p.color:a.styles.xregion_point.color));
								a.c.arc(rx,ry,a.grabSize,0,2*Math.PI,true);
								//a.c.strokeRect(rx-a.grabSize, ry-a.grabSize, (a.grabSize<<1)+1, (a.grabSize<<1)+1);
								//a.c.fillRect(rx,ry,1,1);
								//a.c.fillStyle="rgba(255,0,255,0.2)";
								a.c.fillStyle=a.c.strokeStyle;
								a.c.fill();
								a.c.stroke();
								// dibujar texto
								if (isset(p.caption)) {
									a.c.textBaseline="top";
									var px=rx, py=ry+a.grabSize+a.scale*a.grabSize;
									if (!a.data.measure.points[i]) a.data.measure.points[i]={};
									a.data.measure.points[i].caption=a.fillTextBorder(p.caption, px, py, (p.color_caption?p.color_caption:false), a.styles.xregion_point_caption);
									a.data.measure.points[i].caption.x=px;
									a.data.measure.points[i].caption.y=py;
								}
							}
						}
					}
				}

				// dibujar region en creación
				if (a.tmp.startRegion) {
					var cl=o.regions[a.tmp.startRegion].coords[o.regions[a.tmp.startRegion].coords.length-1];
					var sx=a.dx+cl.x*a.scale,sy=a.dy+cl.y*a.scale;
					var rx=a.tmp.mousePos.x,ry=a.tmp.mousePos.y;
					// dibujar linea desde último punto
					a.c.beginPath();
					a.c.lineWidth=a.styles.xregion_newregion_line.size;
					a.c.strokeStyle=a.styles.xregion_newregion_line.color;
					a.c.moveTo(sx,sy);
					a.c.lineTo(rx,ry);
					a.c.stroke();
					// dibujar punto final
					a.c.lineWidth=a.styles.xregion_point_active.size*a.scale;
					a.c.strokeStyle=a.styles.xregion_point_active.color;
					a.c.strokeRect(rx-a.grabSize, ry-a.grabSize, a.grabSize<<1, a.grabSize<<1);
				}

			};
			// actualizar forzado o cuando esté libre de trabajo (1ms)
			if (notDelayed) _redraw(); else this._redrawTimeout=setTimeout(_redraw,1);
		};

		// comprueba si una posición (x,y) se encuentra en el interior
		// del polígono cerrado indicado por sus coordenadas [{.x,.y}]
		a.checkInsidePoly=function(x,y,coords){
			var i,j,c=false,n=coords.length;
			for (i=0,j=n-1;i<n;j=i++)
				if (((coords[i].y>y) != (coords[j].y>y)) && (x<(coords[j].x-coords[i].x) * (y-coords[i].y) / (coords[j].y-coords[i].y) + coords[i].x))
					c=!c;
			return c;
		};

		// verifica que un polígono, al menos, esté definido por 3 vértices,
		// y no se esté todavía creando
		a.isValidRegion=function(region){
			if (!o.regions[region]) return null;
			if (a.tmp.startRegion && a.tmp.startRegion==region) return false;
			return (o.regions[region].coords.length>=3);
		};

		// realizar todos los cálculos iniciales
		a.computeAll=function(){
			for (var i in o.points)       a.computePoint(i);
			for (var region in o.regions) a.computeRegion(region);
		};

		// calculo del area máxima de una región
		a.computeRegion=function(region) {
			if (!a.tmp.regions[region]) a.tmp.regions[region]={};
			var coords=o.regions[region].coords;
			if (isset(coords[0])) {
				a.tmp.regions[region].rect={};
				for (var i in coords) {
					var p=coords[i];
					if (o.roundValues || o.regionRoundValues) { // aproximar
						o.regions[region].coords[i].x=Math.round(parseFloat(o.regions[region].coords[i].x));
						o.regions[region].coords[i].y=Math.round(parseFloat(o.regions[region].coords[i].y));
					} else {
						o.regions[region].coords[i].x=parseFloat(o.regions[region].coords[i].x);
						o.regions[region].coords[i].y=parseFloat(o.regions[region].coords[i].y);
					}
					if (!a.tmp.regions[region].rect.left   || p.x<a.tmp.regions[region].rect.left  ) a.tmp.regions[region].rect.left  =p.x;
					if (!a.tmp.regions[region].rect.right  || p.x>a.tmp.regions[region].rect.right ) a.tmp.regions[region].rect.right =p.x;
					if (!a.tmp.regions[region].rect.top    || p.y<a.tmp.regions[region].rect.top   ) a.tmp.regions[region].rect.top   =p.y;
					if (!a.tmp.regions[region].rect.bottom || p.y>a.tmp.regions[region].rect.bottom) a.tmp.regions[region].rect.bottom=p.y;
				}
			} else {
				delete a.tmp.regions[region].rect;
			}
		};

		// cálculo de puntos
		a.computePoint=function(point) {
			if (o.roundValues || o.pointRoundValues) { // aproximar
				o.points[point].x=Math.round(parseFloat(o.points[point].x));
				o.points[point].y=Math.round(parseFloat(o.points[point].y));
			} else {
				o.points[point].x=parseFloat(o.points[point].x);
				o.points[point].y=parseFloat(o.points[point].y);
			}
		};

		// start new region
		a.startRegion=function(region,x,y,opt){
			if (!o.editable) return false;
			if (!opt) var opt={};
			a.undoPush();
			delete a.deleted.regions[region];
			o.regions[region]=array_merge({
				"new":true,
				"coords":[{"x":x,"y":y}]
			},opt);
			a.tmp.startRegion=region;
			a.computeRegion(region);
			a.redraw();
			a.setCursor();
			return true;
		};

		// cancel region
		a.cancelRegion=function(){
			if (!isset(a.tmp.startRegion)) return false;
			a.deleteRegion(a.tmp.startRegion);
			delete a.tmp.startRegion;
			a.setCursor();
			return true;
		};

		// create point
		a.createPoint=function(point,x,y,opt){
			if (!o.editable) return false;
			if (!opt) var opt={};
			a.undoPush();
			delete a.deleted.points[point];
			if (!o.points) o.points={};
			o.points[point]=array_merge({
				"new":true,
				"x":x,
				"y":y
			},opt);
			a.redraw();
			return true;
		};

		// get point
		a.getPoint=function(point){
			return o.points[point];
		};

		// set point
		a.setPoint=function(point,info){
			a.undoPush();
			o.points[point]=info;
			a.redraw();
			return true;
		};

		// move point
		a.movePoint=function(point,x,y){
			if (!o.editable) return false;
			if (!opt) var opt={};
			a.undoPush();
			o.points[point].x=x;
			o.points[point].y=y;
			a.redraw();
			return true;
		};

		// mouse position in canvas
		a.getCanvasMousePos=function(e){
			if (!e) var e=window.event;
			a.e=e;
			var rect=o.canvas.getBoundingClientRect();
			a.tmp.mousePos={
				"x":e.clientX-rect.left,
				"y":e.clientY-rect.top
			};
			return a.tmp.mousePos;
		};

		// UNDO: save
		a.undoPush=function(){
			a.undo.push({
				"a.deleted":array_copy(a.deleted),
				"a.clicked":array_copy(a.clicked),
				"a.tmp":array_copy(a.tmp),
				"o.points":array_copy(o.points),
				"o.regions":array_copy(o.regions)
			});
		};

		// UNDO: restore
		a.undoPop=function(){
			var i=(a.undo.length-1);
			if (i<0) return false;
			for (var vn in a.undo[i]) {
				var value=array_copy(a.undo[i][vn]);
				//alert(vn+"="+adump(value));
				eval(vn+"=value;");
			}
			a.undo.splice(i,1);
			a.computeAll();
			a.redraw();
		};

		// borrar región
		a.deleteRegion=function(region,coord){
			if (!o.regions[region]) return false;
			a.undoPush();
			if (!o.regions[region]["new"]) a.deleted.regions[region]=true;
			delete o.regions[region];
			delete a.tmp.regions[region];
			a.redraw();
			return true;
		};

		// borrar coordenada
		a.deleteCoord=function(region,coord){
			if (!o.regions[region]) return false;
			if (o.regions[region].coords.length<4) return false;
			a.undoPush();
			o.regions[region].coords.splice(coord,1);
			a.computeRegion(region);
			a.redraw();
			return true;
		};

		// borrar punto
		a.deletePoint=function(point){
			if (!o.points[point]) return false;
			a.undoPush();
			if (!o.points[point]["new"]) a.deleted.points[point]=true;
			delete o.points[point];
			a.redraw();
			return true;
		};

		// get computed style
		a.getClass=function(className,styleProperty){
			var tdiv=document.createElement("div")
			tdiv.className=className;
			document.body.appendChild(tdiv);
			var c=getStyle(tdiv,styleProperty);
			document.body.removeChild(tdiv);
			return c;
		};

		// reset
		a.reset=function(){

			// load styles from CSS
			var borderTopColor=(isie()?"borderTopColor":"border-top-color");
			var borderTopWidth=(isie()?"borderTopWidth":"border-top-width");
			var xregion_caption={
				"color":a.getClass("xregion_caption",'color'),
				"fontFamily":a.getClass("xregion_caption",'font-family'),
				"fontSize":parseInt(a.getClass("xregion_caption",'font-size')),
				"fontSizeMax":parseInt(a.getClass("xregion_caption_maxsize",'font-size')),
				"border":parseInt(a.getClass("xregion_caption_border", borderTopWidth)),
				"borderColor":a.getClass("xregion_caption_border",borderTopColor)
			};
			a.styles={
				"xregion_background":{
					"color":a.getClass("xregion_background",'color')
				},
				"xregion_caption":xregion_caption,
				"xregion_building_caption":xregion_caption,
				"xregion_point_caption":xregion_caption,
				"xregion_newregion_line":{
					"size":parseInt(a.getClass("xregion_newregion_line",borderTopWidth)),
					"color":a.getClass("xregion_newregion_line",borderTopColor)
				},
				"xregion_region_rect":{
					"size":parseInt(a.getClass("xregion_region_rect",borderTopWidth)),
					"color":a.getClass("xregion_region_rect",borderTopColor)
				},
				"xregion_region_rect_active":{
					"size":parseInt(a.getClass("xregion_region_rect_active",borderTopWidth)),
					"color":a.getClass("xregion_region_rect_active",borderTopColor)
				},
				"xregion_building":{
					"size":parseInt(a.getClass("xregion_building",borderTopWidth)),
					"color":a.getClass("xregion_building",borderTopColor)
				},
				"xregion_building_backrect":{
					"size":parseInt(a.getClass("xregion_building_backrect",borderTopWidth)),
					"color":a.getClass("xregion_building_backrect",borderTopColor)
				},
				"xregion_building_active":{
					"size":parseInt(a.getClass("xregion_building_active",borderTopWidth)),
					"color":a.getClass("xregion_building_active",borderTopColor)
				},
				"xregion_building_hover":{
					"size":parseInt(a.getClass("xregion_building_hover",borderTopWidth)),
					"color":a.getClass("xregion_building_hover",borderTopColor)
				},
				"xregion_coord":{
					"size":parseInt(a.getClass("xregion_coord",borderTopWidth)),
					"color":a.getClass("xregion_coord",borderTopColor)
				},
				"xregion_coord_active":{
					"size":parseInt(a.getClass("xregion_coord_active",borderTopWidth)),
					"color":a.getClass("xregion_coord_active",borderTopColor)
				},
				"xregion_point":{
					"size":parseInt(a.getClass("xregion_point",borderTopWidth)),
					"color":a.getClass("xregion_point",borderTopColor)
				},
				"xregion_point_active":{
					"size":parseInt(a.getClass("xregion_point_active",borderTopWidth)),
					"color":a.getClass("xregion_point_active",borderTopColor)
				}
			};
			if (o.styles) {
				xforeach(o.styles, function(styles, k){
					a.styles[k]=array_merge(a.styles[k]||{}, styles);
				});
			}

			// inicializar básicos y crear canvas
			o.id=gid(o.id);
			if (o.canvas) o.id.removeChild(o.canvas);
			o.canvas=document.createElement("canvas");
			o.id.innerHTML="";
			o.id.appendChild(o.canvas);
			o.canvas.style.position="relative";
			a.o=o;
			a.dx=0;
			a.dy=0;
			a.minScale=0.25;
			a.maxScale=16.00;
			a.scale=(o.scale?o.scale:1);
			a.tmp={
				"regions":{}
			};
			a.deleted={
				"regions":{},
				"points":{}
			};
			a.grabSize=4;
			a.undo=[];

			// create 2D context in Canvas
			try {
				a.c=o.canvas.getContext("2d");
			} catch(e) {
				try {
					G_vmlCanvasManager.initElement(o.canvas);
					a.c=o.canvas.getContext("2d");
				} catch(e) {
					o.id.innerHTML="Detenido: No se puede crear Canvas para la edición de regiones."+(isie()?"<br />Se requiere Internet Explorer 9 o superior.":"");
					return false;
				}
			}

			return true;

		};

		a.destroy=function(){
			window.removeEventListener("resize", a.redraw);
		};

		// startup
		a.init=function(){

			// reset
			if (!a.reset()) return false;

			// convertir coordenadas, si necesario
			xforeach(o.regions, function(region, index){
				if (typeof(region.coords) == "string") o.regions[index].coords=xRegion.coordsFromXYString(region.coords);
			});

			// computar todos los cálculos/cachés
			a.computeAll();

			// deshabilitar botón derecho, y reemplazar por scroll
			o.canvas.oncontextmenu=function(){ return false; }

			// permitir recibir pulsaciones de teclas al canvas una vez activado
			o.canvas.tabIndex=0;

			// keypress
			o.id.onkeyup=function(e){
				switch (e.keyCode) {
				case 90: case 122: // Z,z
					if (e.ctrlKey) a.undoPop(); // Ctrl+Z
					break;
				case 27: // ESC
					if (a.tmp.startRegion) a.cancelRegion();
					break;
				case 46: // Supr
					if (o.editable) {
						if (a.clicked) {
							if (isset(a.clicked.region) && !o.regions[a.clicked.region].readonly) {
								if (!o.noDeleteCoord && isset(a.clicked.coord)) {
									if (o.confirmDeleteCoord) o.confirmDeleteCoord(a.clicked.region,a.clicked.coord);
									else a.deleteCoord(a.clicked.region,a.clicked.coord);
								} else {
									if (!o.noDeleteRegion) {
										if (o.confirmDeleteRegion) o.confirmDeleteRegion(a.clicked.region);
										else a.deleteRegion(a.clicked.region);
									}
								}
							}
							if (!o.noDeletePoint && isset(a.clicked.point) && !o.points[a.clicked.point].readonly) {
								if (o.confirmDeletePoint) o.confirmDeletePoint(a.clicked.point);
								else a.deletePoint(a.clicked.point);
							}
						}
					}
					break;
				}
			}

			// mousescroll para zoom
			var mousewheel=function(e){
				if (a.disabled) return;
				if (a.o.noscale) return; // prevent scale
				if (!e) var e=window.event;
				a.e=e;
				var mouse=a.getCanvasMousePos(e);
				// get relative position on offset of the current image
				var sx=(mouse.x-a.dx); var w=(a.img.width*a.scale); if (sx<0) sx=0; if (sx>w) sx=w; var px=sx/w;
				var sy=(mouse.y-a.dy); var h=(a.img.height*a.scale); if (sy<0) sy=0; if (sy>h) sy=h; var py=sy/h;
				// get new scale and limit
				var delta=(e.wheelDelta?e.wheelDelta/-1200:e.detail/30)
				var newScale=a.scale*(1-delta);
				if (newScale>0.9 && newScale<1.1) newScale=1;
				a.setScale(newScale);
				// calculate new offset
				a.dx-=px*(a.img.width*a.scale-w);
				a.dy-=py*(a.img.height*a.scale-h);
				//a.dy+=(a.img.height*a.scale-a.img.height);
				a.redraw();
				if (e.preventDefault) e.preventDefault();
				e.returnValue=false;
				return false;
			};
			if (ismoz()) o.canvas.addEventListener('DOMMouseScroll',mousewheel,true);
			else o.canvas.onmousewheel=mousewheel;

			// mouse down
			o.canvas.onmousedown=function(e){
				if (a.disabled) return;
				if (!e) var e=window.event;
				a.e=e;
				// double click check
				delete a.tmp.dblClicked;
				if (a.tmp.lastOnMouseDown) {
					var ms=(new Date())-a.tmp.lastOnMouseDown;
					if (ms<=250) {
						// this calcs the difference between last click, so we accept double click
						// only if the second is near the first point
						var d=(Math.abs(e.clientX-a.tmp.startX)+Math.abs(e.clientY-a.tmp.startY))/2;
						if (d<5) a.tmp.dblClicked=true;
					}
				}
				a.tmp.lastOnMouseDown=new Date();
				if (a.tmp.dblClicked) delete a.tmp.lastOnMouseDown;
				// coordenadas del ratón
				a.tmp.startX=e.clientX;
				a.tmp.startY=e.clientY;
				var mouse=a.getCanvasMousePos(e);
				var x=parseInt(mouse.x-a.dx),y=parseInt(mouse.y-a.dy);
				// acciones
				if (e.button == (a.isie78()?1:0)) {
					a.clicked=false;
					// ver si se ha pulsado encima de una región
					for (var region in a.tmp.regions) {
						if (a.checkInsidePoly(x/a.scale,y/a.scale,o.regions[region].coords)) {
							a.clicked={"region":region};
							if (!a.tmp.startRegion && !o.regions[region].readonly && o.editable) {
								a.undoPush();
								a.tmp.startMoveRegion=true;
							}
						}
					}
					// ver si se ha pulsado encima de un punto
					for (var i in o.points) {
						var p=o.points[i];
						var rx=p.x*a.scale,ry=p.y*a.scale;
						var gs=a.grabSize*(a.scale > 1?a.scale:1);
						if (x>=rx-gs && x<=rx+gs && y>=ry-gs && y<=ry+gs) {
							if (!a.clicked) a.undoPush();
							a.clicked={"point":i};
							a.tmp.startMoveRegion=false;
							a.tmp.startMovePoint=(o.editable && !p.readonly?true:false);
							break;
						}
					}
					// ver si se ha pulsado encima del caption de una coordenada
					for (var i in o.points) {
						var p=o.points[i];
						var rx=p.x*a.scale,ry=p.y*a.scale;
						var gs=a.grabSize*(a.scale > 1?a.scale:1);
						var m=(a.data.measure.points[i]?a.data.measure.points[i].caption||false:null);
						if (m && x >= m.x-a.dx && x<=m.x-a.dx+m.width && y>=m.y-a.dy && y<=m.y-a.dy+m.height) {
							if (!a.clicked) a.undoPush();
							a.clicked={"point":i};
							a.tmp.startMoveRegion=false;
							a.tmp.startMovePoint=(o.editable && !p.readonly?true:false);
							break;
						}
					}
					// ver si se ha pulsado encima de una coordenada
					// (la selección de coordenadas tiene preferencia a las regiones)
					if (o.editable) {
						for (var region in o.regions) {
							var coords=o.regions[region].coords;
							if (o.regions[region].readonly) continue;
							for (var i in coords) {
								var p=coords[i];
								var rx=p.x*a.scale,ry=p.y*a.scale;
								var gs=a.grabSize*(a.scale > 1?a.scale:1);
								if (x>=rx-gs && x<=rx+gs && y>=ry-gs && y<=ry+gs) {
									if (!a.clicked) a.undoPush();
									a.clicked={
										"region":region,
										"coord":parseInt(i)
									};
									if (!a.tmp.startRegion) {
										a.tmp.startMoveRegion=false;
										a.tmp.startMoveCoord=true;
										a.tmp.start_coord=array_copy(o.regions[a.clicked.region].coords[a.clicked.coord]);
									}
									break;
								}
							}
						}
					}
					// si se ha empezado a definir una región, definir punto
					if (a.tmp.startRegion) {
						// ver si se ha terminado la región, o si hay que añadir un punto
						if (a.clicked && a.clicked.region==a.tmp.startRegion && a.clicked.coord===0) {
							//a.undoPush();
							a.clicked=false;
							a.tmp.startMoveRegion=false;
							a.tmp.startMoveCoord=false;
							a.computeRegion(a.tmp.startRegion);
							delete a.tmp.startRegion;
							a.setCursor();
						} else {
							a.undoPush();
							o.regions[a.tmp.startRegion].coords.push({"x":x/a.scale,"y":y/a.scale});
							a.computeRegion(a.tmp.startRegion);
						}
					} else {
						if (a.tmp.dblClicked) {
							if (a.clicked) {
								if (isset(a.clicked.region))
									if (o.onRegionDblClick) {
										a.tmp.startMoveRegion=false;
										a.tmp.startMoveCoord=false;
										if (!isset(a.clicked.coord))
											o.onRegionDblClick(x/a.scale,y/a.scale,a.clicked.region,o.regions[a.clicked.region]);
									}
								if (a.clicked.point)
									if (o.onPointDblClick) {
										a.tmp.startMovePoint=false;
										o.onPointDblClick(x/a.scale,y/a.scale,a.clicked.point,o.points[a.clicked.point]);
									}
							} else {
								// evento doble click
								if (o.onMapDblClick)
									o.onMapDblClick(x/a.scale,y/a.scale,mouse);
							}
						} else {
							if (a.clicked) {
								if (isset(a.clicked.region) && o.onRegionClick)
									if (!o.onRegionClick(x/a.scale,y/a.scale,a.clicked.region,o.regions[a.clicked.region],mouse)) {
										a.tmp.startMoveRegion=false;
										a.tmp.startMoveCoord=false;
									}
								if (a.clicked && isset(a.clicked.point) && o.onPointClick)
									if (!o.onPointClick(x/a.scale,y/a.scale,a.clicked.point,o.points[a.clicked.point],a)) {
										a.tmp.startMovePoint=false;
									}
							} else {
								// evento click simple
								if (o.onMapClick)
									o.onMapClick(x/a.scale,y/a.scale,mouse);
							}
						}
					}
					// redibujar si no deshabilitado
					if (!o.nomouseredraw) a.redraw();
					// si no se hace nada importante, mover :D
					if (!a.clicked && !a.tmp.startRegion)
						a.tmp.startMove=true;
				} else {
					a.tmp.startMove=true;
				}
				if (a.tmp.startMove) {
					a.tmp.start_dx=a.dx;
					a.tmp.start_dy=a.dy;
					a.setCursor();
				}
			};

			// mouse move
			if (!a.o.nohover) {
				var onmousemove_last=window.onmousemove;
				var mousemoveEvent=function(e){
					try { if (onmousemove_last) onmousemove_last(e); } catch(e) {}
					if (!e) var e=window.event;
					if (a.disabled) return;
					a.e=e;
					var mouse=a.getCanvasMousePos(e);
					var x=parseInt(mouse.x-a.dx),y=parseInt(mouse.y-a.dy);
					// hovers
					var hover_last=(a.hover?true:false);
					a.hover=false;
					// ver si el ratón pasa por una región
					for (var region in a.tmp.regions)
						if (a.checkInsidePoly(x/a.scale,y/a.scale,o.regions[region].coords))
							a.hover={"region":region};
					// movimiento
					var mx=(e.clientX-a.tmp.startX);
					var my=(e.clientY-a.tmp.startY);
					if (a.tmp.startMoveRegion) {
						if (!a.tmp.startMoveRegionOffset && (mx>8 || my>8 || mx<-8 || my<-8)) a.tmp.startMoveRegionOffset=true;
						if (a.tmp.startMoveRegionOffset) {
							var dfx=a.tmp.startX-e.clientX; a.tmp.startX=e.clientX;
							var dfy=a.tmp.startY-e.clientY; a.tmp.startY=e.clientY;
							for (var i in o.regions[a.clicked.region].coords) {
								o.regions[a.clicked.region].coords[i].x-=dfx/a.scale;
								o.regions[a.clicked.region].coords[i].y-=dfy/a.scale;
							}
						}
					}
					if (a.tmp.startMovePoint) {
						if (!a.tmp.startMovePointOffset && (mx>2 || my>2 || mx<-2 || my<-2)) a.tmp.startMovePointOffset=true;
						if (a.tmp.startMovePointOffset && o.points && o.points[a.clicked.point]) {
							var dfx=a.tmp.startX-e.clientX; a.tmp.startX=e.clientX;
							var dfy=a.tmp.startY-e.clientY; a.tmp.startY=e.clientY;
							o.points[a.clicked.point].x-=dfx/a.scale;
							o.points[a.clicked.point].y-=dfy/a.scale;
						}
					}
					if (a.tmp.startMoveCoord) {
						if (!a.tmp.startMoveCoordOffset && (mx>2 || my>2 || mx<-2 || my<-2)) a.tmp.startMoveCoordOffset=true;
						if (a.tmp.startMoveCoordOffset) {
							o.regions[a.clicked.region].coords[a.clicked.coord].x=a.tmp.start_coord.x+mx/a.scale;
							o.regions[a.clicked.region].coords[a.clicked.coord].y=a.tmp.start_coord.y+my/a.scale;
						}
					}
					if (a.tmp.startMove) {
						if (!a.o.nomove) {
							a.dx=a.tmp.start_dx+mx;
							a.dy=a.tmp.start_dy+my;
						}
					}
					if (a.o.onMouseMove)
						a.o.onMouseMove(x/a.scale,y/a.scale,mouse,a);
					// redraw
					if (!a.o.nohoverdraw) {
						a.redraw(hover_last!=(a.hover?true:false));
					}
				};
				if (a.hasCanvasEvents()) o.canvas.onmousemove=mousemoveEvent;
				else window.onmousemove=mousemoveEvent;
			}

			// mouse up
			var onmouseup_last=window.onmouseup;
			var mouseupEvent=function(e){
				try { if (onmouseup_last) onmouseup_last(e); } catch(e) {}
				if (!e) var e=window.event;
				if (a.disabled) return;
				a.e=e;
				if (a.tmp.startMoveRegion) {
					a.computeRegion(a.clicked.region);
					a.tmp.startMoveRegion=false;
					a.tmp.startMoveRegionOffset=false;
				}
				if (a.tmp.startMovePoint) {
					if (a.clicked.point) a.computePoint(a.clicked.point);
					a.tmp.startMovePoint=false;
					a.tmp.startMovePointOffset=false;
				}
				if (a.tmp.startMoveCoord) {
					a.computeRegion(a.clicked.region);
					a.tmp.startMoveCoord=false;
					a.tmp.startMoveCoordOffset=false;
				}
				if (a.tmp.startMove) {
					a.tmp.startMove=false;
					a.setCursor();
				}
				// redibujar si no deshabilitado
				if (!o.nomouseredraw) a.redraw();
			};
			if (a.hasCanvasEvents()) o.canvas.onmouseup=mouseupEvent;
			else window.onmouseup=mouseupEvent;

			// bloquear scroll en IE7/8 si se está dentro del canvas (bug javascript)
			if (a.hasCanvasEvents()) {
				o.canvas.onmouseover=function(){ document.onmousewheel=function(){ return false; } }
				o.canvas.onmouseout=function(){ document.onmousewheel=function(){ return true; } }
			}

			// resize
			if (window.addEventListener) {
				window.addEventListener("resize", a.redraw(), false);
			} else {
				var onresize_last=window.onresize;
				window.onresize=function(e){
					try { if (onresize_last) onresize_last(e); } catch(e) {}
					if (!e) var e=window.event;
					a.redraw();
				};
			}

			// load map
			a.img=new Image();
			a.img.onload=function(){
				if (o.onImageLoad) o.onImageLoad(a,a.img); // evento
				a.redraw();
			};
			a.img.src=o.img;

			// marcar temporales de centraje
			a.tmp.centerMap=true;
			a.tmp.centerRegion=o.centerRegion;
			a.tmp.centerPoint=o.centerPoint;

			// initial setup
			a.setCursor();

			// init event
			if (o.onInit) o.onInit(a);

		};

		// startup
		a.init();

	}

};
