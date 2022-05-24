<?php

// base class for manage TPV
abstract class TPV {

	protected $defaults=array(
		"test"=>false,
		"table"=>"tpv",
	);
	protected $setup=false;
	protected $lasterr=false;
	protected $operation=false;

	// getter/setter/isset
	function __get($n) { return $this->setup[$n]; }
	function __set($n, $v) { $this->setup[$n]=$v; }
	function __isset($n) { return isset($this->setup[$n]); }

	// TPV type
	function type() { return "unknown"; }

	// get/set defaults
	function defaults($defaults=null) {
		if ($defaults!==null) {
			$this->defaults=array_merge($this->defaults, $defaults);
			$this->checkDefaults();
		}
		return $this->defaults;
	}

	// check and set default values
	function checkDefaults() {
		foreach ($this->defaults as $n=>$v)
			if (!isset($this->setup[$n]))
				$this->setup[$n]=$v;
	}

	// get/set setup
	function setup($setup=null) {
		if ($setup!==null) {
			$this->setup=$setup;
			$this->checkDefaults();
		}
		return $this->setup;
	}

	// get option
	function get($option=null) {
		return $this->setup[$option];
	}

	// set option or options
	function set($o=null, $v=null) {
		if (is_array($o)) {
			foreach ($o as $n=>$v)
				$this->setup[$n]=$v;
		} else if (is_string($o) && $v!==null)
			$this->setup[$o]=$v;
	}

	// unset options
	function del($option=null) {
		if (!is_array($option)) $option=array($option);
		if ($option)
			foreach ($option as $v)
				unset($this->setup[$v]);
		$this->checkDefaults();
	}

	// get/set database
	function db($db=null) {
		if ($db !== null) $this->setup["db"]=$db;
		return $this->setup["db"];
	}

	// get/set operation data
	function operation($o=null) {
		if ($o !== null) $this->operation=$o;
		return ($this->operation?$this->operation:[]);
	}

	// get/set operation id
	function localizador($v=null) {
		if ($v!==null) $this->operation["id"]=$v;
		return $this->operation["id"];
	}

	// test mode enable
	function test($enabled=null) {
		if ($enabled!==null) $this->setup["test"]=$enabled;
		return $this->setup["test"];
	}

	// get/set table
	function table($table=null) {
		if ($table !== null) $this->setup["table"]=$table;
		return $this->setup["table"];
	}

	// get/set credit card
	function cc($cc=null) {
		if (is_array($cc)) $this->set([
			"pan"       =>(string)substr(preg_replace("/[^0-9]/", "", $cc["pan"]), 0, 16),
			"expirydate"=>(string)substr(preg_replace("/[^0-9]/", "", $cc["expirydate"]), 0, 4),
			"cvv2"      =>(string)substr(preg_replace("/[^0-9]/", "", $cc["cvv2"]), 0, 3),
		]);
		return [
			"pan"       =>$this->setup["pan"],
			"expirydate"=>$this->setup["expirydate"],
			"cvv2"      =>$this->setup["cvv2"],
		];
	}

	// verify URL
	function fullurl($url) {
		// base vars
		$server="http".($_SERVER["HTTPS"] && $_SERVER["HTTPS"]!="off"?"s":"")."://".$_SERVER["HTTP_HOST"];
		if (isset($_SERVER["REQUEST_URI"])) {
			$requesturi=$_SERVER["REQUEST_URI"];
			if ($i=strpos($requesturi,"?")) $requesturi=substr($requesturi,0,$i);
		} else {
			$requesturi=$_SERVER["SCRIPT_NAME"];
		}
		$baseurl=$server.(($i=strrpos($requesturi,"/"))!==false?substr($requesturi,0,$i+1):"/");
		// if URL is not complete, fill it
		if (strpos($url,"://")===false) {
			// if not root specified, absolute path, and we use $server,
			// any other case, its relative and we use $baseurl
			if (substr($url,0,1)=="/") $url=$server.$url;
			else $url=$baseurl.$url;
		}
		// return full URL
		return $url;
	}

	// URL OK
	function urlok($url=null) {
		$this->set("urlok", $url);
		return $this->fullurl($this->setup["ok"]?$this->setup["ok"]:"ok".(defined("ALINK_NOEXT")?"":".php"));
	}

	// URL OK
	function urlko($url=null) {
		$this->set("urlko", $url);
		return $this->fullurl($this->setup["ko"]?$this->setup["ko"]:"ko".(defined("ALINK_NOEXT")?"":".php"));
	}

	// get Form URL
	function getFormURL() {
		return $this->setup[($this->test()?"TestURL":"URL")];
	}

	// get HTML entities
	function entities($v) {
		return (method_exists("x", "entities")?x::entities($v):htmlentities($v, null, 'UTF-8'));
	}

	// get HTML Form
	function getForm() {
		$fields=$this->getFormValues();
		if ($fields) {
			$html='<form id="tpv_form" action="'.$this->getFormURL().'" method="post">'."\n";
			foreach ($fields as $n=>$v)
				$html.='<input type="hidden" name="'.$this->entities($n).'" value="'.$this->entities($v).'" />'."\n";
			$html.='Se ha detectado JavaScript desactivado: <input id="tpv_form_button" type="submit" value="Continuar con el pago on-line" />'."\n";
			$html.='</form>'."\n";
			return $html;
		}
		return false;
	}

