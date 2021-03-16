// implementación básica de gid (si no incluida)
if (typeof(gid)=="undefined") {
	function gid(id) {
		try {
			if (typeof(id)=="object") return id;
			else return document.getElementById(id);
		} catch(e) {
			return(null);
		}
	}
}

// habilitar/deshabilitar un combo
function comboEnabled(combo, enabled) {
	gid(combo).disabled=!enabled;
}

// mostrar/ocultar un combo
function comboVisible(combo, visible) {
	gid(combo).style.visibility=(visible?"visible":"hidden");
}

// añadir opción a combo
function comboAdd(combo, text, value, selected, o) {
	var o=o||{};
	if (o===true || o===false) o={"bold":o};
	var combo=gid(combo);
	var selOpcion=new Option(text, (typeof(value)=="undefined"?text:value));
	if (isset(selected)) selOpcion.selected=selected;
	try {
		try { combo.add(selOpcion,null); } catch(e) { combo.add(selOpcion); }
		if (isset(o.bold)) selOpcion.style.fontWeight=(o.bold?"bold":"normal");
		if (isset(o.color)) selOpcion.style.color=o.color;
	} catch(e) {
		//alert(e+"\nError adding option ("+value+") - "+text);
		return false;
	}
	return selOpcion;
}

// reemplazar opción de combo
function comboReplace(combo, text, value, selected, o) {
	var combo=gid(combo);
	for (var i in combo.options) {
		if (combo.options[i].value==value) {
			combo.options[i].text=text;
			return true;
		}
	}
	return comboAdd(combo, text, value, selected, o);
}

// borrar opción por índice
function comboDel(combo, index) {
	combo=gid(combo);
	try {
		combo.options[index]=null;
		return(true);
	} catch(e) {
		return(false);
	}
}

// borrar seleccionados
function comboDelSelected(combo) {
	while (combo.selectedIndex>=0)
		comboDel(combo, combo.selectedIndex);
}

// limpiar combo
function comboClear(combo) {
	gid(combo).length=0;
}

// obtener valor o reemplazar
function comboValue(combo, value, newvalue) {
	combo=gid(combo);
	if (newvalue==undefined) {
		if (combo.selectedIndex<0) return(false);
		if (value!=undefined) { combo.value=value; return true; }
		return combo.value;
	} else {
		for (var i in combo.options) {
			if (combo.options[i].value==value) {
				combo.options[i].value=newvalue;
				return true;
			}
		}
		return false;
	}
}

// establecer valor, si existe en la lista de opciones
function comboValueIfExists(combo, value) {
	var exists=comboValueExists(combo, value);
	if (exists === true) comboValue(combo, value);
	return exists;
}

// cambiar texto de una opción
function comboText(combo, id, text) {
	var combo=gid(combo);
	if (typeof(id)=="undefined") {
		if (combo.selectedIndex<0) return(false);
		return(combo.options[combo.selectedIndex].text);
	} else {
		for (var i in combo.options) {
			if (combo.options[i].value==id) {
				if (typeof(text)=="undefined") return combo.options[i].text;
				else {
					combo.options[i].text=text;
					return true;
				}
			}
		}
		return false;
	}
}

// obtener índice por valor
function comboIndexByValue(combo,value) {
	combo=gid(combo);
	for (var i=0;i<combo.options.length;i++)
		if (combo.options[i].value==value) return(i);
	return -1;
}

// obtener índice por texto
function comboIndexByText(combo,text) {
	combo=gid(combo);
	for (var i=0;i<combo.options.length;i++)
		if (combo.options[i].text==text) return(i);
	return -1;
}

// comprobar si el valor existe
function comboValueExists(combo, value) {
	combo=gid(combo);
	return (comboIndexByValue(combo, value)>=0);
}

// comprobar si el texto existe
function comboTextExists(combo, text) {
	combo=gid(combo);
	return (comboIndexByText(combo, text)>=0);
}

// limpiar y rellenar combo
function comboClearFill(combo,lista,valorActual,formaMostrar,indiceValor,filtro) {
	comboFill(combo,lista,valorActual,formaMostrar,indiceValor,filtro, {"clear":true});
}

// comprobar si el objeto es un elemento HTML
function comboIsElement(o) {
	return (typeof HTMLElement === "object"
		?o instanceof HTMLElement
		:o && (typeof o === "object") && (o !== null) && (o.nodeType === 1) && (typeof o.nodeName === "string")
	);
}

