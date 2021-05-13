/*
	Ejemplos de uso:
	
	var xcal1,xcal2,xcal3,xcal4;
	
	init(function(){
		
		xcal1=new xcalendar({
			"input":"fecha_inicio",
			"trigger":"fecha_inicio",
			"min":new Date(2011,3,12),
			"max":"xcal2",
			"onselect":function(fecha){
				//xcal2.min=fecha;
			}
		});

		xcal2=new xcalendar({
			"input":"fecha_fin",
			"trigger":"fecha_fin",
			"min":"xcal1"
		});
			
		xcal3=new xcalendar({
			"div":"calendario1",
			"input":"fecha_inicio2",
			"min":new Date(2011,3,12),
			"max":"xcal4",
			"onselect":function(fecha){
				//xcal2.min=fecha;
			}
		});

		xcal4=new xcalendar({
			"div":"calendario2",
			"input":"fecha_fin2",
			"min":"xcal3"
		});
			
		gid("fecha_inicio").focus();
		
	});
*/
var xcalendars=[];
var xcalendars_active=null;
var xcalendars_cancelclick=false;
function xcalendar(o) {
	this.fecha=o.fecha;
	if (!this.fecha) this.fecha=new Date();

	var a=xcalendars.length;
	xcalendars_active=a;
	xcalendars[a]=this;
	var xcal=xcalendars[a];
	
	this.popopen=false;
	this.seleccionada=null;
	this.seleccionados={};
	
	this.o=o;
	this.id=o.id;
	this.div=gid(o.div);
	this.input=gid(o.input);
	this.trigger=gid(o.trigger);
	this.min=o.min;
	this.max=o.max;
	this.onpop=o.onpop;
	this.onselect=o.onselect;
	this.autoclose=(typeof(o.autoclose)!="undefined"?o.autoclose:true);
	
	this.loaded=false;

	this.y=this.fecha.getFullYear();
	this.m=this.fecha.getMonth()+1;
	this.d=this.fecha.getDate();
	var week_abrev="LMXJVSD";
	var months=[
		"Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio",
		"Agosto","Septiembre","Octubre","Noviembre","Diciembre"
	];

	this.mesAnterior=function(){
		this.m--; if (this.m<1) { this.m=12; this.y--; }
		this.refresh();
	};

	this.mesSiguiente=function(){
		this.m++; if (this.m>12) { this.m=1; this.y++; }
		this.refresh();
	};
	
	this.setAnyo=function(ny){
		if (ny>=1900 && ny<3000)
			this.y=ny;
		this.refresh();
	};

	this.spDate=function(fecha){
		if (!fecha) var fecha=this.seleccionada;
		try {
			return (fecha.getDate()<10?"0":"")+fecha.getDate()+"/"+(fecha.getMonth()<9?"0":"")+(1+fecha.getMonth())+"/"+fecha.getFullYear();
		} catch(e) {
			return null;
		}
	};

	this.sqlDate=function(fecha){
		if (!fecha) var fecha=this.seleccionada;
		try {
			return fecha.getFullYear()+"-"+(fecha.getMonth()<9?"0":"")+(1+fecha.getMonth())+"-"+(fecha.getDate()<10?"0":"")+fecha.getDate();
		} catch(e) {
			return null;
		}
	};
	
	this.getFecha=function(){
		if (!this.seleccionada) return this.seleccionada;
		this.seleccionada.setHours(0);
		this.seleccionada.setMinutes(0);
		this.seleccionada.setSeconds(0);
		return this.seleccionada;
	};

	this.setFecha=function(fecha){
		this.seleccionar(fecha);
	}
		
	this._efectiveDate=function(minmax){
		if (minmax===null) return null;
		if (typeof(minmax)=="object" && minmax.getDate) return minmax;
		if (typeof(minmax)=="string") {
			eval("var o="+minmax+";");
			if (o && o.getFecha()) {
				return o.getFecha();
			}
			return null;
		}
		return minmax;
	};
		
	// actualizar
	this.refresh=function(){
		if (!this.div) return;
		
		var month_day=((new Date(this.y-1900,this.m-1,1)).getDay()+1)%7;
		var month_days=(32-new Date(this.y-1900,this.m-1,32).getDate());
		
		var i;
		var h="";
		h+="<table class='xcalendar"+(this.trigger?" xcalendar_popup":"")+"'>";
		h+="<thead>";
		h+="<tr class='xcalendar_title'>";
		h+="<td><a href='javascript:void(0)' onClick='javascript:xcalendars["+a+"].mesAnterior();'>&#9668;</a></td>";
		h+="<td colspan='5'>"
			+months[this.m-1]
			+" <input type='text' value='"+this.y+"' style='text-align:center;' size='3'"
				+" onFocus='javascript:this.select()'"
				+" onChange='javascript:xcalendars["+a+"].setAnyo(this.value)'"
				+" onKeyPress='javascript:if(event.keyCode==13)xcalendars["+a+"].setAnyo(this.value)'"
			+" />"
			+"</td>";
		h+="<td><a href='javascript:void(0)' onClick='javascript:xcalendars["+a+"].mesSiguiente();'>&#9658;</a></td>";
		h+="</tr>";
		h+="<tr>";
		for (i=0;i<7;i++) {
			h+="<th>"+week_abrev.substring(i,i+1)+"</th>";
		}
		h+="</tr>\n";
		h+="</thead>\n";
		h+="<tbody>\n";
		h+="<tr>";
		for (i=0;i<month_day;i++) {
			h+="<td></td>";
		}
		
		var today=new Date();
		for (i=0;i<month_days;i++) {
			var dia=new Date(this.y,this.m-1,i+1);
			var isToday=(this.sqlDate(dia)==this.sqlDate(today));
			if (dia)
				h+="<td>"
					+(this._efectiveDate(this.min) && dia<this._efectiveDate(this.min)
						|| this._efectiveDate(this.max) && dia>this._efectiveDate(this.max)
						?"<span class='"
							+(isToday?"xcalendar_today ":"")
							+"'>"+(i+1)+"</span>"
						:"<a"
						+" class='"
							+(isToday?"xcalendar_today ":"")
							+(this.seleccionados[this.sqlDate(dia)]?"xcalendar_selected":"")
						+"'"
						+" title='"+(isToday?"Hoy":"")+"'"
						+" href='javascript:void(0)'"
						+" onClick='javascript:xcalendars["+a+"]._seleccionar_dia("+(i+1)+");'"
						+">"+(i+1)+"</a>"
					)
					+"</td>"
			if (!((i+month_day+1)%7)) {
				h+="</tr>\n";
				if (i+1!=month_days) {
					h+="<tr>";
				}
			}
		}
		for (i=((i+month_day)%7);i<7;i++) {
			h+="<td></td>";
		}
		h+="</tr>\n";
		h+="</tbody>\n";
		h+="</table>";
		this.div.innerHTML=h;
	};
	
	// actualizar otros divs relaccionados
	this._updateOthers=function(){
		if (typeof(this.min)=="string") {
			eval("var o="+this.min+";");
			if (o && o.refresh) o.refresh();
		}
		if (typeof(this.max)=="string") {
			eval("var o="+this.max+";");
			if (o && o.refresh) o.refresh();
		}
		this.refresh();
	};
	
	// seleccionar día
	this._seleccionar_dia=function(dia){
		this._seleccionar(new Date(this.y,this.m-1,dia));
	}
	
	// seleccionar fecha
	this._seleccionar=function(fecha){
		this.seleccionar(fecha);
		if (this.input)
			this.input.value=this.spDate(this.seleccionada);
		if (this.loaded)
			if (this.onselect)
				this.onselect(fecha);
		if (this.autoclose)
			this.close();
	};

	// seleccionar
	this.seleccionar=function(fecha){
		this.seleccionada=fecha;
		this.seleccionados={};
		if (fecha) {
			this.set(fecha);
			this.seleccionados[this.sqlDate(this.seleccionada)]=this.seleccionada;
		}
		this.refresh();
		this._updateOthers();
	};
	
	// cerrar popup
	this.close=function(){
		if (xcal.popopen) {
			xcal.popopen=false;
			if (xcal.trigger && xcal.div) {
				xcal.div.parentNode.removeChild(xcal.div);
				delete xcal.div;
			}
		}
	};
	
	// establecer fecha
	this.set=function(fecha){
		this.d=fecha.getDate();
		this.m=fecha.getMonth()+1;
		this.y=fecha.getFullYear();
		this.sql=this.y+"-"+(this.m<10?"0":"")+this.m+"-"+(this.d<10?"0":"")+this.d;
		return fecha;
	}

	// limpiar fecha
	this.clear=function(){
		var fecha=new Date();
		this.d=fecha.getDate();
		this.m=fecha.getMonth()+1;
		this.y=fecha.getFullYear();
		this.sql="";
		this.seleccionada=null;
		if (this.input)
			this.input.value="";
	}
	
	// convertir valor en fecha
	this.dateFromInput=function(v){
		if (v) {
			var x=v.split("/");
			if (x
				&& parseInt(x[0],10)>=1 && parseInt(x[0],10)<=31
				&& parseInt(x[1],10)>=1 && parseInt(x[1],10)<=12
				&& parseInt(x[2],10)>=1900 && parseInt(x[2],10)<=2999
			) {
				return this.set(new Date(parseInt(x[2],10),parseInt(x[1],10)-1,parseInt(x[0],10)));
			}
		} else {
			return null;
		}
		this.refresh();
	}
	
	// actualizar desde valor de input
	this.fromInput=function(){
		if (xcal.input) {
			var nueva=this.dateFromInput(this.input.value);
			if (nueva) {
				if (
					(
						(!this._efectiveDate(this.min) || this.min && nueva>=this._efectiveDate(this.min))
						&& (!this._efectiveDate(this.max) || this.max && nueva<=this._efectiveDate(this.max))
					)
					// si todavía no han sido inicializados los enlaces, se selecciona igual
					|| (this.min && !this._efectiveDate(this.min))
					|| (this.max && !this._efectiveDate(this.max))
				) {
					this.seleccionar(nueva);
				}
			} else {
				this.seleccionar(null);
			}
		}
	};
	
	this.pop=function(where){
		if (xcal.o.onpop)
			xcal.seleccionar(xcal.o.onpop());
		if (!xcal.div) {
			// corrección de posicionamiento:
			// si un objeto pariente tiene posición fija, el elemento popup la hereda
			var o=gid(where);
			var isfixed=false;
			do {
				if (o.style.position=="fixed") isfixed=true;
			} while (o=o.offsetParent);
			// popup
			xcal.div=document.createElement("div");
			style(xcal.div,{
				"display":"block",
				"position":(isfixed?"fixed":"absolute"),
				"top":(getTop(where)+getHeight(where))+"px",
				"left":getLeft(where)+"px",
				"zIndex":"990" // mismo orden que newalert
			});
			xcal.div.onmousedown=function(){
				xcalendars_cancelclick=true;
			}
			document.body.appendChild(xcal.div);
			xcal.popopen=true;
			xcal.refresh();
		}
	};
	
	// activar triggers
	if (this.input) {
	
		this.input.onfocus=this.input.onclick=function(){
			
			var xcal=xcalendars[xcalendars_active];
			xcal.close();
			
			xcalendars_active=a;
			var xcal=xcalendars[xcalendars_active];
			
			if (xcal.input) {
				xcal.fromInput();
				xcal.input.select();
			}
			
			if (xcal.trigger)
				xcal.pop(xcal.input);
			
		};
		
	}
	
	if (xcal.trigger) {
		this.trigger.onclick=function(e){
			xcal.pop(xcal.input?xcal.input:xcal.trigger);
		};
		this.trigger.onkeydown=function(e){
			if (!e) var e=window.event;
			if (e.keyCode==9)
				xcal.close();
		};
		
	}
	
	// inicialización de eventos por primera vez
	if (xcalendars.length==1) {
		if (this.trigger) {
			mouseup(function(){
				var xcal=xcalendars[xcalendars_active];
				if (!xcalendars_cancelclick) {
					if (xcal.div && xcal.trigger)
						xcal.close();
					for (var xcal in xcalendars) {
						if (xcalendars[xcal].popopen)
							xcalendars[xcal].close();
					}
				}
				xcalendars_cancelclick=false;
			});
		}
	}
	
	// filtrar inputs
	if (this.input) {
		
		this.input.onkeydown=function(e) {
			if (!e) var e=window.event;
			var c=e.keyCode;
			if (c>=35 && c<=39) return true;
			switch (c) {
			case 32:
			//case 190:
				//if (with_time)
					//return true;
			case 67: // C
			case 86: // V
			case 88: // X
				if (xcal.keyControl)
					return true;
				return false;
			case 16: xcal.keyShift=true;
			case 17: xcal.keyControl=true;
			case 46:
			case 116:
			case 8:
			case 13:
			case 9:
				xcal.close();
			case 15:
			case 55:
				return true;
			default:
				//alert(c);
			}
			//if (selectedText()=="" && i.value.length==10) return false;
			if (!xcal.keyShift && c>=48 && c<=57) return true;
			return false;
		}

		this.input.onkeypress=function(e) {
			if (!e) var e=window.event;
			var c=e.keyCode;
		}

		this.input.onkeyup=function(e) {
			if (!e) var e=window.event;
			var c=e.keyCode;
			//var l=(with_time?16:10);
			switch (c) {
			case 16: xcal.keyShift=false; break;
			case 17: xcal.keyControl=false; break;
			}
			//if (xcal.input.value.length>l) xcal.input.value=xcal.input.value.substring(0,l);
			xcal.fromInput();
		}

		this.input.onchange=function(){
			xcal.fromInput();
		};
		
	}
	
	// actualizar fecha desde input
	this.fromInput();
	
	// si hay DIV indicado, escribir el calendario en dicho DIV
	this.refresh();
	if (o.fecha) this.seleccionar(o.fecha);
	
	// actualizar otros divs asociados
	setTimeout(function(){
		xcal._updateOthers();
		xcal.loaded=true;
	},1);

}
