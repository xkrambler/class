// common.js by mr.xkr v2 rev.4b

// check if a variable is set
function isset(v) { return (typeof(v) != "undefined"?true:false); }

// if not (a), then (b)
function ifnot(a, b) { return (a?a:b); }

// little functions to reduce code
function gid(id) { try { var rid=(typeof(id) == "object"?id:document.getElementById(id)); return rid; } catch(e) { return null; } }
function gidget(id) { return gid(id).innerHTML; }
function gidset(id, html) { var e=gid(id); try { e.innerHTML=(typeof(html) == "undefined"?"":html); } catch(e) { console.warn("gidset("+id+", html): "+e); } }
function gidval(id, data) { if (typeof(data) != "undefined") gid(id).value=(data == null?"":data); else return(gid(id).type=="checkbox"?(gid(id).checked?gid(id).value:""):gid(id).value); }
function gidvals(idsdata) { for (var i in idsdata) gidval(i,idsdata[i]); }
function giddel(id) { var d=gid(id); d.parentNode.removeChild(d); }
function gidmove(id_org, id_dst) { gid(id_dst).innerHTML=gid(id_org).innerHTML; gid(id_org).innerHTML=""; }
function alter(id) { gid(id).style.display=(gid(id).style.display == "none"?"block":"none"); }
function show(id) { gid(id).style.display="block"; }
function hide(id) { gid(id).style.display="none"; }
function cell(id) { gid(id).style.display="table-cell"; }
function visible(id) { gid(id).style.visibility="visible"; }
function hidden(id) { gid(id).style.visibility="hidden"; }
function isShow(id) { return(gid(id).style.display == "block"?true:false); }
function isVisible(id) { return(gid(id).style.display != "none"?true:false); }
function showSwitch(id) { gid(id).style.display=(gid(id).style.display != "none"?"none":""); }
function gidfocus(id) { if (gid(id)) { try { gid(id).select(); } catch(e) {}; try { gid(id).focus(); } catch(e) {}; } }

// set element is enabled
function gidenabled(id, enabled) {
	var e=gid(id);
	if (e) {
		if (enabled) e.removeAttribute("disabled");
		else e.setAttribute("disabled", "");
	}
}

// foreach implementation
function xforeach(a, f) {
	var c=0;
	if (typeof(a) == "object" && typeof(f) == "function")
		for (var i in a)
			if (f(a[i], i, a))
				c++;
	return c;
}

// ensure valid date in ISO format YYYY-MM-DD (deprecated)
function gidvalFecha(id) {
	var d=intval(gidval(id+"_d"));
	var m=intval(gidval(id+"_m"));
	var y=intval(gidval(id+"_y"));
	if (isNaN(d) || d<1 || d>31) return "";
	if (isNaN(m) || m<1 || m>12) return "";
	if (isNaN(y) || y<1900 || y>3000) return "";
	return y+"-"+(m<10?"0":"")+m+"-"+(d<10?"0":"")+d;
}

// obtener todos los datos de campos según su ID prefijados y/o sufijados
function gpreids(prefix, ids, sufix) {
	var ids=ids.split(" ");
	var a={};
	for (var i in ids)
		if (gid((prefix?prefix+"_":"")+ids[i]+(sufix?"_"+sufix:"")))
			a[ids[i]]=gidval((prefix?prefix+"_":"")+ids[i]+(sufix?"_"+sufix:""));
	return a;
}

// establecer todos los datos de campos según su ID prefijados y/o sufijados
function spreids(prefix, ids, values, sufix) {
	var ids=ids.split(" ");
	for (var i in ids)
		if (gid((prefix?prefix+"_":"")+ids[i]+(sufix?"_"+sufix:""))) {
			var o=gid((prefix?prefix+"_":"")+ids[i]+(sufix?"_"+sufix:""));
			var v=values[ids[i]];
			switch (o.type) {
			case "checkbox":
				if (parseInt(v) || (v.length>0 && v!="0")) o.checked=true;
				break;
			case "select-one":
			case "select-multiple":
				for (var j=0;j<o.options.length;j++)
					if (o.options[j].value==v) {
						gidval(o,v);
						break;
					}
				break;
			default:
				gidval(o,v);
			}
		}
}

// template substitutions from an array of strings
function template(s, replaces) {
	if (replaces)
		for (var i in replaces)
			s=s.replace(new RegExp(i.replace(/([.?*+^$[\]\\(){}-])/g, "\\$1"),'g'), replaces[i]);
	return s;
}

