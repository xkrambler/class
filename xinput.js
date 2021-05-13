/*

	xInput: selector de elementos con búsqueda local y ajax (compatible con xitemssearch)
	2016 Pablo Rodríguez Rey. Bajo licencia GPL v2.

	var xinput=new xInput({
		"id":"div",
		//"align":"left",
		//"valign":"bottom",
		//"editable":false,
		//"item":data.item,
		"ajax":"ajax.search",
		"options":[
			{"id":5,"name":"Name Surname","email":"undis@closed"},
			{"id":6,"name":"Name Surname2"},
			{"id":7,"name":"Name Surname3"}
		],
		"empty":"No options available.",
		"notfound":"No option found.",
		"render":function(a, o){
			return ""
				+"<span style='white-space:nowrap;'>"
					+(o.item
						?o.item.name+(o.item.email?" <span style='color:#888;'>("+o.item.email+")</span>":"")
						:"- No selection -"
					)
				+"</span>"
			;
		},
		"search":function(a, o){
			return o.item.name;
		},
		"del":true,
		"add":{
			"onclick":function(a){
				var n=prompt("Name?");
				if (n) a.add({"name":n});
			}
		},
		"onselect":function(a, o){
			alert(adump(o.item));
		}
	});

	Acciones AJAX:

	if ($ajax == "ajax.search")
		xItemsSearch::query(Array(
			"db"=>$db,
			"sql"=>"SELECT SQL_CALC_FOUND_ROWS name, email FROM users WHERE %sqlsearch%",
		));

*/
function xInput(o){
	var a=this;
	a.o=o;
	if (!a.o.searchInterval || a.o.searchInterval<1) a.o.searchInterval=300;
	if (!isset(a.o.actual)) a.o.actual=null;
	if (!isset(a.o.item)) a.o.item=null;
	a.isOpen=false;
	a.el={};

	// creación automatizada de nuevo elemento HTML
	a.newElement=function(element, o) {
		var e=document.createElement(element);
		if (o) {
			if (o.id) e.id=o.id;
			if (o.class) e.className=o.class;
			if (o.html) e.innerHTML=o.html;
			if (o.properties)
				for (var i in o.properties)
					if (o.properties[i]!==null)
						e[i]=o.properties[i];
			if (o.attributes)
				for (var i in o.attributes)
					if (o.attributes[i]!==null)
						e.setAttribute(i, o.attributes[i]);
			if (o.childs)
				for (var i in o.childs)
					if (o.childs[i]!==null)
						e.appendChild(o.childs[i]);
		}
		return e;
	};

	// establecer foco
	a.isFocused=function(state) {
		a.o.isFocused=state;
		if (a.o.input) {
			a.o.input(a, {
				"input":a.el.input,
				"event":"focus",
				"focus":state
			});
		} else {
			classEnable(a.o.id, "xinput_focus", state);
			classEnable(a.el.input, "widget_focus", state);
		}
		classEnable(a.el.popup, "widget_focus", state);
		if (!state) {
			if (a.o.isFocusedTimer) clearTimeout(a.o.isFocusedTimer);
			a.o.isFocusedTimer=setTimeout(function(){
				delete a.o.isFocusedTimer;
				if (!a.o.isFocused) a.close();
			}, 1);
		}
	};

	// abrir
	a.open=function(){
		if (!a.isOpen) {
			if (a.o.ajax || a.o.options) {
				a.search();
				a.isOpen=true;
				style(a.el.popup, {"minWidth":a.el.item.offsetWidth+"px"});
				classAdd(a.el.container, "xinput_visible");
				if (a.el.search_input) gidfocus(a.el.search_input);
				a.refresh();
			}
		}
	};

	// cerrar
	a.close=function(){
		if (a.isOpen) {
			a.isOpen=false;
			classDel(a.el.container, "xinput_visible");
		}
	};

	// destruir
	a.destroy=function(){
		a.close();
		a.el.container.parentNode.removeChild(a.el.container);
	};

	// añadir item
	a.add=function(item, select){
		if (!a.o.options) a.o.options=[];
		a.o.options.push(item);
		if (isset(select)) {
			a.o.item=item;
			a.refreshItem();
		}
		a.refresh();
	};

	// devolver/establecer opciones
	a.options=function(options){
		if (isset(options)) {
			a.o.options=options;
			a.refresh();
		}
		return a.o.options;
	};

	// devolver/establecer selección actual
	a.actual=function(actual) {
		if (isset(actual)) a.o.actual=actual;
		return a.o.actual;
	};

	// borrar selección actual
	a.clear=function(){
		a.selectOption(null);
	};

	// devolver/establecer item actual
	a.item=function(item){
		if (isset(item)) {
			a.o.item=item;
			a.refreshItem();
		}
		return a.o.item;
	};

	// obtener valor de un item
	a.itemValue=function(item){
		if (!a.o.editable) return false;
		return (item?(typeof(item)=="string"?item:item.value):"");
	};

	// limpiar acentos (para búsquedas)
	a.accentRemove=function(text){
		return text
			.toLowerCase()
			.replace(/á/g,"a")
			.replace(/é/g,"e")
			.replace(/í/g,"i")
			.replace(/ó/g,"o")
			.replace(/ú/g,"u")
			.replace(/Á/g,"a")
			.replace(/É/g,"e")
			.replace(/Í/g,"i")
			.replace(/Ó/g,"o")
			.replace(/Ú/g,"u")
		;
	};

	// seleccionar primera opción
	a.focusClosest=function(){
		return (a.o._valignTop?a.focusLast():a.focusFirst());
	};

	// seleccionar primera opción
	a.focusFirst=function(){
		for (var i in a.results) {
			a.results[i].div.focus();
			return true;
		}
	};

	// seleccionar última opción
	a.focusLast=function(){
		for (var i in a.results);
		a.results[i].div.focus();
		return true;
	};

	// enfocar la opción seleccionada
	a.focusItem=function(){
		if (a.o.editable) {
			var value=a.value();
			for (var i in a.results) {
				if (value==a.itemValue(a.results[i].row.item)) {
					a.results[i].div.focus();
					return true;
				}
			}
		} else {
			for (var i in a.results) {
				if (a.o.item==a.results[i].row.item) {
					a.results[i].div.focus();
					return true;
				}
			}
		}
		return false;
	};

	// seleccionar opción previa
	a.focusPrev=function(result_index){
		var n=result_index-1;
		var l=(a.results?array_count(a.results)-1:false);
		if (a.results[n]) a.results[n].div.focus();
		else if (a.o._valignTop && l!==false && a.results[l]) a.results[l].div.focus();
		else if (!a.focusSearch()) a.focus();
	};

	// seleccionar opción previa
	a.focusSearch=function(){
		var e=a.el.search_input;
		if (e) {
			e.focus();
			e.select();
			return true;
		}
		return false;
	};

	// seleccionar siguiente opción
	a.focusNext=function(result_index){
		var n=result_index+1;
		if (a.results[n]) a.results[n].div.focus();
		else if (!a.o._valignTop && a.results[0]) a.results[0].div.focus();
		else if (!a.focusSearch()) a.focus();
	};

	// hacer scroll hasta el item
	a.scrollItem=function(){
		if (a.o.editable) {
			var value=a.value();
			for (var i in a.results) {
				if (value==a.itemValue(a.results[i].row.item)) {
					var t=a.results[i].div.offsetTop-9;
					gid(a.el.results).scrollTop=(t>0?t:0);
					return true;
				}
			}
		} else {
			for (var i in a.results) {
				if (a.o.item==a.results[i].row.item) {
					var t=a.results[i].div.offsetTop-9;
					gid(a.el.results).scrollTop=(t>0?t:0);
					return true;
				}
			}
		}
		return false;
	};

	// seleccionar primera opción
	a.selectFirst=function(){
		if (a.o.options && a.found)
			for (var index in a.o.options)
				if (a.found[index]) {
					a.selectOption(index, false);
					return true;
				}
		if (a.o.data)
			for (var index in a.o.data) {
				a.selectOption(index, true);
				return true;
			}
		a.clear();
		return false;
	};

	// seleccionar opción
	a.selectOption=function(index, ajax){
		if (index===null) a.o.item=null;
		else a.o.item=(ajax?a.o.data[index]:a.o.options[index]);
		a.actual({"item":(isset(a.o.item)?a.o.item:null), "ajax":ajax, "index":index});
		a.refreshItem();
		if (a.o.onchange) a.o.onchange(a, null); // FIXME: only fire when item/value changes
		if (a.o.onselect) return a.o.onselect(a, a.actual());
	};

	// buscar (temporizado si no es primera petición)
	a.search=function(search){
		if (isset(search) && a.el.search_input) gidval(a.el.search_input, search);
		a.refresh();
		if (a.o.ajax) {
			if (a.searchTimer) clearTimeout(a.searchTimer);
			if (a.o.requested) {
				a.searchTimer=setTimeout(function(){ a.ajax(search); }, a.o.searchInterval);
			} else {
				a.o.requested=true;
				a.ajax(search);
			}
		}
	};

	// lanzar búsqueda AJAX
	a.ajax=function(search){
		if (a.searchTimer) {
			clearTimeout(a.searchTimer);
			a.searchTimer=false;
		}
		if (a.o.ajax) {
			a.o.requested=true;
			var v={
				"search":(a.el.search_input?gidval(a.el.search_input):(isset(search)?search:"")),
				"visible":(a.o.visible?a.o.visible:50)
			};
			if (a.o.ajaxvalues) v=a.o.ajaxvalues(a, v);
			ajax(a.o.ajax, v, function(){
			},function(r){
				if (r.data.err) newerror(r.data.err);
				if (r.data.ok) {
					// actualizar datos
					a.o.data=r.data.data;
					// actualizar lista
					a.refresh({"ajax":true});
				}
			});
		}
	};

	// actualizar item seleccionado
	a.refreshItem=function(){
		if (a.o.input) {
			a.o.input(a, {
				"input":a.el.input,
				"event":"item",
				"item":a.o.item
			});
		} else {
			if (a.o.editable) gidval(a.el.input, a.itemValue(a.o.item));
			else {
				var html=(a.o.render?a.o.render(a, {"item":(isset(a.o.item)?a.o.item:null)}):(a.o.item?a.o.item:""))
				gidset(a.el.input, (html?html:""));
			}
		}
	};

	// actualizar opciones
	a.refresh=function(o){
		var o=o||{};
		var alignRight=false;
		var valignTop=false;
		var maxWidth=(a.o.parent?getLeft(a.o.parent)+getWidth(a.o.parent):windowWidth()); if (maxWidth > windowWidth()) maxWidth=windowWidth();
		var maxHeight=(a.o.parent?getTop(a.o.parent)+getHeight(a.o.parent):windowHeight()); if (maxHeight > windowHeight()) maxHeight=windowHeight();
		switch (a.o.align) {
		case "left": break;
		case "right": alignRight=true; break;
		default: alignRight=(getLeft(a.el.container) > (maxWidth / 2));
		}
		switch (a.o.valign) {
		case "top": valignTop=true; break;
		case "bottom": break;
		default: valignTop=((getTop(a.el.container) - scrollTop()) > (maxHeight / 2));
		}
		// establecer alineaciones
		classEnable(a.el.container, "xinput_align_left",    !alignRight);
		classEnable(a.el.container, "xinput_align_right",    alignRight);
		classEnable(a.el.container, "xinput_valign_top",     valignTop);
		classEnable(a.el.container, "xinput_valign_bottom", !valignTop);
		// establecer dimensiones para optimizar la selección
		var inputWidth=getWidth(a.el.input_container);
		var inputHeight=getHeight(a.el.input_container);
		var resultsWidth=maxWidth-inputHeight;
		if (alignRight) {
			maxWidth-=maxWidth-getLeft(a.el.input_container)-getWidth(a.el.input_container);
		} else {
			resultsWidth+=-getLeft(a.el.popup_container);
		}
		var resultsHeight=-inputHeight-(a.el.search_input?getHeight(a.el.search_input):0);
		if (valignTop) {
			var bigger=(!a.o.parent || scrollTop() > getTop(a.o.parent)?scrollTop():getTop(a.o.parent));
			resultsHeight+=(getTop(a.el.container)-bigger);
		} else {
			resultsHeight+=maxHeight-(getTop(a.el.popup_container)-scrollTop());
		}
		a.el.results.style.maxWidth =parseInt(resultsWidth  < inputWidth ?inputWidth :resultsWidth )+"px";
		a.el.results.style.maxHeight=parseInt(resultsHeight < inputHeight?inputHeight:resultsHeight)+"px";
		// guardar alineación vertical
		a.o._valignTop=valignTop;
		// guardar elemento enfocado
		a.activeElement=(document.activeElement.className.split(" ").indexOf("xinput_result")!=-1
			?{
				"ajax":(document.activeElement.getAttribute("data-ajax")?true:false),
				"index":parseInt(document.activeElement.getAttribute("data-index"))
			}
			:null
		);
		// iniciar búsqueda
		var search=(a.el.search_input?gidval(a.el.search_input):(a.o.search && a.o.editable?a.el.input.value:""));
		if (search) search=a.accentRemove(search).split(" ");
		// limpiar resultados previos
		gidset(a.el.results, "");
		// scroll arriba (deshabilitado)
		//gid(a.el.results).scrollTop=0;
		a.found=[];
		a.results=[];
		// renderizador
		var resultRender=function(ajax, index, result){
			var row={"item":result, "index":index, "ajax":ajax};
			var result_search=(a.o.search?(a.o.search===true?"":a.o.search(a, row)):"");
			var found=(ajax || result_search!==null?true:false);
			if (a.o.filter && a.o.filter(a, row) || !a.o.filter) {
				if (!ajax && search && result_search) {
					result_search=a.accentRemove(result_search);
					for (var i in search) {
						if (result_search.search(new RegExp(search[i], "i"))==-1) {
							found=false;
							break;
						}
					}
				}
				if (!ajax) a.found[index]=found;
				var html=(a.o.render?a.o.render(a, row):row.item);
				if (found && html) {
					var div_result=a.newElement("div",{
						"class":"xinput_result",
						"html":html,
						"attributes":{
							"data-result":a.results.length,
							"data-ajax":(ajax?"1":""),
							"data-index":index,
							"tabindex":"-1"
						},
						"properties":{
							"onfocus":function(e){
								a.isFocused(true);
							},
							"onblur":function(e){
								a.isFocused(false);
							},
							"onclick":function(e){
								e.preventDefault();
								a.close();
								if (a.selectOption(this.getAttribute("data-index"), (this.getAttribute("data-ajax")?true:false))!==false) {
									a.focus();
									a.select();
								}
								return false;
							},
							"onkeypress":function(e){
								if (e.keyCode==27) {
									a.close();
									a.focus();
								}
							},
							"onkeydown":function(e){
								if (e.keyCode==38) {
									e.preventDefault();
									a.focusPrev(parseInt(this.getAttribute("data-result")));
								}
								if (e.keyCode==40) {
									e.preventDefault();
									a.focusNext(parseInt(this.getAttribute("data-result")));
								}
								if (e.keyCode==9 || e.keyCode==13) {
									if (e.keyCode==13) e.preventDefault();
									a.close();
									if (a.selectOption(this.getAttribute("data-index"), (this.getAttribute("data-ajax")?true:false))!==false) {
										a.focus();
										a.select();
									}
								}
								if (e.keyCode==27) {
									a.close();
									a.focus();
								}
							}
						}
					});
					a.el.results.appendChild(div_result);
					a.results.push({"row":row,"div":div_result});
					if (a.activeElement && a.activeElement.ajax==ajax && a.activeElement.index==index)
						div_result.focus();
				}
			}
		};
		// renderizar opciones y datos
		if (array_count(a.o.options) || array_count(a.o.data)) {
			if (a.o.options)
				for (var index in a.o.options)
					resultRender(false, index, a.o.options[index]);
			if (a.o.data)
				for (var index in a.o.data)
					resultRender(true, index, a.o.data[index]);
		} else {
			// si tengo mensaje de vacío, colocar
			if (a.o.empty)
				gidset(a.el.results, a.o.empty);
		}
		// si no hay resultados, cancelar apertura
		//if (a.results.length<1) a.close();
		//if (scrollTop) a.el.results.scrollTop=scrollTop;
	};

	// establecer foco
	a.focus=function(){
		if (a.el.input.focus) a.el.input.focus();
	};

	// seleccionar (si editable)
	a.select=function(){
		if (a.o.editable && a.el.input.select) a.el.input.select();
	};

	// devolver/establecer valor
	a.value=function(value){
		if (a.o.editable) {
			if (isset(value)) a.el.input.value=value;
			return a.el.input.value;
		} else {
			if (isset(value)) {
				if (value === null) {
					a.clear();
					return true;
				} else {
					if (a.o.options)
						for (var index in a.o.options)
							if (index==value) {
								a.selectOption(index, false);
								return true;
							}
					if (a.o.data)
						for (var index in a.o.data)
							if (index==value) {
								a.selectOption(index, true);
								return true;
							}
					return false;
				}
			}
			var actual=a.actual();
			return (actual?actual.index:null);
		}
	};

	// inicializar
	a.init=function(){

		var input_events={
			"onfocus":function(e){
				a.isFocused(true);
			},
			"onblur":function(e){
				a.isFocused(false);
			},
			"onclick":function(e){
				a.open();
				a.scrollItem();
			},
			"onkeydown":function(e){
				if (e.keyCode==27) {
					e.preventDefault();
					a.close();
					a.focus();
				}
				if (e.keyCode==38) {
					e.preventDefault();
					if (a.o._valignTop) {
						a.open();
						if (!a.focusSearch() && !a.focusItem()) a.focusLast();
					} else {
						a.close();
						a.focus();
					}
				}
				if (e.keyCode==40) {
					e.preventDefault();
					if (a.o._valignTop) {
						a.close();
						a.focus();
					} else {
						a.open();
						if (!a.focusSearch() && !a.focusItem()) a.focusFirst();
					}
				}
				if (e.keyCode==9) {
					a.close();
				}
				if (e.keyCode==13)
					if (a.o.onenter)
						a.o.onenter();
			}
		};

		if (a.o.input) {
			a.el.input=a.o.input(a, {
				"event":"init",
				"input_events":input_events,
			});
		} else {
			if (a.o.editable) {
				a.el.input=a.newElement("input", {
					"class":"widget xinput_input"+(a.o.class && a.o.class.input?" "+a.o.class.input:""),
					"properties":array_merge({
						"type":"text",
						"placeholder":(a.o.placeholder?a.o.placeholder:""),
						"value":(a.o.item?a.o.item:""),
						"onchange":function(e){
							if (!e) var e=window.event;
							if (a.o.onchange) a.o.onchange(a, e);
						},
						"oninput":function(e){
							if (!e) var e=window.event;
							if (a.o.oninput) a.o.oninput(a, e);
							if (a.o.search) a.search(this.value);
						}
					}, input_events)
				});
			} else {
				a.el.input=a.newElement("div",{
					"class":"widget xinput_div"+(a.o.class && a.o.class.div?" "+a.o.class.div:""),
					"attributes":{
						"tabindex":(a.o.tabindex?a.o.tabindex:"0"),
					},
					"properties":input_events
				});
			}
		}

		if (a.o.del) {
			a.el.action_del=a.newElement("div",{
				"class":"noselect xinput_action"+(a.o.class && a.o.class.action?" "+a.o.class.action:""),
				"html":"<span class='fa fa-trash'></span>",
				"properties":{
					"onclick":function(e){
						a.close();
						a.clear();
						a.focus();
					}
				}
			});
		}

		if (a.o.add) {
			a.el.action_add=a.newElement("div",{
				"class":"noselect xinput_action"+(a.o.class && a.o.class.action?" "+a.o.class.action:""),
				"html":"<span class='fa fa-plus'></span>",
				"properties":{
					"onclick":function(e){
						e.stopPropagation();
						a.close();
						if (a.o.add.onclick) a.o.add.onclick(a);
					}
				}
			});
		}

		if (a.o.edit) {
			a.el.action_edit=a.newElement("div",{
				"class":"noselect xinput_action"+(a.o.class && a.o.class.action?" "+a.o.class.action:""),
				"html":"<span class='fa fa-edit'></span>",
				"properties":{
					"onclick":function(e){
						e.stopPropagation();
						a.close();
						if (a.o.edit.onclick) a.o.edit.onclick(a);
					}
				}
			});
		}

		a.el.input_container=a.newElement("div",{
			"class":"xinput_input_container"+(a.o.class && a.o.class.container?" "+a.o.class.container:""),
			"childs":[
				a.el.input
			]
		});

		// elemento presentado
		a.el.item=a.newElement("div",{
			"class":"xinput_item"+(a.o.class && a.o.class.item?" "+a.o.class.item:""),
			"childs":[
				a.el.input_container,
				(a.o.del?a.el.action_del:null),
				(a.o.add?a.el.action_add:null),
				(a.o.edit?a.el.action_edit:null)
			]
		});

		// presentar filtro de búsqueda, si no editable
		if (a.o.search && !a.o.editable) {

			// input de búsqueda
			a.el.search_input=a.newElement("input",{
				"class":"widget xinput_search_input"+(a.o.class && a.o.class.search?" "+a.o.class.search:""),
				"properties":{
					"type":"text",
					"value":"",
					"placeholder":"buscar...",
					"onfocus":function(e){
						a.isFocused(true);
					},
					"onblur":function(e){
						a.isFocused(false);
					},
					"ondblclick":function(){
						this.value="";
						a.search();
					},
					"onkeydown":function(e){
						if (e.keyCode==9) a.close();
						if (e.keyCode==38) {
							e.preventDefault();
							e.stopPropagation();
							if (a.o._valignTop) {
								a.focusClosest();
							} else {
								a.close();
								a.focus();
							}
						}
						if (e.keyCode==40) {
							e.preventDefault();
							e.stopPropagation();
							if (a.o._valignTop) {
								a.close();
								a.focus();
							} else {
								a.focusClosest();
							}
						}
						if (e.keyCode==27) {
							a.close();
							a.focus();
						}
					},
					"onkeypress":function(e){
						if (e.keyCode==13) {
							a.ajax();
							/*
							a.selectFirst();
							a.close();
							a.focus();
							*/
						}
					},
					"onkeyup":function(e){
						if (e.keyCode!=13 && e.keyCode!=38 && e.keyCode!=40) a.search();
					}
				}
			});

			var elements=[];

			// filtros pre-búsqueda
			if (a.o.search_filters && a.o.search_filters.pre) {
				var items=a.o.search_filters.pre(a, o);
				for (var i in items) elements.push(items[i]);
			}

			// filtro de búsqueda
			elements.push(a.el.search_input);

			// filtros post-búsqueda
			if (a.o.search_filters && a.o.search_filters.post) {
				var items=a.o.search_filters.post(a, o);
				for (var i in items) elements.push(items[i]);
			}

			// añadir elementos de búsqueda
			a.el.search=a.newElement("div",{
				"class":"xinput_search",
				"childs":elements
			});

		}

		// resultados
		a.el.results=a.newElement("div",{
			"class":"noselect xinput_results"
		});

		// popup
		a.el.popup=a.newElement("div",{
			"class":"xinput_popup"+(a.o.class && a.o.class.popup?" "+a.o.class.popup:""),
			"childs":[
				(a.el.search?a.el.search:null),
				a.el.results
			]
		});

		// popup container
		a.el.popup_container=a.newElement("div",{
			"class":"xinput_popup_container",
			"attributes":{
				"tabindex":"-1"
			},
			"properties":{
				"onfocus":function(e){
					a.isFocused(true);
				},
				"onblur":function(e){
					a.isFocused(false);
				}
			},
			"childs":[
				a.el.popup
			]
		});

		// contenedor de xinput
		a.el.container=a.newElement("div",{
			"class":"xinput_container",
			"attributes":{
				"tabindex":"-1",
			},
			"childs":[
				a.el.item,
				a.el.popup_container
			]
		});

		// renderizar contenedor
		gidset(a.o.id, "");
		gid(a.o.id).appendChild(a.el.container);

		// refrescar
		a.refreshItem();
		a.refresh();

		// si tiene un índice, seleccionar
		if (a.o.index && a.o.options && a.o.index < a.o.options.length)
			a.selectOption(a.o.index, false);

		// si tiene el autofoco, acciones por defecto
		if (a.o.focus) {
			a.focus();
			if (a.o.item) a.select();
			else a.open();
		}

		// si tiene valor establecido, fijar
		if (isset(a.o.value)) a.value(a.o.value);

		// inicializar filtros de búsqueda
		if (a.o.search_filters && a.o.search_filters.init) {
			a.el.search_filters=a.o.search_filters.init(a, o);
		}

	};

	// inicializar
	a.init();

}
