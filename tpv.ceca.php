<?php if (!class_exists("TPV")) die();

/*

	Gestión de TPV CECA.

	Tarjetas OK (AAAA=Año en curso):
		5540500001000004 12/AAAA 989
		5020470001370055 12/AAAA 989
		5020080001000006 12/AAAA 989
		4507670001000009 12/AAAA 989
	Tarjeta KO:
		1111111111111111 12/2015 989

*/
class TPVCECA extends TPV {

	// constructor
	function __construct($o) {
		$this->defaults(array(
			'TipoMoneda'=>'978',
			'Exponente'=>'2',
			'Cifrado'=>'SHA2',
			'Idioma'=>'1',
			'Pago_soportado'=>'SSL',
			'URL'=>'https://pgw.ceca.es/cgi-bin/tpv',
			'TestURL'=>'https://tpv.ceca.es/tpvweb/tpv/compra.action',
			'CancelURL'=>'https://comercios.ceca.es/webapp/ConsTpvVirtWeb/ConsTpvVirtS?modo=anularOperacionExt',
			'CancelTestURL'=>'https://democonsolatpvvirtual.ceca.es/webapp/ConsTpvVirtWeb/ConsTpvVirtS?modo=anularOperacionExt',
			'Success'=>'$*$OKY$*$',
		));
		$this->setup($o);
	}

	// tipo de TPV
	function type() {
		return "CECA";
	}

	// traducir una cantidad al tipo CECA
	function amountTranslate($amount) {
		if (empty($amount)) return '000';
		return str_pad(round($amount*100), 3, "0", STR_PAD_LEFT);
	}

	// obtener un valor rellenado con 0 a la izquierda
	function getValuePadded($value, $length) {
		return str_pad($value, $length, '0', STR_PAD_LEFT);
	}

	// obtener valores del formulario
	function getFormValues() {
		$fields=array(
			"MerchantID"=>$this->getValuePadded($this->setup["MerchantID"], 9),
			"AcquirerBIN"=>$this->getValuePadded($this->setup["AcquirerBIN"], 10),
			"TerminalID"=>$this->getValuePadded($this->setup["TerminalID"], 8),
			"TipoMoneda"=>$this->setup["TipoMoneda"],
			"Exponente"=>$this->setup["Exponente"],
			"Cifrado"=>$this->setup["Cifrado"],
			"Pago_soportado"=>$this->setup["Pago_soportado"],
			"Idioma"=>$this->setup["Idioma"],
			"Num_operacion"=>$this->operation["localizador"],
			"Descripcion"=>$this->operation["concepto"],
			"Importe"=>$this->amountTranslate($this->operation["total"]),
			"URL_OK"=>$this->urlok(),
			"URL_NOK"=>$this->urlko(),
		);
		$fields["Firma"]=$this->getSignature($fields);
		return $fields;
	}

	// obtener firma
	function getFirma() {
		$fields=$this->getFormValues();
		return $fields["Firma"];
	}

	// firmar operación
	function getSignature($fields, $signfields=array(
		'MerchantID',
		'AcquirerBIN',
		'TerminalID',
		'Num_operacion',
		'Importe',
		'TipoMoneda',
		'Exponente',
		'Cifrado',
		'URL_OK',
		'URL_NOK',
	)) {
		$key='';
		foreach ($signfields as $field) {
			if (!isset($fields[$field]))
				return $this->error('Field <strong>'.$field.'</strong> is empty and is required to create signature key');
			$key.=$fields[$field];
		}
		return hash('sha256', $this->setup['ClaveCifrado'].$key);
	}

	// firma de la cancelacion
	function getCancelSignature($fields) {
		return $this->getSignature($fields, array(
			'MerchantID',
			'AcquirerBIN',
			'TerminalID',
			'Num_operacion',
			'Importe',
			'TipoMoneda',
			'Exponente',
			'Referencia',
			'Cifrado', // fijo SHA2
		));
	}

	// comprobar firma de la operación
	function getNotifySignature($fields) {
		return $this->getSignature($fields, array(
			'MerchantID',
			'AcquirerBIN',
			'TerminalID',
			'Num_operacion',
			'Importe',
			'TipoMoneda',
			'Exponente',
			'Referencia',
		));
	}

	// devolver código de transacción correcta
	function getSuccess() {
		return $this->setup["Success"];
	}

	// comprobar transacción online
	function checkOnlineNotify() {
		// verificar si hay una operación pendiente
		if (!$this->operation) {
			$this->lasterr='No se ha definido la operación';
			return array("err"=>$this->lasterr, "nooperation"=>true);
		}
		// si no hay firma, no se comprueba la operación
		if (!$_POST['Firma']) return false;
		// calcular la firma
		if (!$signature=$this->getNotifySignature($_POST))
			array("err"=>$this->lasterr, "wrong"=>true);
		// comprobar la firma
		if ($signature !== $_POST['Firma']) {
			$this->lasterr='Signature not valid ('.$signature.' != '.$_POST['Firma'].')';
			return array("err"=>$this->lasterr, "badsign"=>true);
		}
		// devolver información de transacción correcta
		return array(
			"ok"=>true,
			"localizador"=>$_POST['Num_operacion'],
			"referencia"=>$_POST['Referencia'],
			"firma"=>$_POST['Firma'],
		);
	}

	// cancelar operación
	function cancelOperation($tpv_id) {
		if (!function_exists("curl_init")) return $this->error("cancelOperation: CURL no habilitado.");
		if (!function_exists("simplexml_load_string")) return $this->error("cancelOperation: SimpleXML no habilitado.");

		// obtener operación por identificador
		if (!($operacion=$this->dbGet($tpv_id))) return $this->error("cancelOperation: operación ".$tpv_id." no encontrada.");

		// obtener operación, solicitud realizada y preparar campos
		if (!($notificacion=json_decode($operacion["notificacion"], true))) return $this->error("cancelOperation: no se puede decodificar notificacion.");
		$fields=array();
		foreach ($_fields=array(
			"MerchantID",
			"AcquirerBIN",
			"TerminalID",
			"Num_operacion",
			"Importe",
			"TipoMoneda",
			"Exponente",
			"Referencia",
			"Firma",
		) as $f)
			$fields[$f]=$notificacion["_POST"][$f];

		// añadir campo cifrado
		$fields["Cifrado"]="SHA2";

		// el importe debe pasar de 00..00123 a 123 ¿?¿?¿?¿?¿?
		$fields["Importe"]=(string)intval($fields["Importe"]);

		// firmar
		$fields["Firma"]=$this->getCancelSignature($fields);

		// preparar y lanzar CURL
		$ch=curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL=>$this->setup[($this->test()?"CancelTestURL":"CancelURL")],
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

		// parsear salida
		$xml=simplexml_load_string($this->cancel_result);
		$ok=($xml["valor"] == "OK");
		$err=$xml->ERROR_DESC;
		if (!$ok) return $this->error("cancelOperation: ".($err?$err:"Error desconocido, salida RAW: ".$this->cancel_result));

		// update TPV entry
		if (!$this->db->query($this->db->sqlupdate($this->table(), array(
			"devolucion"=>$this->db->now(),
			"estado"=>"RETURN",
		), array("id"=>$tpv_id)))) return $this->dbErr();

		// OK
		return true;

	}

}
