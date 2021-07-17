/*

	Stripe v3 Payment Helper Class v0.1

	Example usage:

		var xstripe=new xStripe({
			"id":"stripe", // id/element to render card
			"focus":true, // focus on load
			"onok":function(xstripe, r){ // event on payment OK
				alert("Payment successful.");
			},
			"onko":function(xstripe, r){ // event on payment KO
				alert("Payment error: "+r.err);
			}
		});

*/
function xStripe(o){
	var self=this;

	// OK event
	self.ok=function(ok){
		var msg="Pago confirmado.";
		if (self.o.onok) self.o.onok(self, ok);
		else if (isset(newok)) newok(msg);
		else alert(msg);
	};

	// KO event
	self.ko=function(error){
		var msg="Stripe ERROR: "+error;
		if (self.o.onko) self.o.onko(self, {"err":error});
		else if (isset(newerror)) newerror(msg, function(){ self.focus(); });
		else alert(msg);
	};

	// wait event
	self.wait=function(enabled){
		if (self.o.onwait) self.o.onwait(enabled);
		else {
			if (enabled && isset(newsimplewait)) newsimplewait();
			else if (isset(newwait_close)) newwait_close();
		}
	};

	// focus card
	self.focus=function(){
		if (self.card && self.card.focus) self.card.focus();
	};

	// start payment process
	self.pay=function(){
		if (!self.cardReady) return false;
		if (!self.o.ajax) self.o.ajax="xstripe.pay";
		self.wait(true);
		try {
			self.stripe.createPaymentMethod({
				"type": 'card',
				"card": self.card
			}).then(function(result) {
				if (result.error) {
					self.wait(false);
					self.ko(result.error.message);
					self.focus();
				} else {
					ajax(self.o.ajax, {
						"payment_method_id":result.paymentMethod.id,
					}, function(){
						self.wait(false);
					}, function(r){
						if (r.data.err) self.ko(r.data.err);
						if (r.data.ok) {
							if (r.data.client_secret) {
								self.stripe.handleCardAction(r.data.client_secret).then(function(result){
									if (result.error) self.ko(result.error.message);
									else {
										self.wait(true);
										ajax(self.o.ajax, {
											"payment_intent_id":result.paymentIntent.id 
										}, function(){
											self.wait(false);
										}, function(r){
											if (r.data.err) self.ko(r.data.err);
											if (r.data.ok) self.ok(r.data);
										});
									}
								});
							} else {
								self.ok(r.data);
							}
						}
					});
				}
			});
		} catch (e) {
			self.wait(false);
		}
		return false;
	};

	// get parameters
	self.get=function(){
		return self.o;
	};

	// set parameters
	self.set=function(o){
		if (o)
			for (var k in o)
				self.o[k]=o[k];
	};

	// destroy
	self.destroy=function(){
		if (self.elements) {
			self.cardReady=false;
			self.elements.clear();
			delete self.elements;
		}
	};

	// initialize
	self.init=function(o){
		if (typeof o !== 'object' || o === null) return false;
		self.o=o;
		if (!isset(Stripe)) return false;

		gidset(self.o.id, "");

		self.cardElement=document.createElement("div");
		self.cardElement.setAttribute("data-locale", "auto");
		gid(self.o.id).appendChild(self.cardElement);

		self.cardErrors=document.createElement("div");
		self.cardErrors.setAttribute("role", "alert");
		gid(self.o.id).appendChild(self.cardErrors);

		self.stripe=Stripe(data.stripe_pk);
		self.elements=self.stripe.elements();
		self.card=self.elements.create("card", {
			//"classes":"",
			"style":(self.o.style || {
				base:{
					"color":"#303238",
					"fontSize":"16px",
					"fontFamily":'"Open Sans", sans-serif',
					"fontSmoothing":"antialiased",
					"::placeholder":{
						"color":"#CFD7DF"
					}
				},
				"invalid":{
					"color":"#e5424d",
					":focus":{
						"color":"#303238"
					}
				}
			}),
			"hidePostalCode":true
		});
		self.card.on("ready", function(){ self.cardReady=true; });
		self.card.mount(self.cardElement);
		if (self.o.focus) init(function(){ self.focus() });

	};

	if (isset(o)) self.init(o);

}
