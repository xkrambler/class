<?php

/*
	Basic CSV generator class.
*/
class CSV {

	protected $o;
	protected $first=true;

	// constructor
	function __construct($o=[]) {
		$this->o=$o;
		$this->reset();
	}

	// getter/setter/isset
	function __get($n) { return $this->setup[$n]; }
	function __set($n, $v) { $this->setup[$n]=$v; }
	function __isset($n) { return isset($this->setup[$n]); }

	// reset
	function reset($o=[]) {
		$this->o=array_merge($this->o, $o);
		$f=(isset($this->o["file"])?$this->o["file"]:"export_".time().".csv");
		if (!isset($this->o["delimiter"])) $this->o["delimiter"]='"';
		if (!isset($this->o["separator"])) $this->o["separator"]=';';
		if (!isset($this->o["eol"])) $this->o["eol"]="\n";
		if (!isset($this->o["headers"]) || $this->o["headers"]) {
			if (!isset($this->o["mimetype"])) {
				if (strtolower(substr($f, -4, 4)) == ".csv") $this->o["mimetype"]='text/csv';
				else {
					require_once(__DIR__."/mimetypes.php");
					$this->o["mimetype"]=Mimetypes::file($f);
				}
			}
			if ($this->o["mimetype"]) header("Content-Type: ".$this->o["mimetype"]);
			if ($f) header("Content-Disposition: attachment; filename=\"".str_replace("\"", "''", $f)."\"");
		}
		if ($this->o["addbom"]) {
			// Esta modificación hace que los CSV generados se vean bien en Excel para Windows, pero no en el de MAC.
			// Para que funcione bien en el Excel for MAC, hay que generar el fichero en formato UTF-16.
			// - Añadir BOM para que el texto en UTF-8 se vea bien en Excel
			echo "\xEF\xBB\xBF";
		}
		$this->first=true;
	}

	// escape value
	function escape($v) {
		return str_replace($this->o["delimiter"], "\\".$this->o["delimiter"], $v);
	}

	// value
	function value($v) {
		return $this->o["delimiter"].$this->escape($v).$this->o["delimiter"];
	}

	// export row
	function row($row) {
		if ($this->o["filter"]) $row=$this->o["filter"]($row);
		if ($this->first) {
			$this->first=false;
			$i=0;
			foreach ($row as $n=>$v)
				echo ($i++?$this->o["separator"]:"").$this->value($n);
			echo $this->o["eol"];
		}
		$i=0;
		foreach ($row as $n=>$v)
			echo ($i++?$this->o["separator"]:"").$this->value($v);
		echo $this->o["eol"];
	}

}
