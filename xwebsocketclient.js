/*

	Light Class to create WebSocket Clients based on RFC6455 and KISS principle v0.2
	(c)2017 by Pablo Rodríguez Rey (https://mr.xkr.es/)
	Distributed under the GPL version 3 license (http://www.gnu.org/licenses/gpl-3.0.html)

	var wsc=new xWebSocketClient({
		"url":"ws://127.0.0.1:52303/",
		"onconnect":function(wsc){
			console.log("[WS] Connected");
		},
		"ondisconnect":function(wsc){
			console.log("[WS] Disconnected");
		},
		"onerror":function(wsc, e){
			console.log('[WS] Error', e);
		},
		"onrecv":function(wsc, raw){
			//console.log("[WS] "+raw);
		},
		"ondata":function(wsc, data, raw){
			console.log(data !== null?data:raw);
			if (data == "hello!") wsc.data("hi!");
		},
		"onsend":function(wsc, raw){
			console.log('[WS] Send ', raw);
		},
		"onping":function(wsc){
			console.log('[WS] Ping');
			wsc.data({"op":"ping"});
		}
	});

*/
function xWebSocketClient(o) {
	var self=this;

	// get/set options
	self.get=function(k){ return (typeof(k) == "undefined"?self.o:self.o[k]); };
	self.set=function(k, v){ if (typeof(k) == "object") { for (var i in k) self.o[i]=k[i]; } else self.o[k]=v; };
	self.del=function(k){ delete self.o[k]; };

	// get/set URL
	self.url=function(url){
		if (typeof(url) == "string") self.o.url=url;
		return self.o.url;
	};

	// connect
	self.connect=function(url){
		if (url) self.o.url=url;
		if (!self.o.url) return false;
		self.connected=false;
		self.doreconnect=true;
		self.ws=new WebSocket(self.o.url); // self.ws.readyState
		self.ws.onopen=function(e){
			if (!self.connected && self.o.onconnect) self.o.onconnect(self);
			self.connected=true;
			self.pingTimer();
		};
		self.ws.onclose=function(e){
			if (self.connected && self.o.ondisconnect) self.o.ondisconnect(self);
			if (self.doreconnect) self.reconnect();
			delete self.connected;
		};
		self.ws.onmessage=function(e){
			if (self._ping_timeout) clearTimeout(self._ping_timeout);
			var data=null;
			try { data=JSON.parse(e.data); } catch(e) {}
			if (self.o.onrecv) self.o.onrecv(self, e.data);
			if (self.o.ondata) self.o.ondata(self, data, e.data);
			self.pingTimer();
		};
		self.ws.onerror=function(e) {
			if (self.o.onerror) self.o.onerror(self, e);
		};
	};

	// disconnect
	self.disconnect=function(status){
		delete self.doreconnect;
		if (self.ws) {
			self.ws.close(status||1000);
			delete self.ws;
		}
	};

	// reconnect at current timeout interval
	self.reconnect=function(){
		if (self._reconnect_timer) clearTimeout(self._reconnect_timer);
		if (self.o.timeout) self._reconnect_timer=setTimeout(function(){ delete self._reconnect_timer; self.connect(); }, self.o.timeout);
	};

	// send timed ping, if defined
	self.pingTimer=function(){
		if (self.connected && self.o.onping) {
			if (self._ping_timer) clearTimeout(self._ping_timer);
			self._ping_timer=setTimeout(function(){ self.ping(); }, self.o.ping);
		}
	};

	// send ping, if defined
	self.ping=function(){
		if (self.connected) {
			if (self._ping_timer) clearTimeout(self._ping_timer);
			delete self._ping_timer;
			if (self.o.onping) {
				self.o.onping(self);
				self._ping_timeout=setTimeout(function(){
					delete self._ping_timeout;
					self.ws.close(3008);
					delete self.ws;
				}, self.o.timeout);
			}
		}
	};

	// send raw data
	self.send=function(raw){
		var sent=false;
		if (self.ws) {
			try {
				self.ws.send(raw);
				sent=true;
			} catch(e) {
				console.warn(e);
			}
			self.pingTimer();
			if (self.o.onsend) self.o.onsend(self, raw);
		}
		return sent;
	};

	// send data
	self.data=function(data){
		return self.send(JSON.stringify(data));
	};

	// init
	self.init=function(o){
		var o=o||{};
		if (!o.ping) o.ping=60000;
		if (!o.timeout) o.timeout=3000;
		self.o=o;
		if (self.o.url) self.connect();
	};

	self.init(o);

}
