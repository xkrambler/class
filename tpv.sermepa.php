<?php if (!class_exists("TPV")) die();

/*

	Gestión de TPV SERMEPA/4B

	Tarjeta OK:
		4548812049400004 12/20 CVV2: 123 CIP 123456
	Tarjeta KO:
		5576440022788500 12/20 CVV2: 123 CIP 123456

*/
class TPVSERMEPA extends TPV {

	// configuración
	function __construct($o=array()) {
		$this->defaults(array(
			'URL'=>'https://sis.redsys.es/sis/realizarPago',
			'TestURL'=>'https://sis-t.redsys.es:25443/sis/realizarPago',
			'moneda'=>'978',
			'terminal'=>'001',
			'idioma'=>$this->langToIdioma(),
		));
		if (!$o["idioma"] && $o["lang"]) $o["idioma"]=$this->langToIdioma($o["lang"]);
		$this->setup($o);
	}

	function api() {
		return ($this->test()
			?'https://sis-t.redsys.es:25443/sis/'
			:'https://sis.redsys.es/sis/'
		);
	}

	// tipo de TPV
	function type() {
		return "SERMEPA";
	}

	// traducir una cantidad al tipo SERMEPA
	function amountTranslate($amount) {
		return round($amount*100);
	}

	// convertir formato de idioma
	function langToIdioma($lang=null) {
		$langs=array(
			"es"=>"001",
			"en"=>"002",
			"fr"=>"004",
			"it"=>"007",
		);
		$c=$langs[($lang?$lang:x::page("lang"))];
		return ($c?$c:"001");
	}

	// obtener/establecer localizador
	function localizador($v=null) {
		if ($v !== null) $this->operation["id"]=$v;
		return str_pad($this->operation["id"], 10, "0", STR_PAD_LEFT).$this->operation["tipo"];
	}