// rellenar combo
function comboFill(combo, lista, renderer, formaMostrar, indiceValor, filtro, o) {
	if (typeof combo == "object" && !comboIsElement(combo)) {
		var valorActual=(typeof combo.value != "undefined"?combo.value:gidval(combo.id));
		if (combo.clear) comboClear(combo.id);
		if (combo.empty) comboAdd(combo.id, combo.empty, "", ("" === valorActual));
		if (combo.items) for (var i in combo.items) {
			var item=combo.item(combo.items[i], i);
			if (item) comboAdd(combo.id, item.caption, item.value, (item.value === valorActual), item);
		}
	} else if (typeof renderer=="function") {
		var valorActual=gidval(combo);
		if (o && o.clear) comboClear(combo);
		for (var i in lista) {
			var item=renderer(lista[i], i);
			if (item !== null) comboAdd(combo, item.caption, item.value, (item.value===valorActual), item);
		}
	} else {
		var valorActual=renderer;
		if (o && o.clear) comboClear(combo);
		if (!indiceValor) indiceValor="i";
		if (!formaMostrar) formaMostrar="lista[i]";
		if (!filtro) filtro="";
		eval // es más eficiente meter el bucle for dentro que fuera, aunque el código quede más sucio
			("for (var i in lista) {"
			+"	"+(filtro?"if("+filtro+")":"")+"comboAdd(combo,"+formaMostrar+","+indiceValor+",(valorActual=="+indiceValor+"?true:false));"
			+"}");
	}
}

// copia todo el contenido de un combo a otro
// OJO: No limpia el combo, así que se agregan los valores al combo sin quitar los existentes
function comboCopy(destiny, source) {
	var d=gid(destiny);
	var s=gid(source);
	for (var i in s.options)
		comboAdd(d, s.options[i].text, s.options[i].value);
}

function comboSortTextNaturalSP(t) {
	var s=t.toLowerCase();
	s=s.replace("á","a");
	s=s.replace("é","e");
	s=s.replace("í","i");
	s=s.replace("ó","o");
	s=s.replace("ú","u");
	s=s.replace("Á","a");
	s=s.replace("É","e");
	s=s.replace("Í","i");
	s=s.replace("Ó","o");
	s=s.replace("Ú","u");
	s=s.replace("Ñ","nz");
	s=s.replace("ñ","nz");
	return s;
}

function comboSortCompareText(o1, o2) {
	return (comboSortTextNaturalSP(o1.text)<comboSortTextNaturalSP(o2.text)
		?-1
		:(comboSortTextNaturalSP(o1.text)>comboSortTextNaturalSP(o2.text)?1:0)
	);
}

function comboSortCompareTextCaseSensitive(o1, o2) {
	return (o1.text<o2.text?-1:o1.text>o2.text?1:0);
}

function comboSort(combo,compareFunction) {
	combo=gid(combo);
	if (!compareFunction) compareFunction=comboSortCompareText;
	var options=new Array(combo.options.length);
	var styles=new Array(combo.options.length);
	for (var i=0; i<options.length; i++) {
		var className=combo.options[i].className;
		var style=combo.options[i].style.cssText;
		options[i]=new Option(
			combo.options[i].text,
			combo.options[i].value,
			combo.options[i].defaultSelected,
			combo.options[i].selected
		);
		options[i].className=className;
		options[i].style.cssText=style;
	}
	options.sort(compareFunction);
	combo.options.length=0;
	for (var i=0; i<options.length; i++)
		combo.options[i]=options[i];
}

function comboMove(combo1,combo2,sorted,compareFunction) {
	combo1=gid(combo1);
	combo2=gid(combo2);
	while (combo1.selectedIndex>=0) {
		var option=new Option(
			combo1.options[combo1.selectedIndex].text,
			combo1.options[combo1.selectedIndex].value,
			combo1.options[combo1.selectedIndex].defaultSelected,
			combo1.options[combo1.selectedIndex].selected
		);
		try { combo2.add(option,null); } catch(e) { combo2.add(option); }
		combo1.options[combo1.selectedIndex]=null;
	}
	if (sorted==undefined || sorted) comboSort(combo2,compareFunction);
}

function comboMoveIndex(combo1,index,combo2,sorted,compareFunction) {
	combo1=gid(combo1);
	combo2=gid(combo2);
	var option=new Option(
		combo1.options[index].text,
		combo1.options[index].value,
		combo1.options[index].defaultSelected,
		combo1.options[index].selected
	);
	try { combo2.add(option,null); } catch(e) { combo2.add(option); }
	combo1.options[index]=null;
}

function combosVisible(visible) {
	var elements=document.body.getElementsByTagName("select");
	for (var i in elements)
		if (elements[i].type)
			if (elements[i].type=="select-one" || elements[i].type=="select-multiple")
				elements[i].style.visibility=(visible?"visible":"hidden");
}

function combosVisibleIE(visible) {
	if (isie()) combosVisible(visible);
}

function comboAutoImage(s) {
	var option=(gid(s).selectedIndex>=0?gid(s).options[gid(s).selectedIndex]:null);
	var backgroundImage=(option?option.style.backgroundImage:null);
	gid(s).style.backgroundImage=(option && backgroundImage?backgroundImage:"url()");
}

function comboAutoImageSetup(s) {
	gid(s).onclick=function(){ comboAutoImage(s); }
	gid(s).onchange=function(){ comboAutoImage(s); }
	gid(s).onkeyup=function(){ comboAutoImage(s); }
	comboAutoImage(s);
}
