<?php

/*

	xForm3
	Form management class, version 3 rev.2

	// form declaration
	$xf=new xForm3([
		"fields"=>[
			"date"  =>["caption"=>"Date","type"=>"date"],
			"text"  =>["caption"=>"Name","type"=>"text","required"=>true,"maxlength"=>50,"width"=>"300px"],
			"select"=>["caption"=>"Selection","type"=>"select","options"=>["A"=>"Option A","B"=>"Option B"]],
			"audio1"=>["caption"=>"Audio","type"=>"audio"],
			"image" =>["caption"=>"One Image","type"=>"image"],
			"images" =>["caption"=>"Images","type"=>"images"],
			"file" =>["caption"=>"One File","type"=>"file"],
			"files" =>["caption"=>"Files","type"=>"files"],
			//"file" =>["caption"=>"One File","type"=>"file","file"=>"data/file.txt"], // load file
			//"file" =>["caption"=>"One File","type"=>"file","file"=>["file"=>"data/file.txt"]], // load file
			//"files" =>["caption"=>"Files","type"=>"files","files"=>[["file"=>"data/file.txt"]]], // load files
		],
		"values"=>[
			"date"=>"2019-11-19",
			"text"=>"example text",
			"select"=>"B",
		],
	]);

	// assign files
	$xf->file("audio1", ["file"=>"file/audio.wav"]);
	$xf->file("image", ["file"=>"file.jpg"]);
	$xf->files("files", [["file"=>"file.jpg"],["name"=>"file.txt","data"=>"Hello world"]]);

	// AJAX actions
	$xf->ajax();

	// validate form
	if (!$xf->validate($adata["values"]))
		foreach ($xf->errors() as $error)
			ajax(["field"=>$error["id"], "warn"=>$error["err"]]);

	// get sent values
	$values=$xf->values();

	// get uploaded files
	$files=$xf->files();

*/
class xForm3 {

	static private $sid;
	protected $o;
	protected $fields;
	protected $errors=array();
	protected $magicMimes=array(
		"image/jpeg"=>array("\xFF\xD8\xFF"),
		"image/gif"=>array("GIF"),
		"image/png"=>array("\x89\x50\x4e\x47\x0d\x0a\x1a\x0a"),
		"image/bmp"=>array("BM"),
		"audio/wav"=>array("RIFFF", "\x56\x45"),
		"audio/mp3"=>array("ID3", "\xFF\xFB"),
	);
	protected $filesLimit=array(
		"audio"=>1,
		"image"=>1,
		"file"=>1,
		"files"=>false,
		"images"=>false,
	);
	protected $trimHTML=["&#9;", "&#10;", "&#13;", "&nbsp;"];

	// constructor and default values
	function __construct($o) {
		self::$sid++;
		$default_class=array(
			"tel"=>"txt",
			"email"=>"txt",
			"text"=>"txt",
			"number"=>"txt",
			"date"=>"txt",
			"time"=>"txt",
			"datetime"=>"txt",
			"password"=>"txt",
			"area"=>"area",
			"checkbox"=>"checkbox",
			"checkboxes"=>"checkbox",
			"radio"=>"radio",
			"select"=>"cmb",
			"color"=>"cmd",
			"image"=>"image",
			"images"=>"images",
			"audio"=>"audio",
			"file"=>"file",
			"files"=>"files",
			"html"=>"html",
			"div"=>"div",
		);
		foreach ($default_class as $f=>$c)
			if (!isset($o["class"][$f]))
				$o["class"][$f]=$c;
		if (!isset($o["name"])) $o["name"]="xf".self::$sid;
		$this->o=$o;
		if (is_array($this->o["ssid"])) $this->o["ssid"]=\x::base().\x::link($this->o["ssid"]);
		if (!isset($this->o["ssid"])) $this->o["ssid"]=\x::base().\x::link();
		$this->fields($o["fields"]); unset($this->o["fields"]);
		// si no es petición AJAX, se asume limpieza de sesión
		if (!$this->isajax()) $this->sstart($this->o["ssid"]);
		// asignar valores iniciales, si especificado
		if ($o["values"]) {
			$this->values($o["values"]);
			unset($this->o["values"]);
		}
	}

	// check if a variable is a hashmap
	private function _is_hash($var) {
		if (!is_array($var)) return false;
		return array_keys($var) !== range(0, sizeof($var)-1);
	}

	// strtolower w/o multibyte
	function strtolower($s) {
		return (function_exists("mb_strtolower")?mb_strtolower($s):(function_exists("strtolower_utf8")?strtolower_utf8($s):strtolower($s)));
	}

	// strtoupper w/o multibyte
	function strtoupper($s) {
		return (function_exists("mb_strtoupper")?mb_strtoupper($s):strtoupper($s));
	}

	// ucwords w/o multibyte
	function ucwords($s) {
		return (function_exists("mb_convert_case")?mb_convert_case($s, MB_CASE_TITLE):ucwords($s));
	}

	// return AJAX action
	function isajax() { return $GLOBALS["ajax"]; }

