var XITEMSSEARCH_ASC=0;
var XITEMSSEARCH_DESC=1;

if (!kernel) var kernel={};

function xItemsSearch(o) {
	var a=this;

	// registrar clase
	window.xItemsSearchs=window.xItemsSearchs || {};
	window.xItemsSearchs[o.id]=a;

	// aux: limpiar selección actual
	a.clearBrowserSelection=function(){
		if (window.getSelection) {
			if (window.getSelection().empty) {  // Chrome
				window.getSelection().empty();
			} else if (window.getSelection().removeAllRanges) {  // Firefox
				window.getSelection().removeAllRanges();
			}
		} else if (document.selection) {  // IE?
			document.selection.empty();
		}
	};

	// legacy cookie/localStorage selector
	a.storage=function(key, value){
		if (window.localStorage) {
			if (typeof(value) !== "undefined") {
				if (value === null) window.localStorage.removeItem(key);
				else window.localStorage.setItem(key, JSON.stringify(value));
			}
			var value=window.localStorage.getItem(key);
		} else {
			if (typeof(value) !== "undefined") {
				if (value === null) delCookie(key);
				else setCookie(key, JSON.stringify(value));
			}
			var value=getCookie(key);
		}
		try {
			return JSON.parse(value);
		} catch (e) {
			return false;
		}
	};

	// get/set to store last setup
	a.store=function(key, value){
		if (!a.o.cookie) return null;
		var values=a.storage(a.o.cookie);
		if (values && typeof(values) !== "object") values={};
		if (typeof(value) !== "undefined") {
			values=values || {};
			if (value === null) delete values[key];
			else values[key]=value;
			a.storage(a.o.cookie, (array_count(values)?values:null));
		}
		if (values) return values[key];
		return null;
	};

	// establecer/devolver/borrar filtro
	a.filter=function(f,v){
		if (typeof(f) == "object") {
			for (var i in f)
				a.filter(i, f[i]);
		} else {
			if (typeof(v) == "undefined") return a.filters[f];
			if (v === null) delete a.filters[f];
			else a.filters[f]=v;
			a.timedSearch(1);
		}
	};

	// quitar filtro
	a.unfilter=function(f){ a.filter(f,null); };

	// establecer el número de elementos visibles
	a.getVisible=function(num){
		if (gid(a.o.id+"_visible_list")) a.o.visibleNum=gidval(a.o.id+"_visible_list");
		if (!a.o.visibleNum || a.o.visibleNum < 1) a.o.visibleNum=o.visible;
		return a.o.visibleNum;
	};

	// establecer el número de elementos visibles
	a.setVisible=function(num){
		if (!num || num < 1) num=o.visible;
		a.o.visibleNum=num;
		if (gid(a.o.id+"_visible_list")) gidval(a.o.id+"_visible_list", num);
	};

	// establecer parámetros extra
	a.extra=function(v){
		if (typeof(v) == "undefined") return a.o.extra;
		a.o.extra=v;
	};

	// obtener texto de búsqueda
	a.getSearch=a.getSearchValue=function(){
		if (gid(a.o.id+"_search_txt")) a.searchValue=gidval(a.o.id+"_search_txt");
		return a.searchValue;
	};

	// establecer texto de búsqueda
	a.setSearch=a.setSearchValue=function(v){
		if (gid(a.o.id+"_search_txt")) gidval(a.o.id+"_search_txt", v);
		a.searchValue=v;
		a.storeSearchInputText();
	}

	// ir a una página
	a.go=function(page, o){
		var o=o||{};
		if (!page || page < 0) page=0;
		if (a.o.ongo) a.o.ongo(a, page, o);
		a.page=page;
		a.search();
	};

	// seleccionar una fila
	a.select=function(i){
		if (!a.o.multiselect) {
			a.selected={};
			if (isset(a.select_last)) a.trRefresh(a.select_last);
		}
		a.select_last=i;
		a.selected[a.data.data[i][a.key]]=a.data.data[i];
		a.trRefresh(i);
		if (a.o.select) a.o.select(a.data.data[i]);
	};

	// seleccionar una fila con doble click
	a.dblclick=function(i){
		if (a.o.dblclick) a.o.dblclick(a.data.data[i]);
	};

	// seleccionar por valor de un campo
	a.selectByField=function(field, value){
		for (var i in a.data.data) {
			if (a.data.data[i][field] == value) {
				a.select(i);
				return true;
			}
		}
		return false;
	};

	// actualizar TR
	a.trRefresh=function(i){
		try {
			if (a.selected && a.data.data[i] && a.selected[a.data.data[i][a.key]]) classAdd(a.o.id+"_tr_"+i,"xitemssearch_tr_item_active");
			else classDel(a.o.id+"_tr_"+i,"xitemssearch_tr_item_active");
		} catch(e) {}
	};

	// obtener última respuesta del servidor
	a.getData=function(){
		return a.data;
	};

	// obtener item recibido por índice
	a.getItem=function(index){
		if (!a.data.data || !isset(index)) return null;
		return a.data.data[index];
	};

	// obtener item recibido por clave
	a.getItemByKey=function(key){
		if (!a.data.data || !a.key) return null;
		for (var i in a.data.data)
			if (a.data.data[i][a.key] == key)
				return a.data.data[i];
		return null;
	};

	// obtener items recibidos
	a.getItems=function(){
		return a.data.data;
	};

	// obtener selección (como array, id o false)
	a.getSelected=function(){
		if (a.o.multiselect) {
			var n=a.selectCount();
			if (!n) return false;
			if (n == 1) {
				for (var id in a.selected)
					return id;
			} else {
				return array_keys(a.selected);
			}
		} else {
			if (isset(a.select_last)) {
				for (var id in a.selected)
					return id;
			} else
				return false;
		}
	};

	// obtener selección (como array, id o false)
	a.getSelectedKeys=function(){
		var selected=a.getSelected();
		var n=a.selectCount();
		var elements=[];
		if (n) {
			if (n == 1) {
				elements.push(selected);
			} else {
				for (var i in selected)
					elements.push(selected[i]);
			}
		}
		return elements;
	};

	// obtener selección ordenada (como array, id o false)
	a.getSelectedKeysOrdered=function(){
		var selected=a.getSelectedKeys();
		var elements=[];
		for (var i in a.data.data) {
			var id=a.data.data[i][a.key];
			if (selected.indexOf(id) != -1) elements.push(id);
		}
		return elements;
	};

	// obtener items de selección
	a.getSelectedItems=function(){
		a.getSelected();
		return a.selected;
	};

	// multiselección
	a.multiSelect=function(e,i){
		if (!e) var e=window.event;
		if (e.shiftKey) {
			if (isset(a.select_last)) {
				var js=a.select_last,je=i,jaux;
				if (js > je) { jaux=js; js=je; je=jaux; } else { js++; je++; }
				for (var j=js; j < je; j++) {
					if (a.selected[a.data.data[j][a.key]]) delete a.selected[a.data.data[j][a.key]];
					else a.selected[a.data.data[j][a.key]]=a.data.data[j];
					a.trRefresh(j);
				}
			}
		} else {
			if (!e.ctrlKey) a.clearSelection();
			if (a.selected[a.data.data[i][a.key]]) delete a.selected[a.data.data[i][a.key]];
			else a.selected[a.data.data[i][a.key]]=a.data.data[i];
			a.trRefresh(i);
		}
		a.select_last=i;
		if (a.o.multiselect) {
			try {
				a.o.multiselect(a.getSelectedItems(), a);
			} catch(e) {}
		}
	};

	// limpia la selección actual
	a.clearSelection=function(){
		if (isset(a.selected)) {
			for (var id in a.selected) {
				var j=a.ids[id];
				delete a.selected[id];
				a.trRefresh(j);
			}
			a.selected={};
		}
		if (isset(a.select_last))
			classDel(a.o.id+"_tr_"+a.select_last, "xitemssearch_tr_item_active");
		delete a.select_last;
		if (a.o.multiselect) a.o.multiselect(false, a);
		else if (a.o.select) a.o.select(false, a);
	};

	// selecciona todos los elementos
	a.selectAll=function(){
		a.selected={};
		for (var i in a.data.data)
			a.selected[a.data.data[i][a.key]]=a.data.data[i];
		a.refresh();
	};

	// devolver número de elementos seleccionados
	a.selectCount=function(){
		if (a.o.multiselect) {
			var n=0;
			for (var i in a.selected) n++;
			return n;
		} else {
			return (isset(a.select_last)?1:0);
		}
	};

	// ver si hay elementos seleccionados
	a.isSelected=function(){
		return a.selectCount();
	};

	// cambiar ordenación
	a.sort=function(sort){
		a.o.sort=sort;
	};

	// intercambiar ordenación
	a.swapsort=function(field){
		// si todavía es la primera vez que se cambia la ordenación,
		// borramos todas las ordenaciones por defecto
		if (a.sortfirst) {
			a.sortfirst=false;
			a.o.sort={};
			var unfirsted=true;
		}
		// bucle de ordenación: (sin ordenar - ascendente - descendente)
		do {
			var repeat=false;
			if (!isset(a.o.sort[field])) a.o.sort[field]=0;
			else {
				if (!a.o.sort[field]) a.o.sort[field]=1;
				else delete a.o.sort[field];
			}
			// si lo que he hecho ha resultado en obtener el mismo array que al principio,
			// es que era mejor no haberlo tocado, y repetimos el proceso con defaultsort
			if (unfirsted && array_equals(a.o.sort, a.defaultsort)) {
				a.o.sort=array_copy(a.defaultsort);
				repeat=true;
				unfirsted=false;
			}
		} while (repeat);
		// si no hay ninguna ordenación, usar ordenación por defecto
		var foundany=false; for (var i in a.o.sort) { foundany=true; break; }
		if (!foundany) {
			a.sortfirst=true;
			a.o.sort=array_copy(a.defaultsort);
		}
		// actualizar tabla y refrescar datos con la nueva ordenación
		a.refresh();
		a.search();
	};

	// poner el foco en el buscador
	a.focus=function(){
		if (a.o.nofocus) return;
		if (gid(a.o.id+"_search_txt")) gidfocus(a.o.id+"_search_txt");
	};

	// devolver información de paginación
	a.pagerInfo=function(){
		var mostrar=a.pagerNum;
		var pages=parseInt(a.data.max / a.data.visible);
		if ((a.data.max / a.data.visible) != parseInt(a.data.max / a.data.visible)) pages++;
		var start=(a.page - parseInt(a.pagerNum/2)); if (start < 0) start=0;
		if (pages > a.pagerNum && start+a.pagerNum > pages) start=pages - a.pagerNum;
		return {
			"href":"window.xItemsSearchs[\""+a.o.id+"\"]",
			"max":a.data.max,
			"page":a.page,
			"show":a.pagerNum,
			"start":start,
			"pages":pages,
			"visible":a.data.visible
		};
	};

	// devolver enlace HTML
	a.pagerLinkHTML=function(i, o){
		var o=o||{};
		var page=parseInt(i)+1;
		if (!page) return "";
		if (i == a.page) {
			return "<input class='txt xitemsearch_page_input xitemsearch_page_input_length_"+(""+page).length+(o.input_alone?" xitemsearch_page_input_alone":"")+"' type='text' value='"+page+"' onFocus='this.select();'"
				+" size='"+(o.pages?(""+o.pages).length:"")+"' onKeyPress='javascript:if(event.keyCode == 13){ window.xItemsSearchs[\""+a.o.id+"\"].go(parseInt(this.value)-1, {\"down\":"+(o.down?"true":"false")+"}); }' />"
		}
		return ""
			+"<span class='noselect xitemsearch_page xitemsearch_page_enabled'"
				+" onClick='javascript:void(window.xItemsSearchs[\""+a.o.id+"\"].go("+i+",{\"down\":"+(o.down?"true":"false")+"}))'"
			+">"
				+page
			+"</span>"
		;
	};

	// devolver enlace página previa HTML
	a.pagerLinkPrevHTML=function(i, o){
		var o=o||{};
		var page=parseInt(i)+1;
		if (!page) return "";
		var enabled=(page > 1);
		return ""
			+"<span class='noselect xitemsearch_page "+(enabled?"xitemsearch_page_enabled":"xitemsearch_page_disabled")+"'"
				+(page > 1?" onClick='javascript:void(window.xItemsSearchs[\""+a.o.id+"\"].go("+(page-2)+", {\"down\":"+(o.down?"true":"false")+"}))'":"")
			+">&lt;<span class='nomobile'> Anterior</span></span>"
		;
	};

	// devolver enlace página siguiente HTML
	a.pagerLinkNextHTML=function(i, o){
		var o=o||{};
		var page=parseInt(i)+1;
		if (!page) return "";
		var enabled=(page < o.pages);
		return ""
			+"<span class='noselect xitemsearch_page "+(enabled?"xitemsearch_page_enabled":"xitemsearch_page_disabled")+"'"
				+(page < o.pages?" onClick='javascript:void(window.xItemsSearchs[\""+a.o.id+"\"].go("+page+", {\"down\":"+(o.down?"true":"false")+"}))'":"")
			+"><span class='nomobile'>Siguiente </span>&gt;</span>"
		;
	};

	// generador HTML del paginador
	a.pagerHTML=function(o){
		var o=o||{};
		var info=a.pagerInfo();
		var mostrar=info.show;
		var h="";
		if (info.start > 0) {
			mostrar--;
			h+=a.pagerLinkHTML(0, o);
			if (info.start > 1) h+="<span class='xitemsearch_page noselect'>...</span>";
		}
		for (i=info.start; i < info.start+mostrar; i++) {
			if (i == info.pages) break;
			h+=a.pagerLinkHTML(i, o);
		}
		if (i < info.pages) {
			if (i < (info.pages - 1)) h+="<span class='xitemsearch_page noselect'>...</span>";
			h+=a.pagerLinkHTML(info.pages - 1, o);
		}
		return h;
	};

	// generador HTML del paginador
	a.pagerCountHTML=function(o){
		return (a.o.count?a.o.count(o):""
			+"<span class='nowrap'>"
				+"<b>"+spd(a.data.max)+"</b> "+(a.data.max != 1?"items":"item")
				+(a.data.timedout?" <b>(alcanzado límite de tiempo)</b>":"")
			+"</span>"
		);
	};

	// renderizar paginador
	a.renderPager=function(o){
		var pager=gid(o.id);
		if (!gid(pager)) return false;
		gidset(pager, "");

		var pager_div=document.createElement("div");
		pager_div.className="xitemsearch_pager";

		var pager_pages=document.createElement("div");
		pager_pages.className="xitemsearch_pager_pages";
		pager_div.appendChild(pager_pages);

		var pager_counter=document.createElement("div");
		pager_counter.className="xitemsearch_pager_counter";
		pager_counter.innerHTML=a.pagerCountHTML();
		pager_div.appendChild(pager_counter);

		pager.appendChild(pager_div);

		// empezar desde la página actual a renderizar páginas hacia fuera
		var w=pager_pages.offsetWidth, h="", lh="";
		h+=(o.page != 0 && o.page != o.pages-1?a.pagerLinkHTML(o.page, o):""); // página actual
		if (o.pages > 1) {
			pc=0;
			var no_pages=(isset(a.o.pagesMax) && !a.o.pagesMax);
			for (var i=1; i < o.pages; i++) {
				var pp=o.page-i, np=o.page+i;
				h=(no_pages?h:""
					+(pp > 0?a.pagerLinkHTML(o.page-i, o):"") // anterior
					+h // anteriores
					+(np < o.pages-1?a.pagerLinkHTML(o.page+i, o):"") // siguiente
				);
				var hfiller="<span class='xitemsearch_page xitemsearch_page_filler noselect'>...</span>";
				var hpp=(pp > 1?hfiller:""), npp=(np < o.pages-2?hfiller:"");
				var hstart="<div class='xitemsearch_pager_pages_divisor'>"
					+a.pagerLinkPrevHTML(o.page, o)
					+a.pagerLinkNextHTML(o.page, o)
				;
				var hend="</div>";
				var fh=hstart+a.pagerLinkHTML(0, o)+hpp+h+npp+a.pagerLinkHTML(o.pages-1, o)+hend;
				pager_pages.innerHTML=fh;
				if (pager_pages.offsetWidth > w) {
					pager_pages.innerHTML=(lh?lh:hstart+hpp+h+npp+hend);
					if (pager_pages.offsetWidth > w)
						pager_pages.innerHTML=hstart+a.pagerLinkHTML(o.page, array_merge(o, {"input_alone":true}))+hend;
					break;
				}
				lh=fh;
				if (isset(a.o.pagesMax) && (++pc >= a.o.pagesMax)) break;
			}
		} else {
			pager_pages.innerHTML=""
				+"<div class='xitemsearch_pager_pages_divisor'>"
					+a.pagerLinkPrevHTML(o.page, o)
					+a.pagerLinkNextHTML(o.page, o)
				+"</div>"
			;
		}

	};

	// control de tamaño
	a.resize=function(){
		a.refresh();
	};

	// actualizar tabla
	a.refresh=function(){

		// información
		var pi=a.pagerInfo();
		var default_sort=(array_equals(a.o.sort, a.defaultsort)?true:false);

		// event pre-refresh
		if (a.o.onprerefresh) a.o.onprerefresh(a.o.id+"_data");

		// renderizar paginadores
		if (a.o.pager) a.o.pager(a, a.pagerInfo());
		else {
			if (a.o.paged) {
				for (var pager=0; pager < 2; pager++)
					a.renderPager(array_merge(pi, {"id":a.o.id+"_pager"+(pager+1), "down":(pager?true:false)}));
			} else {
				hide(a.o.id+"_pager1");
				hide(a.o.id+"_pager2");
			}
		}

		// preparar tabla
		h="<table id='xitemssearch_"+a.o.id+"_main' class='xitemsearch_table'>";
		h+="<thead><tr>";
		for (var i in a.fields) if (!a.fields[i].disabled) {
			var n=a.fields[i];
			if (!n.disabled) {
				var thstyle=(n.nowrap?"white-space:nowrap;":"");
				var title=htmlentities(n.title?n.title:(n.caption?n.caption:""));
				h+="<th"+(n.class?" class='"+n.class+"'":"")+(n.nosort?" title='"+title+"'":"")+(thstyle?" style='"+thstyle+"'":"")+">"
					+(n.nosort?"":"<a href='javascript:window.xItemsSearchs[\""+a.o.id+"\"].swapsort(\""+i+"\");' title='"+title+"'>")
					+"<span class='"+(a.o.sort && isset(a.o.sort[i])?(a.o.sort[i]?"sortdesc":"sortasc"):"")+(default_sort?"_default":"")+"'>"
					+(n.caption?n.caption:"")
					+"</span>"
					+(n.nosort?"":"</a>")
					+"</th>"
				;
			}
		}
		h+="</tr></thead><tbody>";
		var c=0;
		a.ids={};
		for (var j in a.data.data) {
			c++;
			var e=a.data.data[j];
			var trclass=(a.o.trclass?a.o.trclass(e, i):"");
			var trstyle=(a.o.trstyle?a.o.trstyle(e, i):"");
			a.ids[e[a.key]]=j;
			h+="<tr class='xitemssearch_tr_item"
					+(a.o.select?" xitemssearch_tr_item_selectable":(a.o.selectable?"":" xitemssearch_tr_item_noselectable"))
					+(a.o.dblclick?" xitemssearch_tr_item_dblclick":"")
					+(a.o.tr_class_filter?" "+a.o.tr_class_filter(j,e):"")
				 	+(a.selected[e[a.key]]?" xitemssearch_tr_item_active":"")
				 	+(trclass?" "+trclass:"")
					+"' id='"+a.o.id+"_tr_"+j+"'"
					+(a.o.dblclick || a.o.select?" on"+(a.o.multiselect?"dbl":"")+"click='javascript:window.xItemsSearchs[\""+a.o.id+"\"].select("+j+");"+(a.o.dblclick?"window.xItemsSearchs[\""+a.o.id+"\"].clearBrowserSelection();":"return false;")+"'":"")
					+(a.o.dblclick && !a.o.multiselect?" ondblclick='javascript:window.xItemsSearchs[\""+a.o.id+"\"].dblclick("+j+");window.xItemsSearchs[\""+a.o.id+"\"].clearBrowserSelection();return false;'":"")
					+(a.o.multiselect?" onmousedown='javascript:window.xItemsSearchs[\""+a.o.id+"\"].multiSelect(event,"+j+");if(event.shiftKey || event.ctrlKey)return false;"+(a.o.selectable?"":"return false;")+"'":"")
					 +(trstyle?" style='"+trstyle+"'":"")
				+">";
			for (var i in a.fields) {
				var n=a.fields[i];
				if (!n.disabled) {
					var v=(e[i] === null || typeof(e[i]) === "undefined"?"":e[i]);
					if (n.filter && typeof(n.filter) == "string") n.filter=eval(n.filter);
					else if (v && n.limit) v=(v.length >= n.limit?v.substring(0, n.limit-1)+"…":v);
					var caption=(n.filter?n.filter(e[i],i,e,c-1,n):v);
					var tdclass=(n.tdclass?n.tdclass(e[i],i,e,c-1,n):"");
					var tdextra=(n.tdextra?n.tdextra(e[i],i,e,c-1,n):"");
					var tdstyle=(n.tdstyle?n.tdstyle(e[i],i,e,c-1,n):"");
					var tdtitle=(n.tdtitle?(typeof(n.tdtitle) == "function"?n.tdtitle(e[i],i,e,c-1,n):n.tdtitle):htmlentities(v));
					var link=(n.link?n.link(e[i], i, e, c-1, n):null);
					h+="<td"
							+(tdclass || n.class?" class='"+(n.class?n.class:"")+(tdclass?" "+tdclass:"")+"'":"")
							+(n.width?" width='"+n.width+"'":"")
							+(n.align?" align='"+n.align+"'":"")
							+(n.valign?" valign='"+n.valign+"'":"")
							+" style='"+(n.minwidth?"min-width:"+n.minwidth+";":"")+(n.maxwidth?"max-width:"+n.maxwidth+";":"")+(n.style?n.style+";":"")+(n.color?"color:"+n.color+";":"")+(n.nowrap?"white-space:nowrap;":"")+tdstyle+"'"
						+" title='"+tdtitle+"'"
						+tdextra+">"
						+(n.cut?"<div style='position:relative;"+(n.width && n.width.indexOf && n.width.indexOf("px") != -1?"width:"+n.width+";":"")+"overflow:hidden;'><div style='position:absolute;white-space:nowrap;'>":"")
						+(link
							?"<a href=\""+link+"\""+(n.ico?" class='icon' style='background-image:url("+n.ico+")'":"")+" onclick='window.event.stopPropagation();'>"
							:"<span"+(n.ico && caption?" class='icon' ":"")+" style='"+(n.nowrap?"white-space:nowrap;":"")+(n.ico?"background-image:url("+n.ico+");":"")+"'>"
						)
							+caption
						+(n.link?"</a>":"</span>")
						+(n.cut?"</div>&nbsp;</div>":"")
						+"</td>"
					;
				}
			}
			h+="</tr>";
		}
		if (!a.o.noempty) for (j=0; j < (a.data.visible-c); j++) {
			h+="<tr class='xitemssearch_tr_none' onMouseDown='javascript:window.xItemsSearchs[\""+a.o.id+"\"].clearSelection();return false;'>";
			for (var i in a.fields)
				if (!a.fields[i].disabled) 
					h+="<td><div style='visibility:hidden;'>&nbsp;</div></td>"
			h+="</tr>";
		}
		h+="</tbody></table>";

		// renderizar tabla, permitir hacerlo como cadena o como objeto DOM
		var h=(a.o.render?a.o.render(a, a.data, h):h);
		if (typeof(h) == "string") gidset(a.o.id+"_data", h);
		else {
			gidset(a.o.id+"_data", "");
			gid(a.o.id+"_data").appendChild(h);
		}

		// evento post-refresh
		if (a.o.onrefresh) a.o.onrefresh(a.o.id+"_data");

	};

	// botones extra
	a.renderButtons=function(){
		if (a.helpers["extra_buttons"]) {
			gidset(a.o.id+"_extra_buttons","");
			for (var i in a.o.buttons) {
				var b=a.o.buttons[i];
				var button=document.createElement(b.href?"a":"button");
				button.className="cmd";
				button.innerHTML=b.caption;
				if (b.class) button.className=b.class;
				if (b.href) button.href=b.href;
				if (b.action) button.onclick=b.action;
				gid(a.o.id+"_extra_buttons").appendChild(button);
			}
		}
	};

	// búsqueda con retraso
	a.timedSearch=function(timeout){
		if (this.searchTimer) clearTimeout(this.searchTimer);
		this.searchTimer=setTimeout(function(){ a.search(); },(timeout?timeout:300));
	};

	// update class for search input
	a.storeSearchInputText=function(){
		a.refreshSearchInputText();
		if (a.o.cookie) {
			var v=a.getSearchValue();
			a.store("search", (v == ""?null:v));
		}
	};

	// update class for search input
	a.refreshSearchInputText=function(){
		var id=a.o.id+"_search_txt";
		if (gid(id)) {
			var input=gidval(id).length;
			classEnable(id, "xitemsearch_search_input", input);
			classEnable(id, "xitemsearch_search_empty", !input);
		}
	};

	// store visible value
	a.storeVisible=function(){
		if (a.o.cookie) {
			var v=a.getVisible();
			if (a.o.visible == v) a.store("visible", null);
			else a.store("visible", v);
		}
	};

	// búsqueda
	a.search=function(p){
		if (typeof(p) == "string") var p={"search":p};
		var p=p||{};
		if (p.search) a.setSearch(p.search);
		a.search_again=false;
		if (a.searching) {
			a.search_again=true;
			return;
		}
		// preparar datos
		a.searching=true;
		// eventos
		var events=["onsearch", "onload", "onajaxstart", "onajaxend"];
		for (var i in events) {
			var ev=events[i];
			if (!p[ev] && a.o[ev]) p[ev]=a.o[ev];
		}
		// petición AJAX
		a.refreshSearchInputText();
		if (gid(a.o.id+"_search_txt")) classAdd(a.o.id+"_search_txt", "xitemsearch_search_wait");
		if (p.onajaxstart) p.onajaxstart(a, p);
		if (a.autoupdateTimer) clearTimeout(a.autoupdateTimer);
		ajax(a.o.ajax,{
			"search":a.getSearchValue(),
			"page":(a.o.paged?a.page:0),
			"sort":a.o.sort,
			"visible":(a.o.paged?a.getVisible():0),
			"extra":(a.o.extra?a.o.extra:{}),
			"filters":(a.filters?a.filters:{}),
		},function(){
			if (p.onajaxend) p.onajaxend(a, p);
			if (gid(a.o.id+"_search_txt")) classDel(a.o.id+"_search_txt", "xitemsearch_search_wait");
			a.searching=false;
		},function(r){
			if (a.o.autoupdate > 0) {
				a.autoupdateTimer=setTimeout(function(){
					a.search();
				}, a.o.autoupdate);
			}
			if (r.data.err) newerror(r.data.err);
			if (r.data.ok) {
				a.storeSearchInputText();
				a.storeVisible();
				a.setVisible(r.data.visible);
				if (p.onsearchok) p.onsearchok(a, r);
				if ((a.page*a.getVisible()) > r.data.max) {
					a.page=parseInt((r.data.max-1)/a.getVisible()); if (a.page < 0) a.page=0;
					a.search();
				} else {
					a.store("page", (a.page?a.page:null));
					a.data=r.data;
					if (a.selected && a.data.data)
						for (var i in a.data.data)
							if (a.data.data[i][a.key] && a.selected[a.data.data[i][a.key]])
								a.selected[a.data.data[i][a.key]]=a.data.data[i];
					if (a.search_again) a.search();
					a.refresh();
					if (p.onsearch) p.onsearch(a, a.data);
					if (p.onload && !a.loaded) {
						a.loaded=true;
						p.onload(a);
					}
				}
			}
		});
	};

	// alias general para mejor lectura
	a.update=function(p){
		var p=p||{};
		a.search(p);
	};

	// inicializar
	a.init=function(o){
		var o=o || {};

		// preparar parámetros
		a.o=o;
		if (!isset(a.o.sortable)) a.o.sortable=true;
		if (!isset(a.o.paged)) a.o.paged=true;
		if (!isset(a.o.autofocus)) a.o.autofocus=!('ontouchstart' in window);
		a.o.extra=(a.o.extra?a.o.extra:{});
		a.o.placeholder=a.o.placeholder || "";
		a.o.buttons=(a.o.buttons?a.o.buttons:{});
		a.helpers=array_merge({
			"search":true,
			"listvisiblenum":true,
			"pagertop":true,
			"pagerbottom":true,
			"extra_buttons":true,
			"extra_html":false
		},(a.o.helpers?a.o.helpers:{}));
		a.o.cookie=(isset(a.o.cookie) && a.o.cookie !== true?a.o.cookie:location.pathname+":xitemssearch:"+a.o.id);
		a.sortfirst=true;
		a.searchValue=a.o.searchValue=(a.o.searchValue?a.o.searchValue:(a.store("search")?a.store("search"):""));
		a.defaultsort=(a.o.sort?a.o.sort:{});
		a.page=parseInt(a.o.page?a.o.page:(a.store("page")?a.store("page"):0));
		a.pagerNum=(a.o.pagerNum?a.o.pagerNum:15);
		a.loaded=false;
		a.filters={};
		a.fields=(a.o.fields?a.o.fields:{});
		a.selected={};
		a.data=o.data || false;
		a.key=(a.o.key?a.o.key:"id");
		o.visible=o.visible || 10;
		a.o.visibleNum=(a.o.visibleNum?a.o.visibleNum:a.store("visible"));
		if (a.o.visibleActual) a.setVisible(a.o.visibleActual);

		var item_data=a.o.id+"_data";

		// helpers
		if (!isset(a.o.finder) || a.o.finder) {

			// div finder y extras
			var item_search=document.createElement("div");
			item_search.id=a.o.id+"_search";
			item_search.className="xitemsearch_search";
			var visibleList=[5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,30,40,50,100,250,500,1000];
			if (a.o.visibleList) visibleList=a.o.visibleList;

			// finder
			var h="";
			for (var i=0; i < visibleList.length; i++) {
				h+="<option value='"+visibleList[i]+"'"+(visibleList[i] == a.getVisible()?" selected":"")+">"
						+(a.o.showCaption?a.o.showCaption:"Ver ")+visibleList[i]
					+"</option>"
				;
			}
			gidset(item_search, ""
				+"<div class='xitemssearch_finder'>"
					+(a.helpers["search"]
						?(a.o.searchCaption?"<div>"+a.o.searchCaption+":&nbsp;</div>":"")
							+"<input id='"+a.o.id+"_search_txt' class='txt xitemsearch_search_txt' type='text' value='' />"
					:"")
					+(a.o.paged && a.helpers["listvisiblenum"]
						?"<select id='"+a.o.id+"_visible_list' class='cmb xitemsearch_visible_cmb'>"+h+"</select>"
					:"")
					+(a.helpers["extra_buttons"]
						?"<span id='"+a.o.id+"_extra_buttons' class='xitemsearch_buttons'></span>"
					:"")
					+(a.helpers["extra_html"]
						?"<span id='"+a.o.id+"extra_html'>"+a.helpers["extra_html"]+"</span>"
					:"")
				+"</div>"
			);
			gid(a.o.id).appendChild(item_search);
			if (gid(a.o.id+"_search_txt")) {
				gid(a.o.id+"_search_txt").placeholder=a.o.placeholder;
				gid(a.o.id+"_search_txt").addEventListener("change", function(e){ a.refreshSearchInputText(); });
				gid(a.o.id+"_search_txt").addEventListener("input", function(e){ a.refreshSearchInputText(); });
			}
			a.refreshSearchInputText();

			// acciones del buscador
			var search_txt=gid(a.o.id+"_search_txt");
			if (search_txt) {
				search_txt.onkeypress=function(e){
					if (!e) var e=window.event;
					if (e.keyCode == 9) return;
					if (e.keyCode == 13) {
						a.page=0;
						a.timedSearch(1);
					}
				};
				search_txt.oninput=function(){
					a.page=0;
					a.timedSearch(0);
				};
				search_txt.ondblclick=function(){
					this.value="";
					a.page=0;
					a.search();
				};
			}

			// renderizar varios
			a.renderButtons();

		}

		// filtros debajo de la búsqueda
		if (a.helpers.filters) {
			if (a.helpers.filters === true) {
				var e=document.createElement("div");
				e.id=a.o.id+"_filters";
				e.className="xitemsearch_filters";
			} else {
				var e=a.helpers.filters(a);
			}
			gid(a.o.id).appendChild(e);
		}

		// paginador superior
		if (a.helpers["pagertop"] && !a.o.pager) {
			var item_pager1=document.createElement("div");
			item_pager1.id=a.o.id+"_pager1";
			item_pager1.className="xitemsearch_pager";
			gid(a.o.id).appendChild(item_pager1);
		}

		// datos
		var item_data=document.createElement("div");
		item_data.id=a.o.id+"_data";
		item_data.className="xitemsearch_data";
		gidset(item_data,"");
		gid(a.o.id).appendChild(item_data);

		// paginador inferior
		if (a.helpers["pagerbottom"] && !a.o.pager) {
			var item_pager2=document.createElement("div");
			item_pager2.id=a.o.id+"_pager2";
			item_pager2.className="xitemsearch_pager";
			gid(a.o.id).appendChild(item_pager2);
		}

		// recuperar última búsqueda
		if (!isset(a.o.finder) || a.o.finder) {
			if (search_txt) gidval(search_txt, a.searchValue);
			//if (!isset(a.o.page)) a.page=(a.cookie?parseInt(getCookie(a.cookie+"_cookie_page")):false);
		}
		if (!a.page) a.page=0;

		// lista de visibles
		var visible_list=gid(a.o.id+"_visible_list");
		if (visible_list) {
			gidval(visible_list, a.o.visibleActual);
			gid(visible_list).onchange=function(){ a.search(); };
		}

		// establecer filtros
		if (a.o.filters) a.filter(a.o.filters);

		// búsqueda inicial
		if (a.data) a.refresh();
		else a.search();
		if (a.o.autofocus === true) a.focus();

	};

	// lanzar inicialización al crear clase
	a.init(o);

}

function xItemsSearchResizer() {
	for (var i in window.xItemsSearchs)
		window.xItemsSearchs[i].resize();
}

window.addEventListener("resize", xItemsSearchResizer, false);
