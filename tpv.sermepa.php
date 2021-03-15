<?php

	// TPV SERMEPA/4B
	class TPVSERMEPA extends TPV {
		
		// configuración
		function __construct($o=Array()) {
			$this->defaults(Array(
				'URL'=>'https://sis.redsys.es/sis/realizarPago',
				'TestURL'=>'https://sis-t.redsys.es:25443/sis/realizarPago',
				'moneda'=>'978',
				'terminal'=>'001',
				'idioma'=>$this->langToIdioma(),
			));
			if (!$o["idioma"] && $o["lang"]) $o["idioma"]=$this->langToIdioma($o["lang"]);
			$this->setup($o);
		}
		
		// tipo de TPV
		function type() { return "SERMEPA"; }
		
		// traducir una cantidad al tipo SERMEPA
		function amountTranslate($amount) {
			return round($amount*100);
		}
		
		// convertir formato de idioma
		function langToIdioma($lang=null) {
			global $page;
			if (!$lang) $lang=$page["lang"];
			switch ($lang) {
			case "it": return "007";
			case "fr": return "004";
			case "en": return "002";
			case "es":
			default:   return "001";
			}
			return false;
		}
		
		// obtener/establecer localizador
		function localizador($v=null) {
			if ($v!==null) $this->operation["id"]=$v;
			return str_pad($this->operation["id"], 10, "0", STR_PAD_LEFT).$this->operation["tipo"];
		}

		// obtener valores del formulario
		function getFormValues() {
			if ($this->setup["secret_key256"]) {
				$fields=Array(
					"DS_MERCHANT_AMOUNT"=>$this->amountTranslate($this->operation["total"]),
					"DS_MERCHANT_ORDER"=>$this->localizador(),
					"DS_MERCHANT_MERCHANTCODE"=>$this->setup["clave_comercio"],
					"DS_MERCHANT_CURRENCY"=>$this->setup["moneda"],
					"DS_MERCHANT_TRANSACTIONTYPE"=>0,
					"DS_MERCHANT_TERMINAL"=>$this->setup["terminal"],
					"DS_MERCHANT_MERCHANTURL"=>$this->urlnotify(),
					"DS_MERCHANT_URLOK"=>$this->urlok(),
					"DS_MERCHANT_URLKO"=>$this->urlko(),
				);
				$fields=Array(
					"Ds_SignatureVersion"=>'HMAC_SHA256_V1',
					"Ds_MerchantParameters"=>base64_encode(json_encode($fields)),
					"Ds_Signature"=>$this->getSignature($fields),
				);
			} else if ($this->setup["secret_key"]) {
				$fields=Array(
					"Ds_Merchant_Amount"=>$this->amountTranslate($this->operation["total"]),
					"Ds_Merchant_Currency"=>$this->setup["moneda"],
					"Ds_Merchant_Order"=>$this->localizador(),
					"Ds_Merchant_ProductDescription"=>$this->operation["concepto"],
					"Ds_Merchant_Titular"=>$this->operation["cliente"],
					"Ds_Merchant_MerchantName"=>$this->setup["comercio"],
					"Ds_Merchant_MerchantCode"=>$this->setup["clave_comercio"],
					"Ds_Merchant_MerchantURL"=>$this->urlnotify(),
					"Ds_Merchant_UrlOK"=>$this->urlok(),
					"Ds_Merchant_UrlKO"=>$this->urlko(),
					"Ds_Merchant_ConsumerLanguage"=>$this->setup["idioma"],
					"Ds_Merchant_Terminal"=>$this->setup["terminal"],
					"Ds_Merchant_TransactionType"=>0,
					"Ds_Merchant_MerchantData"=>"",
				);
				$fields["Ds_Merchant_MerchantSignature"]=$this->getSignature($fields);
			} else {
				$this->lasterr='secret_key or secret_key256 required!';
				return false;
			}
			return $fields;
		}

		// encriptar usando 3DES
		private function encrypt3DES($message, $key) {
			$message_padded = $message;
			if (strlen($message_padded) % 8) {
				$message_padded = str_pad($message_padded,
				strlen($message_padded) + 8 - strlen($message_padded) % 8, "\0");
			}
			return openssl_encrypt($message_padded, "des-ede3-cbc", $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, implode(array_map("chr", array(0,0,0,0,0,0,0,0))));
		}

		// firmar MAC256
		function mac256($ent, $key) {
			return hash_hmac('sha256', $ent, $key, true);
		}

		// vergüenza de funciones hechas por un gañán
		function base64_url_encode($input) { return strtr(base64_encode($input), '+/', '-_'); }
		function base64_url_decode($input) { return base64_decode(strtr($input, '-_', '+/')); }
		
		// firmar operación
		function getSignature($fields) {
			if ($this->setup["secret_key256"]) {
				$key=base64_decode($this->setup["secret_key256"]);
				$ent=base64_encode(json_encode($fields)); // Se genera el parámetro Ds_MerchantParameters
				$key=$this->encrypt3DES($fields["DS_MERCHANT_ORDER"], $key); // Se diversifica la clave con el Número de Pedido
				$res=$this->mac256($ent, $key); // MAC256 del parámetro Ds_MerchantParameters
				return base64_encode($res); // Se codifican los datos Base64
			} else if ($this->setup["secret_key"]) {
				//return sha1($this->merchantAmount().$this->localizador().$this->claveComercio().$this->moneda()."0".$this->urlnotify().$this->secretKey());
				$signfields=array('Ds_Merchant_Amount','Ds_Merchant_Order','Ds_Merchant_MerchantCode','Ds_Merchant_Currency');
				$key='';
				foreach ($signfields as $field) {
					if (isset($fields[$field])) $key.=$fields[$field];
					else {
						$this->lasterr='Field <strong>'.$field.'</strong> is empty and is required to create signature key';
						return false;
					}
				}
				return sha1($key."0".$this->urlnotify().$this->setup['secret_key']);
			}
		}
		
		// obtener campos obtenidos en la notificación
		function getNotifyFields($ent) {
			return json_decode($this->base64_url_decode($ent), true);
		}

		// firmar operación de notificación
		function getNotifySignature($ent) {
			if ($this->setup["secret_key256"]) {
				$key=base64_decode($this->setup["secret_key256"]);
				$fields=$this->getNotifyFields($ent);
				$key=$this->encrypt3DES($fields["Ds_Order"], $key); // Se diversifica la clave con el Número de Pedido
				$res=$this->mac256($ent, $key); // MAC256 del parámetro Ds_MerchantParameters
				return $this->base64_url_encode($res); // Se codifican los datos base64_url_encode
			}
			return false; // other methods not allowed
		}
		
		// URL NOTIFY
		function urlnotify($url=null) {
			$this->set("notify", $url);
			return $this->fullurl($this->setup["notify"]?$this->setup["notify"]:"notify".(defined("ALINK_NOEXT")?"":".php"));
		}
		
		// devolver si estamos en una notificación online
		function isNotify() {
			if ($this->setup["secret_key256"]) {
				return ($_REQUEST["Ds_Signature"]?true:false);
			} else if ($this->setup["secret_key"]) {
				return ($_REQUEST["Ds_Response"]?true:false);
			}
			$this->lasterr='No secret keys defined.';
			return null;
		}

		// comprobar notificación online
		function checkNotify() {
			if (!$this->isNotify()) return false;
			if ($this->setup["secret_key256"]) {
				if ($_POST["Ds_SignatureVersion"]!='HMAC_SHA256_V1')
					return Array("err"=>"Ds_SignatureVersion not valid");
				if (!$_POST["Ds_MerchantParameters"])
					return Array("err"=>"Ds_MerchantParameters not set");
				$parameters=$this->getNotifyFields($_POST["Ds_MerchantParameters"]);
				$signature=$_POST["Ds_Signature"];
				$signature_calc=$this->getNotifySignature($_POST["Ds_MerchantParameters"]);
				$response=$parameters["Ds_Response"];
				/*xob();
				echo "---".date("Y-m-d H:i:s")."---\n";
				echo "parameters=\n"; print_r($parameters);
				echo "signature=".$signature."\n";
				echo "signature_calc=".$signature_calc."\n";
				echo "response=".$response."\n";
				print_r($_GET); print_r($_POST);
				file_put_contents("___tpv_sermepa_notify.log", xob()."\n", FILE_APPEND);*/
				if ($signature==$signature_calc) {
					if ($parameters["Ds_ErrorCode"]) return Array("err"=>"Ds_ErrorCode present (".strip_tags($parameters["Ds_ErrorCode"]).")","parameters"=>$parameters);
					if ($response=="0000") return Array("ok"=>true,"parameters"=>$parameters);
					return Array("err"=>"Ds_Response not 0000 (".strip_tags($response).")","parameters"=>$parameters);
				}
				return Array("err"=>"Ds_Signature verified NOT OK (".strip_tags($signature)."!=".strip_tags($signature_calc).")");
			} else if ($this->setup["secret_key"]) {
				if ($_REQUEST["Ds_Response"]=="0000") return Array("ok"=>true);
				return Array("err"=>"Ds_Response not valid (".strip_tags($_REQUEST["Ds_Response"]).")");
			}
			$this->lasterr='No secret keys defined.';
			return null;
		}
		
		// enviar notificación online OK
		function success() {
			echo "OK";
			exit;
		}

		// enviar notificación online KO
		function unsuccess() {
			echo "KO";
			exit;
		}

	}