// carga el contenido HTML de una capa y realiza reemplazos para usarla como template para ventanas o búsquedas AJAX
// por defecto, los reemplazos que hace son automáticamente de id y name con prefijo $
function gtemplate(id, replaces) {
	var s=gidget("template:"+id);
	s=s.replace(/ id=\$/gi," id=");
	s=s.replace(/ id=\'\$/gi," id='");
	s=s.replace(/ id=\"\$/gi,' id="');
	s=s.replace(/ name=\$/gi," name=");
	s=s.replace(/ name=\'\$/gi," name='");
	s=s.replace(/ name=\"\$/gi,' name="');
	if (replaces) s=template(s,replaces);
	return s;
}

// simplified creation of a new DOM element
function newElement(element, o) {
	var e=document.createElement(element);
	if (o) {
		if (o.id) e.id=o.id;
		if (o.class) e.className=o.class;
		if (o.html) e.innerHTML=o.html;
		if (o.type) e.type=o.type;
		if (o.title) e.title=o.title;
		if (o.style) e.style=o.style;
		if (o.value) e.value=o.value;
		if (o.attributes)
			for (var i in o.attributes)
				if (o.attributes[i]!==null)
					e.setAttribute(i, o.attributes[i]);
		if (o.properties)
			for (var i in o.properties)
				if (o.properties[i]!==null)
					e[i]=o.properties[i];
		if (o.styles)
			for (var i in o.styles)
				if (o.styles[i]!==null)
					e.style[i]=o.styles[i];
		if (o.events)
			for (var i in o.events)
				if (o.events[i]!==null)
					e.addEventListener(i, o.events[i]);
		if (o.childs)
			for (var i in o.childs)
				if (o.childs[i]!==null)
					e.appendChild(o.childs[i]);
	}
	return e;
}

// dump JavaScript variable tree
// Thanks to Binny V A (binnyva -at- hotmail -dot- com - binnyva.com) for adump implementation
function adump(arr, level) {
	if (!level) level=0;
	var s="";
	var t=""; for (var j=0; j < level; j++) t+="\t";
	try {
		if (typeof(arr) == 'object') {
			if (arr.nextSibling) return t+"{*}\n"; // NO devolver elementos internos del navegador
			for (var item in arr) {
				var value=arr[item];
				if (typeof(value) == 'object') {
					var size=0; for (var none in value) size++;
					s+=t+'"' + item + '" = '+typeof(value)+'('+size+'):\n';
					s+=adump(value,level+1);
				} else {
					s+=t+'"' + item + '" = '+typeof(value)+'("' + value + '")\n';
				}
			}
		} else {
			s="("+typeof(arr)+") "+arr;
		}
	} catch(e) {}
	return s;
}

// element positioning functions
function getTop(id) { var o=gid(id); var p=0; do { p+=o.offsetTop; } while (o=o.offsetParent); return(p); }
function getLeft(id) { var o=gid(id); var p=0; do { p+=o.offsetLeft; } while (o=o.offsetParent); return(p); }
function getScrollTop(id) { var o=gid(id); var p=0; do { p+=(o.scrollTop?o.scrollTop:0); } while (o=o.parentNode); return(p); }
function getScrollLeft(id) { var o=gid(id); var p=0; do { p+=(o.scrollLeft?o.scrollLeft:0); } while (o=o.parentNode); return(p); }
function getWidth(id) { return gid(id).offsetWidth; }
function getHeight(id) { return gid(id).offsetHeight; }

// element style change
function style(id, styles) { var o=gid(id); for (var i in styles) o.style[i]=styles[i]; }

// element className functions
function classAdd(id, c) {
	var a=gid(id); if (!a) return false;
	a=a.className.split(" ");
	for (var i in a)
		if (a[i] == c) return;
	gid(id).className=trim(gid(id).className+" "+c);
	return true;
}
function classDel(id, c) {
	var a=gid(id); if (!a) return false;
	a=a.className.split(" ");
	var cs="";
	for (var i in a)
		if (a[i] != c)
			cs+=" "+a[i];
	gid(id).className=trim(cs);
	return true;
} 
function classEnable(id, c, enabled) {
	return (enabled?classAdd(id, c):classDel(id, c));
}
function classSwap(id, c) {
	return (hasClass(id, c)?classDel(id, c):classAdd(id, c));
}
function hasClass(id, c){
	if (!gid(id) || !gid(id).className) return;
	var cs=gid(id).className.split(" ");
	return (cs.indexOf(c) != -1);
}

// document and window properties
function ieTrueBody() { return((document.compatMode && document.compatMode != "BackCompat")?document.documentElement:document.body); }
function scrollLeft() { return ieTrueBody().scrollLeft; }
function scrollTop() { return ieTrueBody().scrollTop; }
function windowWidth() { return (document.documentElement.clientWidth?document.documentElement.clientWidth:(window.innerWidth?window.innerWidth:document.body.clientWidth)); }
function windowHeight() { return (document.documentElement.clientHeight?document.documentElement.clientHeight:(window.innerHeight?window.innerHeight:document.body.clientHeight)); }
function documentWidth() { return document.body.clientWidth; }
function documentHeight() { return document.body.clientHeight; }

// natural properties of an image (real size)
function naturalWidth(idimg) {
	if (typeof(gid(idimg).naturalWidth) == "number") return gid(idimg).naturalWidth;
	else { var tmp=new Image(); tmp.src=gid(idimg).src; return tmp.width; }
}
function naturalHeight(idimg) {
	if (typeof(gid(idimg).naturalHeight) == "number") return gid(idimg).naturalHeight;
	else { var tmp=new Image(); tmp.src=gid(idimg).src; return tmp.height; }
}

// notify if an element is partial or fully visible in the view
function isIntoView(id, full) {
	var e=gid(id);
	if (!e || !e.getBoundingClientRect) return false;
	var r=e.getBoundingClientRect();
	if (!r) return false;
	return (isset(full) && full
		?(r.top >= 0) && (r.bottom <= window.innerHeight) // completely visible
		:r.top < window.innerHeight && r.bottom >= 0      // partially visible
	);
}

// callback if an element appears into the view
function appearIntoView(id, callback, o) {
	var o=o||{};
	var intoview=false;
	var check_appear=function(e){
		o.id=id;
		o.event=e;
		o.intoview=isIntoView(id, o.full);
		if ((!intoview && o.intoview) || (intoview && !o.intoview)) {
			callback(o);
			if (!o.always) document.removeEventListener("scroll", check_appear);
		}
		intoview=o.intoview;
	};
	document.addEventListener("scroll", check_appear);
}

// set cursor
function setCursor(cursor) { document.body.style.cursor=(cursor?cursor:"auto"); }

// get element border width (horizontal)
function getBorderWidth(id) {
	var wext=(parseInt(getStyle(id, "border-left-width"))+parseInt(getStyle(id,"border-right-width")));
	return (!isNaN(wext)?wext:0);
}

// get element border height (vertical)
function getBorderHeight(id) {
	var wext=(parseInt(getStyle(id, "border-top-width"))+parseInt(getStyle(id,"border-bottom-width")));
	return (!isNaN(wext)?wext:0);
}

// get element top border height
function getBorderTopHeight(id) {
	var wext=parseInt(parseInt(getStyle(id, "border-top-width")));
	return (!isNaN(wext)?wext:0);
}

// get element bottom border height
function getBorderBottomHeight(id) {
	var wext=parseInt(parseInt(getStyle(id, "border-bottom-width")));
	return (!isNaN(wext)?wext:0);
}

// get element left border width
function getBorderLeftWidth(id) {
	var wext=parseInt(parseInt(getStyle(id, "border-left-width")));
	return (!isNaN(wext)?wext:0);
}

// get element right border width
function getBorderRightWidth(id) {
	var wext=parseInt(parseInt(getStyle(id, "border-right-width")));
	return (!isNaN(wext)?wext:0);
}

// get element padding width (horizontal)
function getPaddingWidth(id) {
	var wext=(parseInt(getStyle(id, "padding-left"))+parseInt(getStyle(id, "padding-right")));
	return (!isNaN(wext)?wext:0);
}

// get element padding height (vertical)
function getPaddingHeight(id) {
	var wext=(parseInt(getStyle(id, "padding-top"))+parseInt(getStyle(id, "padding-bottom")));
	return (!isNaN(wext)?wext:0);
}

// obtiene el estilo final computado de un elemento
function getStyle(id, styleprop) {
	var x=gid(id);
	if (x.currentStyle) { return x.currentStyle[styleprop]; }
	else if (window.getComputedStyle) { return document.defaultView.getComputedStyle(x,null).getPropertyValue(styleprop); }
	return null;
}

// element inner width (without margin/padding/border)
function crossInnerWidth(id) {
	var element=gid(id);
	try {
		if (window.getComputedStyle(element,""))
			return(element.clientWidth-parseInt(window.getComputedStyle(element,"").getPropertyValue("padding-left"))-parseInt(window.getComputedStyle(element,"").getPropertyValue("padding-right")));
	} catch(e) {
		return(element.clientWidth-parseInt(element.currentStyle.paddingLeft)-parseInt(element.currentStyle.paddingRight));
	}
}

// element inner height (without margin/padding/border)
function crossInnerHeight(id) {
	var element=gid(id);
	try {
		if (window.getComputedStyle(element,""))
			return(element.clientHeight-parseInt(window.getComputedStyle(element,"").getPropertyValue("padding-top"))-parseInt(window.getComputedStyle(element,"").getPropertyValue("padding-bottom")));
	} catch(e) {
		return(element.clientHeight-parseInt(element.currentStyle.paddingTop)-parseInt(element.currentStyle.paddingBottom));
	}
}

// image preloading
var imagePreloadList={};
function imagePreload(imageorlist) {
	var image_list=(typeof(imageorlist) == "string"?[imageorlist]:imageorlist);
	for (var i in image_list) {
		var image=image_list[i];
		imagePreloadList[image]={"loaded":false,"img":new Image()};
		imagePreloadList[image].img.src=image;
		imagePreloadList[image].img.onload=function(){ imagePreloadList[image].loaded=true; };
	}
}

// get scrollbar width (horizontal)
// thanks to Alexandre Gomes (Portugal)
// http://www.alexandre-gomes.com/?p=115
function scrollWidth() {

	var inner=document.createElement('p');
	inner.style.width='100%';
	inner.style.height='200px';

	var outer=document.createElement('div');
	outer.style.position='absolute';
	outer.style.top='0px';
	outer.style.left='0px';
	outer.style.visibility='hidden';
	outer.style.width='200px';
	outer.style.height='150px';
	outer.style.overflow='hidden';

	outer.appendChild(inner);
	document.body.appendChild(outer);

	var w1=inner.offsetWidth;
	outer.style.overflow='scroll';
	var w2=inner.offsetWidth;
	if (w1==w2) w2=outer.clientWidth;

	document.body.removeChild(outer);
	return(w1-w2);
}

// get cookie
function getCookie(name) {
	var nameEQ=name.replace(/=/gi,"_")+"=";
	var ca=document.cookie.split(';');
	for (i=0; i<ca.length; i++) {
		var c=ca[i];
		while (c.charAt(0) == ' ')
			c=c.substring(1, c.length);
		if (c.indexOf(nameEQ) == 0)
			return c.substring(nameEQ.length, c.length).replace(/\\\\/gi,"\\").replace(/\\n/gi,"\n").replace(/\\,/gi,";");
	}
	return "";
}

// set cookie
function setCookie(name, value, o) {
	var o=(Number.isFinite(o)?{"days":o}:o||{});
	if (o.days) o.expires=o.days*86400000;
	var expires="";
	if (o.expires) {
		var date=new Date();
		date.setTime(date.getTime()+o.expires);
		expires="; expires="+date.toGMTString();
	}
	document.cookie
		=name.replace(/=/gi,"").replace(/;/gi,"")
		+"="+(""+value).replace(/\\/gi,"\\\\").replace(/\n/gi,"\\n").replace(/;/gi,"\\,").replace(/;/gi,"_")
		+expires
		+"; path="+(o.path||"/")
		+"; sameSite="+(o.samesite||"Lax")
		+(o.secure?"; Secure":"")
	;
}

// delete cookie
function delCookie(name) {
	setCookie(name, "", {"expires":-1, "samesite":"Strict"});
	setCookie(name, "", {"expires":-1, "samesite":"Lax"});
	setCookie(name, "", {"expires":-1, "samesite":"None", "secure":true});
	setCookie(name, "", {"expires":-1, "samesite":"None"});
}

// delete all accesible cookies
function delAllCookies() {
	var cookies=document.cookie.split(";");
	for (var i=0; i<cookies.length; i++) {
		var cookie=cookies[i];
		var eqPos=cookie.indexOf("=");
		var name=(eqPos>-1?cookie.substr(0, eqPos):cookie);
		delCookie(name);
	}
}

// check for typical navigators
function isie() { return (navigator.userAgent.indexOf("MSIE") != -1); }
function ismoz() { return (navigator.userAgent.indexOf("Firefox") != -1 || navigator.userAgent.indexOf("Iceweasel") != -1); }
function ischrome() { return (navigator.userAgent.indexOf("Chrome") != -1); }

// hide all selects (only in IE)
function hideSelects(hidden) {
	if (!isie()) return;
	selects=document.getElementsByTagName("select");
	for (i=0; i<selects.length; i++) selects[i].style.visibility=(hidden?"hidden":"");
}

// search and run embeded <script>
function getrunjs(data) {
	runjs(getjs(data));
}

// search embeded <script>
function getjs(data) {
	scode="";
	while (true) {
		ss=data.toLowerCase().indexOf("<script>"); if (ss<0) break;
		es=data.toLowerCase().indexOf("<\/script>", ss+2); if (es<0) break;
		scode=scode+data.substring(ss+8, es);
		data=data.substring(0, ss)+data.substring(es+9);
	}
	return scode;
}

// include javascript from string (faster than eval)
function runjs(data) {
	if (!data) return;
	var escode=document.createElement("script");
	escode.setAttribute("type","text/javascript");
	escode.text=data;
	document.getElementsByTagName("body").item(0).appendChild(escode);
}

// include javascript from file
function includejs(filename, onload) {
	if (!filename) return;
	var escode=document.createElement("script");
	escode.setAttribute("type","text/javascript");
	escode.src=filename;
	if (onload) escode.onload=onload
	document.getElementsByTagName("body").item(0).appendChild(escode);
}

// convert string new lines to HTML <br /> tags
function nl2br(t) {
	try { return t.replace(/\n/gi, "<br />"); } catch(e) {}
	return t;
}

// id/object merge: de una lista de identificadores separadas por comas,
// mezclar sus datos con objetos JavaScript previamente existentes
function ioMerge(ids, root, obj) {
	ids=ids.split(",");
	for (i=0;i<ids.length;i++)
		if (gid(root+ids[i]))
			obj[ids[i]]=gidval(root+ids[i]);
	return(obj);
}

// object/object merge: de una lista de objetos separados por comas,
// copia los los datos del primero objeto en el segundo y devuelve este
function ooMerge(ids, o1, o2) {
	ids=ids.split(",");
	for (i=0;i<ids.length;i++)
		o2[ids[i]]=o1[ids[i]];
	return o2;
}

// añade CSS al documento (no funciona bien en IE6)
function cssAdd(css) {
	var style=document.createElement("style");
	style.type="text/css";
	if (style.styleSheet) style.styleSheet.cssText=css;
	else style.appendChild(document.createTextNode(css));
	document.getElementsByTagName("head")[0].appendChild(style);
}

// copia en profundidad de un objeto
function array_copy(o) {
	if (typeof o != "object" || o === null || o instanceof HTMLElement) return o;
	var r=(o instanceof Array?[]:{});
	for (var i in o) r[i]=array_copy(o[i]);
	return r;
}

// array_count: cuenta el número de elementos de un array asociativo
function array_count(a) {
	var c=0;
	try { if (a) for (var i in a) c++; } catch(e) {}
	return c;
}

// array_merge: mezcla arrays puros o asociativos en profundidad
function array_merge(a1, a2) {
	var a=array_copy(a1);
	var b=array_copy(a2);
	for (var i in b) {
		var e=b[i];
		if (typeof a[i] === "object" && typeof e === "object") {
			a[i]=array_merge(a[i], e);
		} else if (typeof a[i] === "array" && typeof e === "array") {
			a[i]=a[i].concat(e);
		} else {
			a[i]=e;
		}
	}
	return a;
}

// elimina un elemento en la posición del indice de un array
function array_delete(a, index) {
	var n=Array();
	for (var i in a)
		if (i != index)
			n[n.length]=a[i];
	return n;
}

// array_remove: elimina claves de un array asociativo
function array_remove(a1, a2) {
	var a=new Object();
	var clone;
	for (var i in a1) {
		clone=true;
		for (var j in a2)
			if (i == a2[j]) {
				clone=false;
				break;
			}
		if (clone) a[i]=a1[i];
	}
	return a;
}

// array_get: devuelve las claves de un array dada una lista de ellas
function array_get(a, list) {
	var o=new Object();
	for (var i in list)
		o[list[i]]=a[list[i]];
	return o;
}

// array_save: busca las claves de la lista en el segundo array y los mezcla con el primero
function array_save(a1, a2, list) {
	for (var i in list)
		a1[list[i]]=a2[list[i]];
	return a1;
}

// añade un objeto a un array plano
function array_push(a,o) {
	a.push(o);
}

// verifica si un array está vacío o no
function array_isclean(a) {
	if (!a) return true;
	for (var i in a)
		return false;
	return true;
}

// devuelve si un array es idéntico a otro (se usa ===)
function array_equals(a, b) {
	var c=0, d=0;
	for (var i in a) {
		if (a[i] !== b[i])
			return false;
		c++;
	}
	for (var i in b)
		d++;
	return (c == d?true:false);
}

// devolver un hash siempre, aunque el constructor sea un array
function array_hash(a) {
	for (var i in a) return a;
	return {};
}

// devuelve las claves de un hash en un nuevo array
function array_keys(a) {
	var b=[];
	for (var i in a) b.push(i);
	return b;
}

// convierte un hash/array para devolver siempre un array
function array_values(h) {
	var a=[];
	for (var i in h) a.push(h[i]);
	return a;
}

// verifica si un elemento está dentro del array
function in_array(e, a) {
	if (!a) return false;
	for (var i in a)
		if (a[i] === e) return true;
	return false;
}

// control de impresión, sobrecargar este método
function doprint() { window.print(); }

// init_fast
var init_fast_func=[];
function init_fast(add) {
	if (add) init_fast_func[init_fast_func.length]=add;
	else { for (var i in init_fast_func) { try { init_fast_func[i](); } catch(e) {} } }
}

// onload
var init_func=[];
var init_func_last=window.onload;
function init(add) {
	if (add) init_func[init_func.length]=add;
	else { for (var i in init_func) { try { init_func[i](); } catch(e) {} } }
}
window.onload=function(){
	try { init_func_last(); } catch(e) {}
	try { init(); } catch(e) {}
}

// onunload
var unload_func=[];
var unload_func_last=window.onunload;
function unload(add) {
	if (add) unload_func[unload_func.length]=add;
	else { for (var i in unload_func) { try { unload_func[i](); } catch(e) {} } }
}
window.onunload=function(){
	try { unload_func_last(); } catch(e) {}
	try { unload(); } catch(e) {}
}

// onresize
var resize_func=[];
var resize_func_last=window.onresize;
function resize(add) {
	if (add) resize_func[resize_func.length]=add;
	else { for (var i in resize_func) { try { resize_func[i](); } catch(e) {} } }
}
window.onresize=function(){
	try { resize_func_last(); } catch(e) {}
	try { resize(); } catch(e) {}
}

// onkeydown
var keydown_func=[];
var keydown_func_last=document.onkeydown;
function keydown(p) {
	if (typeof(p)=="function") keydown_func[keydown_func.length]=p;
	else { for (var i in keydown_func) { try { keydown_func[i](p); } catch(e) {} } }
}
document.onkeydown=function(we){
	try { keydown_func_last(we); } catch(e) {}
	try { keydown(we); } catch(e) {}
}

// onkeyup
var keyup_func=[];
var keyup_func_last=document.onkeyup;
function keyup(p) {
	if (typeof(p)=="function") keyup_func[keyup_func.length]=p;
	else { for (var i in keyup_func) { try { keyup_func[i](p); } catch(e) {} } }
}
document.onkeyup=function(we){
	try { keyup_func_last(we); } catch(e) {}
	try { keyup(we); } catch(e) {}
}

// onscroll
var scroll_func=[];
var scroll_func_last=document.onscroll;
function scroll(p) {
	if (typeof(p)=="function") scroll_func[scroll_func.length]=p;
	else { for (var i in scroll_func) { try { scroll_func[i](p); } catch(e) {} } }
}
document.onscroll=function(we){
	try { scroll_func_last(we); } catch(e) {}
	try { scroll(we); } catch(e) {}
}

// onmouseup
var mouseup_func=[];
var mouseup_func_last=document.onmouseup;
function mouseup(p) {
	if (typeof(p)=="function") mouseup_func[mouseup_func.length]=p;
	else { for (var i in mouseup_func) { try { mouseup_func[i](p); } catch(e) {} } }
}
document.onmouseup=function(we){
	if (!we) we=window.event;
	try { mouseup_func_last(we); } catch(e) {}
	try { mouseup(we); } catch(e) {}
}

// onmousedown
var mousedown_func=[];
var mousedown_func_last=document.onmousedown;
function mousedown(p) {
	var ret=null;
	if (typeof(p)=="function") mousedown_func[mousedown_func.length]=p;
	else {
		for (var i in mousedown_func) { try { ret=mousedown_func[i](p); } catch(e) {} }
		if (typeof(ret)!="undefined" && typeof(ret)!="null") return(ret);
	}
}
document.onmousedown=function(we){
	var ret=null;
	if (!we) we=window.event;
	try { ret=mousedown_func_last(we); } catch(e) {}
	try { ret=mousedown(we); } catch(e) {}
	if (typeof(ret)!="undefined" && typeof(ret)!="null") return(ret);
}

// onmousemove
var mousemove_func=[];
var mousemove_func_last=document.onmousemove;
function mousemove(p) {
	if (typeof(p)=="function") mousemove_func[mousemove_func.length]=p;
	else { for (var i in mousemove_func) { try { mousemove_func[i](p); } catch(e) {} } }
}
document.onmousemove=function(we){
	if (!we) we=window.event;
	try { mousemove_func_last(we); } catch(e) {}
	try { mousemove(we); } catch(e) {}
}

// mouse delta
var mousewheel_func=[];
function mousewheel(p) {
	if (typeof(p)=="function") mousewheel_func[mousewheel_func.length]=p;
	else { for (var i in mousewheel_func) { try { mousewheel_func[i](p); } catch(e) {} } }
}
function mousewheel_eventhandler(event) {
	var delta=0;
	if (!event) event=window.event; // IE
	if (event.wheelDelta) { // IE/Opera
		delta=event.wheelDelta/120;
		// In Opera 9, delta differs in sign as compared to IE.
		if (window.opera) delta=-delta;
	} else if (event.detail) { // Mozilla
		// In Mozilla, sign of delta is different than in IE. Also, delta is multiple of 3.
		delta=-event.detail/3;
	}
	// If delta is nonzero, handle it. Basically, delta is now positive
	// if wheel was scrolled up, and negative, if wheel was scrolled down.
	if (delta) {
		//handle(delta);
		var cancel=false;
		for (var i in mousewheel_func) { try { if (mousewheel_func[i](delta,event)) cancel=true; } catch(e) {} }
	}
	// Prevent default actions caused by mouse wheel. That might be ugly,
	// but we handle scrolls somehow anyway, so don't bother here..
	if (cancel) {
		if (event.preventDefault)
			event.preventDefault();
		event.returnValue=false;
	}
}
if (window.addEventListener) window.addEventListener('DOMMouseScroll', mousewheel_eventhandler, false); // DOMMouseScroll for Mozilla
window.onmousewheel=document.onmousewheel=mousewheel_eventhandler; // IE/Opera

// evento mouseenter
var mouseenter_cancellers_count=0;
var mouseenter_cancellers={};
function mouseenter(id, func, bubbling) {
	mouseenter_cancellers_count++;
	var id=gid(id);
	if (!id.id) {
		id.id="mouseenter_canceller_id_"+mouseenter_cancellers_count;
	}
	if (typeof id.onmouseleave=="object") {
		if (id.addEventListener) {
			id.addEventListener("mouseenter", func, (bubbling?bubbling:false));
		} else {
			id.onmouseenter=func;
		}
	} else {
		id.addEventListener("mouseover", function(event) {
			var id=this.id;
			if (mouseenter_cancellers[id]) {
				clearTimeout(mouseenter_cancellers[id]);
				mouseenter_cancellers[id]=false;
			} else {
				func(event);
			}
		}, bubbling);
		id.addEventListener("mouseout", function(event) {
			var id=this.id;
			mouseenter_cancellers[id]=setTimeout(function(){
				mouseenter_cancellers[id]=false;
			},1);
		}, bubbling);
	}
}

// evento mouseleave
var mouseleave_cancellers_count=0;
var mouseleave_cancellers={};
function mouseleave(id, func, bubbling) {
	mouseleave_cancellers_count++;
	var id=gid(id);
	if (!id.id) {
		id.id="mouseleave_canceller_id_"+mouseleave_cancellers_count;
	}
	if (typeof id.onmouseleave=="object") {
		if (id.addEventListener) {
			id.addEventListener("mouseleave", func, (bubbling?bubbling:false));
		} else {
			id.onmouseleave=func;
		}
	} else {
		id.addEventListener("mouseover", function(event) {
			var id=this.id;
			if (mouseleave_cancellers[id]) {
				clearTimeout(mouseleave_cancellers[id]);
				mouseleave_cancellers[id]=false;
			}
		}, bubbling);
		id.addEventListener("mouseout", function(event) {
			var id=this.id;
			mouseleave_cancellers[id]=setTimeout(function(){
				mouseleave_cancellers[id]=false;
				func(event);
			},1);
		}, bubbling);
	}
}

// habilitar/deshabilitar seleccionar texto en un elemento
function selectionEnabled(o,enable) {
	var o=gid(o);
	if (typeof o.onselectstart!="undefined") { if (enable) o.onselectstart=null; else { o.onselectstart=function(){ return false; } } } // IE
	else if (typeof o.style.MozUserSelect!="undefined") { o.style.MozUserSelect=(enable?"":"none"); } //Firefox
	else { if (enable) o.onmousedown=null; else { o.onmousedown=function(){ return false; } } } // all other navs
	o.style.cursor="default";
}

// get current decimal separator
function localeDecimalSeparator() {
	return (1.1).toLocaleString().substring(1, 2);
}

// entrada sólo numérica entera
function gInputInt(id, negatives, floating) {
	var input=gid(id);
	var lds=localeDecimalSeparator();
	input.style.textAlign="right";
	input.onkeyup=function(e){
		var c=e.keyCode;
		if (c==16) input.removeAttribute("data-key-shift");
		if (c==17) input.removeAttribute("data-key-control");
	};
	input.onkeydown=function(e){
		var c=e.keyCode;
		if (c>=35 && c<=39) return true;
		if (c==16) {
			input.setAttribute("data-key-shift","1");
			return true;
		}
		if (c==17) {
			input.setAttribute("data-key-control","1");
			return true;
		}
		if (c==8) return true;
		if (c==46) return true;
		if (c==116) return true;
		if (c==9) return true;
		if (c==13) return true;
		if (c==15) return true;
		if (input.getAttribute("data-key-control")=="1") {
			if (c==90) return true; // Ctrl+Z
			if (c==88) return true; // Ctrl+X
			if (c==67) return true; // Ctrl+C
			if (c==86) return true; // Ctrl+V
		}
		if (floating)
			if ((lds == "," && (c==188)) || (c==110 || c==190))
				return (this.value.indexOf(lds)==-1 && this.value.indexOf(".")==-1?true:false);
		if (negatives)
			if (c==109 || (c==173 && !input.getAttribute("data-key-shift")))
				return (this.value.indexOf("-")==-1?true:false);
		if (c>=48 && c<=57) return true;
		if (c>=96 && c<=105) return true;
		return false;
	};
	input.onblur=function(e){
		var lds=localeDecimalSeparator();
		var v=parseFloat(this.value.replace(lds, "."));
		v=(isNaN(v)?"":""+v);
		if (this.getAttribute("type") != "number") v=v.replace(".", lds);
		this.value=v;
	};
	input.onblur();
}

// entrada sólo numérica floatante
function gInputFloat(id, negatives) {
	gInputInt(id,negatives,true);
}

// comprobación autogrow
function autogrowcheck(id) {
	var e=gid(id), h=0, p=['padding-top', 'padding-bottom'];
	var s=window.getComputedStyle(e, null);
	for (var i in p) h+=parseInt(s[p[i]]);
	e.style.height='auto';
	e.style.height=(e.scrollHeight+(s["box-sizing"] == "border-box"?h:-h))+'px';
}

// textarea autogrowing
function autogrow(id) {
	var e=gid(id);
	e.style.resize='none';
	e.addEventListener("input", function(){ autogrowcheck(id); });
	autogrowcheck(id);
}

// eliminar espacios de una cadena
function trim(str) {
	return (""+str).replace(/^\s*|\s*$/g,"");
}

// elimina la ruta de un nombre de fichero completo,
// y también su sufijo, si se especifica y coincide
function basename(path, suffix) {
	var b=path.replace(/^.*[\/\\]/g, '');
	if (typeof(suffix) == 'string' && b.substr(b.length-suffix.length) == suffix)
		b=b.substr(0, b.length-suffix.length);
	return b;
}

// equivalente a br2nl en php
function br2nl(s) {
	return s.replace(/<br\s*\/?>/mg,"\n");
}

// mostrar un número en formato X.XXX,XX
function spf(n, f) {
	var n=parseFloat(n);
	if (isNaN(n)) return "";
	var f=f || 2;
	var e=Math.pow(10, f);
	var n=Math.round(n*e)/e;
	if (typeof(Intl) == "object" && Intl.NumberFormat) return ""+(new Intl.NumberFormat(undefined, {minimumFractionDigits:f}).format(n));
	return n.toFixed(f).replace(".", localeDecimalSeparator());
}

// mostrar un número en formato X.XXX
function spd(n) {
	var n=parseFloat(n);
	if (isNaN(n)) return "";
	if (typeof(Intl) == "object" && Intl.NumberFormat) return ""+(new Intl.NumberFormat(undefined, {maximumFractionDigits:0}).format(n));
	return n.toFixed(0).replace(".", localeDecimalSeparator());
}

// mostrar un número en formato X.XXX o X.XXX,XX (solo si tiene decimales)
function spn(n, f) {
	return (parseInt(n) == parseFloat(n)?spd(n):spf(n, f));
}

// devuelve fecha y hora en formato ISO YYYY-MM-DD HH:II:SS desde fecha JavaScript (o fecha y hora actual)
function isoDatetime(f) { return isoDateTime(f); } // coherencia
function isoDateTime(f) {
	var
		f=f||new Date(),
		y=f.getFullYear(),
		m=f.getMonth()+1,
		d=f.getDate(),
		h=f.getHours(),
		i=f.getMinutes(),
		s=f.getSeconds()
	;
	return y+"-"+(m>9?"":"0")+m+"-"+(d>9?"":"0")+d
		+ " "+(h>9?"":"0")+h+":"+(i>9?"":"0")+i+":"+(s>9?"":"0")+s
	;
}

// devuelve fecha en formato ISO YYYY-MM-DD desde fecha JavaScript (o fecha actual)
function isoDate(f) {
	return isoDatetime(f).substring(0, 10);
}

// devuelve fecha en formato ISO HH:II:SS desde fecha JavaScript (o fecha actual)
function isoTime(f) {
	return isoDatetime(f).substring(11, 19);
}

// convierte una fecha Javascript a format SQL YYYY-MM-DD HH:II:SS (alias OBSOLETO)
function sqlFromDate(f) { return isoDatetime(f); }

// convierte una fecha SQL en formato YYYY-MM-DD con o sin HH:II:SS a formato JavaScript
function sqlDate(fecha) {
	var d=fecha.split(" ");
	var f=d[0].split("-");
	var h=(d.length>1?d[1].split(":"):[0,0,0]);
	try {
		return new Date(
			intval(f[0]), intval(f[1])-1, intval(f[2]),
			intval(h[0]), intval(h[1]), intval(h[2]),
		0);
	} catch(e) {
		return null;
	}
}

// convierte una cadena YYYY-MM-DD HH:II:SS a DD/MM/YYYY HH:II:SS
function sqlDateSP(d) {
	if (!d || (d.length!=10 && d.length!=19)) return "";
	return d.substring(8,10)+"/"+d.substring(5,7)+"/"+d.substring(0,4)+d.substring(10);
}

// convierte una cadena DD/MM/YYYY HH:II:SS a YYYY-MM-DD HH:II:SS
function spDateSQL(d) {
	if (!d || (d.length!=10 && d.length!=19)) return "";
	return d.substring(6,10)+"-"+d.substring(3,5)+"-"+d.substring(0,2)+d.substring(10);
}

// devuelve una fecha JavaScript en formato DD/MM/YYYY
function spDate(f) {
	var f=f || new Date();
	var dd=f.getDate(); if (dd<10) dd='0'+dd;
	var mm=f.getMonth()+1; if (mm<10) mm='0'+mm;
	var yyyy=f.getFullYear();
	return String(dd+"/"+mm+"/"+yyyy);
}

// devuelve una hora JavaScript en formato HH:II:SS
function spTime(f) {
	var f=f || new Date();
	var hh=f.getHours(); if (hh<=9) hh='0'+hh;
	var ii=f.getMinutes(); if (ii<=9) ii='0'+ii;
	var ss=f.getSeconds(); if (ss<=9) ss='0'+ss;
	return String(hh+":"+ii+":"+ss);
}

// devuelve la fecha actual en formato DD/MM/YYYY
function spDateNow() {
	return spDate(new Date());
}

// devuelve la hora actual en formato HH:II:SS
function spTimeNow() {
	return spTime(new Date());
}

// getElementsByClassName, implementación para IE (el único que no lo soporta)
if (typeof(getElementsByClassName) == "undefined") {
	function getElementsByClassName(oElm, strTagName, strClassName){
		var arrElements=(strTagName == "*" && oElm.all)? oElm.all :	oElm.getElementsByTagName(strTagName);
		var arrReturnElements=[];
		strClassName=strClassName.replace(/\-/g, "\\-");
		var oRegExp=new RegExp("(^|\\s)" + strClassName + "(\\s|$)");
		var oElement;
		for (var i=0; i<arrElements.length; i++) {
			oElement = arrElements[i];     
			if(oRegExp.test(oElement.className))
				arrReturnElements.push(oElement);
		}
		return arrReturnElements;
	}
}

// pasa cualquier cadena a entero/decimal, incluyendo números que comienzan con 0
function intval(t) { t=t+""; while (t.substring(0,1)=="0") t=t.substring(1); if (!t) t=0; return parseInt(t); }
function doubleval(t) { t=t+""; while (t.substring(0,1)=="0") t=t.substring(1); if (!t) t=0; return parseFloat(t); }

// devuelve el timestamp con resolución de milisegundos
function militime() { return new Date().getTime(); }

// devuelve parámetros GET (si no se especifican) o un parámetro GET de una URL o de la URL actual
function get(n, url) {
	var url=url || location.href;
	var i=url.indexOf("?");
	if (i !== -1) {
		url=url.substring(i+1);
		var i=url.indexOf("#");
		if (i !== -1) url=url.substring(0, i);
		var items=url.split("&");
		var k, v;
		if (typeof(n) === "undefined" || n === null) {
			var a={};
			for (var i=0; i<items.length; i++) {
				[k, v]=items[i].split("=");
				a[decodeURIComponent(k)]=(typeof(v) == "undefined"?"":decodeURIComponent(v));
			}
			return a;
		} else {
			for (var i=0; i<items.length; i++) {
				[k, v]=items[i].split("=");
				if (decodeURIComponent(k) === n) return (typeof(v) == "undefined"?"":decodeURIComponent(v));
			}
		}
	}
	return null;
}

// modifica parámetros (de una URL)
function alink(p, url) {
	var p=p||{};
	var url=url||location.href;
	var get={};
	var marker="";
	var i=url.indexOf("#");
	if (i !== -1) {
		marker=url.substring(i);
		url=url.substring(0, i);
	}
	var i=url.indexOf("?");
	if (i !== -1) {
		var g=url.substring(i+1).split("&");
		url=url.substring(0, i);
		for (var i in g) {
			[k, v]=g[i].split("=");
			get[decodeURIComponent(k)]=(typeof(v) == "undefined"?"":decodeURIComponent(v));
		}
	}
	for (var k in p) {
		var v=p[k];
		if (v === null) delete get[k];
		else get[k]=v;
	}
	var qs="";
	for (var k in get) {
		var v=get[k];
		qs+=(qs?"&":"")+encodeURIComponent(k)+(v===""?"":"="+encodeURIComponent(v));
	}
	return url+(qs?"?"+qs:"")+marker;
}

// evita la acción de carga de ficheros al arrastrarlos sobre la página o sobre un elemento de ella
function preventDefaultDrag(id) {
	if (typeof(id)=="undefined") var id=document.body;
	o=(typeof(id)=="object"?id:document.getElementById(id));
	o.addEventListener("dragover",function(event) { event.preventDefault(); },true);
	o.addEventListener("drop", function(event) { event.preventDefault(); }, false);
}

// phpjs: http://locutus.io/php/strip_tags/
function strip_tags(input, allowed) {
	allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('')
	var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi
	return input.replace(commentsAndPhpTags, '').replace(tags, function ($0, $1) {
		return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : ''
	});
}

// phpjs: tabla de traducciones HTML
function get_html_translation_table(table, quote_style) {
	var entities={}, hash_map={}, decimal;
	var constMappingTable={
		0:'HTML_SPECIALCHARS',
		1:'HTML_ENTITIES'
	}, constMappingQuoteStyle={
		0:'ENT_NOQUOTES',
		2:'ENT_COMPAT',
		3:'ENT_QUOTES'
	};
	var useTable={}, useQuoteStyle={};

	// translate arguments
	useTable=(!isNaN(table) ? constMappingTable[table] : table ? table.toUpperCase() : 'HTML_SPECIALCHARS');
	useQuoteStyle=(!isNaN(quote_style) ? constMappingQuoteStyle[quote_style] : quote_style ? quote_style.toUpperCase() : 'ENT_COMPAT');

	if (useTable !== 'HTML_SPECIALCHARS' && useTable !== 'HTML_ENTITIES') {
		throw new Error("Table: " + useTable + ' not supported');
	}

	entities['38']='&amp;';
	if (useTable === 'HTML_ENTITIES') {
		entities['160']='&nbsp;';
		entities['161']='&iexcl;';
		entities['162']='&cent;';
		entities['163']='&pound;';
		entities['164']='&curren;';
		entities['165']='&yen;';
		entities['166']='&brvbar;';
		entities['167']='&sect;';
		entities['168']='&uml;';
		entities['169']='&copy;';
		entities['170']='&ordf;';
		entities['171']='&laquo;';
		entities['172']='&not;';
		entities['173']='&shy;';
		entities['174']='&reg;';
		entities['175']='&macr;';
		entities['176']='&deg;';
		entities['177']='&plusmn;';
		entities['178']='&sup2;';
		entities['179']='&sup3;';
		entities['180']='&acute;';
		entities['181']='&micro;';
		entities['182']='&para;';
		entities['183']='&middot;';
		entities['184']='&cedil;';
		entities['185']='&sup1;';
		entities['186']='&ordm;';
		entities['187']='&raquo;';
		entities['188']='&frac14;';
		entities['189']='&frac12;';
		entities['190']='&frac34;';
		entities['191']='&iquest;';
		entities['192']='&Agrave;';
		entities['193']='&Aacute;';
		entities['194']='&Acirc;';
		entities['195']='&Atilde;';
		entities['196']='&Auml;';
		entities['197']='&Aring;';
		entities['198']='&AElig;';
		entities['199']='&Ccedil;';
		entities['200']='&Egrave;';
		entities['201']='&Eacute;';
		entities['202']='&Ecirc;';
		entities['203']='&Euml;';
		entities['204']='&Igrave;';
		entities['205']='&Iacute;';
		entities['206']='&Icirc;';
		entities['207']='&Iuml;';
		entities['208']='&ETH;';
		entities['209']='&Ntilde;';
		entities['210']='&Ograve;';
		entities['211']='&Oacute;';
		entities['212']='&Ocirc;';
		entities['213']='&Otilde;';
		entities['214']='&Ouml;';
		entities['215']='&times;';
		entities['216']='&Oslash;';
		entities['217']='&Ugrave;';
		entities['218']='&Uacute;';
		entities['219']='&Ucirc;';
		entities['220']='&Uuml;';
		entities['221']='&Yacute;';
		entities['222']='&THORN;';
		entities['223']='&szlig;';
		entities['224']='&agrave;';
		entities['225']='&aacute;';
		entities['226']='&acirc;';
		entities['227']='&atilde;';
		entities['228']='&auml;';
		entities['229']='&aring;';
		entities['230']='&aelig;';
		entities['231']='&ccedil;';
		entities['232']='&egrave;';
		entities['233']='&eacute;';
		entities['234']='&ecirc;';
		entities['235']='&euml;';
		entities['236']='&igrave;';
		entities['237']='&iacute;';
		entities['238']='&icirc;';
		entities['239']='&iuml;';
		entities['240']='&eth;';
		entities['241']='&ntilde;';
		entities['242']='&ograve;';
		entities['243']='&oacute;';
		entities['244']='&ocirc;';
		entities['245']='&otilde;';
		entities['246']='&ouml;';
		entities['247']='&divide;';
		entities['248']='&oslash;';
		entities['249']='&ugrave;';
		entities['250']='&uacute;';
		entities['251']='&ucirc;';
		entities['252']='&uuml;';
		entities['253']='&yacute;';
		entities['254']='&thorn;';
		entities['255']='&yuml;';
	}
	if (useQuoteStyle !== 'ENT_NOQUOTES') entities['34']='&quot;';
	if (useQuoteStyle === 'ENT_QUOTES') entities['39']='&#39;';
	entities['60']='&lt;';
	entities['62']='&gt;';

	// ascii decimals to real symbols
	for (decimal in entities) {
		if (entities.hasOwnProperty(decimal)) {
			hash_map[String.fromCharCode(decimal)] = entities[decimal];
		}
	}

	return hash_map;
}

// phpjs: convierte un texto a entidades HTML
function htmlentities(string, quote_style, charset, double_encode) {

	var hash_map = get_html_translation_table('HTML_ENTITIES', quote_style);
	symbol = '';
	string = string == null ? '' : string + '';

	if (!hash_map) return false;

	if (quote_style && quote_style === 'ENT_QUOTES') hash_map["'"] = '&#039;';

	if (!!double_encode || double_encode == null) {
		for (symbol in hash_map) {
			if (hash_map.hasOwnProperty(symbol)) {
				string = string.split(symbol).join(hash_map[symbol]);
			}
		}
	} else {
		string = string.replace(/([\s\S]*?)(&(?:#\d+|#x[\da-f]+|[a-zA-Z][\da-z]*);|$)/g, function (ignore, text, entity) {
			for (symbol in hash_map) {
				if (hash_map.hasOwnProperty(symbol)) {
					text = text.split(symbol).join(hash_map[symbol]);
				}
			}
			return text + entity;
		});
	}
	
	return string;
}

// abrir una ventana centrada
function windowOpen(url, pw, ph, o) {
	var o=o||{};
	var options={
		"toolbar":0,
		"location":0,
		"directories":0,
		"resizable":1,
		"scrollbars":1
	};
	if (typeof(pw) === "object") {
		var o=pw;
	} else {
		var w=parseInt(screen.width*pw);
		var h=parseInt(screen.height*ph);
		var l=parseInt((screen.width-w)/2);
		var t=parseInt((screen.height-h)/2.5);
		if (o.wratio) w=h*o.wratio;
		if (o.hratio) h=w*o.hratio;
	}
	if (o) for (var i in o) {
		if (o[i]===null || i=="name") delete options[i];
		else options[i]=o[i];
	}
	var p="";
	for (var i in options)
		p+=(p?",":"")+i+"="+options[i];
	return window.open(url, (o.name?o.name:""), p);
}

// convierte bytes a un string fácilmente legible
function bytesToString(bytes) { return sizeString(bytes); }
function sizeString(bytes) {
	if (bytes >= 1099511627776) return spf(bytes/1099511627776)+" TB";
	if (bytes >= 1073741824) return spf(bytes/1073741824)+" GB";
	if (bytes >= 1048576) return spf(bytes/1048576)+" MB";
	if (bytes >= 1024) return spf(bytes/1024)+" KB";
	return spd(bytes);
}

// copiar texto al portapapeles
function copyToClipboard(text) {
	var e=document.createElement("textarea");
	e.type="text";
	e.value=text;
	document.body.appendChild(e);
	e.select();
	e.focus();
	document.execCommand("copy");
	e.parentNode.removeChild(e);
}

// insertar texto en la posición del cursor o en sustitución de la selección de un input/textarea
function insertAtCursor(id, text) {
	var e=gid(id);
	if (document.selection) {
		e.focus();
		sel=document.selection.createRange();
		sel.text=text;
	} else if (e.selectionStart || e.selectionStart == '0') {
		var selectionEnd=e.selectionEnd;
		e.value=e.value.substring(0, e.selectionStart)+text+e.value.substring(selectionEnd, e.value.length);
		e.selectionEnd=selectionEnd+text.length;
	} else {
		e.value+=text;
	}
}
