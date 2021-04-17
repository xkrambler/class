<?php

/*

	xForm3: clase de manejo de formularios, versión 3

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
			//"file" =>["caption"=>"One File","type"=>"file","file"=>["file"=>"data/file.txt"]], // another way
			//"files" =>["caption"=>"Files","type"=>"files","files"=>[["file"=>"data/file.txt"]]],
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

	// validation
	if (!$xf->validate($adata["values"]))
		foreach ($xf->errors() as $error)
			ajax(["field"=>$error["id"], "warn"=>$error["err"]]);

	// values
	$values=$xf->values();

	// files
	$files=$xf->files();

*/
class xForm3 {

	static private $sid;
	protected $o;
	protected $fields;
	protected $ssid="";
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

	// constructor y asignación de valores por defecto
	function __construct($o) {
		self::$sid++;
		$default_class=array(
			"text"=>"txt",
			"number"=>"txt",
			"date"=>"txt",
			"time"=>"txt",
			"datetime"=>"txt",
			"password"=>"txt",
			"area"=>"area",
			"checkbox"=>"checkbox",
			"radio"=>"radio",
			"select"=>"cmb",
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
		if (!$o["name"]) $o["name"]="xform".self::$sid;
		$this->o=$o;
		$this->fields($o["fields"]); unset($this->o["fields"]);
		// si no es petición AJAX, se asume limpieza de sesión
		if (!$this->isajax()) $this->sstart($this->ssid);
		// asignar valores, si especificado
		if ($o["values"]) {
			$this->values($o["values"]);
			unset($this->o["values"]);
		}
	}

	// comprueba si una variable es un hash
	private function _is_hash($var) {
		if (!is_array($var)) return false;
		return array_keys($var) !== range(0,sizeof($var)-1);
	}

	// devolver petición AJAX
	function isajax() { return $GLOBALS["ajax"]; }

	// obtener mime por magic bytes
	function mimeByMagic($bytes) {
		foreach ($this->magicMimes as $mime=>$magics)
			foreach ($magics as $magic)
				if (substr($bytes, 0, strlen($magic)) == $magic)
					return $mime;
		return false;
	}

	// obtener/establecer campos
	function fields($fields=null) {
		if ($fields!==null) $this->fields=$fields;
		return $this->fields;
	}

	// obtener/establecer un campo
	function field($id, $v=null) {
		if ($v !== null) $this->fields[$id]=$v;
		return $this->fields[$id];
	}

	// eliminar uno o más campos
	function del($fields) {
		if (!$fields) return false;
		if (!is_array($fields)) $fields=array($fields);
		foreach ($fields as $f)
			unset($this->fields[$f]);
		return true;
	}

	// filtrar entidades HTML
	function entities($value) {
		return htmlentities($value, null, "UTF-8");
	}

	// obtener estilos
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

	// obtener/establecer texto de un campo
	function caption($field, $caption=null) {
		if ($caption!==null) $this->fields[$field]["caption"]=$caption;
		return $this->fields[$field]["caption"];
	}

	// obtener/establecer etiqueta de un campo
	function label($field, $label=null) {
		if ($label!==null) $this->fields[$field]["label"]=$label;
		return $this->fields[$field]["label"];
	}

	// obtener/establecer placeholder
	function placeholder($field, $placeholder=null) {
		if ($placeholder!==null) $this->fields[$field]["placeholder"]=$placeholder;
		return $this->fields[$field]["placeholder"];
	}

	// obtener/establecer valor de un campo
	function value($field, $value=null) {
		if ($value!==null)
			if (!$this->fields[$field]["readonly"])
				$this->fields[$field]["value"]=$value;
		switch ($this->fields[$field]["type"]) {
		case "audio":
		case "file":
		case "image":
			if ($info=$this->svalue($field))
				foreach ($info["files"] as $i=>$v)
					return ($v["deleted"]?"":$v["name"]);
			return (isset($this->fields[$field]["value"])?$this->fields[$field]["value"]:"");
		case "files":
		case "images":
			if ($info=$this->svalue($field)) {
				foreach ($info["files"] as $i=>$v)
					if (is_array($this->fields[$field]["value"][$i]))
						$info["files"][$i]=array_merge($this->fields[$field]["value"][$i], $v);
				return $info["files"];
			}
			return (isset($this->fields[$field]["value"])?$this->fields[$field]["value"]:[]);
		case "": return null;
		default: return $this->fields[$field]["value"];
		}
	}

	// obtener/establecer valores de los campos
	function values($values=null, $parse=true) {
		// establecer valores (si especificado)
		if ($values !== null)
			foreach ($values as $f=>$value)
				if ($this->fields[$f]) {
					$this->fields[$f]["value"]=($parse
						?$this->parseInValue($f, $this->purgeFieldValue($f, $value))
						:$this->purgeFieldValue($f, $value)
					);
				}
		// consultar y devolver valores (si no ignorado)
		$values=array();
		foreach ($this->fields as $f=>$field) if ($field["type"] && !$field["ignore"]) {
			$value=$this->value($f);
			$values[$f]=($parse?$this->parseOutValue($f, $value):$value);
		}
		return $values;
	}

	// obtiene el nombre de fichero de un campo que lo soporte, o cadena vacía
	function fileName($field) {
		$file=$this->file($field);
		return ($file && !$file["deleted"]?$file["name"]:"");
	}

	// obtiene/establece un fichero de un campo que lo soporte
	function file($field, $file=null) {
		$files=($file !== null
			?$this->files($field, array($file))
			:$this->files($field)
		);
		return ($files?$files[0]:false);
	}

	// obtener/establecer ficheros de un campo que lo soporte, o de todos los campos
	function files($field=null, $files=null) {
		if ($field === null) {
			$a=array();
			if ($this->fields) foreach ($this->fields as $f=>$v)
				$a[$f]=$this->files($f);
			return $a;
		}
		$f=$this->fields[$field];
		$limit=$this->filesLimit[$f["type"]];
		if (isset($limit)) {
			$svalue=$this->svalue($field);
			$svalue_store=!$svalue; // si estaba en sesión, marcar para guardar
			// si se especifican ficheros, asignar
			if ($files) {
				$index=0;
				$svalue["files"]=array();
				$this->fields[$field]["files"]=array();
				foreach ($files as $f) {
					if (file_exists($f["file"]) || $f["data"]) {
						if ($f["file"]) {
							if (!$f["name"]) $f["name"]=basename($f["file"]);
							if (!$f["ext"] && (($j=strrpos($f["name"], ".")) !== false)) $f["ext"]=substr($f["name"], $j+1);
							if (!$f["type"]) $f["type"]=""; // asegurarse que el campo está creado
							if (file_exists($f["file"])) {
								$f["size"]=filesize($f["file"]);
								$f["data"]=file_get_contents($f["file"]);
							}
						}
						if (isset($f["data"])) $f["size"]=strlen($f["data"]);
						require_once(__DIR__."/mimetypes.php");
						if (!$f["type"]) $f["type"]=($f["data"]?$this->mimeByMagic(substr($f["data"], 0, 16)):Mimetypes::file($f["name"]));
						$this->fields[$field]["files"][$index]=$f;
						$svalue["files"][$index]=$f;
						$index++;
					}
				}
				// guardar en sesión, si requerido
				if ($svalue_store) $this->svalue($field, $svalue);
			}
			// si hay ficheros en sesión, y no se especifican ficheros, devolver datos de sesión
			if ($svalue["files"] && $files === null) {
				foreach ($svalue["files"] as $index=>$file)
					$svalue["files"][$index]["last"]=$this->fields[$field]["files"][$index];
				return $svalue["files"];
			}
			// en otro caso, devolver ficheros del campo
			return $this->fields[$field]["files"];
		}
		// campo no soporta ficheros
		return false;
	}

	// set, purge & validate
	function validate($values=null) {
		if ($values!==null) $this->values($values, false);
		$this->purge();
		return ($this->verify()?$this->values():false);
	}

	// funciones de entrada de valores
	function parseInValue($f, $value) {
		$field=$this->fields[$f];
		switch ($field["type"]) {
		case "integer": return intval($value);
		case "number": return doubleval($value);
		case "positive": return abs(doubleval($value));
		case "decimal": return doubleval($value);
		//case "datetime": return $this->spdate($value);
		case "datetime": return $value;
		default: return $value;
		}
	}

	// funciones de salida de valores
	function parseOutValue($f, $value) {
		$field=$this->fields[$f];
		if ($field["nullifempty"] && !strlen($value)) return null;
		switch ($field["type"]) {
		//case "datetime": return $this->sqldate($value);
		default: return $value;
		}
	}

	// provista la fecha y hora, se transforma a notación dd/mm/yyyy hh:mm:ss
	// provista la fecha, se convierte a notación dd/mm/yyyy
	// provista la hora, se devuelve de la misma forma, hh:mm:ss
	function spdate($sqldate) {
		if (strlen($sqldate)==19) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11);
		if (strlen($sqldate)==16) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11).":00";
		if (strlen($sqldate)==13) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11).":00:00";
		if (strlen($sqldate)==10) return substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4);
		if (strlen($sqldate)==8) return $sqldate;
	}

	// provisto la fecha en formato dd/mm/yyyy RESTO se pasa a yyyy-mm-dd RESTO
	function sqldate($spdate) {
		if (strlen($spdate)<10) return($spdate);
		$t=substr($spdate,11);
		if (strlen($t)==2) $t.=":00:00";
		if (strlen($t)==5) $t.=":00";
		return(substr($spdate,6,4)."-".substr($spdate,3,2)."-".substr($spdate,0,2).($t?" ".$t:""));
	}

	// purgar todos los campos que tengan habilitada la purga (por defecto todos)
	function purge() {
		foreach ($this->fields as $f=>$field)
			$this->fields[$f]["value"]=$this->purgeFieldValue($f, $field["value"]);
	}

	// ver si la purga de un campo está habilitada
	function purgeFieldEnabled($f, $value) {
		$field=$this->fields[$f];
		return (
			$value!==null
			&& !$field["nopurge"]
			&& $field["type"]!="html"
			&& $field["type"]!="files"
			&& $field["type"]!="images"
		);
	}

	// purgar: realiza las conversiones al valor que se hayan especificado y se sanea para evitar inyecciones
	function purgeFieldValue($f, $value) {
		if (!$this->purgeFieldEnabled($f, $value)) return $value;
		$field=$this->fields[$f];
		// readonly inicial, sólo si el valor no estaba establecido previamente
		if ($field["readonly"] && isset($field["value"])) $value=$field["value"];
		// resto de filtros
		if ($field["trim"]) $value=trim($value);
		if ($field["lowercase"]) $value=strtolower_utf8($value);
		if ($field["uppercase"]) $value=strtoupper($value);
		if ($field["capitalize"]) $value=ucwords(strtolower_utf8($value));
		if ($field["integer"]) $value=intval($value);
		if ($field["number"]) $value=doubleval($value);
		if ($field["positive"]) $value=abs(doubleval($value));
		if ($field["decimal"]) $value=doubleval($value);
		if ($field["nozero"] && !$value) $value="";
		$value=strip_tags(str_replace(array("<", ">"), array("&lt;", "&gt;"), $value));
		if ($field["date"] && strlen($value)) $value=null;
		if ($field["nullifempty"] && !strlen($value)) $value=null;
		return $value;
	}

	// verificar campos
	function verify() {
		$this->errors=array();
		foreach ($this->fields as $field=>$f) {
			$prefix=($f["caption"]?$f["caption"].": ":($f["label"]?$f["label"].": ":""));
			// si requerido, verificar
			$v=$this->value($field);
			if ($f["required"] && !trim($v))
				$this->errors[$field]=array(
					"id"=>$this->id($field),
					"field"=>$field,
					"type"=>"required",
					"err"=>(is_string($f["required"])?$f["required"]:$prefix."Campo requerido."),
				);
			// si tengo un intervalo, comprobar cualquiera de ellos
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
				// comprobar solo mínima longitud
				if ($f["minlength"] && $f["minlength"] > strlen($v))
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"minlength",
						"err"=>$prefix."Debe tener un mínimo de ".$f["minlength"]." caracteres.",
					);
				// comprobar solo máxima longitud
				if ($f["maxlength"] && $f["maxlength"] < strlen($v))
					$this->errors[$field]=array(
						"id"=>$this->id($field),
						"field"=>$field,
						"type"=>"maxlength",
						"err"=>$prefix."Debe tener un máximo de ".$f["maxlength"]." caracteres.",
					);
			}

			// si tengo un intervalo, comprobar cualquiera de ellos
			// comprobar solo mínima numérico
			if ($f["min"] && $f["min"] > doubleval($v))
				$this->errors[$field]=array(
					"id"=>$this->id($field),
					"field"=>$field,
					"type"=>"min",
					"err"=>$prefix."Debe ser como mínimo ".$f["min"].".",
				);
			// comprobar solo máximo numérico
			if ($f["max"] && $f["max"] < doubleval($v))
				$this->errors[$field]=array(
					"id"=>$this->id($field),
					"field"=>$field,
					"type"=>"max",
					"err"=>$prefix."Debe ser como máximo ".$f["max"].".",
				);
		}
		return ($this->errors?false:true);
	}

	// nombre de la clase de un campo
	function className($field) {
		$f=$this->fields[$field];
		if ($f["class"]) return $f["class"];
		if ($this->o["class"][$f["type"]]) return $this->o["class"][$f["type"]];
		return "";
	}

	// obtener datos de un campo JSON
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

	// obtener datos de campos para pasar a JSON
	function jsdata() {
		$info=array(
			"ssid"=>$this->ssid(),
			"name"=>$this->o["name"],
			"class"=>$this->o["class"],
			"fields"=>array(),
		);
		foreach ($this->fields as $field=>&$f)
			$info["fields"][$field]=$this->jsfield($field);
		unset($f);
		return $info;
	}

	// obtener identificador de un campo
	function id($field) {
		return $this->o["name"]."_".$field;
	}

	// obtener todos los identificadores
	function ids() {
		$ids=array();
		foreach ($this->fields as $field=>$f)
			$ids[]=$this->id($field);
		return $ids;
	}

	// obtener nombre del formulario
	function name($v=null) {
		if ($v!==null) $this->o["name"]=$v;
		return $this->o["name"];
	}

	// obtener nombre de un campo
	function fieldName($field) {
		return (
			$this->fields[$field]["name"]
			?$this->fields[$field]["name"]
			:$field
		);
	}

	// obtener nombres
	function fieldNames() {
		$names=array();
		foreach ($this->fields as $field=>$f)
			$names[]=$this->fieldName($field);
		return $names;
	}

	// devolver primer identificador
	function firstid() {
		foreach ($this->fields as $field=>$f)
			return $this->id($field);
		return false;
	}

	// generar HTML
	function html($field) {
		$f=$this->fields[$field];
		// identificadores
		$id=$this->id($field);
		$name=$this->fieldName($field);
		// estilos
		$class=$this->className($field);
		$styles=$this->styles($field);
		$datalist="";
		// tipos con conversión
		switch ($f["type"]) {
		case "area":
		case "number":
		case "text":
			if ($f["lowercase"]) $styles.="text-transform: lowercase;";
			else if ($f["uppercase"]) $styles.="text-transform: uppercase;";
			else if ($f["capitalize"]) $styles.="text-transform: capitalize;";
			break;
		}
		// render
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
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"].">".$this->entities($f["value"])."</textarea>"
			;

		case "datetime":
			//$length=($f["precise"]?19:16);
			$styles_date=$this->styles($field, "date:");
			$styles_time=$this->styles($field, "time:");
			return "<input"
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
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
				.($styles || $styles_date?" style='".$styles.$styles_date."'":"")
				.$f["extra"]
				." /> <input"
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
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
				.($styles || $styles_time?" style='".$styles.$styles_time."'":"")
				.$f["extra"]
				." />"
			;

		case "date":
			$length=10;
			return "<input"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				." type='".$f["type"]."'"
				." value='".$this->entities(substr($f["value"],0,$length))."'"
				." maxlength='".$length."'"
				.($f["min"]?" min='".$f["min"]."'":"")
				.($f["max"]?" max='".$f["max"]."'":"")
				.($f["step"]?" step='".$f["step"]."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":" placeholder='dd/mm/yyyy'")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
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
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"]
				." />"
			;

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
				.($f["placeholder"]?" placeholder='".$this->entities($f["placeholder"])."'":"")
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["maxlength"]?" maxlength='".intval($f["maxlength"])."'":"")
				.($f["size"]?" size='".intval($f["size"])."'":"")
				.($f["datalist"]?" list='".(is_string($f["datalist"])?$f["datalist"]:$id."_datalist")."'":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"]
				." />".$datalist
			;

		case "select":
			$opciones="";
			if ($f["options"])
				foreach ($f["options"] as $n=>$v)
					$opciones.="<option value='".$this->entities($n)."'".((string)$n===(string)$f["value"]?" selected":"").">".$this->entities($v)."</option>";
			return "<select"
				." id='".$id."'"
				." name='".$name."'"
				." class='".$class."'"
				.($f["title"]?" title='".$this->entities($f["title"])."'":"")
				.($f["disabled"]?" disabled":"")
				.($f["readonly"]?" readonly":"")
				.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
				.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
				.($styles?" style='".$styles."'":"")
				.$f["extra"].">"
					.$opciones
				."</select>"
			;

		case "radio":
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
							." name='".$name."'"
							." type='radio'"
							." value='".$this->entities($n)."'"
							.($f["title"]?" title='".$this->entities($f["title"])."'":"")
							.((string)$n===(string)$f["value"]?" checked":"")
							.($f["disabled"]?" disabled":"")
							.($f["readonly"]?" readonly":"")
							.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
							.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
							.($styles?" style='".$styles."'":"")
							.$f["extra"]." />"
							."<span id='".$id."-".$num."_span'>".$v."</span>"
						."</label>"
					;
					$num++;
				}
			}
			return $html;

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
					.(($f["values"]?($f["value"]==$f["values"][1]):$f["value"])?" checked":"")
					.($f["disabled"]?" disabled":"")
					.($f["readonly"]?" readonly":"")
					.($f["tabindex"]?" tabindex='".$this->entities($f["tabindex"])."'":"")
					.(isset($f["autocomplete"])?" autocomplete='".($f["autocomplete"]?"on":"off")."'":"")
					.($styles?" style='".$styles."'":"")
					.$f["extra"]." />"
					.(isset($f["label"])?"<span id='".$id."_label'>".$f["label"]."</span>":"")
				."</label>"
			;

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

		}
	}

	// obtener/asignar nombre de sesión
	function ssid($ssid=null) {
		if ($ssid !== null) $this->ssid="".$ssid;
		return $this->ssid;
	}

	// limpiar sesión
	function sclean() {
		unset($_SESSION["xform3"][$_SERVER["PHP_SELF"]][$this->o["name"]][$this->ssid]);
	}

	// iniciar sesión
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

	// obtener/establecer datos de sesión de un campo
	function svalue($field, $value=null) {
		if ($value === false) {
			unset($_SESSION["xform3"][$_SERVER["PHP_SELF"]][$this->o["name"]][$this->ssid][$field]);
		} else {
			if ($value !== null)
				$_SESSION["xform3"][$_SERVER["PHP_SELF"]][$this->o["name"]][$this->ssid][$field]=$value;
			return $_SESSION["xform3"][$_SERVER["PHP_SELF"]][$this->o["name"]][$this->ssid][$field];
		}
	}

	// escalar imagen
	function imageScale($o=array()) {
		$w=intval($o["w"]); if ($w < 1 || $w > 10240) $w=0;
		$h=intval($o["h"]); if ($h < 1 || $h > 10240) $h=0;
		$f=($o["f"]?$o["f"]:"jpg");
		$q=($o["q"]?$o["q"]:90);
		if (!$w || !$h) return ""; // sin datos
		// clases requeridas
		require_once(__DIR__."/ximage.php");
		// crear imagen, o bien limpia, o bien a partir de fichero
		$xi=($o["file"]?new xImage($o["file"]):new xImage());
		// si tenemos datos, cargar de datos
		if ($o["data"]) $xi->fromString($o["data"]);
		// arreglar orientación
		$xi->fixOrientation();
		// solo si supera el tamaño, escalar
		if ($xi->width() > $w || $xi->height() > $h) $xi->scale($w, $h, false, true);
		// devolver datos de la imagen final
		return $xi->toString($f, $q);
	}

	// acciones AJAX
	function ajax() {

		// datos necesarios
		$ajax=$GLOBALS["ajax"];
		$adata=$GLOBALS["adata"];

		// campos obligatorios permiten ser utilizados via REQUEST
		if (!$adata["name"]) $adata["name"]=$_REQUEST["name"];
		if (!$adata["field"]) $adata["field"]=$_REQUEST["field"];

		// verificar que el nombre del formulario esté establecido y sea el mismo
		if (!$adata["name"] || ($adata["name"] != $this->o["name"])) return true;

		// iterar campos
		foreach ($this->fields as $field=>$f) {
			if ($adata["field"] != $field) continue;

			// límite de subidas
			$limit=$this->filesLimit[$f["type"]];
			if (isset($limit)) {

				// acciones de campos de tipo fichero
				switch ($ajax) {

				case "xform3.file.get":
					$this->ssid($_REQUEST["ssid"]);
					$svalue=$this->svalue($field);
					$index=($_REQUEST["index"]?intval($_REQUEST["index"]):0);
					$file=$svalue["files"][$index];
					// intentar obtener el tipo
					if (!$file["type"]) $file["type"]=$this->mimeByMagic(substr($file["data"], 0, 16));
					//if ($file["deleted"]) die("Deleted.");
					// si se solicita escalado...
					//debug($file);
					if (($w=intval($_REQUEST["w"])) && ($h=intval($_REQUEST["h"])) && $file["type"])
						$file["data"]=$this->imageScale(array("data"=>$file["data"], "w"=>$w, "h"=>$h)+($f["scale"]?$f["scale"]:array()));
					// si no tengo datos, ha ocurrido un error con el escalado, etc.
					//if (!$file["data"]) die("ScaleError.");
					// volcar fichero
					require_once(__DIR__."/kernel.php");
					Kernel::httpOutput(array(
						"type"=>$file["type"],
						"file"=>$file["file"],
						"data"=>$file["data"],
						"name"=>$file["name"],
						"disposition"=>(isset($_REQUEST["attachment"])?"attachment":"inline"),
					));
					// terminar
					exit;

				case "xform3.file.del":
					$this->ssid($adata["ssid"]);
					// marcar para borrar
					$index=($adata["index"]?intval($adata["index"]):0);
					$svalue=$this->svalue($field);
					unset($svalue["files"][$index]["uploaded"]);
					$svalue["files"][$index]["deleted"]=true;
					$this->svalue($field, $svalue);
					// todo ok
					ajax(array("ok"=>true));

				case "xform3.files.upload":
					$this->ssid($_REQUEST["ssid"]);
					// procesar ficheros subidos
					new xUploader(array(
						"_field"=>$field,
						"_f"=>$f,
						"_xf"=>$this,
						"_limit"=>$limit,
						"oncomplete"=>function($uploader, $upload, $o){ // at the end of a completed upload
							$f=$o["_f"];
							$limit=$o["_limit"];
							// valor de sesión
							$svalue=$o["_xf"]->svalue($o["_field"]);
							// índice de entrada
							$index=0;
							if (!$svalue["files"]) $svalue["files"]=array();
							foreach ($svalue["files"] as $i=>$v) if ($i >= $index) $index=($i+1);
							if ($limit && $index+1 >= $limit) $index=$limit-1;
							// entrada
							$j=strrpos($upload["name"], ".");
							$file=array(
								"uploaded"=>true,
								"name"=>$upload["name"],
								"ext"=>($j !== false?substr($upload["name"], $j+1):""),
								"size"=>$upload["size"],
								"sizeo"=>$upload["size"],
								"type"=>$upload["type"],
							);
							// asignamiento automático de nombre en base al fichero
							if ($f["filecaption"]) $file["caption"]=$upload["name"];
							// preparar datos
							if ($f["scale"] && ($w=$f["scale"]["w"]) && ($h=$f["scale"]["h"])) {
								$file["scaled"]=true;
								$file["data"]=$this->imageScale(array("file"=>$upload["tmp"])+$f["scale"]);
							} else {
								$file["data"]=file_get_contents($upload["tmp"]);
							}
							// actualizar tamaño con los datos recuperados
							$file["size"]=strlen($file["data"]);
							// guardar
							$svalue["files"][$index]=$file;
							// guardar en sesión
							$o["_xf"]->svalue($o["_field"], $svalue);
						},
						"onupload"=>function($uploader, $uploads, $o){ // at the end of all uploads
							// valor de sesión
							$svalue=$o["_xf"]->svalue($o["_field"]);
							// construir array sin datos
							$files=array_map(function($file){
								unset($file["data"]);
								return $file;
							}, ($svalue["files"]?$svalue["files"]:array()));
							// devolver información de la subida
							ajax(array(
								"files"=>$files,
								"ok"=>true
							));
						},
					));
					// control de errores
					ajax(array("err"=>"No se han especificado ficheros o se ha excedido el tamaño."));

				}
				break;

			} // ajax

		} // limit

	}

	// devolver lista de errores
	function errors() {
		return $this->errors;
	}

}