	// get mime by checking first magic bytes
	function mimeByMagic($bytes) {
		foreach ($this->magicMimes as $mime=>$magics)
			foreach ($magics as $magic)
				if (substr($bytes, 0, strlen($magic)) == $magic)
					return $mime;
		return false;
	}

	// get/set fields
	function fields($fields=null) {
		if ($fields !== null) $this->fields=$fields;
		return $this->fields;
	}

	// get/set one field
	function field($id, $v=null) {
		if ($v !== null) $this->fields[$id]=$v;
		return $this->fields[$id];
	}

	// delete one (string) or more fields (array)
	function del($fields) {
		if (!$fields) return false;
		if (!is_array($fields)) $fields=array($fields);
		foreach ($fields as $f)
			unset($this->fields[$f]);
		return true;
	}

	// filter HTML entities
	function entities($value) {
		return (method_exists("x", "entities")?x::entities($value):htmlentities($value, null, "UTF-8"));
	}

	// convert typical CSS styles to one line style attribute
	function styles($field, $prefix="") {
		$f=$this->fields[$field];
		$s=($f["style"]?$f["style"].";":"");
		foreach ($ops=array(
			"width","min-width","max-width",
			"height","min-height","max-height",
			"color","background","background-color","background-url",
		) as $op)
			if ($v=$f[$prefix.$op])
				$s.=$op.":".$v.";";
		return $s;
	}

	// get/set field caption
	function caption($field, $value=null) {
		return $this->property($field, "caption", $value);
	}

	// get/set field disabled
	function disabled($field, $value=null) {
		return $this->property($field, "disabled", $value);
	}

	// get/set field label
	function label($field, $value=null) {
		return $this->property($field, "label", $value);
	}

	// get/set field placeholder
	function placeholder($field, $value=null) {
		return $this->property($field, "placeholder", $value);
	}

	// get/set field readonly
	function readonly($field, $value=null) {
		return $this->property($field, "readonly", $value);
	}

	// get/set field required
	function required($field, $value=null) {
		return $this->property($field, "required", $value);
	}

	// get/set field property value
	function property($field, $property, $value=null) {
		if ($value !== null) $this->fields[$field][$property]=$value;
		return $this->fields[$field][$property];
	}

	// get/set field value
	function value($field, $value=null) {
		$f=$this->fields[$field];
		if (!is_array($f)) return null;
		if ($value !== null && !$f["readonly"]) $this->fields[$field]["value"]=$value;
		switch ($f["type"]) {
		case "audio":
		case "file":
		case "image":
			if ($info=$this->svalue($field))
				foreach ($info["files"] as $i=>$v)
					return ($v["deleted"]?"":$v["name"]);
			return (isset($f["value"])?$f["value"]:"");
		case "":
		case "files":
		case "images":
			return null;
		case "checkboxes":
			if (!is_array($f["value"])) return [];
		}
		return $f["value"];
	}

	// get/set fields values
	function values($values=null, $parse=true) {
		// set values
		if ($values !== null)
			foreach ($values as $f=>$value)
				if ($this->fields[$f]) {
					$this->fields[$f]["value"]=($parse
						?$this->parseInValue($f, $this->purgeFieldValue($f, $value))
						:$this->purgeFieldValue($f, $value)
					);
				}
		// get values
		$values=array();
		foreach ($this->fields as $f=>$field) if ($this->fieldHasValue($field)) {
			$value=$this->value($f);
			$values[$f]=($parse?$this->parseOutValue($f, $value):$value);
		}
		return $values;
	}

	// check if a field has value
	function fieldHasValue($field) {
		if (!is_array($field)) $field=$this->fields[$field];
		if ($field) switch ($field["type"]) {
		case "":
		case "files":
		case "images":
			return false;
		}
		return !$field["ignore"];
	}

	// get/set file from field that supports it
	function file($field, $file=null) {
		$files=($file !== null
			?$this->files($field, array($file))
			:$this->files($field)
		);
		return ($files?$files[0]:false);
	}

	// get filename from a supported field, or empty filename
	function fileName($field) {
		$file=$this->file($field);
		return ($file && !$file["deleted"]?$file["name"]:"");
	}

	// store/delete file from field
	function fileUpdate($field, $filename) {
		if (!strlen($filename)) return null;
		if ($file=$this->file($field)) {
			$p=dirname($filename);
			if (!is_dir($p)) mkdir($p, 0775, true);
			if ($file["deleted"] && file_exists($filename)) unlink($filename);
			else if ($file["uploaded"]) file_put_contents($filename, $file["data"]);
			return true;
		}
		return false;
	}

	// sort file function
	function fileSort($a, $b) {
		if (isset($a["orden"]) && isset($b["orden"])) {
			if ($a["orden"] < $b["orden"]) return -1;
			else if ($a["orden"] > $b["orden"]) return 1;
		}
		return 0;
	}

