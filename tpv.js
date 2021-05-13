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
	}
};
