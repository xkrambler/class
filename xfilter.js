/*

	xfilter 0.1alpha, convert spans into clickable filters
	
	Public data:
		.list // field list
		.o    // options
	
	Methods:
		.value(fname,value); // get/set field value
		.del(fname);         // del field value
		.refresh();          // refresh
	
	Example:
		var filter=new xfilter({
			"prefix":"filter_",
			"list":{
				"factura":{"type":"select","onselect":function(v,fname,f,tab){
					alert(v);
				}},
				"estado":{
					"type":"options",
					"options":{
						"SIN_ENVIAR"           :{"caption":"Sin enviar","ico":"images/ico16/user.png"},
						"ENVIADOS_SIN_INSTALAR":{"caption":"Enviados pendientes de instalar"},
						"ENVIADOS_INSTALADOS"  :{"caption":"Enviados e Instalados"},
					},
					"onselect":function(v,fname,f,tab){
						alert(v);
					}
				}
				//,"void":{"type":"void"}
			}
		});

*/
function xfilter(o) {
	
	var a=this;
	a.list=o.list;
	a.o=o;
	
	// actualizar
	a.refresh=function(){
		for (var i in a.list) {
			var f=a.list[i];
			var tab=gid(a.o.prefix+i);
			if (tab) {
				
				var valueSet=(typeof(f.value)!="undefined");
				var captionSet=(typeof(f.caption)!="undefined");
				var defaultCaption=(f.default?f.default:"Seleccionar");
				
				// vaciar contenido
				tab.innerHTML="";

				var e=document.createElement("span");
				e.className="xfilter_tab xfilter_tab_"+f.type+" "+(valueSet?"xfilter_tab_set":"");
				e.setAttribute("data-id",i);

				// crear elemento
				switch (f.type) {
				case "select":
					e.innerHTML=(valueSet?(captionSet?f.caption:f.value):defaultCaption);
					e.onclick=function(){
						var i=this.getAttribute("data-id");
						var f=a.list[i];
						var tab=gid(a.o.prefix+i);
						if (f.onselect)
							f.onselect(f.value,i,f,tab,a);
					};
					tab.appendChild(e);
					break;

				case "options":
					e.innerHTML=(valueSet?(captionSet?f.caption:f.value):defaultCaption);
					e.onclick=function(){
						var i=this.getAttribute("data-id");
						var f=a.list[i];
						var tab=gid(a.o.prefix+i);
						if (f.value && f.bistate) { // bistate: permite tener una selección o nada
							delete f.value;
							a.refresh();
							if (f.onselect)
								f.onselect(f.value,i,f,tab,a);
						} else {
							if (!this.divo) {
								this.divo=new xdivOverlay({"attach":tab});
								var divo=this.divo;
								divo.show();
								var popupbox_div=document.createElement("div");
								popupbox_div.className="popupbox xfilter_options";
								for (var j in f.options) {
									var option=f.options[j];
									var popupbox_a=document.createElement("a");
									if (isset(option.ico)) {
										popupbox_a.className="aicon";
										if (option.ico) popupbox_a.style.backgroundImage="url("+option.ico+")";
									}
									popupbox_a.href="javascript:void(0)";
									popupbox_a.onclick=function(j){ return function(e){
										var option=f.options[j];
										f.caption=option.caption;
										f.value=j;
										divo.hide();
										a.refresh();
										if (f.onselect)
											f.onselect(f.value,i,f,tab,a);
									}};
									popupbox_a.onclick=popupbox_a.onclick(j);
									popupbox_a.innerHTML=option.caption;
									popupbox_div.appendChild(popupbox_a);
								}
								divo.div.innerHTML="";
								divo.div.appendChild(popupbox_div);
							} else {
								this.divo.show();
							}
						}
					};
					tab.appendChild(e);
					break;
				
				case "boolean":
					e.innerHTML=(valueSet?(f.value?"Si":"No"):"Todos");
					e.onclick=function(){
						var i=this.getAttribute("data-id");
						var f=a.list[i];
						var tab=gid(a.o.prefix+i);
						if (typeof(f.value)=="undefined") f.value=1;
						else if (f.value==1) f.value=0;
						else delete f.value;
						f.onselect(f.value,i,f,tab,a);
						a.refresh();
					};
					tab.appendChild(e);
					break;
				
				case "void":
					break;
					
				}
			
			}
		}
	};
	
	// establecer/devolver valor de un campo
	a.value=function(field,value,caption){
		if (typeof(value)=="undefined") return a.list[field].value;
		if (value===null) {
			delete a.list[field].value;
		} else {
			a.list[field].value=value;
			if (typeof(caption)!="undefined")
				a.list[field].caption=caption;
		}
		a.refresh();
	};
	
	// borrar valor de un campo
	a.del=function(field){
		a.value(field,null);
	};
	
	// inicializar
	a.init=function(){
		a.refresh();
	};
	a.init();
	
}