	// get/set files from fields that supports it
	function files($field=null, $files=null) {
		if ($field === null) {
			$a=array();
			if ($this->fields)
				foreach ($this->fields as $f=>$v)
					$a[$f]=$this->files($f);
			return $a;
		}
		if ($f=$this->fields[$field]) {
			$limit=$this->filesLimit[$f["type"]];
			if (isset($limit)) {
				$svalue=$this->svalue($field);
				$svalue_store=!$svalue; // if in session, mark to save it
				// if files specified, assign
				if ($files) {
					$index=0;
					$svalue["files"]=array();
					$this->fields[$field]["files"]=array();
					foreach ($files as $f) {
						if (is_string($f)) $f=["file"=>$f];
						if (file_exists($f["file"]) || $f["data"]) {
							if ($f["file"]) {
								if (!$f["name"]) $f["name"]=basename($f["file"]);
								if (!$f["ext"] && (($j=strrpos($f["name"], ".")) !== false)) $f["ext"]=substr($f["name"], $j+1);
								if (!$f["type"]) $f["type"]=""; // asegurarse que el campo está creado
								if (file_exists($f["file"])) $f["size"]=filesize($f["file"]);
							}
							if (!isset($f["size"]) && isset($f["data"])) $f["size"]=strlen($f["data"]);
							require_once(__DIR__."/mimetypes.php");
							if (!$f["type"]) $f["type"]=($f["data"]?$this->mimeByMagic(substr($f["data"], 0, 16)):Mimetypes::file($f["name"]));
							$this->fields[$field]["files"][$index]=$f;
							$svalue["files"][$index]=$f;
							$index++;
						}
					}
					// save in session, if required
					if ($svalue_store) $this->svalue($field, $svalue);
				}
				// if there is files in session, and no specified files, return sessioned files
				if ($svalue["files"] && $files === null) {
					// mix value data and set last value
					foreach ($svalue["files"] as $i=>$v) {
						if (is_array($f["value"][$i])) $svalue["files"][$i]=array_merge($f["value"][$i], $v);
						$svalue["files"][$i]["last"]=$this->fields[$field]["files"][$i];
					}
					// sort if supported by field
					$f=$this->fields[$field];
					if ($f["sortable"]) usort($svalue["files"], array($this, "fileSort"));
					// return sessioned and sorted files
					$files=$svalue["files"];
				} else {
					// files from field
					$files=$this->fields[$field]["files"];
				}
				// fill always with data
				if (is_array($files)) foreach ($files as $i=>&$f) {
					if (is_array($f) && file_exists($f["file"]) && !isset($f["data"]))
						$f["data"]=file_get_contents($f["file"]);
				} unset($f);
				// otherwise, return field files
				return $files;
			}
		}
		// field does not support files
		return false;
	}

	// set, purge & validate
	function validate($values=null) {
		if ($values !== null) $this->values($values, false);
		$this->purge();
		return ($this->verify()?$this->values():false);
	}

	// parse in values for a field
	function parseInValue($f, $value) {
		$field=$this->fields[$f];
		switch ($field["type"]) {
		case "integer": return intval($value);
		case "number": return doubleval($value);
		case "decimal": return doubleval($value);
		case "positive": return abs(doubleval($value));
		case "checkboxes": if (!is_array($value)) return [];
		}
		if ($field["base64"]) $value=base64_decode($value);
		return $value;
	}

	// parse out values for a field
	function parseOutValue($f, $value) {
		$field=$this->fields[$f];
		if ($field["string"]) return (string)$value;
		if ($field["nullifempty"] && !strlen($value)) return null;
		if ($field["nozero"] && !strlen($value)) return 0;
		if ($field["base64"]) $value=base64_encode($value);
		return $value;
	}

