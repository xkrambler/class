<?php

	class PayPal {

		protected $o;
		protected $db=false;
		protected $pperror=false;

		function __construct($o) {
			$this->o=$o;
			if (!$this->o["ppurl"])
				$this->o["ppurl"]=($this->o["sandbox"]
					?'https://www.sandbox.paypal.com/cgi-bin/webscr'
					:'https://www.paypal.com/cgi-bin/webscr'
				);
		}

		function __get($n) { return $this->o[$n]; }
		function __set($n, $v) { $this->o[$n]=$v; }

		function get() { return $this->o; }
		function set($o=Array()) { if ($o) foreach ($o as $n=>$v) $this->o[$n]=$v; }
		function db($v=null) { if ($v!==null) $this->db=$v; return $this->db; }

		function entities($v) { return htmlentities($v,null,"UTF-8"); }

		// get/set tabla de trabajo
		function table($table=null) {
			if ($table!==null) $this->o["table"]=$table;
			return ($this->o["table"]?$this->o["table"]:"paypal");
		}

		// realizar una petición a la pasarela de PayPal
		function ppRequest($method, $fields) {

			// suprimir último error
			unset($this->pperror);

			// API URL
			$apiurl=($this->o["sandbox"]
				?"https://api-3t.sandbox.paypal.com/nvp"
				:"https://api-3t.paypal.com/nvp"
			);

			// set additional fields to the request: method, version and authentication
			$fields=array_merge(array(
				"METHOD"=>$method,
				"VERSION"=>"109.0",
				"USER"=>$this->o["apiusername"],
				"PWD"=>$this->o["apipassword"],
				"SIGNATURE"=>$this->o["apisignature"],
			),$fields);

			// inicializar, definir y lanzar petición CURL
			$ch=curl_init();
			curl_setopt_array($ch, Array(
				CURLOPT_URL=>$apiurl,
				CURLOPT_VERBOSE=>1,
				CURLOPT_SSL_VERIFYPEER=>FALSE,
				CURLOPT_SSL_VERIFYHOST=>FALSE,
				CURLOPT_RETURNTRANSFER=>1,
				CURLOPT_POST=>1,
				CURLOPT_POSTFIELDS=>http_build_query($fields)
			));
			if (!$out=curl_exec($ch))
				die($method." failed: ".curl_error($ch).'('.curl_errno($ch).')');

			// parsear resultado
			parse_str($out, $res);
			if (!$res || !$res["ACK"]) {
				$this->pperror="La respuesta HTTP ha sido incorrecta";
				return false;
			}
			if ($res["ACK"]=="Failure") {
				$this->pperror=$res["L_SEVERITYCODE0"]." #".$res["L_ERRORCODE0"].": ".$res["L_SHORTMESSAGE0"].": ".$res["L_LONGMESSAGE0"];
				return false;
			}

			// preparar URL de redirección con token
			if ($res["TOKEN"]) {
				$res["_redirect"]=($this->o["sandbox"]
					?"https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=".$res["TOKEN"]
					:"https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=".$res["TOKEN"]
				);
			}

			// devolver resultado
			return $res;

		}

		// detener ejecución de pasarela si error
		function err() {
			?><h1>PayPal Error</h1>
			<p><b><?=($this->pperror?$this->pperror:"Error no definido.")?></b></p>
			<p>Proceso detenido por seguridad.</p><?
			exit;
		}

		// obtener parcial de los items
		function parcial() {
			$parcial=0;
			if ($this->o["items"])
				foreach ($this->o["items"] as $item_index=>$item)
					$parcial+=($item["unidades"]*$item["precio"]);
			return round($parcial,2);
		}

		// obtener total completo
		function total() {
			if (!$this->o["impuestos"]) $this->o["impuestos"]=0;
			if (!$this->o["envio"]) $this->o["envio"]=0;
			if (!$this->o["envioDescuento"]) $this->o["envioDescuento"]=0;
			if (!$this->o["gestion"]) $this->o["gestion"]=0;
			if (!$this->o["seguro"]) $this->o["seguro"]=0;
			$total=(
				$this->parcial()
				+$this->o["impuestos"]
				+$this->o["envio"]
				+$this->o["gestion"]
				+$this->o["envioDescuento"]
				+$this->o["seguro"]
			);
			return round($total, 2);
		}

		// iniciar transacción de pago
		function start() {
			$this->pperror="Error crítico: Parámetros incorrectos";
			if (!$this->o["item_id"]) return false;
			if (!$this->o["items"]) return false;
			if (!$this->o["moneda"]) return false;
			unset($this->pperror);
			// limpiar sesión
			$_SESSION["paypal"]=Array();
			// obtener total
			$total=$this->total();
			if ($total<=0) {
				$this->pperror="Error crítico: El total de los items a pagar es 0";
				return false;
			}
			// campos Express Checkout
			$fields=Array(
				"RETURNURL"=>$this->o["ok"],
				"CANCELURL"=>$this->o["ko"],
				"PAYMENTREQUEST_0_PAYMENTACTION"=>"SALE",
			);
			foreach ($this->o["items"] as $item_index=>$item) {
				$fields=array_merge($fields,Array(
					"L_PAYMENTREQUEST_0_NUMBER".$item_index=>($item_index+1),
					"L_PAYMENTREQUEST_0_NAME".$item_index  =>$item["concepto"],
					"L_PAYMENTREQUEST_0_DESC".$item_index  =>$item["descripcion"],
					"L_PAYMENTREQUEST_0_QTY".$item_index   =>$item["unidades"],
					"L_PAYMENTREQUEST_0_AMT".$item_index   =>$item["precio"],
				));
			}
			$fields=array_merge($fields,Array(
				"PAYMENTREQUEST_0_ITEMAMT"=>$this->parcial(),
				"PAYMENTREQUEST_0_TAXAMT"=>$this->o["impuestos"],
				"PAYMENTREQUEST_0_SHIPPINGAMT"=>$this->o["envio"],
				"PAYMENTREQUEST_0_SHIPDISCAMT"=>$this->o["envioDescuento"],
				"PAYMENTREQUEST_0_HANDLINGAMT"=>$this->o["gestion"],
				"PAYMENTREQUEST_0_INSURANCEAMT"=>$this->o["seguro"],
				"PAYMENTREQUEST_0_AMT"=>$total,
				"PAYMENTREQUEST_0_CURRENCYCODE"=>$this->o["moneda"],
				"ALLOWNOTE"=>0, // no permitir anotaciones
				"NOSHIPPING"=>1, //set 1 to hide buyer's shipping address, in-case products that does not require shipping
				/* 
				//Override the buyer's shipping address stored on PayPal, The buyer cannot edit the overridden address.
				'&ADDROVERRIDE=1'.
				'&PAYMENTREQUEST_0_SHIPTONAME=J Smith'.
				'&PAYMENTREQUEST_0_SHIPTOSTREET=1 Main St'.
				'&PAYMENTREQUEST_0_SHIPTOCITY=San Jose'.
				'&PAYMENTREQUEST_0_SHIPTOSTATE=CA'.
				'&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=US'.
				'&PAYMENTREQUEST_0_SHIPTOZIP=95131'.
				'&PAYMENTREQUEST_0_SHIPTOPHONENUM=408-967-4444'.
				*/
			));
			if ($this->o["logoimg"]) $fields["LOGOIMG"]=$this->o["logoimg"];
			if ($this->o["location"]) $fields["LOCALECODE"]=$this->o["location"];
			if ($this->o["border"]) $fields["CARTBORDERCOLOR"]=$this->o["border"];
			// crear entrada de registro
			if ($this->db) {
				if (!$this->db->query($this->db->sqlinsert($this->table(), Array(
					"fecha"=>$this->db->now(),
					"modificacion"=>$this->db->now(),
					"estado"=>"START",
					"total"=>$total,
					"item_id"=>$this->o["item_id"],
					"token"=>null,
					"payerid"=>null,
					"localizador"=>$this->o["localizador"],
					"cliente"=>($this->o["cliente"]?$this->o["cliente"]:""),
					"email"=>($this->o["email"]?$this->o["email"]:""),
					"items"=>serialize($this->o["items"]),
					"peticion"=>serialize($fields),
					"resultado"=>"",
				)))) $this->db->err();
				$paypal_id=$this->db->lastid();
				if (!$this->db->commit()) $this->db->err();
			}
			// crear Express Checkout para obtener token
			if (!$res=$this->ppRequest('SetExpressCheckout', $fields)) $this->err();
			// ver respuesta
			switch ($res["ACK"]) {
			case "Success":
			case "SuccessWithWarning":
				if (!$res["TOKEN"]) {
					$this->pperror="Error crítico: Success sin token generado.";
					return false;
				}
				if (!$res["_redirect"]) {
					$this->pperror="Error crítico: Redirect no generado.";
					return false;
				}
				// actualizar registro
				if ($this->db) {
					if (!$this->db->query($this->db->sqlupdate($this->table(), array(
						"modificacion"=>$this->db->now(),
						"estado"=>"TOKEN",
						"token"=>$res["TOKEN"],
						"resultado"=>serialize($res),
					),Array(
						"id"=>$paypal_id,
					)))) $this->db->err();
					if (!$this->db->commit()) $this->db->err();
				}
				// guardar sesión y redireccionar a PayPal
				$_SESSION["paypal"][$res["TOKEN"]]=$fields;
				header('Location: '.$res["_redirect"]);
				exit;
			default:
				$this->pperror="Respuesta no aceptada.";
				return false;
			}
		}

		// realiza el cobro efectivo dado un token y un identificador de pago
		function process() {
			// obtener token e identificador de pago
			$token=$_GET["token"];
			$payerid=$_GET["PayerID"];
			if ($token && $payerid) {
				// comprobar que el token está en sesión
				if (!$fields=$_SESSION["paypal"][$token]) {
					$this->pperror="Token no encontrado, quizás la sesión ha expirado.";
					return false;
				}
				// campos adicionales
				$fields["TOKEN"]=$token;
				$fields["PAYERID"]=$payerid;
				// actualizar registro
				if ($this->db) {
					if (!$this->db->query($this->db->sqlupdate($this->table(), array(
						"modificacion"=>$this->db->now(),
						"estado"=>"PROCESS",
						"payerid"=>$payerid,
					),Array(
						"token"=>$token,
						"estado"=>"TOKEN",
					)))) $this->db->err();
					if (!$this->db->commit()) $this->db->err();
				}
				// realizar cobro
				$dopay=false;
				if (!$_SESSION["paypal"][$token]["payment"]) {
					if (!$res=$this->ppRequest('DoExpressCheckoutPayment', $fields)) $this->err();
					switch ($res["ACK"]) {
					case "Success":
					case "SuccessWithWarning":
						$dopay=date("Y-m-d H:i:s");
						$_SESSION["paypal"][$token]["payment"]=$res;
						break;
					default:
						// actualizar registro
						if ($this->db) {
							if (!$this->db->query($this->db->sqlupdate($this->table(), array(
								"modificacion"=>$this->db->now(),
								"estado"=>"KO",
								"resultado"=>serialize($res),
							),Array(
								"token"=>$token,
							)))) $this->db->err();
							if (!$this->db->commit()) $this->db->err();
						}
						// devolver error
						$this->pperror="Respuesta no aceptada.";
						return false;
					}
				}
				// obtener información del cobro
				if ($payment=$_SESSION["paypal"][$token]["payment"]) {
					$res=Array(
						"dopay"=>$dopay,
						"status"=>$payment["PAYMENTINFO_0_PAYMENTSTATUS"],
						"payment"=>$payment,
						"details"=>$this->details($token),
					);
					// actualizar registro
					if ($this->db && $dopay) {
						if (!$this->db->query($this->db->sqlupdate($this->table(), array(
							"modificacion"=>$this->db->now(),
							"estado"=>"OK",
							"resultado"=>serialize($res),
						),Array(
							"token"=>$token,
						)))) $this->db->err();
						if (!$this->db->commit()) $this->db->err();
					}
					// devolver resultados
					return $res;
				}
				// actualizar registro
				if ($this->db) {
					if (!$this->db->query($this->db->sqlupdate($this->table(), array(
						"modificacion"=>$this->db->now(),
						"estado"=>"KO",
						"resultado"=>serialize($res),
					),Array(
						"token"=>$token,
					)))) $this->db->err();
					if (!$this->db->commit()) $this->db->err();
				}
				// devolver error
				$this->pperror=$res["L_SEVERITYCODE0"]." #".$res["L_ERRORCODE0"].": ".$res["L_SHORTMESSAGE0"].": ".$res["L_LONGMESSAGE0"];
				return false;
			}
			$this->pperror="Token no reconocido: Fuera de orden.";
			return false;
		}

		// cancelación
		function cancel() {
			// obtener token
			$token=$_GET["token"];
			if ($token) {
				// si no ha sido cancelado...
				if (!$_SESSION["paypal"][$token]["canceled"]) {
					// marcar como cancelado
					$_SESSION["paypal"][$token]["canceled"]=true;
					// actualizar registro
					if ($this->db) {
						if (!$this->db->query($this->db->sqlupdate($this->table(), array(
							"modificacion"=>$this->db->now(),
							"estado"=>"KO",
							"payerid"=>$payerid,
						),Array(
							"token"=>$token,
						)))) $this->db->err();
						if (!$this->db->commit()) $this->db->err();
					}
					return true;
				}
			}
			// en otro caso, ya había sido cancelado previamente
			return false;
		}

		// obtener detalles de una transacción
		function details($token) {
			$fields=Array("TOKEN"=>$token);
			if (!$res=$this->ppRequest('GetExpressCheckoutDetails', $fields)) $this->err();
			switch ($res["ACK"]) {
			case "Success":
			case "SuccessWithWarning":
				return $res;
			}
			$this->pperror=$res["L_SEVERITYCODE0"]." #".$res["L_ERRORCODE0"].": ".$res["L_SHORTMESSAGE0"].": ".$res["L_LONGMESSAGE0"];
			return false;
		}

	}

	if ($paypal_setup)
		foreach ($paypal_setup as $n=>$v)
			$$n=new PayPal($v);
