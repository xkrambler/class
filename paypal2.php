<?php

/*
	PayPal API v2 implementation
	paypal table changes from v1 to v2:
	- Add 'CANCEL' ENUM to field estado
	- Add field payid VARCHAR(40)
*/
class PayPal {

	protected $o;
	protected $db=false;
	protected $lasterr="";

	function __construct($o) {
		$this->o=$o;
		if (!$this->moneda) $this->moneda="EUR";
		if (!$this->locale) $this->locale="es_ES";
	}
	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }
	function __isset($n) { return isset($this->o[$n]); }

	// get all parameters
	function get() { return $this->o; }

	// set all parameters
	function set($o=[]) { if ($o) foreach ($o as $n=>$v) $this->o[$n]=$v; }

	// get/set database
	function db($v=null) { if ($v !== null) $this->db=$v; return $this->db; }

	// get/set database table
	function table($table=null) {
		if ($table !== null) $this->o["table"]=$table;
		return ($this->o["table"]?$this->o["table"]:"paypal");
	}

	// get/set API endpoint URL
	function endpoint($v=null) {
		if ($v !== null) $this->endpoint=$v;
		return ($this->endpoint?$this->endpoint:($this->sandbox
			?'https://api.sandbox.paypal.com/'
			:'https://api.paypal.com/'
		));
	}

	// obtain calculated partial
	function partial() {
		$v=0;
		if ($this->items)
			foreach ($this->items as $item)
				$v+=($item["unidades"]*$item["precio"]);
		return round($v, 2);
	}

	// obtain calculated total
	function total() {
		if (!$this->impuestos)      $this->impuestos=0;
		if (!$this->envio)          $this->envio=0;
		if (!$this->envioDescuento) $this->envioDescuento=0;
		if (!$this->gestion)        $this->gestion=0;
		if (!$this->seguro)         $this->seguro=0;
		$v=(
			$this->partial()
			+$this->impuestos
			+$this->envio
			+$this->gestion
			+$this->envioDescuento
			+$this->seguro
		);
		return round($v, 2);
	}

	// numeric format
	function numberFormat($v) {
		return number_format(doubleval($v), 2, ".", "");
	}

	// start payment transaction
	function start() {

		$this->error("");
		if (!$this->item_id) return $this->error("Detenido: item_id no especificado");
		if (!$this->items)   return $this->error("Detenido: items no especificados");
		if (!$this->moneda)  return $this->error("Detenido: moneda no configurada");

		// get total
		$total=$this->total();
		if ($total <= 0) return $this->error("Error crítico: El total de los items a pagar es 0");

		// prepare item list
		$items=[];
		if ($this->items) foreach ($this->items as $item_i=>$item) {
			$items[]=[
				"name"       =>$item["concepto"],
				"description"=>$item["descripcion"],
				"quantity"   =>intval($item["unidades"]),
				"price"      =>$this->numberFormat($item["precio"]),
				"currency"   =>$this->moneda,
				//"tax"=>"0.01",
				//"sku"=>"1",
			];
		}

		// prepare payment
		$payment=[
			"intent"=>"sale",
			"payer"=>[
				"payment_method"=>"paypal"
			],
			"transactions"=>[
				[
					"amount"=>[
						"total"=>$this->numberFormat($total),
						"currency"=>$this->moneda,
						"details"=>[
							"subtotal"=>$this->numberFormat($this->partial()),
							"tax"=>$this->numberFormat($this->impuestos),
							"shipping"=>$this->numberFormat($this->envio),
							"handling_fee"=>$this->numberFormat($this->gestion),
							"shipping_discount"=>$this->numberFormat($this->envioDescuento),
							"insurance"=>$this->numberFormat($this->seguro),
						]
					],
					"description"=>"".$this->concepto,
					"custom"=>"".$this->localizador, // "EBAY_EMS_90048630024435", // custom field
					//"invoice_number"=>"48787589673", // Your own invoice or tracking ID number. Value is a string of single-byte alphanumeric characters.
					"payment_options"=>[
						"allowed_payment_method"=>"INSTANT_FUNDING_SOURCE"
					],
					//"soft_descriptor"=>"ECHI5786786", //  The payment descriptor on account transactions on the customer's credit card statement, that PayPal sends to processors. The maximum length of the soft descriptor information that you can pass in the API field is 22 characters, in the following format: 22 - len(PAYPAL * (8)) - len(Descriptor in Payment Receiving Preferences of Merchant account + 1)
					"item_list"=>[
						"items"=>$items,
						/*"shipping_address"=>[
							"recipient_name"=>"Hello World",
							"line1"=>"4thFloor",
							"line2"=>"unit#34",
							"city"=>"SAn Jose",
							"country_code"=>"US",
							"postal_code"=>"95131",
							"phone"=>"011862212345678",
							"state"=>"CA"
						]*/
					]
				]
			],
			//"note_to_payer"=>"Contacte con nosotros para cualquier duda sobre su pedido.",
			"redirect_urls"=>[
				"return_url"=>$this->ok,
				"cancel_url"=>$this->ko,
			],
			"application_context"=>[
				//"brand_name"=>"BRAND NAME",
				"locale"=>$this->locale,
				//"shipping_preference"=>"",
			],
		];
		//debug($payment);exit;

		// enable profile experience for this site
		$experience_profile_name=$_SERVER["SERVER_NAME"];

		// search experience profiles to enable custom logo
		if ($res=$this->restRequest("v1/payment-experience/web-profiles")) {
			foreach ($res as $p) if ($p["name"] == $experience_profile_name) {
				$payment["experience_profile_id"]=$p["id"];
				break;
			}
		}

		// enable custom profile para experiencia de usuario con logo
		if (!$payment["experience_profile_id"]) {
			if ($res=$this->restRequest("v1/payment-experience/web-profiles", [
				"name"=>$experience_profile_name,
				"temporary"=>true, // 3h temporary profile
				"presentation"=>[
					//"brand_name"=>
					"locale_code"=>$this->locale,
					"logo_image"=>$this->logoimg,
					//"address_override"=>1
				],
				"input_fields"=>[
					"no_shipping"=>1,
					//"address_override"=>1
				],
			])) $payment["experience_profile_id"]=$res["id"];
		}

		// create paypal entry registry
		if ($this->db) {
			if (!$this->db->query($this->db->sqlinsert($this->table(), [
				"fecha"=>$this->db->now(),
				"modificacion"=>$this->db->now(),
				"estado"=>"START",
				"total"=>$total,
				"item_id"=>$this->item_id,
				"token"=>null,
				"payerid"=>null,
				"payid"=>null,
				"localizador"=>$this->localizador,
				"cliente"=>($this->cliente?$this->cliente:""),
				"email"=>($this->email?$this->email:""),
				"items"=>json_encode($this->items),
				"peticion"=>json_encode($payment),
				"resultado"=>"",
				"cancelacion"=>"",
			]))) $this->db->err();
			$paypal_id=$this->db->lastid();
			if (!$this->db->commit()) $this->db->err();
		}

		// request payment
		if (!($res=$this->restRequest("v1/payments/payment", $payment))) return false;

		// if payment is created
		if ($res["state"] == "created") {

			// get Token
			$token=false;
			$url_redirect=false;
			foreach ($res["links"] as $l) if ($l["method"] == "REDIRECT") {
				$url_redirect=$l["href"];
				$url_info=parse_url($url_redirect);
				$url_query=[];
				parse_str($url_info["query"], $url_query);
				$token=$url_query["token"];
			}
			if (!$token) return $this->error("Error: No se ha podido obtener token.");
			if (!$url_redirect) return $this->error("Error: No se ha podido obtener REDIRECT de la pasarela de pago.");

			// update database
			if ($this->db) {
				if (!$this->db->query($this->db->sqlupdate($this->table(), [
					"modificacion"=>$this->db->now(),
					"estado"=>"TOKEN",
					"token"=>$token,
					"payid"=>$res["id"],
					"resultado"=>json_encode($res),
				], [
					"id"=>$paypal_id,
				]))) $this->db->err();
				if (!$this->db->commit()) $this->db->err();
			}

			// save payment information in session and redirect to PayPal Checkout
			$_SESSION["paypal"][$token]["payment"]=$payment;
			x::redir($url_redirect);

		}

		// otherwise, payment is not created
		return $this->error("No se puede crear el pago: ".($res && $res["name"]?"Sin respuesta del servidor de PayPal":$res["name"].($res["message"]?": ".$res["message"]:"")));

	}

	// execute the payment
	function process() {

		// obtain required data
		$token=$_GET["token"];
		$payerid=$_GET["PayerID"];
		$payment_id=$_GET["paymentId"];
		if ($token && $payerid && $payment_id) {

			// check they payment is in session
			if (!$_SESSION["paypal"][$token]) return $this->error("Token no encontrado, quizás la sesión ha expirado.");

			// if execution in session, return this
			if ($_SESSION["paypal"][$token]["execute"]) return $_SESSION["paypal"][$token];

			// update processing state in database
			if ($this->db) {
				if (!$this->db->query($this->db->sqlupdate($this->table(), [
					"modificacion"=>$this->db->now(),
					"estado"=>"PROCESS",
					"payerid"=>$payerid,
				], [
					"token"=>$token,
					"estado"=>"TOKEN",
				]))) $this->db->err();
				if (!$this->db->commit()) $this->db->err();
			}

			// execute payment
			if (!($res=$this->restRequest(x::link([], "v1/payments/payment/".$payment_id."/execute"), ["payer_id"=>$payerid]))) return false;

			// if payment is approved
			if ($res["state"] == "approved") {

				// save in session
				$_SESSION["paypal"][$token]["execute"]=$res;

				// update database
				if ($this->db) {
					if (!$this->db->query($this->db->sqlupdate($this->table(), [
						"modificacion"=>$this->db->now(),
						"estado"=>"OK",
						"resultado"=>json_encode($res),
					], [
						"token"=>$token,
					]))) $this->db->err();
					if (!$this->db->commit()) $this->db->err();
				}

				// return payment and complete indication
				return ["complete"=>true]+$_SESSION["paypal"][$token];

			}

			// update database to mark error
			if ($this->db) {
				if (!$this->db->query($this->db->sqlupdate($this->table(), [
					"modificacion"=>$this->db->now(),
					"estado"=>"KO",
					"resultado"=>json_encode($res),
				], [
					"token"=>$token,
				]))) $this->db->err();
				if (!$this->db->commit()) $this->db->err();
			}

			// not approved
			return $this->error("Respuesta no aceptada.");

		}

		// parameters needed
		return $this->error("Parámetros incompletos para terminar el pago.");

	}

	// mark payment as cancelled
	function cancel() {
		// request Token
		if ($token=$_GET["token"]) {
			// if not cancelled
			if ($_SESSION["paypal"][$token] && !$_SESSION["paypal"][$token]["canceled"]) {
				// mark as cancelled
				$_SESSION["paypal"][$token]["canceled"]=true;
				// update database
				if ($this->db) {
					if (!$this->db->query($this->db->sqlupdate($this->table(), [
						"modificacion"=>$this->db->now(),
						"estado"=>"KO",
					], [
						"token"=>$token,
					]))) $this->db->err();
					if (!$this->db->commit()) $this->db->err();
				}
				// payment cancelled
				return true;
			}
		}
		// already cancelled
		return false;
	}

	// get operation by id
	function getOperation($id) {
		if (!$this->db) return $this->error("Database setup required");
		if (!$this->db->query("SELECT * FROM ".$this->table()." WHERE id='".$this->db->escape($id)."'")) $this->db->err();
		return $this->db->row();
	}

	// cancel an operation, returning funds
	function cancelOperation($id) {
		if (!$id) return $this->error("id of operation required");
		if (!$this->db) return $this->error("Database required to cancel operation");
		// obtain operation
		if (!($op=$this->getOperation($id))) return $this->error("Operation not found.");
		// check operation status
		switch ($op["estado"]) {
		case "OK":
			// return payment
			if (!($ret=$this->returnPayment($id))) return $this->error("returnPayment failed");
			// check result
			if ($ret && ($ret["state"] == "completed")) {
				// update database
				if (!$this->db->query($this->db->sqlupdate($this->table(), [
					"modificacion"=>$this->db->now(),
					"cancelacion"=>json_encode($ret),
					"estado"=>"CANCEL",
				], [
					"id"=>$op["id"],
				]))) $this->db->err();
				if (!$this->db->commit()) $this->db->err();
				// return result
				return $ret;
			}
			break;

		case "CANCEL":
			return json_decode($op["cancelacion"], true);

		default:
			return $this->error("Operation not elegible to return (status=".$op["estado"].")");

		}
		// return result
		return $this->error("Return not completed: ".json_encode($ret));
	}

	// get PayID by Token
	function getPayIDbyToken($token) {
		if (!$this->db->query("SELECT payid FROM ".$this->table()." WHERE token='".$this->db->escape($payid)."'")) $this->db->err();
		if ($v=$db->field()) return $v;
		return false;
	}

	// get payment details
	function details($payid) {
		if (!($res=$this->restRequest(x::link([], "v1/payments/payment/".$payid)))) return false;
		return $res;
	}

	// list of payments
	function payments($o=[]) {
		return $this->restRequest(x::link($o, "v1/payments/payment"));
	}

	// list of transactions
	function reportingTransactions($o=[]) {
		if (!$o["start_date"]) $o["start_date"]=date(DATE_RFC3339, mktime(0, 0, 0, date("m"), date("d")-1, date("Y"))); // default: last 24h
		if (!$o["end_date"]) $o["end_date"]=date(DATE_RFC3339); // default: now
		return $this->restRequest(x::link($o, "v1/reporting/transactions"));
	}

	// return payment by payment ID
	/*

		{
			name=INVALID_RESOURCE_ID
			message=Requested resource ID was not found.
			information_link=https://developer.paypal.com/docs/api/payments/#errors
			debug_id=4f4e55742f464
		}

		{
			name=PERMISSION_DENIED
			message=Permission denied.
			information_link=https://developer.paypal.com/docs/api/payments/#errors
			debug_id=39450333deb4a
		}

		{
			id=2EN4069833971960K
			create_time=2020-10-21T21:52:07Z
			update_time=2020-10-21T21:52:07Z
			state=completed
			amount {
				total=30.11
				currency=USD
			}
			refund_from_transaction_fee {
				currency=USD
				value=1.32
			}
			total_refunded_amount {
				currency=USD
				value=30.11
			}
			refund_from_received_amount {
				currency=USD
				value=28.79
			}
			sale_id=4PU13620US425422X
			parent_payment=PAYID-L6IKWFY1KC84690NH853470D
			links {
				0 {
					href=https://api.sandbox.paypal.com/v1/payments/refund/2EN4069833971960K
					rel=self
					method=GET
				}
				1 {
					href=https://api.sandbox.paypal.com/v1/payments/payment/PAYID-L6IKWFY1KC84690NH853470D
					rel=parent_payment
					method=GET
				}
				2 {
					href=https://api.sandbox.paypal.com/v1/payments/sale/4PU13620US425422X
					rel=sale
					method=GET
				}
			}
		}

		{
			name=TRANSACTION_ALREADY_REFUNDED
			message=Refund refused. Refund was already issued for transaction.
			information_link=https://developer.paypal.com/docs/api/payments/#errors
			debug_id=5cfc43962e391
		}

	*/
	function returnPayment($id) {
		// obtain operation
		if (!($op=$this->getOperation($id))) return $this->error("Operation not found.");
		// get sale id
		$res=json_decode($op["resultado"], true);
		if (!($sale_id=$res["transactions"][0]["related_resources"][0]["sale"]["id"])) return $this->error("sale_id not found.");
		// request
		return $this->restRequest("v1/payments/sale/".$sale_id."/refund", "{}");
	}

	// ensure library requisites
	function ensureRequisites() {
		if (!function_exists("curl_init")) return $this->error("restRequest: CURL not available");
		return true;
	}

	// request, if needed, bearer authorization
	function bearerRequest() {
		if (!$this->ensureRequisites()) return false;

		// prepare CURL channel for bearer access token
		if (!$this->bearer || time() >= $this->bearer["expire"]) {

			// expire bearer
			$this->bearer=false;

			// request bearer
			$ch=curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL=>$this->endpoint()."v1/oauth2/token",
				//CURLOPT_VERBOSE=>1,
				//CURLOPT_SSL_VERIFYPEER=>false,
				//CURLOPT_SSL_VERIFYHOST=>false,
				CURLOPT_CONNECTTIMEOUT_MS=>3000,
				CURLOPT_TIMEOUT_MS=>5000,
				CURLOPT_RETURNTRANSFER=>1,
				CURLOPT_HTTPHEADER, [
					'Content-Type: application/json',
					'Accept-Language: en_US',
				],
				CURLOPT_USERPWD=>$this->clientid.":".$this->secret,
				CURLOPT_POST=>1,
				CURLOPT_POSTFIELDS=>"grant_type=client_credentials",
			]);
			if (!($out=curl_exec($ch))) return $this->error("PayPal bearerRequest failed: ".curl_error($ch).'('.curl_errno($ch).')');
			curl_close($ch);

			// decode bearer
			if (!($json=json_decode($out, true))) return $this->error("PayPal bearerRequest JSON parse error");

			// check bearer type and save
			if ($json["token_type"] == "Bearer") {
				$json["expire"]=time()+$json["expires_in"]-30; // bearer expiration
				$this->bearer=$json;
			}

		}

		// return bearer
		return $this->bearer;

	}

	// request to PayPal REST API
	function restRequest($action, $o=null) {

		// ensure if we have a valid bearer
		if (!$this->bearerRequest()) return ($this->lasterr?false:$this->error("PayPal restRequest bearer failed"));

		// prepare CURL channel for action request
		$ch=curl_init();
		curl_setopt_array($ch, [
			//CURLINFO_HEADER_OUT=>1,
			CURLOPT_VERBOSE=>1,
			CURLOPT_URL=>$this->endpoint().$action,
			CURLOPT_FOLLOWLOCATION=>1,
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_CONNECTTIMEOUT_MS=>5000,
			CURLOPT_TIMEOUT_MS=>15000,
			//CURLOPT_SSL_VERIFYPEER=>false,
			//CURLOPT_SSL_VERIFYHOST=>false,
			CURLOPT_HTTPHEADER=>[
				'Content-Type: application/json',
				'Accept-Language: es_ES',
				'Authorization: Bearer '.$this->bearer["access_token"],
			],
			//CURLOPT_POST=>1,
			//CURLOPT_POSTFIELDS=>http_build_query($fields)
			//CURLOPT_POSTFIELDS=>($o?(is_array($o)?json_encode($o):$o):false),
		]);
		if ($o !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($o)?json_encode($o):$o));
		$out=curl_exec($ch);
		$this->requestinfo=curl_getinfo($ch);
		//debug($this->requestinfo);

		// check output
		if (!$out) return $this->error("PayPal restRequest failed: ".curl_error($ch).'('.curl_errno($ch).'): '.$out);

		// decode JSON
		if (!($json=json_decode($out, true))) return $this->error("PayPal restRequest JSON parse error");

		// return result
		return $json;

	}

	// get/set error
	function error($err=null) {
		if ($err === null) return $this->lasterr;
		$this->lasterr=$err;
		// always returns false!
		return false;
	}

	// throw error
	function err($_exit=true) {
		$message=$this." ".($this->lasterr?"ERROR: ".$this->lasterr:"Undefined error");
		if ($GLOBALS["ajax"] && function_exists("ajax")) ajax(["err"=>$message]);
		echo $text;
		if ($_exit) exit;
	}

	// string with basic info
	function __toString() {
		return "(".get_class($this).") ".($this->sandbox?"SANDBOX":"PRODUCTION");
	}

}

// instance objects via paypal_setup variable
if ($paypal_setup)
	foreach ($paypal_setup as $_n=>$_v)
		$$_n=new PayPal($_v);