	// DEPRECATED: convert ISO date to Spanish date
	function spdate($sqldate) {
		if (strlen($sqldate)==19) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11);
		if (strlen($sqldate)==16) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11).":00";
		if (strlen($sqldate)==13) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11).":00:00";
		if (strlen($sqldate)==10) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4);
		if (strlen($sqldate)==8) return $sqldate;
	}

	// DEPRECATED: convert localized date format (only Spanish supported) to ISO
	function sqldate($spdate) {
		if (strlen($spdate)<10) return($spdate);
		$t=substr($spdate,11);
		if (strlen($t)==2) $t.=":00:00";
		if (strlen($t)==5) $t.=":00";
		return(substr($spdate,6,4)."-".substr($spdate,3,2)."-".substr($spdate,0,2).($t?" ".$t:""));
	}

	// purge all fields values
	function purge() {
		foreach ($this->fields as $f=>$field)
			$this->fields[$f]["value"]=$this->purgeFieldValue($f, $field["value"]);
	}

	// check if value purge is enabled in a field
	function purgeFieldEnabled($f, $value) {
		$field=$this->fields[$f];
		return (
			$value !== null
			&& !$field["nopurge"]
			&& $field["type"] != "html"
			&& $field["type"] != "file"
			&& $field["type"] != "image"
			&& $field["type"] != "files"
			&& $field["type"] != "images"
		);
	}

	// HTML trimming
	function htmlTrim($s) {
		$s=trim((string)$s);
		if ($this->trimHTML) do {
			$f=false;
			foreach ($this->trimHTML as $e) {
				$l=strlen($e);
				if (strtolower(substr($s, 0, $l)) == $e) { $f=true; $s=substr($s, $l); }
				if (strtolower(substr($s, -$l, $l)) == $e) { $f=true; $s=substr($s, 0, -$l); }
			}
		} while ($f);
		$s=trim($s);
		if (in_array(strtolower($s), ["<br>","<br/>","<br />","</br>","<br></br>"])) $s="";
		return $s;
	}

	// purge: apply specified conversions to value and sanitize value
	function purgeFieldValue($f, $value) {
		if (!($field=$this->fields[$f])) return $value;
		// support for array values
		if (is_array($value)) {
			foreach ($value as $i=>$v) {
				$values[$i]=$this->purgeFieldValue($f, $value[$i]);
			}
			return $value;
		}
		//if ($field["caption"] == "Código de Cuenta Contable") {var_dump($value);debug($field);}
		// readonly resets value
		if ($field["readonly"] && isset($field["value"])) $value=$field["value"];
		// allowed filter for HTML
		if ($field["trim"] && $field["type"] == "html") $value=$this->htmlTrim($value);
		// if no purge enabled, return now
		if (!$this->purgeFieldEnabled($f, $value)) return $value;
		// filters
		if ($field["trim"]) $value=trim($value);
		if ($field["lowercase"] || $field["type"] == "email") $value=$this->strtolower($value);
		if ($field["uppercase"]) $value=$this->strtoupper($value);
		if ($field["capitalize"]) $value=$this->ucwords($this->strtolower($value));
		if ($field["integer"]) $value=intval($value);
		if ($field["number"]) $value=doubleval($value);
		if ($field["decimal"]) $value=doubleval($value);
		if ($field["positive"]) $value=abs(doubleval($value));
		if ($field["nozero"] && !$value) $value="";
		// sanitize
		$value=strip_tags(str_replace(array("<", ">"), array("&lt;", "&gt;"), $value));
		// force a string
		if ($field["string"]) $value=(string)$value;
		// nullables
		if (
			   ($field["nullifempty"] || $field["date"] || $field["datetime"]) && !strlen($value)
			|| ($field["nullifzero"] && !doubleval($value))
		) $value=null;
		// return
		return $value;
	}

	// verify fields
	function verify() {
		$this->errors=array();
		foreach ($this->fields as $field=>$f) {
			$prefix=($f["caption"]?$f["caption"].": ":($f["label"]?$f["label"].": ":""));
			$v=$this->value($field);
			$r=null;
			if (isset($f["verify"])) {
				if ($f["verify"] === false) {
					$r=false;
				} else if (is_callable($f["verify"]) && ($verify=$f["verify"]) && ($r=$verify(array("value"=>$v, "prefix"=>$prefix, "field"=>$f)))) {
					$this->errors[$field]=array_merge(array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"verify",
						"err"=>$prefix."Verificación fallida",
					), $r);
				}
			}
			if (!isset($f["verify"]) || $r === null) {
				// required field
				if ($f["required"] && !strlen(trim($v)))
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"required",
						"err"=>(is_string($f["required"])?$f["required"]:$prefix."Campo requerido."),
					);
				// check e-mail
				if ($f["type"] == "email" && strlen($v) && !filter_var($v, FILTER_VALIDATE_EMAIL))
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"email",
						"err"=>$prefix."El e-mail no es válido.",
					);
				// check length interval
				if ($f["minlength"] && $f["maxlength"] && (
					($f["minlength"] > strlen($v)) || ($f["maxlength"] < strlen($v))
				)) {
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"nlength",
						"err"=>$prefix."Debe tener ".($f["minlength"] == $f["maxlength"]
							?($f["minlength"]==1?"1 caracter.":$f["minlength"]." caracteres.")
							:"entre ".$f["minlength"]." y ".$f["maxlength"]." caracteres."
							)
						,
					);
				} else {
					// check minimum length
					if ($f["minlength"] && $f["minlength"] > strlen($v))
						$this->errors[$field]=array(
							"id"=>$this->id($field),
							"field"=>$field,
							"type"=>"minlength",
							"err"=>$prefix."Debe tener un mínimo de ".$f["minlength"]." caracteres.",
						);
					// check maximum length
					if ($f["maxlength"] && $f["maxlength"] < strlen($v))
						$this->errors[$field]=array(
							"id"=>$this->id($field),
							"field"=>$field,
							"type"=>"maxlength",
							"err"=>$prefix."Debe tener un máximo de ".$f["maxlength"]." caracteres.",
						);
				}
				// check minimum number
				if (isset($f["min"]) && $f["min"] > doubleval($v))
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"min",
						"err"=>$prefix."Debe ser como mínimo ".$f["min"].".",
					);
				// check maximum number
				if (isset($f["max"]) && $f["max"] < doubleval($v))
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"max",
						"err"=>$prefix."Debe ser como máximo ".$f["max"].".",
					);
			}
		}
		return ($this->errors?false:true);
	}

	// return verify errors
	function errors() {
		return $this->errors;
	}

	// get/set class for a field
	function className($field, $class=null) {
		if ($class !== null) $this->fields[$field]["class"]=$class;
		$f=$this->fields[$field];
		if ($f["class"]) return $f["class"];
		if ($this->o["class"][$f["type"]]) return $this->o["class"][$f["type"]];
		return "";
	}

	// get JSON data for a field
	function jsfield($field) {
		$f=$this->fields[$field];
		if ($this->value($field)) $f["value"]=true;
		else unset($f["value"]);
		unset($f["extra"]);
		if ($f["file"]) {
			if ($svalue=$this->svalue($field))
				$f["file"]=$svalue["file"];
			unset($f["file"]["file"]);
			unset($f["file"]["data"]);
		}
		if ($f["files"]) {
			if ($svalue=$this->svalue($field))
				$f["files"]=$svalue["files"];
			if ($f["files"]) foreach ($f["files"] as $i=>$v) {
				unset($f["files"][$i]["file"]);
				unset($f["files"][$i]["data"]);
			}
		}
		return $f;
	}

	// get JSON data
	function jsdata() {
		$info=array(
			"ssid"=>$this->ssid(),
			"name"=>$this->o["name"],
			"class"=>$this->o["class"],
			"fields"=>array(),
		);
		if (isset($this->o["chunksize"])) $info["chunksize"]=$this->o["chunksize"];
		foreach ($this->fields as $field=>&$f)
			$info["fields"][$field]=$this->jsfield($field);
		unset($f);
		return $info;
	}

	// get field identifier
	function id($field) {
		$f=$this->fields[$field];
		return (isset($f["id"])?$f["id"]:$this->o["name"]."_".$field);
	}

	// get all field identifiers
	function ids() {
		$ids=array();
		foreach ($this->fields as $field=>$f)
			$ids[]=$this->id($field);
		return $ids;
	}

	// get/set form name
	function name($v=null) {
		if ($v !== null) $this->o["name"]=$v;
		return $this->o["name"];
	}

	// get field name
	function fieldName($field) {
		return (strlen($this->o["name"])?$this->o["name"]."_":"").(
			$this->fields[$field]["name"]
			?$this->fields[$field]["name"]
			:$field
		);
	}

	// get field names
	function fieldNames() {
		$names=array();
		foreach ($this->fields as $field=>$f)
			$names[]=$this->fieldName($field);
		return $names;
	}

	// get first field identifier
	function firstid() {
		foreach ($this->fields as $field=>$f)
			return $this->id($field);
		return false;
	}

	// get HTML for a field
	function html($field) {
		$f=$this->fields[$field];
		$id=$this->id($field);
		$name=$this->fieldName($field);
		$class=$this->className($field);
		$styles=$this->styles($field);
		$datalist="";
		$common=""
			.(isset($f["autocomplete"])?" autocomplete='".(is_string($f["autocomplete"])?$f["autocomplete"]:($f["autocomplete"]?"on":"off"))."'":"")
			.($f["required"]?" required":"")
		;
		// types with align/conversions
		switch ($f["type"]) {
		case "area":
		case "tel":
		case "url":
		case "email":
		case "number":
		case "text":
			if ($f["align"]) $styles.="text-align: ".$f["align"].";";
			if ($f["lowercase"] || $f["type"] == "email") $styles.="text-transform: lowercase;";
			else if ($f["uppercase"]) $styles.="text-transform: uppercase;";
			else if ($f["capitalize"]) $styles.="text-transform: capitalize;";
			break;
		}
		// render by type
		switch ($f["type"]) {
		case "hidden":
			return "<input"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				." type='".$f["type"]."'"
				." value='".$this->entities($f["value"])."'"
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["maxlength"]?" maxlength='".intval($f["maxlength"])."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"]
				." />"
			;

		case "area":
			return "<textarea"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["maxlength"]?" maxlength='".intval($f["maxlength"])."'":"")
				.($f["size"]?" size='".intval($f["size"])."'":"")
				.($f["rows"]?" rows='".intval($f["rows"])."'":"")
				.($f["cols"]?" cols='".intval($f["cols"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.$common
				.($styles?" style='".$styles."'":"")
				.$f["extra"].">".$this->entities($f["value"])."</textarea>"
			;

		case "datetime":
			$styles_date=$this->styles($field, "date:");
			$styles_time=$this->styles($field, "time:");
			return ""
				."<span style='display:flex;'>" // fix for date/time inputs height inconsistence
				."<input"
				." id='".$id.":d'"
				." name='".$name.":d'"
				." class='".$class."'"
				." type='date'"
				." value='".$this->entities(substr($f["value"], 0, 10))."'"
				." maxlength='10'"
				.($f["date:min"]?" min='".$f["date:min"]."'":"")
				.($f["date:max"]?" max='".$f["date:max"]."'":"")
				.($f["date:step"]?" step='".$f["date:step"]."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":" placeholder='dd/mm/yyyy'")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.$common
				.($styles || $styles_date?" style='".$styles.$styles_date."'":"")
				.$f["extra"]
				." />"
				."<span style='overflow:hidden;width:2px;visibility:hidden;'><input class='".$this->o["class"]["text"]."' type='text' /></span>"
				."<input"
				." id='".$id.":t'"
				." name='".$name.":t'"
				." class='".$class."'"
				." type='time'"
				." value='".$this->entities(substr($f["value"], 11, 5))."'"
				.($f["time:min"]?" min='".$f["time:min"]."'":"")
				.($f["time:max"]?" max='".$f["time:max"]."'":"")
				.($f["time:step"]?" step='".$f["time:step"]."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":" placeholder='hh:mm".($f["precise"]?":ss":"")."'")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.$common
				.($styles || $styles_time?" style='".$styles.$styles_time."'":"")
				.$f["extra"]
				." />"
				."</span>"
			;

		case "date":
			$length=10;
			return "<input"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				." type='".$f["type"]."'"
				." value='".$this->entities(substr($f["value"], 0, $length))."'"
				." maxlength='".$length."'"
				.($f["min"]?" min='".$f["min"]."'":"")
				.($f["max"]?" max='".$f["max"]."'":"")
				.($f["step"]?" step='".$f["step"]."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":" placeholder='dd/mm/yyyy'")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.$common
				.($styles?" style='".$styles."'":"")
				.$f["extra"]
				." />"
			;

		case "time":
			$length=5;
			return "<input"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				." type='".$f["type"]."'"
				." value='".$this->entities(substr($f["value"], 0, $length))."'"
				." maxlength='".$length."'"
				.($f["min"]?" min='".$f["min"]."'":"")
				.($f["max"]?" max='".$f["max"]."'":"")
				.($f["step"]?" step='".$f["step"]."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":" placeholder='hh:mm'")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.$common
				.($styles?" style='".$styles."'":"")
				.$f["extra"]
				." />"
			;

		case "tel":
		case "url":
		case "email":
		case "number":
		case "text":
			if ($f["datalist"]) {
				if (is_array($f["datalist"])) {
					$datalist="<datalist id='".$id."_datalist'>";
					$ishash=$this->_is_hash($f["datalist"]);
					foreach ($f["datalist"] as $n=>$v)
						$datalist.="<option value='".$this->entities($ishash?$n:$v)."'>".$this->entities($v)."</option>";
					$datalist.="</datalist>";
				}
			}
		case "password":
			return "<input"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				." type='".$f["type"]."'"
				." value='".$this->entities($f["value"])."'"
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["maxlength"]?" maxlength='".intval($f["maxlength"])."'":"")
				.($f["size"]?" size='".intval($f["size"])."'":"")
				.($f["step"]?" step='".$this->entities($f["step"])."'":"")
				.(isset($f["datalist"])?" list='".(is_string($f["datalist"])?$f["datalist"]:$id."_datalist")."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.$common
				.($styles?" style='".$styles."'":"")
				.$f["extra"]
				." />".$datalist
			;

		case "select":
			$opciones="";
			if ($f["options"])
				foreach ($f["options"] as $n=>$v)
					$opciones.="<option value='".$this->entities($n)."'".((string)$n === $f["value"]?" selected":"").">".$this->entities($v)."</option>";
			return "<select"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.$common
				.($styles?" style='".$styles."'":"")
				.$f["extra"].">"
					.$opciones
				."</select>"
			;

		case "checkbox":
			return "<label"
				." id='".$id."-label'"
				." class='".$class."'"
				.">"
					."<input"
					." id='".$id."'"
					." name='".$name."'"
					." type='checkbox'"
					." value='1'"
					.($f["title"]?" title='".$this->entities($f["title"])."'":"")
					.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
					.(($f["values"]?($f["value"] === $f["values"][1]):$f["value"])?" checked":"")
					.($f["disabled"]?" disabled":"")
					.($f["readonly"]?" readonly onclick='javascript:return false;'":"")
					.$common
					.($styles?" style='".$styles."'":"")
					.$f["extra"]." />"
					.(isset($f["label"])?"<span id='".$id."_label'>".$f["label"]."</span>":"")
				."</label>"
			;

		case "checkboxes":
			$html="";
			if ($f["options"]) {
				$num=0;
				foreach ($f["options"] as $n=>$v) {
					$html.="<label"
						." id='".$id."-".$num."_label'"
						." class='".$class."'"
						.">"
							."<input"
							." id='".$id."-".$num."'"
							." name='".$name."[]'"
							." type='checkbox'"
							." value='".$this->entities($n)."'"
							.($f["title"]?" title='".$this->entities($f["title"])."'":"")
							.(is_array($f["value"]) && in_array($n, $f["value"])?" checked":"")
							.($f["disabled"]?" disabled":"")
							.($f["readonly"]?" readonly onclick='javascript:return false;'":"")
							.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
							.$common
							.($styles?" style='".$styles."'":"")
							.$f["extra"]." />"
							."<span id='".$id."-".$num."_span'>".$v."</span>"
						."</label>"
						.($f["br"]?"<br />":"")
					;
					$num++;
				}
			}
			return $html;

		case "radio":
			$html="";
			if ($f["options"]) {
				$num=0;
				$html.=(isset($f["label"])?"<span id='".$id."_label'>".$f["label"]."</span>":"");
				foreach ($f["options"] as $n=>$v) {
					$html.="<label"
						." id='".$id."-".$num."_label'"
						." class='".$class."'"
						.">"
							."<input"
							." id='".$id."-".$num."'"
							." name='".$name."'"
							." type='radio'"
							." value='".$this->entities($n)."'"
							.($f["title"]?" title='".$this->entities($f["title"])."'":"")
							.((string)$n === $f["value"]?" checked":"")
							.($f["disabled"]?" disabled":"")
							.($f["readonly"]?" readonly onclick='javascript:return false;'":"")
							.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
							.$common
							.($styles?" style='".$styles."'":"")
							.$f["extra"]." />"
							."<span id='".$id."-".$num."_span'>".$v."</span>"
						."</label>"
						.($f["br"]?"<br />":"")
					;
					$num++;
				}
			}
			return $html;

		case "audio":
			return "<div"
				." id='".$id."'"
				." class='".$class."'"
				." src='".$f["default"]."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"]."></div>"
			;

		case "image":
			return "<div"
				." id='".$id."'"
				." class='".$class."'"
				." src='".$f["default"]."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"]."></div>"
			;

		case "file":
		case "files":
		case "images":
			return "<div"
				." id='".$id."'"
				." class='".$class."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"]."></div>"
			;

		case "html":
			return "<div"
				." id='".$id."'"
				." class='".$class."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.($styles?" style='".$styles."'":"")
				.($f["editable"]?" contentEditable='true'":"")
				.$f["extra"].">".$f["value"]."</div>"
			;

		case "div":
			return "<div"
				." id='".$id."'"
				." class='".$class."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"].">".$f["value"]."</div>"
			;

		case "color":
			return "<div"
				." id='".$id."'"
				." class='".$class."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				." style='background-color:#".$this->colorValue($f["value"]).";".$styles."'"
				.$f["extra"].">".(isset($f["label"])?$f["label"]:"&nbsp;")."</div>"
			;

		}
	} // var colorPicker = new iro.ColorPicker('#picker');

	// ensure valid hex RGB color
	function colorValue($c) {
		$d="000000";
		if (!strlen($c)) return $d;
		$c=strtoupper(substr("".$c, 0, 6));
		$c=preg_replace("/[^0-9A-F]/", "", $c);
		if (strlen($c) == 3) $c=substr($c, 0, 1).substr($c, 0, 1).substr($c, 1, 1).substr($c, 1, 1).substr($c, 2, 1).substr($c, 2, 1);
		return (strlen($c) == 6?$c:$d);
	}

	// get/set session name
	function ssid($ssid=null) {
		if ($ssid !== null) $this->o["ssid"]="".$ssid;
		return $this->o["ssid"];
	}

	// clear session
	function sclean() {
		if ($_SESSION["xform3"]
			&& $_SESSION["xform3"][$this->ssid()]
			&& $_SESSION["xform3"][$this->ssid()][$this->o["name"]]
		) unset($_SESSION["xform3"][$this->ssid()][$this->o["name"]]);
	}

	// start session
	function sstart($ssid=null) {
		if ($ssid !== null) $this->ssid($ssid);
		// primero limpiar
		$this->sclean();
		// inicializar campos con sesión
		foreach ($this->fields as $field=>$f) {
			if ($f["file"]) $this->file($field, $f["file"]);
			if ($f["files"]) $this->files($field, $f["files"]);
		}
	}

	// get/set session data for a field
	function svalue($field, $value=null) {
		if ($value === false) {
			unset($_SESSION["xform3"][$this->ssid()][$this->o["name"]][$field]);
		} else {
			if ($value !== null) $_SESSION["xform3"][$this->ssid()][$this->o["name"]][$field]=$value;
			return $_SESSION["xform3"][$this->ssid()][$this->o["name"]][$field];
		}
	}

	// scale image (requires: ximage)
	function imageScale($o=array()) {
		$w=intval($o["w"]); if ($w < 1 || $w > 10240) $w=0;
		$h=intval($o["h"]); if ($h < 1 || $h > 10240) $h=0;
		$q=($o["q"]?$o["q"]:90);
		if (!$w || !$h) return ""; // can't scale
		// required classes
		require_once(__DIR__."/ximage.php");
		// create image, clean or from file
		$xi=($o["file"]?new xImage($o["file"]):new xImage());
		// if content data specified, load from it
		if ($o["data"]) {
			$xi->fromString($o["data"]);
			if (!$o["f"]) $o["f"]=$xi->getFormatByMagic($o["data"]);
		} else {
			if (!$o["f"]) $o["f"]=$xi->getFileFormat($o["file"]);
		}
		// fix orientation
		$xi->fixOrientation();
		// scale only if dimensions exceeds threshold
		if ($xi->width() > $w || $xi->height() > $h) $xi->scale($w, $h);
		// return content data image
		return $xi->toString(($o["f"]?$o["f"]:"jpg"), $q);
	}

	// AJAX actions
	function ajax() {

		// required globals
		$ajax=$GLOBALS["ajax"];
		$adata=$GLOBALS["adata"];

		// required fields can be used via GET/POST
		if (!$adata["name"]) $adata["name"]=$_REQUEST["name"];
		if (!$adata["field"]) $adata["field"]=$_REQUEST["field"];

		// ensure form name is set and matches
		if (!$adata["name"] || ($adata["name"] != $this->o["name"])) return true;

		// iterate fields
		foreach ($this->fields as $field=>$f) if ($adata["field"] == $field) {

			// if field has upload limit...
			$limit=$this->filesLimit[$f["type"]];
			if (isset($limit)) {

				// actions for files
				switch ($ajax) {

				case "xform3.file.get":
					$this->ssid($_REQUEST["ssid"]);
					$svalue=$this->svalue($field);
					$index=($_REQUEST["index"]?intval($_REQUEST["index"]):0);
					$file=$svalue["files"][$index];
					// try to get mimetype
					if (!$file["type"]) $file["type"]=$this->mimeByMagic(substr($file["data"], 0, 16));
					// scale, if requested
					if (($w=intval($_REQUEST["w"])) && ($h=intval($_REQUEST["h"])) && $file["type"]) {
						$file["data"]=$this->imageScale(array_merge(($f["scale"]?$f["scale"]:array()), array(
							"file"=>$file["file"],
							"data"=>$file["data"],
							"w"=>$w,
							"h"=>$h
						)));
					}
					// dump file to HTTP (required: kernel)
					require_once(__DIR__."/kernel.php");
					Kernel::httpOutput(array(
						"type"=>$file["type"],
						"file"=>$file["file"],
						"data"=>$file["data"],
						"name"=>$file["name"],
						"disposition"=>(isset($_REQUEST["attachment"])?"attachment":"inline"),
					));
					// finished
					exit;

				case "xform3.file.del":
					$this->ssid($adata["ssid"]);
					// mark as deleted
					$index=($adata["index"]?intval($adata["index"]):0);
					$svalue=$this->svalue($field);
					unset($svalue["files"][$index]["uploaded"]);
					$svalue["files"][$index]["deleted"]=true;
					$this->svalue($field, $svalue);
					// everything ok
					ajax(array("ok"=>true));

				case "xform3.files.upload":
					$this->ssid($_REQUEST["ssid"]);
					// load required classes (required: xuploader)
					require_once(__DIR__."/xuploader.php");
					// process uploaded files
					new xUploader(array(
						"_field"=>$field,
						"_f"=>$f,
						"_xf"=>$this,
						"_limit"=>$limit,
						"oncomplete"=>function($uploader, $upload, $o){ // at the end of a completed upload
							//$field=$o["_field"];
							$f=$o["_f"];
							$limit=$o["_limit"];
							// session value for field
							$svalue=$o["_xf"]->svalue($o["_field"]);
							// entry index
							$index=0;
							if (!$svalue["files"]) $svalue["files"]=array();
							foreach ($svalue["files"] as $i=>$v) if ($i >= $index) $index=($i+1);
							if ($limit && $index+1 >= $limit) $index=$limit-1;
							// entry
							$j=strrpos($upload["name"], ".");
							$file=array(
								"uploaded"=>true,
								"name"=>$upload["name"],
								"ext"=>($j !== false?substr($upload["name"], $j+1):""),
								"size"=>$upload["size"],
								"sizeo"=>$upload["size"],
								"type"=>$upload["type"],
							);
							// check file, if check callback
							if (($check=$f["check"]) && is_callable($check) && !$check($f, $file)) ajax(["err"=>"No se permite subir el fichero especificado."]);
							// check if extensions is in allowed list
							if ($exts=$f["allowedext"]) {
								$ext=strtolower($file["ext"]);
								foreach ($exts as $e) if ($ext == strtolower($e)) {
									$ext=false;
									break;
								}
								if ($ext) ajax(array("err"=>"La extensión .".$file["ext"]." no está permitida, sólo se permite".(count($exts) == 1?"":"s")." <b>".implode(", ", $exts)."</b>."));
							}
							// automatic name assignment based on filename
							if ($f["filecaption"]) $file["caption"]=$upload["name"];
							// prepare content data, scale if required
							if ($f["scale"] && ($w=$f["scale"]["w"]) && ($h=$f["scale"]["h"])) {
								$file["scaled"]=true;
								$file["data"]=$this->imageScale(array("file"=>$upload["tmp"])+$f["scale"]);
							} else {
								$file["data"]=file_get_contents($upload["tmp"]);
							}
							// update size
							$file["size"]=strlen($file["data"]);
							// save
							$svalue["files"][$index]=$file;
							// save in session
							$o["_xf"]->svalue($o["_field"], $svalue);
						},
						"onupload"=>function($uploader, $uploads, $o){ // at the end of all uploads
							// session value for field
							$svalue=$o["_xf"]->svalue($o["_field"]);
							// construct empty array
							$files=array_map(function($file){
								unset($file["data"]);
								return $file;
							}, ($svalue["files"]?$svalue["files"]:array()));
							// return upload information
							ajax(array(
								"files"=>$files,
								"ok"=>true
							));
						},
					));
					// error control
					ajax(array("err"=>"No se han especificado ficheros o se ha excedido el tamaño."));

				}
				break;

			} // ajax

		} // limit

	}

}
