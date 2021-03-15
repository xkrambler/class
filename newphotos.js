var newphotos_list;
var newphotos_bg;
var newphotos_div;
var newphotos_img;
var newphotos_imgdiv;
var newphotos_navup;
var newphotos_navdown;
var newphotos_anipos;
var newphotos_lastimage;
var newphotos_lastselected;
var newphotos_caption;
var newphotos_actual;
var newphotos_newphotos=[];
var newphotos_conf={
	"opacity":0.5, // 50% opacidad
	"margin":0.1 // 10% margen
};

if (typeof(style)=="undefined") {
	alert("newphotos.js: Falta librería de funciones requerida: common.js");
}

function newphotos(photos) {
	this.id=newphotos_newphotos.length;
	newphotos_newphotos[this.id]=this;
	this.shownames=false;
	this.photos=photos;
	this.photoscache=[];
	this.loaded=[];
	this.cached=true;
	this.cancelclick=false;
	this.opacity=newphotos_conf.opacity;
	this.margin=newphotos_conf.margin;
	this.setup=function() { newphotos_actual=this; }
	this.cache=function(enabled) { this.cached=enabled; }
	this.names=function(visible) { this.shownames=visible; }
	this.select=function(i) { newphotos_select(i); }
	this.show=function(i){
		this.setup();
		newphotos_show(i);
		if (this.cached) {
			for (var i in this.photos) {
				this.photoscache[i]=new Image();
				this.photoscache[i].src=this.geturl(i);
				eval("this.photoscache["+i+"].onload=function(){ newphotos_loaded("+i+"); };");
			}
		}
	}
	this.close=function() { newphotos_close(); }
	this.get=function(i) { return this.photos[i]; }
	this.set=function(i,photo) { this.photos[i]=photo; }
	this.geturl=function(i) {
		var img=this.get(i)[0];
		return img+(this.cached?"":(img.indexOf("?")!=-1?"&":"?")+"nocache="+parseInt(Math.random()*2000000000));
	}
	this.getthumb=function(i) {
		var img=this.get(i)[3];
		if (!img) return this.geturl(i);
		return img+(this.cached?"":(img.indexOf("?")!=-1?"&":"?")+"nocache="+parseInt(Math.random()*2000000000));
	}
	this.html=function(s1,s2,sc,s4) {
		this.setup();
		var s="";
		for (var i in this.photos) {
			s+=(s?s1:(s2?s1:""))+"<a href='"+this.geturl(i)+"' class='newphotos_photo' onClick='javascript:newphotos_newphotos["+this.id+"].show("+i+");return(false);'><img src='"+this.getthumb(i)+"' /></a>"+(s2?s2:"");
			if ((i%sc==sc-1) && (i<this.photos.length-1)) s+=s4;
		}
		return s;
	}
	this.prev=function() {
		var n=this.photos.length-1;
		if (newphotos_lastselected>0) n=newphotos_lastselected-1;
		newphotos_select(n);
		this.locate();
	}
	this.next=function() {
		var n=0;
		if (newphotos_lastselected<this.photos.length-1) n=newphotos_lastselected+1;
		newphotos_select(n);
		this.locate();
	}
	this.locate=function() {
		var s=getTop("newphoto_thumb"+newphotos_lastselected)-getHeight(newphotos_list)/2;
		newphotos_list.scrollTop=(s>0?s:0);
	}
	this.description=function(descriptions) {
		for (var i in descriptions)
			for (var j in this.photos)
				if (this.photos[j][0]==i)
					this.photos[j][4]=descriptions[i];
	}
	this.setup();
}

function newphotos_loaded(i) {
	newphotos_actual.loaded[i]=true;
	newphotos_thumbclass(i);
}

function newphotos_thumbclass(i) {
	gid("newphoto_thumb"+i).className="newphotos_thumbnail"
		+(newphotos_lastselected==i?" newphotos_thumbnail_active":"")
		+(newphotos_actual.loaded[i]?" newphotos_thumbnail_loaded":"");
}

function newphotos_select(i) {
	var i=parseInt(i);
	
	var lastthumb=newphotos_lastselected;
	newphotos_lastselected=i;
	newphotos_thumbclass(lastthumb?lastthumb:0);
	newphotos_thumbclass(i);

	var margin=getBorderWidth(newphotos_imgdiv);
	var x=getWidth(newphotos_list),y=0;
	var w=windowWidth()-x,h=windowHeight();
	var mw=(w-margin),mh=(h-margin);
	var img=newphotos_actual.get(i);
	var iw=img[1],ih=img[2];
	var ratio=iw/ih;

	var ma=newphotos_actual.margin;
	if (ma>=0 && ma<1) { mw*=(1-ma); mh*=(1-ma); }
	else if (ma>=1) { mw-=ma*2; mh-=ma*2; }
	
	if (iw>mw) { iw=mw; ih=iw/ratio; }
	if (ih>mh) { ih=mh; iw=ih*ratio; }
	
	var image=newphotos_actual.geturl(i);

	newphotos_caption.innerHTML
		="<div class='newphotos_caption_content'>"
			+"<div class='newphotos_counter'><span class='newphotos_counter_active'>"+(i+1)+"</span> de <span class='newphotos_counter_max'>"+newphotos_actual.photos.length+"</span></div>"
			+(newphotos_actual.shownames?"<div class='newphotos_filename'>"+basename(img[0])+"</div>":"")
			+(img[4]?"<div class='newphotos_description'>"+img[4]+"</div>":"")
		+"</div>";

	var navuph=getHeight(newphotos_navup);
	var navdownh=getHeight(newphotos_navdown);
	style(newphotos_list,{
		"top":navuph+"px",
		"height":(windowHeight()-navuph-navdownh)+"px"
	});
	style(newphotos_navup,{
		"top":"0px",
		"width":getWidth(newphotos_list)+"px"
	});
	style(newphotos_navdown,{
		"top":(windowHeight()-navdownh)+"px",
		"width":getWidth(newphotos_list)+"px"
	});

	style(newphotos_caption,{
		"top":(windowHeight()-getHeight(newphotos_caption))+"px",
		"left":(windowWidth()-getWidth(newphotos_caption))+"px"
	});

	style(newphotos_imgdiv,{
		"left":x+parseInt((w-iw-getBorderWidth(newphotos_imgdiv))/2)+"px",
		"top":y+parseInt((h-ih-getBorderWidth(newphotos_imgdiv))/2)+"px",
		"width":"auto",
		"height":"auto"
	});
	style(newphotos_img,{
		"visibility":(isie() || newphotos_lastimage==image?"visible":"hidden"),
		"width":iw+"px",
		"height":ih+"px"
	});
	if (newphotos_lastimage!=image)
		newphotos_img.src="";
	newphotos_img.src=image;
		newphotos_img.onload=function(){ style(newphotos_img,{"visibility":"visible"}); } // OJO: en IE no funciona este evento
	newphotos_lastimage=image;
	
}

