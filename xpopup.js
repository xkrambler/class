/*
	Título..: xpopup - mr.xkr's JavaScript Non-obstrusive Popup
	Licencia: GPL (http://www.gnu.org/licenses/gpl.txt)
	Autor...: Pablo Rodríguez Rey (mr -en- xkr -punto- es)
	          http://mr.xkr.es/
	Requiere: common.js
	Usa libremente esta librería bajo los términos de la licencia GPL, pero por favor,
	deja la autoría intacta, es lo único que te pido, sólo son 419 bytes de carga ;-)
*/

var xpopup_menu=null;
var xpopup_cancelContextMenu=false;
var xpopup_cancelClick=false;
var xpopup_menuItem=null;
var xpopup_menuFirstClick=true;
var xpopup_lastShift=null;
var xpopup_lastHold=null;
var xpopup_selectedItems={};

// item actual seleccionado
function xpopupItem(o,func,unfunc) {
	if (o) {
		xpopupSelectNone();
		xpopupDestroy();
		xpopupItemClick(false,o,func,unfunc);
	}
	return xpopup_menuItem;
}

// seleccionar un item
function xpopupItemSelect(o,swap) {
	var i=o.getAttribute("xType")+o.getAttribute("xItem");
	//xpopup_selectedItems[i]=(xpopup_selectedItems[i]?(swap?false:o):o);
	if (xpopup_selectedItems[i]) {
		if (swap) {
			o.className=o.className.replace(" xpopupItemSelected","");
			delete xpopup_selectedItems[i];
		}
	} else {
		xpopup_selectedItems[i]=o;
		o.className+=" xpopupItemSelected";
	}
	//alert(i+" - swap:"+swap);
}

// deseleccionar todos los items
function xpopupSelectNone() {
	for (var i in xpopup_selectedItems)
		xpopupItemSelect(xpopup_selectedItems[i],true);
}

// contar el número de items seleccionados
function xpopupSelectCount() {
	var i=0;
	for (var v in xpopup_selectedItems)
		i++;
	return i;
}

// devolver la lista de items seleccionados
function xpopupSelectedItems() {
	var a=[];
	for (var i in xpopup_selectedItems) {
		var o=xpopup_selectedItems[i];
		a.push({
			"item":o.getAttribute("xItem"),
			"type":o.getAttribute("xType"),
			"template":o.getAttribute("xTemplate"),
			"o":o
		});
	}
	return a;
}