	// send HTML Form via POST
	function sendFormHTML() {
		?><!doctype html>
			<html lang="es">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<title>Por favor, espere...</title>
				<meta name="viewport" content="width=device-width, minimum-scale=1.0, user-scalable=no" />
				<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
				<!-- <all your="base" are="belong/to.us" /> -->
			</head>
			<body>
				<?=$this->getForm()?>
				<script>
					var ko=<?=json_encode($this->ko)?>;
					if (location.hash == "") {
						location.hash='#pay';
						document.getElementById("tpv_form").style.display="none";
						document.getElementById("tpv_form").submit();
					} else {
						if (ko) location.href=ko;
					}
				</script>
			</body>
		</html><?
		exit;
	}

	// send AJAX values to run via JS
	function ajaxValues() {
		return array(
			"type"=>$this->type(),
			"test"=>$this->test(),
			"localizador"=>$this->localizador(),
			"url"=>$this->getFormURL(),
			"form"=>$this->getFormValues(),
			"ok"=>true,
		);
	}

	// AJAX response
	function ajax() {
		ajax($this->ajaxValues());
	}

	// create new database TPV operation entry
	function dbStart($operation) {
		// update operation data
		$operation=array_merge(array(
			"sid"=>session_id(),
			"descripcion"=>"",
			"notificacion"=>"",
			"resultado"=>"",
		), array_merge($this->operation(), $operation));
		// forced operation fields
		$operation["fecha"]=date("Y-m-d H:i:s");
		$operation["estado"]="START";
		// update operation
		$this->operation($operation);
		// create entry
		if (!$this->db->query($this->db->sqlinsert($this->table(), $operation))) $this->dbErr();
		$tpv_id=$this->db->lastid();
		// update locator
		$this->localizador($tpv_id);
		if (!$this->db->query($this->db->sqlupdate($this->table(), array("localizador"=>$this->localizador()), array("id"=>$tpv_id)))) return $this->dbErr();
		// return TPV entry id
		return $tpv_id;
	}

	// get database TPV operation entry
	function dbGet($tpv_id) {
		$db=$this->setup["db"];
		// obtenemos la entrada de TPV
		if (!$db->query("SELECT * FROM ".$this->table()." WHERE id='".$db->escape($tpv_id)."'")) return $this->dbErr();
		if (!$tpv=$db->row()) {
			$this->lasterr="TPV Entry #".intval($tpv_id)." not found.";
			return false;
		}
		// establecer datos de la operación
		$this->operation($tpv);
		// devolver datos
		return $tpv;
	}

	// update TPV entry online notify
	function dbNotifyUpdate($tpv_id) {
		$db=$this->setup["db"];
		if (!$db->query($db->sqlupdate($this->table(), array(
			"notify"=>date("Y-m-d H:i:s"),
			"estado"=>"NOTIFY",
			"notificacion"=>json_encode(array(
				"_GET"=>$_GET,
				"_POST"=>$_POST,
				"_SERVER"=>$_SERVER,
			)),
		), array("id"=>$tpv_id)))) return $this->dbErr();
		// todo OK
		return true;
	}

	// update TPV entry state
	function dbSetEstado($tpv_id, $estado) {
		$db=$this->setup["db"];
		if (!$db->query($db->sqlupdate($this->table(), array(
			"estado"=>$estado,
		), array("id"=>$tpv_id)))) return $this->dbErr();
		// todo OK
		return true;
	}

	// update TPV entry state and result
	function dbSetResultado($tpv_id, $res) {
		$db=$this->setup["db"];
		if (!$db->query($db->sqlupdate($this->table(), array(
			"estado"=>($res["ok"]?"OK":"KO"),
			"resultado"=>json_encode($res),
		), array("id"=>$tpv_id)))) return $this->dbErr();
		// todo OK
		return true;
	}

	// return database error
	function dbErr() {
		$db=$this->setup["db"];
		return $this->error("Database Error #".$db->errnum().": ".$db->error());
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
		echo $this." ".($this->lasterr?"ERROR: ".$this->lasterr:"Exited");
		if ($_exit) exit;
	}

	// devolver respuesta por código
	function responsecode($code) {
		$codes=$this->responsecodes();
		return $codes[(string)$code];
	}

	// mensajes de respuestas de TPV (vacío, sobreescribible)
	function responsecodes() {
		return array();
	}

	// string with basic info
	function __toString() {
		return "(".get_class($this).") TPV-".$this->type().":".($this->test()?"TEST":"PRODUCTION");
	}

}

// instantiate all classes defined in the TPV setup
if (@$tpv_setup) {
	foreach ($tpv_setup as $tpv_name=>$tpv_config) {
		if ($tpv_config["type"]) {
			require_once(__DIR__."/tpv.".strtolower($tpv_config["type"]).".php");
			$tpv_class="TPV".$tpv_config["type"];
			$$tpv_name=new $tpv_class($tpv_config);
		}
	}
}