function newphotos_show(i) {
	
	newphotos_close();

	var ishow=(i?i:0);
	newphotos_anipos=new Date();

	var d=document.createElement("div");
	d.id="newphotos_bg";
	d.title="Click para cerrar";
	d.onmouseup=function(e){
		if (!e) var e=window.event;
		if (e.clientX>getWidth("newphotos_list"))
			newphotos_close();
	};
	document.body.appendChild(d);
	newphotos_bg=d;

	newphotos_navup=document.createElement("div");
	newphotos_navup.id="newphotos_navup";
	newphotos_navup.innerHTML="<a href='javascript:void(0)' onMouseDown='javascript:void(newphotos_actual.prev())'><span>Anterior</span></a>";
	document.body.appendChild(newphotos_navup);
	style(newphotos_navup,{"left":"-"+getWidth(newphotos_navup)+"px"});

	newphotos_navdown=document.createElement("div");
	newphotos_navdown.id="newphotos_navdown";
	newphotos_navdown.innerHTML="<a href='javascript:void(0)' onMouseDown='javascript:void(newphotos_actual.next())'><span>Siguiente</span></a>";
	document.body.appendChild(newphotos_navdown);
	style(newphotos_navdown,{"left":"-"+getWidth(newphotos_navdown)+"px"});

	newphotos_list=document.createElement("div");
	newphotos_list.id="newphotos_list";
	document.body.appendChild(newphotos_list);
	style(newphotos_list,{"left":"-"+getWidth(newphotos_list)+"px"});
	
	var s="";
	for (var i in newphotos_actual.photos) {
		var thumb=newphotos_actual.getthumb(i);
		s+="<div id='newphoto_thumb"+i+"' class='newphotos_thumbnail'><a href='"+newphotos_actual.geturl(i)+"' onClick='javascript:return false;' onMouseDown='javascript:newphotos_select(\""+i+"\");return false;'><img src='"+thumb+"' width='100%' /></a></div>";
	}
	newphotos_list.innerHTML=s;

	newphotos_imgdiv=document.createElement("div");
	newphotos_imgdiv.id="newphotos_imgdiv";
	document.body.appendChild(newphotos_imgdiv);
	
	newphotos_img=document.createElement("img");
	newphotos_img.id="newphotos_img";
	newphotos_imgdiv.appendChild(newphotos_img);
	newphotos_imgdiv.onclick=function(){ newphotos_actual.next(); }

	newphotos_caption=document.createElement("div");
	newphotos_caption.id="newphotos_caption";
	document.body.appendChild(newphotos_caption);

	newphotos_select(ishow);
	newphotos_ani();
	
	newphotos_actual.locate();
}

function newphotos_close() {
	if (newphotos_lastselected!=null) {
		newphotos_lastselected=null;
		if (newphotos_bg) newphotos_bg.parentNode.removeChild(newphotos_bg);
		if (newphotos_list) newphotos_list.parentNode.removeChild(newphotos_list);
		if (newphotos_imgdiv) newphotos_imgdiv.parentNode.removeChild(newphotos_imgdiv);
		if (newphotos_img) newphotos_img.parentNode.removeChild(newphotos_img);
		if (newphotos_navup) newphotos_navup.parentNode.removeChild(newphotos_navup);
		if (newphotos_navdown) newphotos_navdown.parentNode.removeChild(newphotos_navdown);
		if (newphotos_caption) newphotos_caption.parentNode.removeChild(newphotos_caption);
	}
}
	
function newphotos_ani() {
	var d=newphotos_bg;
	var i=new Date()-newphotos_anipos;
	var r=i/400; if (r>=1) r=1;
	try { d.style.opacity=r*newphotos_actual.opacity; } catch(e) {}
	try { d.style.filter="alpha(opacity="+parseInt(r*newphotos_actual.opacity*100)+")"; } catch(e) {}
	var p=parseInt(getWidth(newphotos_list)*(1-r)*(1-r));
	style(newphotos_navup,{"left":"-"+p+"px"});
	style(newphotos_navdown,{"left":"-"+p+"px"});
	style(newphotos_list,{"left":"-"+p+"px"});
	if (r<1) setTimeout(newphotos_ani,1);
}

var newphotosLastWindowOnResize=window.onresize;
window.onresize=function(){
	if (newphotos_lastselected!=null)
		newphotos_select(newphotos_lastselected);
	try { newphotosLastWindowOnResize(); } catch(e) {}
}