// hacer click en un item
function xpopupItemClick(selectable,o,func,unfunc) {
	var o=gid(o);
	var lastItem=xpopup_menuItem;
	// setup
	if (xpopup_menuFirstClick) {
		xpopup_menuFirstClick=false;
		setTimeout(function(){
			scroll(function(){ xpopup_cancelClick=true; });
			mouseup(function(){
				setTimeout(function(){
					if (!xpopup_cancelClick) {
						xpopupSelectNone();
						xpopupDestroy();
					}
					xpopup_cancelClick=false;
				},1);
			});
			xpopup_cancelClick=false;
		},1);
	}
	// actions
	if (xpopup_menuItem && xpopup_menuItem.item==o.getAttribute("xItem") && xpopup_menuItem.type==o.getAttribute("xType")) {
		if (xpopup_menuItem.unfunc) xpopup_menuItem.unfunc(xpopup_menuItem);
		return (o.getAttribute("xNoLink") || o.href=="javascript:void(0)"?false:true);
	}
	xpopupDestroy();
	xpopup_cancelClick=true;
	xpopup_menuItem={
		"item":o.getAttribute("xItem"),
		"type":o.getAttribute("xType"),
		"template":o.getAttribute("xTemplate"),
		"func":(func?func:null),
		"unfunc":(unfunc?unfunc:null),
		"o":o,
		"multi":false // selección múltiple
	};
	if (selectable && ((xpopup_lastShift && lastItem) || (xpopup_lastHold && lastItem))) {
		if (xpopup_lastShift && lastItem) {
			var min=Math.min(lastItem.o.parentNode.offsetTop,xpopup_menuItem.o.parentNode.offsetTop);
			var max=Math.max(lastItem.o.parentNode.offsetTop,xpopup_menuItem.o.parentNode.offsetTop);
		} else {
			var min=xpopup_menuItem.o.parentNode.offsetTop;
			var max=min;
		}
		var elements=o.parentNode.parentNode.getElementsByTagName("LI");
		for (var i=0;i<elements.length;i++) {
			var a=elements[i].getElementsByTagName("A")[0];
		 	var pos=elements[i].offsetTop;
			//alert("("+i+") "+pos+" "+min+"/"+max+" "+a.getAttribute("xItem")+" "+a.getAttribute("xType"));
			if (pos==lastItem.o.parentNode.offsetTop || pos<min || pos>max) continue;
			xpopupItemSelect(a,true);
		}
		xpopup_menuItem.multi=true;
		var last=null;
		for (var i in xpopup_selectedItems)
			if (!last || last.parentNode.offsetTop<xpopup_selectedItems[i].parentNode.offsetTop)
				last=xpopup_selectedItems[i];
		if (last) {
			var aux=xpopup_menuItem.o;
			xpopup_menuItem.o=last;
			xpopupDisplay({"multi":true});
			xpopup_menuItem.o=aux;
		}
	} else {
		xpopupSelectNone();
		xpopupItemSelect(o);
		xpopupDisplay();
	}
	return false;
}

// destruir popup
function xpopupDestroy(preserve_menuItem) {
	if (xpopup_menu) xpopup_menu.parentNode.removeChild(xpopup_menu);
	if (xpopup_menuItem) if (xpopup_menuItem.o) xpopup_menuItem.o.className=xpopup_menuItem.o.className.replace(" xpopupItemActive","");
	xpopup_menu=null;
	if (!preserve_menuItem) xpopup_menuItem=null;
}

// mostrar popup
function xpopupDisplay(o) {
	if (!(o && o.multi))
		xpopup_menuItem.o.className=xpopup_menuItem.o.className+" xpopupItemActive";
	xpopup_menu=document.createElement("div");
	var template="popup."+(o && o.multi?"multi":xpopup_menuItem.template);
	if (!gid("template:"+template)) {
		xpopup_menu=null;
		return;
	}
	gidset(xpopup_menu,gtemplate(template,{
		"$item":xpopup_menuItem.item,
		"$type":xpopup_menuItem.type,
		"$template":xpopup_menuItem.template
	}));
	document.body.appendChild(xpopup_menu);
	xpopup_menu.className="xPopup";
	style(xpopup_menu,{"position":"fixed","float":"left"});
	xpopup_menu.onmousedown=function(){ xpopup_cancelClick=true; }
	xpopup_menu.onload=function(){ xpopupDisplay(o); }
	if (xpopup_menuItem.func) xpopup_menuItem.func(xpopup_menuItem);
	var w=getWidth(xpopup_menu);
	style(xpopup_menu,{
		"left":(getLeft(xpopup_menuItem.o)+getWidth(xpopup_menuItem.o)-w)+"px",
		"top":(getTop(xpopup_menuItem.o)+getHeight(xpopup_menuItem.o)-getBorderBottomHeight(xpopup_menuItem.o))+"px",
		"display":"block",
		"position":"absolute"
	});
}

// eventos
resize(function(){
	xpopupDestroy(true);
	xpopupDisplay();
});
keydown(function(e){
	if (!e) var e=window.event;
	if (e.keyCode==16) xpopup_lastShift=true;
	if (e.keyCode==17) xpopup_lastHold=true;
});
keyup(function(e){
	if (!e) var e=window.event;
	if (e.keyCode==16) xpopup_lastShift=false;
	if (e.keyCode==17) xpopup_lastHold=false;
});
