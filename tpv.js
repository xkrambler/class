var tpv={

	"payHelper":function(o){
		var o=o||{};
		o.wait=function(o){ newwait("Preparando, por favor, espere..."); };
		o.always=function(){ newwait_close(); };
		tpv.pay(o);
	},

	"pay":function(o){
		if (o.wait) o.wait(o);
		ajax({
			"post":{
				"tpv_pay":"ajax",
				"tpv_id":(o.tpv_id?o.tpv_id:"")
			},
			"always":(o.always?o.always:null),
			"showerrors":true,
			"async":function(r){
				if (r.data.err) alert(r.data.err);
				if (r.data.ok) {
					if (o.wait) o.wait(o);
					tpv.sendForm(r.data);
				}
			}
		});
	},

	"sendForm":function(o){
		if (!o.url || !o.form) return false;
		var form=document.createElement("form");
		form.setAttribute("method", "POST");
		form.setAttribute("action", o.url);
		for (var key in o.form) {
			if (o.form.hasOwnProperty(key)) {
				var value=o.form[key];
				var f=document.createElement("input");
				f.setAttribute("type", "hidden");
				f.setAttribute("name", key);
				f.setAttribute("value", value);
				form.appendChild(f);
			}
		}
		document.body.appendChild(form);
		form.submit();
	},

	"cancelHelper":function(){
		tpv.cancel({
			"wait":function(o){ newwait("Por favor, espere..."); },
			"always":function(){ newwait_close(); }
		});
	},

	"cancel":function(o){
		if (o.wait) o.wait(o);
		ajax({
			"post":{"tpv_cancel":""},
			"always":(o.always?o.always:null),
			"showerrors":true,
			"async":function(r){
				if (r.data.err) alert(r.data.err);
				if (r.data.ok) {
					if (o.ok) o.ok(o, r);
					else {
						if (o.wait) o.wait(o);
						location.reload();
					}
				}
			}
		});
	},

	// credit card support
	"cc":function(o){
		var self=this;

		// return named id
		self.id=function(){
			var id=gid(self.o.id).id;
			if (!id) gid(self.o.id).id=id="tpv_id";
			return id;
		};

		// filter PAN
		self.filterPAN=function(value){
			var s=value.replace(/\D/g, '');
			return trim(
				s.substring(0, 4)
				+" "+s.substring(4, 8)
				+" "+s.substring(8, 12)
				+" "+s.substring(12, 16)
			);
		};

		// filter expiry date
		self.filterExpiryDate=function(value){
			var s=value.replace(/\D/g, '');
			var m=s.substring(0, 2);
			var y=s.substring(2, 4);
			return (m && y?m+"/"+y:s);
		};

		// filter cvv2
		self.filterCVV2=function(value){
			return value.replace(/\D/g, '');
		};

		// get/set values
		self.values=function(values){
			var id=self.id();
			if (!gid(id)) return null;
			if (values) {
				gidval(id+"_pan",        self.filterPAN(values.pan));
				gidval(id+"_expirydate", self.filterExpiryDate(values.expirydate));
				gidval(id+"_cvv2",       self.filterCVV2(values.cvv2));
				self.check();
			}
			return {
				"pan"       :gidval(id+"_pan"),
				"expirydate":gidval(id+"_expirydate"),
				"cvv2"      :gidval(id+"_cvv2")
			};
		};

		// check values
		self.check=function(e){
			var id=self.id();
			var values=self.values();
			var ok={
				"pan"       :/[0-9]{4} [0-9]{4} [0-9]{4} [0-9]{4}/.test(values.pan),
				"expirydate":/[0-9]{2}\/[0-9]{2}/.test(values.expirydate),
				"cvv2"      :/[0-9]{3}/.test(values.cvv2)
			};
			var m=""+parseInt(values.expirydate.substring(0, 2)); if (m <= 9) m="0"+m;
			var y=""+parseInt(values.expirydate.substring(3, 5));
			var cm=""+(new Date()).getMonth(); if (cm <= 9) cm="0"+cm;
			var cy=""+((new Date()).getFullYear() - 2000);
			if (y+m < cy+cm) ok.expirydate=false;
			var bad=false;
			var empty=0;
			for (var f in ok) {
				var bad_f=(!e || e != gid(id+"_"+f)?!ok[f]:false);
				if (!values[f].length) {
					empty++;
					bad_f=false;
				}
				if (bad_f) bad=true;
				classEnable(id+"_"+f, "tpv_cc_bad_input", bad_f);
			}
			classEnable(id, "tpv_cc_bad", bad);
			if (!e) classEnable(id, "tpv_cc_ok", !bad && !empty);
		};

		// timed check
		self.checkTimed=function(e){
			setTimeout(function(){ self.check(e); }, 40);
		};

		// initialize
		self.init=function(o){
			self.o=o;

			var id=self.id();

			if (!gid(id)) return false;

			classAdd(id, "tpv_cc");
			gidset(id, ""
				+(self.o.icon?"<span class='tpv_cc_icon'>"+self.o.icon+"</span>":"")
				+"<span class='tpv_cc_caption'>Número de tarjeta</span>"
				+"<input class='tpv_cc_input' id='"+id+"_pan' type='text' size='17' maxlength='19' placeholder='Número de tarjeta' />"
				+"<span class='tpv_cc_caption'>Mes/Año</span>"
				+"<input class='tpv_cc_input' id='"+id+"_expirydate' type='text' size='5' maxlength='5' placeholder='MM/YY' />"
				+"<span class='tpv_cc_caption'>CVV</span>"
				+"<input class='tpv_cc_input' id='"+id+"_cvv2' type='text' size='3' maxlength='3'placeholder='CVV' />"
			);

			var
				pan=gid(id+"_pan"),
				expirydate=gid(id+"_expirydate"),
				cvv2=gid(id+"_cvv2")
			;

			// pan events
			pan.oninput=function(){
				if (this.value.length == 19 && this.selectionStart == this.value.length) {
					expirydate.focus();
					expirydate.selectionStart=expirydate.selectionEnd=0;
				}
			};
			pan.onkeypress=function(){
				if (event.keyCode < 48 || event.keyCode > 57) return false;
			};
			pan.onkeyup=function(){
				if (event.keyCode == 8 && this.value.substring(this.selectionStart-1, this.selectionStart) == " ") {
					this.value=this.value.substring(0, this.selectionStart-1);
					event.preventDefault();
					event.stopPropagation();
					self.check(this);
				} else {
					var s=this.value.replace(/\D/g, '');
					var d=[s.substring(0, 4), s.substring(4, 8), s.substring(8, 12), s.substring(12, 16)];
					s=d[0]
						+(d[0].length == 4?" ":"")+d[1]
						+(d[1].length == 4?" ":"")+d[2]
						+(d[2].length == 4?" ":"")+d[3]
					;
					if (s != this.value) {
						var l=s.length-this.value.length;
						var ss=this.selectionStart;
						this.value=(event.keyCode == 8?trim(s):s);
						this.selectionStart=this.selectionEnd=ss+(event.keyCode == 8?0:l);
						self.check(this);
					}
				}
			};
			pan.onkeydown=function(){
				if (event.keyCode == 39 && this.selectionStart == this.value.length) {
					expirydate.focus();
					expirydate.selectionStart=expirydate.selectionEnd=0;
					event.preventDefault();
					event.stopPropagation();
				}
			};
			pan.onfocus=function(){
				classDel(this, "tpv_cc_bad_input");
			};
			pan.onblur=function(){
				this.value=self.filterPAN(this.value);
				self.check();
			};

			// expirydate events
			expirydate.oninput=function(){
				if (this.selectionStart == this.value.length && this.value.length == 5) {
					cvv2.focus();
					cvv2.selectionStart=cvv2.selectionEnd=0;
				}
			};
			expirydate.onkeypress=function(){
				if (event.keyCode < 48 || event.keyCode > 57) return false;
				if (this.value.length == 2) {
					this.value+="/";
					this.selectionStart=this.selectionEnd=this.value.length;
				}
			};
			expirydate.onkeydown=function(){
				if (event.keyCode == 8 && this.selectionStart == 0) {
					pan.focus();
				} else if (event.keyCode == 37 && this.selectionStart == 0) {
					pan.focus();
					event.preventDefault();
					event.stopPropagation();
					self.check();
				} else if (event.keyCode == 39 && this.selectionStart == this.value.length) {
					cvv2.focus();
					cvv2.selectionStart=cvv2.selectionEnd=0;
					event.preventDefault();
					event.stopPropagation();
					self.check();
				}
			};
			expirydate.onkeyup=function(){
				if (event.keyCode == 8 && this.selectionStart == 3) {
					this.value=this.value.substring(0, 2);
					self.check(this);
				}
			};
			expirydate.onfocus=function(){
				classDel(this, "tpv_cc_bad_input");
				self.check();
			};
			expirydate.onblur=function(){
				this.value=self.filterExpiryDate(this.value);
				self.check();
			};

			// cvv2 events
			cvv2.onkeypress=function(){
				if (event.keyCode < 48 || event.keyCode > 57) return false;
			};
			cvv2.onkeydown=function(){
				if (event.keyCode == 8 && this.selectionStart == 0) {
					expirydate.focus();
				} else if (event.keyCode == 37 && this.selectionStart == 0) {
					expirydate.selectionStart=expirydate.selectionEnd=expirydate.value.length;
					expirydate.focus();
					event.preventDefault();
					event.stopPropagation();
					self.check();
				}
			};
			cvv2.onfocus=function(){
				classDel(this, "tpv_cc_bad_input");
				classAdd(id, "tpv_cc_cvv2_focus");
			};
			cvv2.onblur=function(){
				classDel(id, "tpv_cc_cvv2_focus");
				this.value=self.filterCVV2(this.value);
				self.check();
			};

			return true;

		};

		self.init(o);

	}

};
