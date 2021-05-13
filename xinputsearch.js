var xinputsearchs=[];

function xinputsearch(o) {
	var a=this;
	var cancelClick=false;
	var cancelBlur=false;
	a.o=o;
	a.changed=false;
	a.events=false;
	a.loaded=false;
	a.popup=null;
	a.caption="";
	a.value="";
	a.items=(a.o.items?a.o.items:[]); delete a.o.items;
	
	// guardar esta clase en la lista global
	a.num=xinputsearchs.length;
	xinputsearchs.push(a);
	
	// establecer filtro de búsqueda
	a.search=function(newValue){
		if (isset(newValue) && gid(a.o.input)) gidval(a.o.input, newValue);
		return (gid(a.o.input)?gidval(a.o.input):"");
	};
	
	// filtro de items
	a.filterItems=function(){
		var s=a.search();
		a.itemsFiltered=[];
		if (a.o.first && s=="") {
			a.itemsFiltered.push(a.o.item(a.o.first));
		}
		if (a.items) {
			for (var i in a.items) {
				var item=a.o.item(a.items[i]);
				var p=-1;
				if (s=="") p=true;
				else {
					var search=(item.search?item.search:item.text);
					if (search) p=search.toLowerCase().indexOf(s.toLowerCase());
				}
				if (p!=-1) a.itemsFiltered.push(item);
			}
		}
		if (a.data) {
			for (var i in a.data.data) {
				var item=a.data.data[i];
				a.itemsFiltered.push(a.o.item(item));
			}
		}
		if (a.o.sorted===true) {
			a.itemsFiltered.sort(function(a,b){
				if (a.caption && b.caption) {
					if (a.caption>b.caption) return 1;
					else if (a.caption<b.caption) return -1;
				}
				return 0;
			});
		}
		a.popupRefresh();
	};
	
	// seleccionar un item por índice
	a.select=function(i){
		a.selectItem(a.itemsFiltered[i]);
	};

	// seleccionar un item
	a.selectItem=function(item){
		a.item=item;
		if (gid(a.o.input)) {
			gid(a.o.input).placeholder=(item.text?item.text:item.caption);
			a.setCaption("");
		}
		a.setValue(item.value);
		a.popupClose();
		if (a.events && a.o.onclick) a.o.onclick(a, a.item);
	};
	
	// establecer etiqueta
	a.setCaption=function(newCaption){
		a.caption=newCaption;
		if (gid(a.o.input)) gidval(a.o.input, a.caption);
		a.changed=true;
	};

	// establecer valor
	a.setValue=function(newValue){
		if (a.value!=newValue) {
			a.value=newValue;
			a.changed=true;
		}
		if (gid(a.o.store)) gidval(a.o.store, a.value);
	};

	// actualizar items en desplegable
	a.popupRefresh=function(){
		if (!a.popup) return;
		var s=a.search();
		var h="";
		h+="<div class='xinputsearch_scroll'>";
		if (a.itemsFiltered.length>0) {
			for (var i in a.itemsFiltered) {
				var item=a.itemsFiltered[i];
				if (item.html) {
					h+="<div id='xinputsearch_"+a.num+"_pi_"+i+"' class='xinputsearch_item'>"+item.html+"</div>";
				} else if (item.item) {
					h+="<div id='xinputsearch_"+a.num+"_pi_"+i+"' class='xinputsearch_item xinputsearch_item_noitems'>"+item.item+"</div>";
				} else if (item.separator) {
					h+="<div id='xinputsearch_"+a.num+"_pi_"+i+"' class='xinputsearch_item'><hr /></div>";
				} else {
					h+="<div id='xinputsearch_"+a.num+"_pi_"+i+"' class='xinputsearch_item'>";
					h+="<a href='javascript:void(xinputsearchs["+a.num+"].select("+i+"))'>";
					if (item.caption && item.text) {
						h+=item.caption;
					} else if (item.caption) {
						var p=item.caption.toLowerCase().indexOf(s.toLowerCase());
						if (s.length && p!=-1) {
							h+=item.caption.substring(0,p)
								+"<span class='xinputsearch_item_search_string'>"+item.caption.substring(p,p+s.length)+"</span>"
								+item.caption.substring(p+s.length)
							;
						} else {
							h+=item.caption;
						}
					} else {
						h+=item.value;
					}
				}
				h+="</a>"
				h+="</div>";
			}
			if (a.popup.style.display!="block") a.popup.style.display="block";
		} else {
			//h+="<div class='xinputsearch_item_noitems'><span>Sin resultados</span></div>";
			if (a.popup.style.display!="none") a.popup.style.display="none";
		}
		h+="</div>";
		a.popup.innerHTML=h;
	};
	
	// abrir desplegable
	a.popupOpen=function(){
		if (!a.popup) {
			var d=document.createElement("div");
			style(d,{
				"position":"absolute",
				"left":getLeft(a.o.input)+"px",
				"top":(getTop(a.o.input)+getHeight(a.o.input))+"px"
			});
			d.className="xinputsearch_box";
			d.onmousedown=function(){
				cancelBlur=true;
				cancelClick=true;
				setTimeout(function(){
					cancelBlur=false;
					cancelClick=false;
				},1);
			};
			document.body.appendChild(d);
			a.popup=d;
		}
	};
	
	// cerrar popup
	a.popupClose=function(){
		if (a.popup) {
			a.popup.parentNode.removeChild(a.popup);
			a.popup=null;
		}
	};
	
	// busqueda AJAX temporizada
	a.ajaxSearchTimed=function(o){
		if (a.ajaxSearchTimer) clearTimeout(a.ajaxSearchTimer);
		a.ajaxSearchTimer=setTimeout(function(){
			delete a.ajaxSearchTimer;
			a.ajaxSearch(o)
		}, (a.o.searchtime?a.o.searchtime:150));
	};

	// busqueda AJAX
	a.ajaxSearch=function(o){
		if (!a.o.ajax || a.search_last==o.search) return;
		a.search_again=false;
		if (a.searching) {
			a.search_again=o;
			return;
		}
		a.search_last=o.search;
		a.searching=true;
		if (a.events && a.o.onajaxstart) a.o.onajaxstart(a);
		ajax(a.o.ajax,{
			"search":o.search,
			"page":0,
			"sort":(a.o.sort?a.o.sort:""),
			"visible":(a.o.visible?a.o.visible:0),
			"extra":(a.extra?a.extra:{}),
			"filters":(a.filters?a.filters:{})
		},function(){
			if (a.events && a.o.onajaxend) a.o.onajaxend(a);
			a.searching=false;
		},function(r){
			if (r.data.err) newerror(r.data.err);
			if (r.data.ok) {
				a.data=r.data;
				//alert(adump(a.data));
				//if (!a.xxx) a.xxx=1; else a.xxx++; gidset("lista", a.xxx+"/"+gidval(a.o.input)+nl2br(adump(a.data)));
				a.filterItems();
				if (a.search_again) {
					delete a.search_last;
					a.ajaxSearch(a.search_again);
				}
				if (a.events && a.o.onload && !a.loaded) {
					a.loaded=true;
					a.o.onload();
				}
			}
		});
	};

	// mostrar el desplegable
	a.display=function(){
		a.popupOpen();
		delete a.data;
		a.filterItems();
		a.ajaxSearchTimed({"search":gidval(a.o.input)});
	};

	// input: foco perdido
	a.getout=function(){
		a.popupClose();
		if (a.o.force) a.setCaption("");
		if (a.events && a.o.onchange && a.changed) a.o.onchange(a);
		if (a.events && a.o.onblur) a.o.onblur(a);
		a.changed=false;
	};
	
	// hay una entrada de texto
	if (gid(a.o.input)) {
		
		// establecer clase
		classAdd(a.o.input,"xinputsearch_input");

		// eventos
		gid(a.o.input).onmousedown=function(){
			cancelClick=true;
		};
		gid(a.o.input).onblur=function(){
			if (cancelBlur) { cancelBlur=false; return; }
			a.getout();
		};
		gid(a.o.input).onfocus=function(){
			cancelClick=true;
			a.display();
		};
		gid(a.o.input).onclick=function(){
			a.display();
		};
		gid(a.o.input).onkeyup=function(e){
			if (!e) var e=window.event;
			if (!a.o.force && gidval(a.o.input)) a.setValue("");
			/*if (e.keyCode==40) {
				gid("xinputsearch_"+a.num+"_pi_"+0).focus();
				return false;
			} else {*/
				//a.data=[];
				a.popupOpen();
				a.filterItems();
				a.ajaxSearchTimed({"search":gidval(a.o.input)});
			//}
		};
		document.body.addEventListener('click',function(e){
			if (cancelClick) { cancelClick=false; return; }
			a.getout();
		},true);
	}
	
	// primer filtraje de items
	a.filterItems();

	// por defecto
	if (isset(a.o.default)) {
		var item=a.o.item(a.o.default);
		a.selectItem(item);
	}

	// valor seleccionado
	if (isset(a.o.value)) {
		var found=false;
		if (a.itemsFiltered.length>0) {
			for (var i in a.itemsFiltered) if (a.itemsFiltered[i].value==a.o.value) {
				found=true;
				a.select(i);
				break;
			}
		}
	}
	
	// habilitar eventos
	a.events=true;
	
}