	// obtener valores del formulario
	function getFormValues() {
		if ($this->setup["secret_key256"]) {
			$fields=array(
				"DS_MERCHANT_AMOUNT"=>$this->amountTranslate($this->operation["total"]),
				"DS_MERCHANT_ORDER"=>$this->localizador(),
				"DS_MERCHANT_MERCHANTCODE"=>$this->setup["clave_comercio"],
				"DS_MERCHANT_CURRENCY"=>$this->setup["moneda"],
				"DS_MERCHANT_TRANSACTIONTYPE"=>'0',
				"DS_MERCHANT_TERMINAL"=>$this->setup["terminal"],
				"DS_MERCHANT_MERCHANTURL"=>$this->urlnotify(),
				"DS_MERCHANT_URLOK"=>$this->urlok(),
				"DS_MERCHANT_URLKO"=>$this->urlko(),
			);
			if ($v=$this->setup["comercio"]) $fields["DS_MERCHANT_MERCHANTNAME"]=$v;
			if (($v=$this->setup["titular"]) || ($v=$this->setup["cliente"])) $fields["DS_MERCHANT_TITULAR"]=$v;
			if ($this->setup["bizum"]) {
				$fields["DS_MERCHANT_PAYMETHODS"]="z"; // BIZUM
			} else if (
				($pan=$this->setup["pan"])
				&& ($expirydate=$this->setup["expirydate"])
				&& ($cvv2=$this->setup["cvv2"])
			) {
				$fields=array_merge($fields, array(
					"DS_MERCHANT_PAYMETHODS"=>"C",
					"DS_MERCHANT_PAN"       =>$pan,
					"DS_MERCHANT_EXPIRYDATE"=>substr($expirydate, 2, 2).substr($expirydate, 0, 2),
					"DS_MERCHANT_CVV2"      =>$cvv2,
				));
			}
			$fields=array(
				//"fields"=>$fields,
				"Ds_SignatureVersion"=>'HMAC_SHA256_V1',
				"Ds_MerchantParameters"=>base64_encode(json_encode($fields)),
				"Ds_Signature"=>$this->getSignature($fields),
			);
		} else if ($this->setup["secret_key"]) {
			$fields=array(
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

	// vergüenza de funciones hechas por un gañán para in-necesidades
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
		if ($url !== null) $this->set("notify", $url);
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
	function checkNotify($tpv=null) {
		if (!$this->isNotify()) return false;
		if ($tpv && !$this->checkOperation($tpv)) return false;
		if ($this->setup["secret_key256"]) {
			if ($_POST["Ds_SignatureVersion"] != 'HMAC_SHA256_V1') return array("err"=>"Ds_SignatureVersion not valid");
			if (!$_POST["Ds_MerchantParameters"]) return array("err"=>"Ds_MerchantParameters not set");
			$parameters=$this->getNotifyFields($_POST["Ds_MerchantParameters"]);
			$signature=$_POST["Ds_Signature"];
			$signature_calc=$this->getNotifySignature($_POST["Ds_MerchantParameters"]);
			$response=$parameters["Ds_Response"];
			if ($signature == $signature_calc) {
				if ($parameters["Ds_ErrorCode"]) return array("err"=>"Ds_ErrorCode present (".strip_tags($parameters["Ds_ErrorCode"]).")","parameters"=>$parameters);
				if ($this->isOK($response)) return array("ok"=>true, "parameters"=>$parameters);
				$err=$this->responsecode($response);
				return array("err"=>($err?$err:"Ds_Response not 0000 (".strip_tags($response).")"), "parameters"=>$parameters);
			}
			return array("err"=>"Ds_Signature verified NOT OK (".strip_tags($signature)."!=".strip_tags($signature_calc).")");
		} else if ($this->setup["secret_key"]) {
			if ($this->isOK($_REQUEST["Ds_Response"])) return array("ok"=>true);
			$err=$this->responsecode($_REQUEST["Ds_Response"]);
			return array("err"=>($err?$err:"Ds_Response not valid (".strip_tags($_REQUEST["Ds_Response"]).")"));
		}
		$this->lasterr='No secret keys defined.';
		return null;
	}

	// comprobar si respuesta es correcta
	function isOK($ds_response) {
		return ($ds_response >= "0000" && $ds_response <= "0099");
	}

	// comprobar estado de la operación de base de datos para evitar sobreescritura de eventos en notify
	function checkOperation($tpv) {
		return ($tpv["estado"] == "START");
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

	// firmar respuesta SOAP
	function getSignatureSOAP($response, $ds_merchant_order) {
		if ($this->setup["secret_key256"]) {
			$key=base64_decode($this->setup["secret_key256"]);
			$key=$this->encrypt3DES($ds_merchant_order, $key); // Se diversifica la clave con el Número de Pedido
			$res=$this->mac256($response, $key); // MAC256 del parámetro Ds_MerchantParameters
			//file_put_contents("sermepafirma.txt",$this->setup["secret_key256"]."-".$response."-".$ds_merchant_order."-".base64_encode($res));
			return base64_encode($res); // Se codifican los datos Base64
		}
	}

	// devolver mensaje satisfactorio SOAP
	function getSuccessSOAP($ds_merchant_order){
		$cabecera="<?xml version=\"1.0\" encoding=\"UTF-8\"?><SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"><SOAP-ENV:Body><ns1:procesaNotificacionSIS xmlns:ns1=\"InotificacionSIS\" SOAP-ENV:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\"><XML xsi:type=\"xsd:string\"><![CDATA[";
		$datos="<Response Ds_Version=\"0.0\"><Ds_Response_Merchant>OK</Ds_Response_Merchant></Response>";
		$firma=$this->getSignatureSOAP("<Response Ds_Version=\"0.0\"><Ds_Response_Merchant>OK</Ds_Response_Merchant></Response>", $ds_merchant_order);
		$res=$cabecera."<Message>".$datos."<Signature>".$firma."</Signature></Message>]]></XML></ns1:procesaNotificacionSIS></SOAP-ENV:Body></SOAP-ENV:Envelope>";
		return $res;
	}

	// devolver respuesta por código
	function responsecode($code) {
		$codes=$this->responsecodes();
		$response=$codes[intval($code)];
		if (!$response && $this->isOK($code)) $response="Transacción autorizada para pagos y preautorizaciones";
		return $response;
	}

	// cancelar operación
	function cancelOperation($tpv_id) {
		if (!function_exists("curl_init")) return $this->error("cancelOperation: CURL no habilitado.");
		if (!function_exists("simplexml_load_string")) return $this->error("cancelOperation: SimpleXML no habilitado.");

		// obtener operación por identificador
		if (!($operacion=$this->dbGet($tpv_id))) return $this->error("cancelOperation: operación ".$tpv_id." no encontrada.");

		// obtener operación, solicitud realizada y preparar campos
		if (!($resultado=json_decode($operacion["resultado"], true))) return $this->error("cancelOperation: no se puede decodificar notificacion.");
		$fields=array();
		foreach ($_fields=array(
			"DS_MERCHANT_AMOUNT"      =>"Ds_Amount",
			"DS_MERCHANT_CURRENCY"    =>"Ds_Currency",
			"DS_MERCHANT_MERCHANTCODE"=>"Ds_MerchantCode",
			"DS_MERCHANT_ORDER"       =>"Ds_Order",
			"DS_MERCHANT_TERMINAL"    =>"Ds_Terminal",
		) as $f=>$p)
			$fields[$f]=$resultado["parameters"][$p];

		// añadir tipo de transacción
		$fields["DS_MERCHANT_TRANSACTIONTYPE"]="45";

		// empaquetar y firmar
		$fields=array(
			"Ds_SignatureVersion"=>'HMAC_SHA256_V1',
			"Ds_MerchantParameters"=>base64_encode(json_encode($fields)),
			"Ds_Signature"=>$this->getSignature($fields),
		);

		// preparar y lanzar CURL
		$ch=curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL=>$this->api()."rest/trataPeticionREST",
			CURLOPT_POST=>1,
			CURLOPT_FRESH_CONNECT=>1,
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_FORBID_REUSE=>1,
			CURLOPT_POSTFIELDS=>http_build_query($fields),
			CURLOPT_HTTPHEADER=>array('Content-Type: application/x-www-form-urlencoded'),
			CURLINFO_HEADER_OUT=>1,
		));
		$this->cancel_result=curl_exec($ch);
		//echo "<h2>Resultado</h2>".x::entities($this->cancel_result); debug(curl_getinfo($ch));
		curl_close($ch);

		// decodificar JSON inicial
		if (!($cr=json_decode($this->cancel_result, true))) return $this->error("cancelOperation: Error desconocido, salida RAW: ".$this->cancel_result);

		// decodificar parámetros
		if (!($Ds_MerchantParameters=json_decode(base64_decode($cr["Ds_MerchantParameters"]), true))) return $this->error("cancelOperation: No se puede decodificar JSON de Ds_MerchantParameters, salida RAW: ".$this->cancel_result);

		// comprobar respuesta
		if ($Ds_MerchantParameters["Ds_Response"] != "0400") return $this->error("cancelOperation: Respuesta no válida, parámetros: ".json_encode($Ds_MerchantParameters));

		// update TPV entry
		if (!$this->db->query($this->db->sqlupdate($this->table(), array(
			"devolucion"=>$this->db->now(),
			"estado"=>"RETURN",
		), array("id"=>$tpv_id)))) return $this->dbErr();

		// OK
		return true;

	}

	// mensajes de respuestas de TPV consultables por responsecode("codigo")
	function responsecodes() {
		return array(
			'101'=>'Tarjeta caducada',
			'104'=>'Operación no permitida para esa tarjeta o terminal',
			'116'=>"Disponible insuficiente",
			'118'=>"Tarjeta no registrada",
			'106'=>'Tarjeta bloqueada, exceso de pin erróneo',
			'129'=>"Código de seguridad (CVV2/CVC2) incorrecto",
			'180'=>'Tarjeta ajena al servicio',
			'184'=>'El cliente de la operación no se ha autenticado',
			'190'=>'Denegación emisor',
			'190'=>'Denegación sin especificar Motivo',
			'191'=>'Fecha de caducidad errónea',
			'202'=>'Tarjeta en excepción transitoria o bajo sospecha de fraude con retirada de tarjeta',
			'900'=>'Transacción autorizada para devoluciones y confirmaciones',
			'904'=>'Problema con la configuración de su comercio. Dirigirse a la entidad.',
			'912'=>'Emisor no disponible',
			'915'=>'El titular ha cancelado la operación de pago.',
			'9001'=>'Error Interno',
			'9002'=>'Error genérico',
			'9003'=>'Error genérico',
			'9004'=>'Error genérico',
			'9005'=>'Error genérico',
			'9006'=>'Error genérico',
			'9007'=>'El mensaje de petición no es correcto, debe revisar el formato',
			'9008'=>'falta Ds_Merchant_MerchantCode',
			'9009'=>'Error de formato en Ds_Merchant_MerchantCode',
			'9010'=>'Error falta Ds_Merchant_Terminal',
			'9011'=>'Error de formato en Ds_Merchant_Terminal',
			'9012'=>'Error genérico',
			'9013'=>'Error genérico',
			'9014'=>'Error de formato en Ds_Merchant_Order',
			'9015'=>'Error falta Ds_Merchant_Currency',
			'9016'=>'Error de formato en Ds_Merchant_Currency',
			'9018'=>'Falta Ds_Merchant_Amount',
			'9019'=>'Error de formato en Ds_Merchant_Amount',
			'9020'=>'Falta Ds_Merchant_MerchantSignature',
			'9021'=>'La Ds_Merchant_MerchantSignature viene vacía',
			'9022'=>'Error de formato en Ds_Merchant_TransactionType',
			'9023'=>'Ds_Merchant_TransactionType desconocido',
			'9024'=>'El Ds_Merchant_ConsumerLanguage tiene mas de 3 posiciones',
			'9025'=>'Error de formato en Ds_Merchant_ConsumerLanguage',
			'9026'=>'Problema con la configuración',
			'9027'=>'Revisar la moneda que está enviando',
			'9028'=>'Error Comercio / terminal está dado de baja',
			'9029'=>'Que revise como está montando el mensaje',
			'9030'=>'Nos llega un tipo de operación errónea',
			'9031'=>'Nos está llegando un método de pago erróneo',
			'9032'=>'Revisar como está montando el mensaje para la devolución.',
			'9033'=>'El tipo de operación es erróneo',
			'9034'=>'error interno',
			'9035'=>'Error interno al recuperar datos de sesión',
			'9037'=>'El número de teléfono no es válido',
			'9038'=>'Error genérico',
			'9039'=>'Error genérico',
			'9040'=>'El comercio tiene un error en la configuración, tienen que hablar con su entidad.',
			'9041'=>'Error en el cálculo de la firma',
			'9042'=>'Error en el cálculo de la firma',
			'9043'=>'Error genérico',
			'9044'=>'Error genérico',
			'9046'=>'Problema con la configuración del bin de la tarjeta',
			'9047'=>'Error genérico',
			'9048'=>'Error genérico',
			'9049'=>'Error genérico',
			'9050'=>'Error genérico',
			'9051'=>'Error número de pedido repetido',
			'9052'=>'Error genérico',
			'9053'=>'Error genérico',
			'9054'=>'No existe operación sobre la que realizar la devolución',
			'9055'=>'existe más de un pago con el mismo número de pedido',
			'9056'=>'Revisar el estado de la autorización',
			'9057'=>'Que revise el importe que quiere devolver( supera el permitido)',
			'9058'=>'Que revise los datos con los que está validando la confirmación',
			'9059'=>'Revisar que existe esa operación',
			'9060'=>'Revisar que exista la confirmación',
			'9061'=>'Revisar el estado de la preautorización',
			'9062'=>'Que el comercio revise el importe a confirmar.',
			'9063'=>'Que el comercio revise el númer de tarjeta que nos están enviando.',
			'9064'=>'Número de posiciones de la tarjeta incorrecto',
			'9065'=>'El número de tarjeta no es numérico',
			'9066'=>'Error mes de caducidad',
			'9067'=>'El mes de la caducidad no es numérico',
			'9068'=>'El mes de la caducidad no es válido',
			'9069'=>'Año de caducidad no valido',
			'9070'=>'El Año de la caducidad no es numérico',
			'9071'=>'Tarjeta caducada',
			'9072'=>'Operación no anulable',
			'9073'=>'Error en la anulación',
			'9074'=>'Falta Ds_Merchant_Order ( Pedido )',
			'9075'=>'El comercio tiene que revisar cómo está enviando el número de pedido',
			'9077'=>'El comercio tiene que revisar el número de pedido',
			'9078'=>'Por la configuración de los métodos de pago de su comercio no se permiten los pagos con esa tarjeta.',
			'9079'=>'Error genérico',
			'9080'=>'Error genérico',
			'9081'=>'Se ha perdico los datos de la sesión',
			'9082'=>'Error genérico',
			'9083'=>'Error genérico',
			'9084'=>'El valor de Ds_Merchant_Conciliation es nulo.',
			'9085'=>'El valor de Ds_Merchant_Conciliation no es numérico.',
			'9086'=>'El valor de Ds_Merchant_Conciliation no ocupa 6 posiciones.',
			'9087'=>'El valor de Ds_Merchant_Session es nulo.',
			'9088'=>'El comercio tiene que revisar el valor que envía en ese campo.',
			'9089'=>'El valor de caducidad no ocupa 4 posiciones.',
			'9090'=>'Error genérico. Consulte con Soporte.',
			'9091'=>'Error genérico. Consulte con Soporte.',
			'9092'=>'Se ha introducido una caducidad incorrecta.',
			'9093'=>'Denegación emisor',
			'9094'=>'Denegación emisor',
			'9095'=>'Denegación emisor',
			'9096'=>'El formato utilizado para los datos 3DSecure es incorrecto',
			'9097'=>'Valor del campo Ds_Merchant_CComercio no válido',
			'9098'=>'Valor del campo Ds_Merchant_CVentana no válido',
			'9099'=>'Error al interpretar respuesta de autenticación',
			'9103'=>'Error al montar la petición de Autenticación',
			'9104'=>'Comercio con “titular seguro” y titular sin clave de compra segura',
			'9112'=>'Que revise que está enviando en el campo Ds_Merchant_Transacction_Type.',
			'9113'=>'Error interno',
			'9114'=>'Se está realizando la llamada por GET, la tiene que realizar por POST',
			'9115'=>'Que revise los datos de la operación que nos está enviando',
			'9116'=>'La operación sobre la que se desea pagar una cuota no es una operación válida',
			'9117'=>'La operación sobre la que se desea pagar una cuota no está autorizada',
			'9118'=>'Se ha excedido el importe total de las cuotas',
			'9119'=>'Valor del campo Ds_Merchant_DateFrecuency no válido ( Pagos recurrentes)',
			'9120'=>'Valor del campo Ds_Merchant_ChargeExpiryDate no válido',
			'9121'=>'Valor del campo Ds_Merchant_SumTotal no válido',
			'9122'=>'Formato incorrecto del campo Ds_Merchant_DateFrecuency o Ds_Merchant_SumTotal',
			'9123'=>'Se ha excedido la fecha tope para realiza la Transacción',
			'9124'=>'No ha transcurrido la frecuencia mínima en un pago recurrente sucesivo',
			'9125'=>'Error genérico',
			'9126'=>'Operación Duplicada',
			'9127'=>'Error Interno',
			'9128'=>'Error interno',
			'9129'=>'Error, se ha detectado un intento masivo de peticiones desde la ip',
			'9130'=>'Error Interno',
			'9131'=>'Error Interno',
			'9132'=>'La fecha de Confirmación de Autorización no puede superar en mas de 7 dias a la de Preautorización.',
			'9133'=>'La fecha de Confirmación de Autenticación no puede superar en mas de 45 días a la de Autenticacion Previa que el comercio revise la fecha de la Preautenticación',
			'9134'=>'El valor del Ds_MerchantCiers enviado no es válido',
			'9135'=>'Error generando un nuevo valor para el IDETRA',
			'9136'=>'Error al montar el mensaje de notificación',
			'9137'=>'Error al intentar validar la tarjeta como 3DSecure NACIONAL',
			'9138'=>'Error debido a que existe una Regla del ficheros de reglas que evita que se produzca la Autorizacion',
			'9139'=>'pago recurrente inicial está duplicado',
			'9140'=>'Error Interno',
			'9141'=>'Error formato no correcto para 3DSecure',
			'9142'=>'Tiempo excecido para el pago',
			'9151'=>'Error Interno',
			'9169'=>'El valor del campo Ds_Merchant_MatchingData ( Datos de Case) no es valido , que lo revise',
			'9170'=>'Que revise el adquirente que manda en el campo',
			'9171'=>'Que revise el CSB que nos está enviando',
			'9172'=>'El valor del campo PUCE Ds_Merchant_MerchantCode no es válido',
			'9173'=>'Que el comercio revise el campo de la URL OK',
			'9174'=>'Error Interno',
			'9175'=>'Error Interno',
			'9181'=>'Error Interno',
			'9182'=>'Error Interno',
			'9183'=>'Error interno',
			'9184'=>'Error interno',
			'9186'=>'Faltan datos para operación',
			'9187'=>'Error formato( Interno )',
			'9197'=>'Error al obtener los datos de cesta de la compra',
			'9214'=>'Su comercion no permite devoluciones por el tipo de firma ( Completo)',
			'9216'=>'El CVV2 tiene mas de 3 posiciones',
			'9217'=>'Error de formato en el CVV2',
			'9218'=>'El comercio no permite operaciones seguras por las entradas "operaciones" o "WebService"',
			'9219'=>'Se tiene que dirigir a su entidad.',
			'9220'=>'Se tiene que dirigir a su entidad.',
			'9221'=>'El cliente no está introduciendo el CVV2',
			'9222'=>'Existe una anulación asociada a la preautorización',
			'9223'=>'La preautorización que se desea anular no está autorizada',
			'9224'=>'Su comercio no permite anulaciones por no tener la firma ampliada',
			'9225'=>'No existe operación sobre la que realizar la anulación',
			'9226'=>'Error en en los datos de la anulación manual',
			'9227'=>'Que el comercio revise el campo Ds_Merchant_TransactionDate',
			'9228'=>'El tipo de tarjeta no puede realizar pago aplazado',
			'9229'=>'Error con el codigo de aplazamiento',
			'9230'=>'Su comercio no permite pago fraccionado( Consulte a su entidad)',
			'9231'=>'No hay forma de pago aplicable ( Consulte con su entidad)',
			'9232'=>'Forma de pago no disponible',
			'9233'=>'Forma de pago desconocida',
			'9234'=>'Nombre del titular de la cuenta no disponible',
			'9235'=>'Campo Sis_Numero_Entidad no disponible',
			'9236'=>'El campo Sis_Numero_Entidad no tiene la longitud requerida',
			'9237'=>'El campo Sis_Numero_Entidad no es numérico',
			'9238'=>'Campo Sis_Numero_Oficina no disponible',
			'9239'=>'El campo Sis_Numero_Oficina no tiene la longitud requerida',
			'9240'=>'El campo Sis_Numero_Oficina no es numérico',
			'9241'=>'Campo Sis_Numero_DC no disponible',
			'9242'=>'El campo Sis_Numero_DC no tiene la longitud requerida',
			'9243'=>'El campo Sis_Numero_DC no es numérico',
			'9244'=>'Campo Sis_Numero_Cuenta no disponible',
			'9245'=>'El campo Sis_Numero_Cuenta no tiene la longitud requerida',
			'9246'=>'El campo Sis_Numero_Cuenta no es numérico',
			'9247'=>'Dígito de Control de Cuenta Cliente no válido',
			'9248'=>'El comercio no permite pago por domiciliación',
			'9249'=>'Error genérico',
			'9250'=>'Error genérico',
			'9251'=>'No permite transferencias( Consultar con entidad )',
			'9252'=>'Por su configuración no puede enviar la tarjeta. ( Para modificarlo consualtar con la entidad)',
			'9253'=>'No se ha tecleado correctamente la tarjeta.',
			'9254'=>'Se tiene que dirigir a su entidad.',
			'9255'=>'Se tiene que dirigir a su entidad.',
			'9256'=>'El comercio no permite operativa de preautorizacion.',
			'9257'=>'La tarjeta no permite operativa de preautorizacion',
			'9258'=>'Tienen que revisar los datos de la validación',
			'9259'=>'No existe la operacion original para notificar o consultar',
			'9260'=>'Entrada incorrecta al SIS',
			'9261'=>'Se tiene que dirigir a su entidad.',
			'9262'=>'Moneda no permitida para operación de transferencia o domiciliacion',
			'9263'=>'Error calculando datos para procesar operación',
			'9264'=>'Error procesando datos de respuesta recibidos',
			'9265'=>'Error de firma en los datos recibidos',
			'9266'=>'No se pueden recuperar los datos de la operación recibida',
			'9267'=>'La operación no se puede procesar por no existir Codigo Cuenta Cliente',
			'9268'=>'La devolución no se puede procesar por WebService',
			'9269'=>'No se pueden realizar devoluciones de operaciones de domiciliacion no descargadas',
			'9270'=>'El comercio no puede realizar preautorizaciones en diferido',
			'9274'=>'Tipo de operación desconocida o no permitida por esta entrada al SIS',
			'9275'=>'Premio sin IdPremio',
			'9276'=>'Unidades del Premio no numericas.',
			'9277'=>'Error genérico. Consulte con Redsys',
			'9278'=>'Error en el proceso de consulta de premios',
			'9279'=>'El comercio no tiene activada la operativa de fidelización',
			'9280'=>'Se tiene que dirigir a su entidad.',
			'9281'=>'Se tiene que dirigir a su entidad.',
			'9282'=>'Se tiene que dirigir a su entidad.',
			'9283'=>'Se tiene que dirigir a su entidad.',
			'9283'=>'Se tiene que dirigir a su entidad.',
			'9284'=>'No existe operacion sobre la que realizar el Pago Adicional',
			'9285'=>'Tiene más de una operacion sobre la que realizar el Pago Adicional',
			'9286'=>'La operación sobre la que se quiere hacer la operación adicional no esta Aceptada',
			'9287'=>'la Operacion ha sobrepasado el importe para el Pago Adicional.',
			'9288'=>'No se puede realizar otro pago Adicional. se ha superado el numero de pagos',
			'9289'=>'El importe del pago Adicional supera el maximo días permitido.',
			'9290'=>'Se tiene que dirigir a su entidad.',
			'9291'=>'Se tiene que dirigir a su entidad.',
			'9292'=>'Se tiene que dirigir a su entidad.',
			'9293'=>'Se tiene que dirigir a su entidad.',
			'9294'=>'La tarjeta no es privada.',
			'9295'=>'duplicidad de operación. Se puede intentar de nuevo ( 1 minuto )',
			'9296'=>'No se encuentra la operación Tarjeta en Archivo inicial',
			'9297'=>'Número de operaciones sucesivas de Tarjeta en Archivo superado',
			'9298'=>'No puede realizar este tipo de operativa. (Contacte con su entidad)',
			'9299'=>'Error en pago con PayPal',
			'9300'=>'Error en pago con PayPal',
			'9301'=>'Error en pago con PayPal',
			'9302'=>'Moneda no válida para pago con PayPal',
			'9304'=>'No se permite pago fraccionado si la tarjeta no es de FINCONSUM',
			'9305'=>'Revisar la moneda de la operación',
			'9306'=>'Valor de Ds_Merchant_PrepaidCard no válido',
			'9307'=>'Que consulte con su entidad. Operativa de tarjeta regalo no permitida',
			'9308'=>'Tiempo límite para recarga de tarjeta regalo superado',
			'9309'=>'Faltan datos adicionales para realizar la recarga de tarjeta prepago',
			'9310'=>'Valor de Ds_Merchant_Prepaid_Expiry no válido',
			'9311'=>'Error genérico',
			'9319'=>'El comercio no pertenece al grupo especificado en Ds_Merchant_Group',
			'9320'=>'Error generando la referencia',
			'9321'=>'El identificador no está asociado al comercio',
			'9322'=>'Que revise el formato del grupo',
			'9323'=>'Para el tipo de operación F( pago en dos fases) es necesario enviar uno de estos campos. Ds_Merchant_Customer_Mobile o Ds_Merchant_Customer_Mail',
			'9324'=>'Imposible enviar el link al cliente( Que revise la dirección mail)',
			'9326'=>'Se han enviado datos de tarjeta en fase primera de un pago con dos fases',
			'9327'=>'No se ha enviado ni móvil ni email en fase primera de un pago con dos fases',
			'9328'=>'Token de pago en dos fases inválido',
			'9329'=>'No se puede recuperar el Token de pago en dos fases.',
			'9330'=>'Fechas incorrectas de pago dos fases',
			'9331'=>'La operación no tiene un estado válido o no existe.',
			'9332'=>'El importe de la operación original y de la devolución debe ser idéntico',
			'9333'=>'Error en una petición a MasterPass Wallet',
			'9334'=>'Bloqueo por control de Seguridad',
			'9335'=>'El valor del campo Ds_Merchant_Recharge_Commission no es válido',
			'9335'=>'El valor del campo Ds_Merchant_Recharge_Commission no es válido',
			'9336'=>'Error genérico. Consulte con Redsys',
			'9336'=>'Error genérico',
			'9337'=>'Error genérico. Consulte con Redsys',
			'9337'=>'Error interno (iUPAY)',
			'9338'=>'No se encuentra la operación iUPAY',
			'9338'=>'No se encuentra la operación iUPAY',
			'9339'=>'El comercio no dispone de pago iUPAY',
			'9339'=>'El comercio no dispone de pago iUPAY ( Consulte a su entidad)',
			'9340'=>'Respuesta recibida desde iUPAY no válida',
			'9340'=>'Respuesta recibida desde iUPAY no válida',
			'9341'=>'Error genérico. Consulte con Redsys',
			'9341'=>'Error interno (iUPAY)',
			'9342'=>'El comercio no permite realizar operaciones de pago de tributos',
			'9342'=>'El comercio no permite realizar operaciones de pago de tributos',
			'9343'=>'Falta o es incorrecto el parámetro Ds_Merchant_Tax_Reference',
			'9343'=>'Falta o es incorrecto el parámetro Ds_Merchant_Tax_Reference',
			'9344'=>'No se han aceptado las condiciones de las cuotas',
			'9344'=>'El usuario ha elegido aplazar el pago, pero no ha aceptado las condiciones de las cuotas',
			'9345'=>'Se ha elegido un número de plazos incorrecto',
			'9345'=>'Revisar el número de plazos que está enviando.',
			'9346'=>'Error en el formato del campo DS_MERCHANT_PAY_TYPE',
			'9346'=>'Revisar formato en parámetro DS_MERCHANT_PAY_TYPE',
			'9347'=>'El comercio no está configurado para realizar la consulta de BIN.',
			'9347'=>'El comercio no está configurado para realizar la consulta de BIN.',
			'9348'=>'El BIN indicado en la consulta no se reconoce',
			'9348'=>'El BIN indicado en la consulta no se reconoce',
			'9349'=>'Los datos de importe y DCC enviados no coinciden con los registrados en SIS',
			'9349'=>'Los datos de importe y DCC enviados no coinciden con los registrados en SIS',
			'9350'=>'No hay datos DCC registrados en SIS para este número de pedido',
			'9350'=>'No hay datos DCC registrados en SIS para este número de pedido',
			'9351'=>'Autenticación prepago incorrecta',
			'9351'=>'Autenticación prepago incorrecta',
			'9352'=>'El tipo de firma del comercio no permite esta operativa',
			'9352'=>'El tipo de firma no permite esta operativa',
			'9353'=>'El comercio no tiene definida una clave 3DES válida',
			'9353'=>'Clave no válida',
			'9354'=>'Error descifrando petición',
			'9354'=>'Error descifrando petición al SIS',
			'9355'=>'El comercio-terminal enviado en los datos cifrados no coincide con el enviado en la petición',
			'9355'=>'El comercio-terminal enviado en los datos cifrados no coincide con el enviado en la petición',
			'9356'=>'Existen datos de entrada para control de fraude y el comercio no tiene activo control de fraude',
			'9356'=>'El comercio no tiene activo control de fraude ( Consulte con su entidad',
			'9357'=>'El comercio tiene activo control de fraude y no existe campo ds_merchant_merchantscf',
			'9357'=>'El comercio tiene activo control de fraude y no existe campo ds_merchant_merchantscf',
			'9358'=>'La entidad no dispone de pago iUPAY',
			'9358'=>'No dispone de pago iUPAY',
			'9370'=>'Error en formato Scf_Merchant_Nif. Longitud máxima 16',
			'9370'=>'Error en formato Scf_Merchant_Nif. Longitud máxima 16',
			'9371'=>'Error en formato Scf_Merchant_Name. Longitud máxima 30',
			'9371'=>'Error en formato Scf_Merchant_Name. Longitud máxima 30',
			'9372'=>'Error en formato Scf_Merchant_First_Name. Longitud máxima 30',
			'9372'=>'Error en formato Scf_Merchant_First_Name. Longitud máxima 30',
			'9373'=>'Error en formato Scf_Merchant_Last_Name. Longitud máxima 30',
			'9373'=>'Error en formato Scf_Merchant_Last_Name. Longitud máxima 30',
			'9374'=>'Error en formato Scf_Merchant_User. Longitud máxima 45',
			'9374'=>'Error en formato Scf_Merchant_User. Longitud máxima 45',
			'9375'=>'Error en formato Scf_Affinity_Card. Valores posibles "S" o "N". Longitud máxima 1',
			'9375'=>'Error en formato Scf_Affinity_Card. Valores posibles "S" o "N". Longitud máxima 1',
			'9376'=>'Error en formato Scf_Payment_Financed. Valores posibles "S" o "N". Longitud máxima 1',
			'9376'=>'Error en formato Scf_Payment_Financed. Valores posibles "S" o "N". Longitud máxima 1',
			'9377'=>'Error en formato Scf_Ticket_Departure_Point. Longitud máxima 30',
			'9377'=>'Error en formato Scf_Ticket_Departure_Point. Longitud máxima 30',
			'9378'=>'Error en formato Scf_Ticket_Destination. Longitud máxima 30',
			'9378'=>'Error en formato Scf_Ticket_Destination. Longitud máxima 30',
			'9379'=>'Error en formato Scf_Ticket_Departure_Date. Debe tener formato yyyyMMddHHmmss.',
			'9379'=>'Error en formato Scf_Ticket_Departure_Date. Debe tener formato yyyyMMddHHmmss.',
			'9380'=>'Error en formato Scf_Ticket_Num_Passengers. Longitud máxima 1.',
			'9380'=>'Error en formato Scf_Ticket_Num_Passengers. Longitud máxima 1.',
			'9381'=>'Error en formato Scf_Passenger_Dni. Longitud máxima 16.',
			'9381'=>'Error en formato Scf_Passenger_Dni. Longitud máxima 16.',
			'9382'=>'Error en formato Scf_Passenger_Name. Longitud máxima 30.',
			'9382'=>'Error en formato Scf_Passenger_Name. Longitud máxima 30.',
			'9383'=>'Error en formato Scf_Passenger_First_Name. Longitud máxima 30.',
			'9384'=>'Error en formato Scf_Passenger_Last_Name. Longitud máxima 30.',
			'9385'=>'Error en formato Scf_Passenger_Check_Luggage. Valores posibles "S" o "N". Longitud máxima 1.',
			'9386'=>'Error en formato Scf_Passenger_Special_luggage. Valores posibles "S" o "N". Longitud máxima 1.',
			'9387'=>'Error en formato Scf_Passenger_Insurance_Trip. Valores posibles "S" o "N". Longitud máxima 1.',
			'9388'=>'Error en formato Scf_Passenger_Type_Trip. Valores posibles "N" o "I". Longitud máxima 1.',
			'9389'=>'Error en formato Scf_Passenger_Pet. Valores posibles "S" o "N". Longitud máxima 1.',
			'9390'=>'Error en formato Scf_Order_Channel. Valores posibles "M"(móvil), "P"(PC) o "T"(Tablet)',
			'9391'=>'Error en formato Scf_Order_Total_Products. Debe tener formato numérico y longitud máxima de 3.',
			'9392'=>'Error en formato Scf_Order_Different_Products. Debe tener formato numérico y longitud máxima de 3.',
			'9393'=>'Error en formato Scf_Order_Amount. Debe tener formato numérico y longitud máxima de 19.',
			'9394'=>'Error en formato Scf_Order_Max_Amount. Debe tener formato numérico y longitud máxima de 19.',
			'9395'=>'Error en formato Scf_Order_Coupon. Valores posibles "S" o "N"',
			'9396'=>'Error en formato Scf_Order_Show_Type. Debe longitud máxima de 30.',
			'9397'=>'Error en formato Scf_Wallet_Identifier',
			'9398'=>'Error en formato Scf_Wallet_Client_Identifier',
			'9399'=>'Error en formato Scf_Merchant_Ip_Address',
			'9400'=>'Error en formato Scf_Merchant_Proxy',
			'9401'=>'Error en formato Ds_Merchant_Mail_Phone_Number. Debe ser numérico y de longitud máxima 19',
			'9402'=>'Error en llamada a SafetyPay para solicitar token url',
			'9403'=>'Error en proceso de solicitud de token url a SafetyPay',
			'9404'=>'Error en una petición a SafetyPay',
			'9405'=>'Solicitud de token url denegada SAFETYPAY',
			'9406'=>'Se tiene que poner en contacto con su entidad para que revisen la configuración del sector de actividad de su comercio',
			'9407'=>'El importe de la operación supera el máximo permitido para realizar un pago de premio de apuesta(Gambling)',
			'9408'=>'La tarjeta debe de haber operado durante el último año para poder realizar un pago de premio de apuesta (Gambling)',
			'9409'=>'La tarjeta debe ser una Visa o MasterCard nacional para realizar un pago de premio de apuesta (Gambling)',
			'9410'=>'Denegada por el emisor',
			'9411'=>'Error en la configuración del comercio (Remitir a su entidad)',
			'9412'=>'La firma no es correcta',
			'9413'=>'Denegada, consulte con su entidad.',
			'9414'=>'El plan de ventas no es correcto',
			'9415'=>'El tipo de producto no es correcto',
			'9416'=>'Importe no permitido en devolucion',
			'9417'=>'Fecha de devolucion no permitida',
			'9418'=>'No existe plan de ventas vigente',
			'9419'=>'Tipo de cuenta no permitida',
			'9420'=>'El comercio no dispone de formas de pago para esta operación',
			'9421'=>'Tarjeta no permitida. No es producto Agro',
			'9422'=>'Faltan datos para operacion Agro',
			'9423'=>'CNPJ del comecio incorrecto',
			'9424'=>'No se ha encontrado el establecimiento',
			'9425'=>'No se ha encontrado la tarjeta',
			'9426'=>'Enrutamiento no valido para el comercio',
			'9427'=>'La conexion con CECA no ha sido posible',
			'9428'=>'Operacion debito no segura',
			'9429'=>'Error en la versión enviada por el comercio (Ds_SignatureVersion)',
			'9430'=>'Error al decodificar el parámetro Ds_MerchantParameters',
			'9431'=>'Error del objeto JSON que se envía codificado en el parámetro Ds_MerchantParameters',
			'9432'=>'Error FUC del comercio erróneo',
			'9433'=>'Error Terminal del comercio erróneo',
			'9434'=>'Error ausencia de número de pedido en la op. del comercio',
			'9435'=>'Error en el cálculo de la firma',
			'9436'=>'Error en la construcción del elemento padre',
			'9437'=>'Error en la construcción del elemento',
			'9438'=>'Error en la construcción del elemento',
			'9439'=>'Error en la construcción del elemento',
			'9440'=>'Error genérico',
			'9441'=>'Error no tenemos bancos para Mybank',
			'9442'=>'Error genérico',
			'9443'=>'No se permite pago con esta tarjeta',
			'9444'=>'Se está intentando acceder usando firmas antiguas y el comercio está configurado como HMAC SHA256',
			'9445'=>'Error genérico',
			'9446'=>'Es obligatorio indicar la forma de pago',
			'9447'=>'Error, se está utilizando una referencia que se generó con un adquirente distinto al adquirente que la utiliza.',
			'9448'=>'El comercio no tiene el método de pago "Pago DINERS"',
			'9449'=>'Tipo de pago de la operación no permitido para este tipo de tarjeta',
			'9450'=>'Tipo de pago de la operación no permitido para este tipo de tarjeta',
			'9451'=>'Tipo de pago de la operación no permitido para este tipo de tarjeta',
			'9453'=>'No se permiten pagos con ese tipo de tarjeta',
			'9454'=>'No se permiten pagos con ese tipo de tarjeta',
			'9455'=>'No se permiten pagos con ese tipo de tarjeta',
			'9456'=>'No tiene método de pago configurado (Consulte a su entidad)',
			'9457'=>'Error, se aplica el método de pago "MasterCard SecureCode" con Respuesta [VEReq, VERes] = N con tarjeta MasterCard Comercial y el comercio no tiene el método de pago "MasterCard Comercial"',
			'9458'=>'Error, se aplica el método de pago "MasterCard SecureCode" con Respuesta [VEReq, VERes] = U con tarjeta MasterCard Comercial y el comercio no tiene el método de pago "MasterCard Comercial"',
			'9459'=>'No tiene método de pago configurado (Consulte a su entidad)',
			'9460'=>'No tiene método de pago configurado (Consulte a su entidad)',
			'9461'=>'No tiene método de pago configurado (Consulte a su entidad)',
			'9462'=>'Metodo de pago no disponible para conexión HOST to HOST',
			'9463'=>'Metodo de pago no permitido',
			'9464'=>'El comercio no tiene el método de pago "MasterCard Comercial"',
			'9465'=>'No tiene método de pago configurado (Consulte a su entidad)',
			'9466'=>'La referencia que se está utilizando no existe.',
			'9467'=>'La referencia que se está utilizando está dada de baja',
			'9468'=>'Se está utilizando una referencia que se generó con un adquirente distinto al adquirente que la utiliza.',
			'9469'=>'Error, no se ha superado el proceso de fraude MR',
			'9470'=>'Error la solicitud del primer factor ha fallado.',
			'9471'=>'Error en la URL de redirección de solicitud del primer factor.',
			'9472'=>'Error al montar la petición de Autenticación de PPII.',
			'9473'=>'Error la respuesta de la petición de Autenticación de PPII es nula.',
			'9474'=>'Error el statusCode de la respuesta de la petición de Autenticación de PPII es nulo',
			'9475'=>'Error el idOperación de la respuesta de la petición de Autenticación de PPII es nulo',
			'9476'=>'Error tratando la respuesta de la Autenticación de PPII',
			'9477'=>'Error se ha superado el tiempo definido entre el paso 1 y 2 de PPI',
			'9478'=>'Error tratando la respuesta de la Autorización de PPII',
			'9479'=>'Error la respuesta de la petición de Autorización de PPII es nula',
			'9480'=>'Error el statusCode de la respuesta de la petición de Autorización de PPII es nulo.',
			'9481'=>'Error, el comercio no es Payment Facilitator',
			'9482'=>'Error el idOperación de la respuesta de una Autorización OK es nulo o no coincide con el idOp. de la Auth.',
			'9483'=>'Error la respuesta de la petición de devolución de PPII es nula.',
			'9484'=>'Error el statusCode o el idPetición de la respuesta de la petición de Devolución de PPII es nulo.',
			'9485'=>'Error producido por la denegación de la devolución.',
			'9486'=>'Error la respuesta de la petición de consulta de PPII es nula.',
			'9487'=>'El comercio terminal no tiene habilitado el método de pago Paygold.',
			'9488'=>'El comercio no tiene el método de pago "Pago MOTO/Manual" y la operación viene marcada como pago MOTO.',
			'9489'=>'Error de datos. Operacion MPI Externo no permitida',
			'9490'=>'Error de datos. Se reciben parametros MPI Redsys en operacion MPI Externo',
			'9491'=>'Error de datos. SecLevel no permitido en operacion MPI Externo',
			'9492'=>'Error de datos. Se reciben parametros MPI Externo en operacion MPI Redsys',
			'9493'=>'Error de datos. Se reciben parametros de MPI en operacion no segura',
			'9494'=>'FIRMA OBSOLETA',
			'9495'=>'Configuración incorrecta ApplePay o AndroidPay',
			'9496'=>'No tiene dado de alta el método de pago AndroidPay',
			'9497'=>'No tiene dado de alta el método de pago ApplePay',
			'9498'=>'moneda / importe de la operación de ApplePay no coinciden',
			'9499'=>'Error obteniendo claves del comercio en Android/Apple Pay',
			'9500'=>'Error en el DCC Dinámico, se ha modificado la tarjeta.',
			'9501'=>'Error en La validación de datos enviados para genera el Id operación',
			'9502'=>'Error al validar Id Oper',
			'9503'=>'Error al validar el pedido',
			'9504'=>'Error al validar tipo de transacción',
			'9505'=>'Error al validar moneda',
			'9506'=>'Error al validar el importe',
			'9507'=>'Id Oper no tiene vigencia',
			'9508'=>'Error al validar Id Oper',
			'9510'=>'No se permite el envío de datos de tarjeta si se envía ID de operación',
			'9511'=>'Error en la respuesta de consulta de BINES',
			'9515'=>'El comercio tiene activado pago Amex en Perfil.',
			'9516'=>'Error al montar el mensaje de China Union Pay',
			'9517'=>'Error al establecer la clave para China Union Pay',
			'9518'=>'Error al grabar los datos para pago China Union Pay',
			'9519'=>'Mensaje de autenticación erróneo',
			'9520'=>'El mensaje SecurePlus de sesión está vacío',
			'9521'=>'El xml de respuesta viene vacío',
			'9522'=>'No se han recibido parametros en datosentrada',
			'9523'=>'La firma calculada no coincide con la recibida en la respuesta',
			'9524'=>'el resultado de la autenticación 3DSecure MasterCard es PARes="A" o VERes="N" y no recibimos CAVV del emisor',
			'9525'=>'No se puede utilizar la tarjeta privada en este comercio',
			'9526'=>'La tarjeta no es china',
			'9527'=>'Falta el parametro obligatorio DS_MERCHANT_BUYERID',
			'9528'=>'Formato erróneo del parametro DS_MERCHANT_BUYERID en operación Sodexo Brasil',
			'9529'=>'No se permite operación recurrente en pagos con tarjeta Voucher',
			'9530'=>'La fecha de Anulación no puede superar en mas de 7 dias a la de Preautorización.',
			'9531'=>'La fecha de Anulación no puede superar en mas de 72 horas a la de Preautorización diferida',
			'9532'=>'La moneda de la petición no coincide con la devuelta',
			'9533'=>'El importe de la petición no coincide con el devuelto',
			'9534'=>'No se recibe recaudación emisora o referencia del recibo',
			'9535'=>'Pago de tributo fuera de plazo',
			'9536'=>'Tributo ya pagado',
			'9537'=>'Pago de tributo denegado',
			'9538'=>'Rechazo en el pago de tributo',
			'9539'=>'Error en el envío de SMS',
			'9540'=>'El móvil enviado es demasiado largo (más de 12 posiciones)',
			'9541'=>'La referencia enviada es demasiada larga (más de 40 posiciones)',
			'9542'=>'Error genérico. Consulte con Redsys',
			'9543'=>'Error, la tarjeta de la operación es DINERS y el comercio no tiene el método de pago "Pago DINERS" o "Pago Discover No Seguro"',
			'9544'=>'Error, la tarjeta de la operación es DINERS y el comercio no tiene el método de pago "Pago Discover No Seguro"',
			'9545'=>'Error DISCOVER',
			'9546'=>'Error DISCOVER',
			'9547'=>'Error DISCOVER',
			'9548'=>'Error DISCOVER',
			'9549'=>'Error DISCOVER',
			'9550'=>'ERROR en el gestor de envío de los SMS. Consulte con Redsys',
			'9551'=>'ERROR en el proceso de autenticación.',
			'9552'=>'ERROR el resultado de la autenticacion PARes = "U"',
			'9553'=>'ERROR se ha intentado hacer un pago con el método de pago UPI y la tarjeta no es china',
			'9554'=>'ERROR el resultado de la autenticacion para UPI es PARes = "U" y el comercio no tiene métodos de pago no seguros UPI EXPRESSPAY',
			'9555'=>'ERROR la IP de conexión del módulo de administración no esta entre las permitidas.',
			'9556'=>'Se envía pago Tradicional y el comercio no tiene pago Tradicional mundial ni Tradicional UE.',
			'9557'=>'Se envía pago Tarjeta en Archivo y el comercio no tiene pago Tradicional mundial ni Tradicional UE.',
			'9558'=>'ERROR, el formato de la fecha dsMerchantP2FExpiryDate es incorrecto',
			'9559'=>'ERROR el id Operacion de la respuesta en la autenticación PPII es nulo o no se ha obtenido de la autenticación final',
			'9560'=>'ERROR al enviar la notificacion de autenticacion al comercio',
			'9561'=>'ERROR el idOperación de la respuesta de una confirmacion separada OK es nulo o no coincide con el idOp. de la Confirmacion.',
			'9562'=>'ERROR la respuesta de la petición de confirmacion separada de PPII es nula.',
			'9563'=>'ERROR tratando la respuesta de la confirmacion separada de PPII.',
			'9564'=>'ERROR chequeando los importes de DCC antes del envío de la operación a Stratus.',
			'9565'=>'Formato del importe del campo Ds_Merchant_Amount excede del límite permitido.',
			'9566'=>'Error de acceso al nuevo Servidor Criptográfico.',
			'9567'=>'ERROR se ha intentado hacer un pago con una tarjeta china UPI y el comercio no tiene método de pago UPI',
			'9568'=>'Operacion de consulta de tarjeta rechazada, tipo de transacción erróneo',
			'9569'=>'Operacion de consulta de tarjeta rechazada, no se ha informado la tarjeta',
			'9570'=>'Operacion de consulta de tarjeta rechazada, se ha enviado tarjeta y referencia',
			'9571'=>'Operacion de autenticacion rechazada, protocolVersion no indicado',
			'9572'=>'Operacion de autenticacion rechazada, protocolVersion no reconocido',
			'9573'=>'Operacion de autenticacion rechazada, browserAcceptHeader no indicado',
			'9574'=>'Operacion de autenticacion rechazada, browserUserAgent no indicado',
			'9575'=>'Operacion de autenticacion rechazada, browserJavaEnabled no indicado',
			'9576'=>'Operacion de autenticacion rechazada, browserLanguage no indicado',
			'9577'=>'Operacion de autenticacion rechazada, browserColorDepth no indicado',
			'9578'=>'Operacion de autenticacion rechazada, browserScreenHeight no indicado',
			'9579'=>'Operacion de autenticacion rechazada, browserScreenWidth no indicado',
			'9580'=>'Operacion de autenticacion rechazada, browserTZ no indicado',
			'9581'=>'Operacion de autenticacion rechazada, datos DS_MERCHANT_EMV3DS no está indicado o es demasiado grande y no se puede convertir en JSON',
			'9582'=>'Operacion de autenticacion rechazada, threeDSServerTransID no indicado',
			'9583'=>'Operacion de autenticacion rechazada, threeDSCompInd no indicado',
			'9584'=>'Operacion de autenticacion rechazada, notificationURL no indicado',
			'9585'=>'Operacion de autenticacion rechazada, no se pasa la validación de la Tz301 o no existen datos en la Tz301',
			'9586'=>'Operacion de autenticacion rechazada, PARes no indicado',
			'9587'=>'Operacion de autenticacion rechazada, MD no indicado',
			'9588'=>'Operacion de autenticacion rechazada, la versión no coincide entre los mensajes AuthenticationData y ChallengeResponse',
			'9589'=>'Operacion de autenticacion rechazada, respuesta sin CRes',
			'9590'=>'Operacion de autenticacion rechazada, error al desmontar la respuesta CRes',
			'9591'=>'Operacion de autenticacion rechazada, error la respuesta CRes viene sin threeDSServerTransID',
			'9592'=>'Operacion de autenticacion rechazada, error el transStatus del CRes no coincide con el transStatus de la consulta final de la operación',
			'9593'=>'Operacion de autenticacion rechazada, error el transStatus de la consulta final de la operación no está definido',
			'9594'=>'Operacion de autenticacion rechazada, CRes no indicado',
			'9595'=>'El comercio indicado no tiene métodos de pago seguros permitidos en 3DSecure V2',
			'9596'=>'Operacion de consulta de tarjeta rechazada,moneda errónea',
			'9597'=>'Operacion de consulta de tarjeta rechazada,importe erróneo',
			'9598'=>'Autenticación 3DSecure v2 errónea, y no se permite hacer fallback a 3DSecure v1',
			'9599'=>'Error en el proceso de autenticación 3DSecure v2',
			'9600'=>'Error en el proceso de autenticación 3DSecure v2 - Respuesta Areq N',
			'9601'=>'Error en el proceso de autenticación 3DSecure v2 - Respuesta Areq R',
			'9602'=>'Error en el proceso de autenticación 3DSecure v2 - Respuesta Areq U y el comercio no tiene método de pago U',
			'9603'=>'Error en el parámetro DS_MERCHANT_DCC de DCC enviado en operacion H2H (REST y SOAP)',
			'9604'=>'Error en los datos de DCC enviados en el parámetro DS_MERCHANT_DCC en operacion H2H (REST y SOAP)',
			'9605'=>'Error en el parámetro DS_MERCHANT_MPIEXTERNAL enviado en operacion H2H (REST y SOAP)',
			'9606'=>'Error en los datos de MPI enviados en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP)',
			'9607'=>'Error del parámetro TXID de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9608'=>'Error del parámetro CAVV de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9609'=>'Error del parámetro ECI de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9610'=>'Error del parámetro threeDSServerTransID de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9611'=>'Error del parámetro dsTransID de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9612'=>'Error del parámetro authenticacionValue de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9613'=>'Error del parámetro protocolVersion de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9614'=>'Error del parámetro Eci de MPI enviado en el parámetro DS_MERCHANT_MPIEXTERNAL en operacion H2H (REST y SOAP) es erróneo',
			'9615'=>'Error en MPI Externo, marca de tarjeta no permitida en SIS para MPI Externo',
			'9616'=>'Error del parámetro DS_MERCHANT_EXCEP_SCA tiene un valor erróneo',
			'9617'=>'Error del parámetro DS_MERCHANT_EXCEP_SCA es de tipo MIT y no vienen datos de COF o de pago por referencia',
			'9618'=>'Error la exención enviada no está permitida y el comercio no está preparado para autenticar',
			'9619'=>'Se recibe orderReferenceId de Amazon y no está el método de pago configurado',
			'9620'=>'Error la operación de DCC tiene asociado un markUp más alto del permitido, se borran los datos de DCC',
			'9621'=>'El amazonOrderReferenceId no es válido',
			'9622'=>'Error la operación original se hizo sin marca de Nuevo modelo DCC y el comercio está configurado como Nuevo Modelo DCC',
			'9623'=>'Error la operación original se hizo con marca de Nuevo modelo DCC y el comercio no está configurado como Nuevo Modelo DCC',
			'9624'=>'Error la operación original se hizo con marca de Nuevo modelo DCC pero su valor difiere del modelo configurado en el comercio',
			'9625'=>'Error en la anulación del pago, porque ya existe una devolución asociada a ese pago',
			'9626'=>'Error en la devolución del pago, ya existe una anulación de la operación que se desea devolver',
			'9627'=>'El número de referencia o solicitud enviada por CRTM no válida.',
			'9628'=>'Error la operación de viene con datos de 3DSecure y viene por la entrada SERMEPA',
			'9629'=>'Error no existe la operación de confirmación separada sobre la que realizar la anulación',
			'9630'=>'Error en la anulación de confirmación separada, ya existe una devolución asociada a la confirmación separada',
			'9631'=>'Error en la anulación de confirmación separada, ya existe una anulación asociada a la confirmación separada',
			'9632'=>'Error la confirmacion separada sobre la que se desea anular no está autorizada',
			'9633'=>'La fecha de Anulación no puede superar en los días configurados a la confirmacion separada.',
			'9634'=>'Error no existe la operación de pago sobre la que realizar la anulación',
			'9635'=>'Error en la anulación del pago, ya existe una anulación asociada al pago',
			'9636'=>'Error el pago que se desea anular no está autorizado',
			'9637'=>'La fecha de Anulación no puede superar en los días configurados al pago.',
			'9638'=>'Error existe más de una devolución que se quiere anular y no se ha especificado cual.',
			'9639'=>'Error no existe la operación de devolución sobre la que realizar la anulación',
			'9640'=>'Error la confirmacion separada sobre la que se desea anular no está autorizada o ya está anulada',
			'9641'=>'La fecha de Anulación no puede superar en los días configurados a la devolución.',
			'9642'=>'La fecha de la preautorización que se desea reemplazar no puede superar los 30 días de antigüedad',
			'9643'=>'Error al obtener la personalización del comercio',
			'9644'=>'Error en el proceso de autenticación 3DSecure v2 - Se envían datos de la entrada IniciaPetición a la entrada TrataPetición',
			//'9645'=>'',
			//'9646'=>'',
			//'9647'=>'',
			//'9648'=>'',
			//'9649'=>'',
			'9650'=>'Error, la MAC no es correcta en la mensajeria de pago de tributos',
			'9651'=>'Error la exención exige SCA y el comercio no está preparado para autenticar',
			'9652'=>'Error la exención y la configuración del comercio exigen no SCA y el comercio no está configurado para autorizar con dicha marca de tarjeta',
			'9653'=>'Operacion de autenticacion rechazada, browserJavascriptEnabled no indicado',
			'9654'=>'Error, se indican datos de 3RI en Inicia Petición y la versión que se envía en el trataPetición no es 2.2',
			'9655'=>'Error, se indican un valor de Ds_Merchant_3RI_Ind no permitido',
			'9656'=>'Error, se indican un valor Ds_Merchant_3RI_Ind diferentes en el Inicia Petición y en el trataPetición',
			'9657'=>'Error, se indican datos de 3RI pero están incompletos',
			'9658'=>'Error, el parámetro threeRITrasactionID es erróneo o no se encuentran datos de operación original',
			'9659'=>'Error, los datos de FUC y Terminal obtenidos del threeRITrasactionID no corresponden al comercio que envía la operación',
			'9660'=>'3RI',
			'9661'=>'3RI',
			'9662'=>'Error, el comercio no está entre los permitidos para realizar confirmaciones parciales.',
			'9663'=>'No existe datos de Inicia Petición que concuerden con los enviados por el comercio en el mensaje Trata Petición',
			'9664'=>'No se envía el elemento Id Transaccion 3DS Server en el mensaje Trata Petición y dicho elemento existe en el mensaje Inicia Petición',
			'9665'=>'La moneda indicada por el comercio en el mensaje Trata Petición no corresponde con la enviada en el mensaje Inicia Petición',
			'9666'=>'El importe indicado por el comercio en el mensaje Trata Petición no corresponde con el enviado en el mensaje Inicia Petición',
			'9667'=>'El tipo de operación indicado por el comercio en el mensaje Trata Petición no corresponde con el enviado en el mensaje Inicia Petición',
			'9668'=>'La referencia indicada por el comercio en el mensaje Trata Petición no corresponde con la enviada en el mensaje Inicia Petición',
			'9669'=>'El Id Oper Insite indicado por el comercio en el mensaje Trata Petición no corresponde con el enviado en el mensaje Inicia Petición',
			'9670'=>'La tarjeta indicada por el comercio en el mensaje Trata Petición no corresponde con la enviada en el mensaje Inicia Petición',
			'9671'=>'Denegación por TRA Lynx',
			'9672'=>'Bizum. Fallo en la autenticación. Bloqueo tras tres intentos.',
			'9673'=>'Bizum. Operación cancelada. El usuario no desea seguir.',
			'9674'=>'Bizum. Abono rechazado por beneficiario.',
			'9675'=>'Bizum. Cargo rechazado por ordenante.',
			'9676'=>'Bizum. El procesador rechaza la operación.',
			'9677'=>'Bizum. Saldo disponible insuficiente.',
			'9681'=>'Error al insertar los datos de autenticación en una operación con MPI Externo',
			'9682'=>'Error la operación es de tipo Consulta de TRA y el parámetro Ds_Merchant_TRA_Data es erróneo',
			'9683'=>'Error la operación es de tipo Consulta de TRA Fase 1 y falta el parámetro Ds_Merchant_TRA_Type',
			'9684'=>'Error la operación es de tipo Consulta de TRA Fase 1 y el parámetro Ds_Merchant_TRA_Type tiene un valor no permitido',
			'9685'=>'Error la operación es de tipo Consulta de TRA Fase 1 y el perfil del comercio no le permite exención TRA',
			'9686'=>'Error la operación es de tipo Consulta de TRA Fase 1 y la confifguración del comercio no le permite usar el TRA de Redsys',
			'9687'=>'Error la operación es de tipo Consulta de TRA Fase 2 y falta el parámetro Ds_Merchant_TRA_Result o tiene un valor no permitido',
			'9688'=>'Error la operación es de tipo Consulta de TRA Fase 2 y falta el parámetro Ds_Merchant_TRA_Method o tiene un valor erróneo',
			'9689'=>'Error la operación es de tipo Consulta de TRA Fase 2, no existe una operación concreta de Fase 1',
			'9690'=>'Error la operación es de tipo Consulta de TRA Fase 2 y obtenemos un error en la respuesta de Lynx',
			'9700'=>'PayPal a devuelto un KO',
			'9801'=>'Denegada por iUPAY',
			'9899'=>'No están correctamente firmados los datos que nos envían en el Ds_Merchant_Data.',
			'9900'=>'SafetyPay ha devuelto un KO',
			'9909'=>'Error interno',
			'9912'=>'Emisor no disponible',
			'9913'=>'Excepción en el envío SOAP de la notificacion',
			'9914'=>'Respuesta KO en el envío SOAP de la notificacion',
			'9915'=>'Cancelado por el titular',
			'9928'=>'El titular ha cancelado la preautorización',
			'9929'=>'El titular ha cancelado la operación',
			'9930'=>'La transferencia está pendiente',
			'9931'=>'Consulte con su entidad',
			'9932'=>'Denegada por Fraude (LINX)',
			'9933'=>'Denegada por Fraude (LINX)',
			'9934'=>'Denegada ( Consulte con su entidad)',
			'9935'=>'Denegada ( Consulte con su entidad)',
			'9966'=>'BIZUM ha devuelto un KO en la autorización',
			'9992'=>'Solicitud de PAE',
			'9994'=>'No ha seleccionado ninguna tarjeta de la cartera.',
			'9995'=>'Recarga de prepago denegada',
			'9996'=>'No permite la recarga de tarjeta prepago',
			'9997'=>'Con una misma tarjeta hay varios pagos en "vuelo" en el momento que se finaliza uno el resto se deniegan con este código. Esta restricción se realiza por seguridad.',
			'9998'=>'Operación en proceso de solicitud de datos de tarjeta',
			'9999'=>'Operación que ha sido redirigida al emisor a autenticar',
		);
	}

}
