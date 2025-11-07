<?php

// global core
class Kernel {

	static $id=0;
	public $db;
	public $dbref;
	public $conf_table="conf";
	protected $o;

	// constructor
	function __construct($db=null) {
		$this->db($db);
		$this->dbref=__CLASS__.++self::$id;
	}

	// return GET by key or null
	static function get($k) {
		return self::removeMagicQuotes(isset($_GET[(string)$k])?$_GET[(string)$k]:null);
	}

	// return POST by key or null
	static function post($k) {
		return self::removeMagicQuotes(isset($_POST[(string)$k])?$_POST[(string)$k]:null);
	}

	// remove magic quotes, if needed
	static function removeMagicQuotes($v) {
		return (ini_get("magic_quotes_gpc")?stripslashes($v):$v);
	}

	// get/set database
	function db($db=null) {
		if ($db !== null) $this->db=$db;
		return $db;
	}

	// register generic autoloader, base path as an option
	static function autoload($base=null) {
		spl_autoload_register(function($c){
			if (!isset($base) || $base === null) $base=getcwd();
			$f=$base.(substr($base, -1, 1) != "/"?"/":"").strtolower(str_replace('\\', '/', $c)).".php";
			if (file_exists($f)) require_once($f);
		});
	}

	// DEPRECATED: get/set key/value pair in configuration table
	function conf($name, $data=null) {
		if (!$this->db->query("SELECT data FROM ".$this->db->sqltable($this->conf_table)." WHERE name='".$this->db->escape($name)."'", $this->dbref)) return(false);
		if ($data === null) {
			if ($this->db->numrows($this->dbref)) return $this->db->field(null, $this->dbref);
		} else {
			$sql=($this->db->numrows($this->dbref)
				?$this->db->sqlupdate($this->conf_table, array("data"=>$data), array("name"=>$name))
				:$this->db->sqlinsert($this->conf_table, array("data"=>$data, "name"=>$name))
			);
			return $this->db->query($sql, $this->dbref);
		}
		return false;
	}

	// DEPRECATED: delete key from configuration table
	function confdel($name) {
		return $this->db->query($this->db->sqldelete($this->conf_table,array("name"=>$name)), $this->dbref);
	}

	// IP ascendent sort function
	function ipAscSortFunction($a, $b) {
		$ipa=sprintf("%u", ip2long($a));
		$ipb=sprintf("%u", ip2long($b));
		return ($ipa > $ipb?1:($ipa < $ipb?-1:0));
	}

	// IP descendent sort function
	function ipDescSortFunction($b, $a) {
		$ipa=sprintf("%u", ip2long($a));
		$ipb=sprintf("%u", ip2long($b));
		return ($ipa > $ipb?1:($ipa < $ipb?-1:0));
	}
	
	// sort IP array
	function ipArraySort(&$arrayToSort, $desc=false) {
		if (!$desc) uasort($arrayToSort, array($this, "ipAscSortFunction"));
		else uasort($arrayToSort, array($this, "ipDescSortFunction"));
	}

	// count timming from first to second call
	function ctime($doend=true) {
		static $ctime_called;
		$t=(time()+microtime());
		if ($ctime_called) {
			echo "(ctime) Time: ".($t-$ctime_called)."s<br />\n";
			if ($doend) exit;
		}
		$ctime_called=$t;
	}

	// encode mail string
	static function mailEncode($s) {
		return '=?'.\x::charset().'?B?'.base64_encode($s).'?=';
	}

	// encode e-mail destination
	static function mailDestination($s) {
		$s=trim($s);
		if ($i=strrpos($s, " ")) {
			if (substr($s, 0, 1) != "=") {
				return self::mailEncode(substr($s, 0, $i)).substr($s, $i);
			}
		}
		return $s;
	}

	// prepare e-mail destinations
	static function headerMailDestinations($header, $emails) {
		$s="";
		if ($emails)
			foreach ($a=(is_array($emails)?$emails:explode(",", $emails)) as $e)
				$s.=$header.": ".self::mailDestination($e)."\r\n";
		return $s;
	}

	// e-mail sending helper
	/*
		Kernel::mailto([
			"from"=>"sender@domain",
			"to"=>["Name <recipient1@domain>","recipient2@domain"],
			"cc"=>"recipient3@domain,recipient4@domain",
			"subject"=>"Subject",
			"html"=>"Message with <b>HTML</b> <img src='cid:testid' />",
			"text"=>"Message with text fallback",
			"attachments"=>[
				array("name"=>"test.txt", "data"=>"File data", "id"=>"testid"),
				//array("file"=>"test.txt", "type"=>"application/octet-stream"),
			],
		]);
	*/
	static function mailto($o) {
		if (!is_array($o)) return false;
		// prepare boundaries
		$mime_boundary_alt="==awesome_ALT".strtoupper(sha1(uniqid()))."_";
		$mime_boundary_mix="==awesome_MIX".strtoupper(sha1(uniqid()))."_";
		// prepare headers
		$headers
			=(($e=$o["from"])?"From: ".self::mailDestination($e)."\r\n":"")
			.(($e=$o["reply"])?"Reply-To: ".self::mailDestination($e)."\r\n":"")
			.self::headerMailDestinations("Cc", $o["cc"])
			.self::headerMailDestinations("Bcc", $o["bcc"])
			."Date: ".date("r")."\r\n"
			."MIME-Version: 1.0\r\n"
			."Content-Type: multipart/related;\r\n"
			." boundary=\"{$mime_boundary_mix}\"\r\n"
		;
		// add mailer
		if (!isset($o["mailer"]) || $o["mailer"]) $headers.="X-Mailer: ".(($v=$o["mailer"])?$v:"fsme_mailer/1.0.4")."\r\n";
		// add headers
		if (is_string($o["headers"])) $headers.=$o["headers"];
		else if (is_array($o["headers"])) foreach ($o["headers"] as $v) $headers.=$v."\r\n";
		// prepare body
		$body="Scrambled by the Flying Spaghetti Monster Engine Mailer.\r\n\r\n";
		$body.="--{$mime_boundary_mix}\r\n"
			."Content-Type: multipart/alternative;\r\n"
			." boundary=\"{$mime_boundary_alt}\"\r\n"
			."\r\n\r\n"
		;
		if ($v=$o["text"]) {
			$body.="--{$mime_boundary_alt}\r\n"
				."Content-Type: text/plain; charset=UTF-8\r\n"
				."Content-Transfer-Encoding: base64\r\n"
				."\r\n".rtrim(chunk_split(base64_encode($v)))."\r\n\r\n"
			;
		}
		if ($v=$o["html"]) {
			$body.="--{$mime_boundary_alt}\r\n"
				."Content-Type: text/html; charset=UTF-8\r\n"
				."Content-Transfer-Encoding: base64\r\n"
				."\r\n".rtrim(chunk_split(base64_encode($v)))."\r\n\r\n"
			;
		}
		$body.="--{$mime_boundary_alt}--\r\n\r\n";
		if ($o["attachments"]) foreach ($o["attachments"] as $a) {
			$name=basename($a["name"]);
			if ($a["file"]) {
				if (!($a["data"]=file_get_contents($a["file"]))) return false;
				if (!$name) $name=basename($a["file"]);
			}
			if (!$a["type"]) $a["type"]=self::getMimetype($name);
			if ($name) $name=str_replace('"', '_', str_replace(['\r', '*', '?', '/', '\\', '\n'], '', $name));
			$temp=rtrim(chunk_split(base64_encode($a["data"])));
			$body
				.="--{$mime_boundary_mix}\r\n"
				."Content-Type: ".($a["type"]?$a["type"]:"application/octet-stream").($name?";\r\n name=\"".$name."\"":"")."\r\n"
				.($a["id"]?"Content-ID: <".$a["id"].">\r\n":"")
				."Content-Transfer-Encoding: base64\r\n"
				//."Content-Length: ".strlen($temp)."\r\n"
				.($name?"Content-Disposition: attachment;\r\n filename=\"".self::headerFilename($name)."\"\r\n":"")
				."\r\n".$temp."\r\n\r\n"
			;
			unset($temp);
		}
		$body.="--{$mime_boundary_mix}--";
		// prepare destinations
		$to="";
		if ($o["to"]) {
			if (is_array($o["to"])) {
				foreach ($o["to"] as $e) $to.=($to?",":"").self::mailDestination($e);
			} else {
				$to=(strpos($o["to"], ",")?$o["to"]:self::mailDestination($o["to"]));
			}
		} else {
			if ($o["bcc"]) $to="undisclosed-recipients:;";
		}
		// prepare subject: if not 7-bit, encode
		$subject=$o["subject"];
		if (!preg_match("/^[\\040-\\176]+$/", $subject)) $subject=self::mailEncode($o["subject"]);
		// send it!
		return @mail($to, $subject, $body, $headers);
	}

	// dir filename sorting
	static function nameSort($a, $b) {
		return strnatcasecmp($a["name"], $b["name"]);
	}

	// read a directory sorting it naturally, first folders, and return entries as array
	static function dir($p="./") {
		if (substr($p, -1, 1) != DIRECTORY_SEPARATOR) $p.=DIRECTORY_SEPARATOR;
		$fd=array();
		$fa=array();
		$d=dir($p);
		while ($e=$d->read()) {
			if ($e == "." || $e == "..") continue;
			if (is_dir($p.$e)) {
				$fd[]=array(
					"dir"=>true,
					"name"=>$e,
					"mtime"=>filemtime($p.$e),
				);
			} else {
				$fa[]=array(
					"name"=>$e,
					"size"=>sprintf("%u", filesize($p.$e)),
					"mtime"=>filemtime($p.$e),
				);
			}
		}
		$d->close();
		usort($fd, array(__CLASS__, "nameSort"));
		usort($fa, array(__CLASS__, "nameSort"));
		$a=array();
		if ($fd) foreach ($fd as $e) $a[]=$e;
		if ($fa) foreach ($fa as $e) $a[]=$e;
		return $a;
	}

	// recursive file find
	function dir_recursive($root, $paths=false) {
		if (substr($root, -1, 1) != "/") $root.="/";
		$a=array();
		$d=dir($root);
		while ($e=$d->read()) {
			if ($e == "." || $e == "..") continue;
			if (is_dir($root.$e)) {
				$res=$this->dir_recursive($root.$e."/", $paths);
				if ($res)
					foreach ($res as $f)
						$a[]=$f;
				if ($paths) $a[]=$root.$e;
			} else {
				if (!$paths) $a[]=$root.$e;
			}
		}
		$d->close();
		return $a;
	}

	// recursive file deletion (USE WITH CARE)
	function rm_recursive($base) {
		if (!$base) return false;
		if (substr($base,-1,1)!="/") return false;
		if (!file_exists($base)) return false;
		foreach ($this->dir_recursive($base) as $f)
			@unlink($f);
		$rutas=$this->dir_recursive($base, true);
		rsort($rutas);
		foreach ($rutas as $f)
			@rmdir($f);
		return true;
	}

	// return filename trimed and saned without quotes, or empty string if possible hack detected
	static function fileSanity($f) {
		if (strpos($f, "\\") !== false) return "";
		if (strpos($f, "/") !== false) return "";
		if (strpos($f, "..") !== false) return "";
		$f=trim($f);
		$f=str_replace('"', "", $f);
		$f=str_replace("'", "", $f);
		return $f;
	}

	// vuelca el contenido de un fichero/buffer/datos a la web
	static function dumpFile($o) {
		// capturar metodo/tipo
		if ($o["readfile"]) $o["file"]=$o["readfile"]; // compatibilidad
		if ($o["file"]) { $method="file"; $f=basename($o["file"]); }
		if ($o["ob"]) { $method="ob";   $f=$o["ob"]; }
		if ($o["headers"]) { $method="headers"; $f=$o["headers"]; }
		if ($o["data"]) { $method="data"; $f=$o["filename"]; }
		if ($o["filename"]) $f=$o["filename"];
		if (!$o["type"]) $o["type"]=self::getMimetype($f);
		if (!$f) $f="file";
		// cabeceras
		header("Content-Type: ".$o["type"].($o["charset"]?"; charset=".$o["charset"]:""));
		header("Content-Disposition: ".($o["attachment"]?"attachment; ":"inline; ")."filename=\"".self::headerFilename($f).'"');
		switch ($method) {
		case "headers":
			return true;
		case "file":
			header("Content-Length: ".filesize($o["file"]));
			if ($h=fopen($o["file"], 'rb')) {
				while (!feof($h)) {
					echo fread($h, 64*1024);
					ob_flush();
					flush();
				}
				fclose($h);
			}
			break;
		case "ob":
			$o["data"]=ob_get_contents();
			ob_end_clean();
			// no break
		case "data":
		default:
			if (!isset($o["data"])) return false;
			header("Content-Length: ".strlen($o["data"]));
			echo $o["data"];
		}
		// terminar/continuar
		if (!isset($o["exit"]) || $o["exit"]) exit;
		// todo OK
		return true;
	}

	// dump file as attachment
	static function attachment($o) {
		return self::dumpfile($o+array("attachment"=>true));
	}

	// get mimetype for a given filename
	static function getMimetype($f) {
		require_once(__DIR__."/mimetypes.php");
		return Mimetypes::file($f);
	}

	// escape header file name special chars
	static function headerFilename($f) {
		return basename(str_replace(array('"', "'", "?", "*", "\r", "\n", "\t"), "", $f));
	}

	// output data as CSV
	static function csv(array $o) {
		foreach ($defaults=array(
			"attachment"=>true,
			"delimiter"=>'"',
			"separator"=>';',
			"numeric"=>true,
			"doexit"=>true,
			"escape"=>"\\",
			"eol"=>"\n",
			"mimetype"=>self::getMimetype(".csv"),
			"filename"=>"export_".date("YmdHis").".csv",
		) as $k=>$v)
			if (!isset($o[$k]))
				$o[$k]=$v;
		if ($o["mimetype"]) header('Content-Type: '.$o["mimetype"]);
		if ($o["filename"]) header('Content-Disposition: '.($o["attachment"]?"attachment":"inline").'; filename="'.self::headerFilename($o["filename"]).'"');
		if ($o["add_bom"] || $o["addBom"]) echo "\xEF\xBB\xBF"; // BOM header (required for Windows if exported in UTF-8)
		$d=$o["delimiter"];
		$s=$o["separator"];
		$eol=$o["eol"];
		$escape=$o["escape"];
		$numeric=$o["numeric"];
		$filter=$o["filter"];
		if ($r=is_array($o["data"])) {
			foreach ($o["data"] as $i=>$row) {
				if ($filter) $row=$filter($row);
				$c=0;
				foreach ($row as $v=>$n) {
					if (!strlen($d)) $v=str_replace($s, $escape.$s, $v);
					echo ($c++?$s:"").(strlen($d)?$d.str_replace($d, $escape.$d, $v).$d:$v);
				}
				echo $eol;
				break;
			}
			foreach ($o["data"] as $i=>$row) {
				if ($filter) $row=$filter($row);
				$c=0;
				foreach ($row as $n=>$v) {
					$rd=(!strlen($d) || $numeric && is_numeric($v)?"":$d);
					if (!strlen($d)) $v=str_replace($s, $escape.$s, $v);
					echo ($c++?$s:"").$rd.(strlen($d)?str_replace($d, $escape.$d, $v):$v).$rd;
				}
				echo $eol;
			}
		}
		if ($v=$o["doexit"]) exit($v === true?0:$v);
		return $r;
	}

	// replace template values enclosed between tags characters
	static function template($template, $fields, $o=array()) {
		if (!isset($o["tags"])) $o["tags"]="%%";
		$j=0;
		while ($j < strlen($template)) {
			$i=strpos($template, substr($o["tags"], 0, 1), $j);   if ($i === false) break;
			$j=strpos($template, substr($o["tags"], 1, 1), $i+1); if ($j === false) break;
			$v=substr($template, $i+1, $j-$i-1);
			if ($v === "") {
				$template=substr($template, 0, $i).substr($template, $j);
			} else {
				$vs=explode(".", $v);
				$l=&$fields;
				foreach ($vs as $ni=>$nv) {
					if (!isset($l) || !is_array($l)) break;
					$l=&$l[$nv];
				}
				if (isset($l)) {
					$r=(is_callable($l)?$l($v, $nv, $ni):$l);
					$template=substr($template, 0, $i).$r.substr($template, $j+1);
					$j=$i+strlen($r)-1;
				}
			}
			$j++;
		}
		return $template;
	}

	// e-mail verification
	static function verifyEmail($email) {
		if (!$email) return array(false,"Dirección de correo no especificada");
		$atIndex = strrpos($email, "@");
		if ($atIndex===false) return array(false,"No es una dirección de correo.");
		$domain = substr($email, $atIndex+1);
		$local = substr($email, 0, $atIndex);
		$localLen = strlen($local);
		$domainLen = strlen($domain);
		// local part length exceeded
		if ($localLen < 1 || $localLen > 64) return array(false,"Nombre de usuario excede la norma.");
		// domain part length exceeded
		else if ($domainLen < 1 || $domainLen > 255) return array(false,"Longitud del dominio excede la norma.");
		// local part starts or ends with '.'
		else if ($local[0] == '.' || $local[$localLen-1] == '.') return array(false,$genmsg." No se admite . para empezar/terminar el nombre de usuario.");
		// local part has two consecutive dots
		else if (preg_match('/\\.\\./', $local)) return array(false,"Doble puntuación no admitida para el nombre de usuario.");
		// character not valid in domain part
		else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) return array(false,"Nombre de dominio tiene carácteres fuera de norma.");
		// domain part has two consecutive dots
		else if (preg_match('/\\.\\./', $domain)) return array(false,"Doble puntuación no admitida para el dominio.");
		else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
			// character not valid in local part unless local part is quoted
			if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) return array(false,"Caracter no válido.");
		}
		// domain not found in DNS
		if (function_exists("checkdnsrr")) {
			if (!(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A"))) return array(false,"Dominio no existe.");
		}
		// all OK
		return array(true,"Dirección de correo electrónico ".$email." es correcta.");
	}

	// default HTML entities conversion to UTF-8 with double and single quotes
	static function entities($s) {
		return htmlentities($s, ENT_QUOTES, "UTF-8");
	}

	// sanitize input values, trimming outer spaces and HTML tags
	static function sanitize($value, $exclude=array()) {
		if (is_array($value)) {
			$a=array();
			foreach ($value as $i=>$v)
				if (!$exclude[$i])
					$a[$i]=self::sanitize($v);
			return $a;
		} else {
			return ($value === null || $value === true || $value === false?$value:trim(strip_tags($value)));
		}
	}

	/*
		pexec.
		Execute command using pipes.

		Parameters:
	    cmd  Command (required)
	    out  Output returned. (reference)
	    o    Options:
	      in        Send data to the input pipe.
	      cwd       Current Working Directory.
	      env       Setup Environment.
	      buffer    Buffer. (defult 4KB)
	      out       Callback function for output. Called with ($data, $pipes, $proc) parameters.
	      err       Callback function for errors. Called with ($error, $pipes, $proc) parameters.
	      callback  DEPRECATED. Callback function for output. Called with ($data, $pipes, $proc) parameters.
	  Returns:
	    Return code, false if any error found.
	*/
	static function pexec($cmd, &$out=null, $o=array()) {

		// start process
		$proc=proc_open(
			$cmd,
			array(
				0=>array('pipe', 'r'),
				1=>array('pipe', 'w'),
				2=>array('pipe', 'w'),
			),
			$pipes,
			(isset($o["cwd"])?$o["cwd"]:null),
			(isset($o["env"])?$o["env"]:null),
			(isset($o["options"])?$o["options"]:null)
		);
		if (is_resource($proc)) {

			// write data to input pipe
			if (isset($o["in"]) && ($in=$o["in"])) {
				if (is_callable($in)) $in($o, $pipes, $proc);
				else fwrite($pipes[0], $in);
				fclose($pipes[0]);
			}

			// read output and call back if requested
			while (!feof($pipes[1]) || !feof($pipes[2])) {
				if (!feof($pipes[1])) $data=fread($pipes[1], (isset($o["buffer"])?$o["buffer"]:4096));
				if (!feof($pipes[2])) $error=fread($pipes[2], (isset($o["buffer"])?$o["buffer"]:4096));
				if (strlen($error) && isset($o["redir"]) && ($redir=$o["redir"])) {
					if (is_callable($redir)) $error=$redir($error, $pipes, $proc);
					if (is_string($error)) $data.=$error;
				}
				if (is_callable($o["out"])) $o["out"]($data, $pipes, $proc);
				else if ($o["callback"]) {
					$in=$o["callback"]($data, $pipes, $proc);
					if (is_string($in) && strlen($in)) fwrite($pipes[0], $in);
				}
				if (is_callable($o["err"])) $o["err"]($error, $pipes, $proc);
				$out.=$data;
			}

			// close pipes
			if (!isset($o["in"]) && !($in??null)) @fclose($pipes[0]);
			@fclose($pipes[1]);
			@fclose($pipes[2]);

			// terminate process and get return code
			$ret=proc_close($proc);

		} else {
			$ret=false;
		}

		// return
		return $ret;

	}

	// detects charset encoding for a given string
	// thanks to the public code ofered by Clbustos in http://php.apsique.com/node/536 and modified by mape367 in http://www.forosdelweb.com/f18/como-detectar-codificacion-string-448344/
	static function getEncoding($text) {
		$c=0;
		$ascii=true;
		for ($i=0; $i < strlen($text); $i++) {
			$byte=ord($text[$i]);
			if ($c > 0) {
				if (($byte>>6) != 0x2) return "ISO-8859-1";
				else $c--;
			} elseif ($byte&0x80) {
				$ascii=false;
				if (($byte>>5) == 0x6) $c=1;
				elseif (($byte>>4) == 0xE) $c=2;
				elseif (($byte>>3) == 0x14) $c=3;
				else return "ISO-8859-1";
			}
		}
		return ($ascii?"ASCII":"UTF-8");
	}

	// return file via HTTP accepting a byte range protocol
	static function httpOutput($o) {
		if ($o["file"]) {
			if (!$o["type"]) $o["type"]=self::getMimetype($o["file"]);
			if (!$o["len"]) $o["len"]=($o["data"]?strlen($o["data"]):filesize($o["file"]));
			if (!isset($o["name"])) $o["name"]=basename($o["file"]);
		}
		if ($o["attachment"]) $o["disposition"]="attachment";
		if ($o["name"] || $o["disposition"]) {
			header('Content-Disposition: '
				.($o["disposition"]?$o["disposition"]:'')
				.($o["name"]?($o["disposition"]?'; ':'').'filename="'.self::headerFilename($o["name"]).'"':'')
			);
		}
		if (isset($_SERVER['HTTP_RANGE']) && $o["len"]) {
			$ranges=explode(',', substr($_SERVER['HTTP_RANGE'], 6));
			foreach ($ranges as $range) {
				list($start, $end)=explode('-', $range);
				if (!$start) $start=0;
				if (!$end) $end=$o["len"];
				if ($start > $end) {
					header('HTTP/1.1 416 Requested Range Not Satisfiable');
					header('Content-Range: bytes */'.$o["len"]); // required in 416
					exit;
				} else {
					header('HTTP/1.1 206 Partial Content');
					if ($o["type"]) header("Content-Type: ".$o["type"]);
					header("Content-Length: ".$o["len"]);
					header('Content-Range: bytes '.$start.'-'.$end.'/'.$o["len"]);
					header('Accept-Ranges: bytes');
					if ($o["streamer"]) {
						echo $o["streamer"](array_merge($o, array("start"=>$start, "end"=>$end, "length"=>($end-$start))));
					} else if ($o["data"]) {
						echo substr($o["data"], $start, $end-$start);
					} else if ($o["file"]) {
						if ($f=fopen($o["file"], "r")) {
							fseek($f, $start);
							echo fread($f, $end-$start);
							fclose($f);
						}
					}
					if ($end >= $o["len"] && $o["onend"]) $o["onend"]($o);
				}
			}
		} else {
			if ($o["type"]) header("Content-Type: ".$o["type"]);
			if ($o["len"]) header("Content-Length: ".$o["len"]);
			header('Accept-Ranges: bytes');
			if ($o["streamer"]) {
				echo $o["streamer"](array_merge($o, array("all"=>true)));
			} else if ($o["data"]) {
				echo $o["data"];
			} else if ($o["file"]) {
				readfile($o["file"]);
			}
			if ($o["onend"]) $o["onend"]($o);
		}
		exit;
	}

	// DEPRECATED: check if an array is associative
	static function isHash($data) {
		if (is_array($data))
			foreach ($data as $j=>$v)
				return ($j?true:false);
		return false;
	}

	// DEPRECATED: returns a valued array (like array_values)
	static function arrayPure($data) {
		if (self::isHash($data)) {
			$newdata=array();
			foreach ($data as $i=>$v)
				$newdata[]=$i;
			return $newdata;
		} else {
			return $data;
		}
	}

	// DEPRECATED: returns only the keys that exists by name
	static function arrayGet($data, $keys) {
		$newdata=array();
		if ($this->isHash($keys)) {
			foreach ($keys as $k=>$v)
				$newdata[$k]=$data[$k];
		} else {
			foreach ($keys as $k)
				$newdata[$k]=$data[$k];
		}
		return $newdata;
	}

	// DEPRECATED: remove specific keys by name in an associative array
	static function arrayRemove($data, $keys) {
		$keys_aux=self::arrayPure($keys);
		foreach ($data as $k=>$v)
			if (!in_array($k, $keys_aux))
				$newdata[$k]=$data[$k];
		return $newdata;
	}

	// DEPRECATED: get unique keys from an array by key name
	static function keys($data, $key) {
		$a=array();
		if (is_array($data))
			foreach ($data as $v)
				$a[$v[$key]]=true;
		return array_keys($a);
	}

	// DEPRECATED: creates an associative array from a list of array with key name and, optionally, value-name elements.
	static function asoc($data, $key, $value=null) {
		$a=array();
		if (is_array($data))
			foreach ($data as $v)
				$a[$v[$key]]=($v === null?$v:$v[$value]);
		return $a;
	}

	// DEPRECATED: obtener todos los parámetros como array
	function arrayFromQueryString($qs=null) {
		if ($qs === null) $qs=$_SERVER["QUERY_STRING"];
		$a=[];
		if (substr($qs, 0, 1) == "?") $qs=substr($qs, 1);
		parse_str($qs, $a);
		return $a;
	}

	// DEPRECATED: forma una Query String de un array asociativo
	function queryStringFromarray($a) {
		$s=http_build_query($a);
		return (strlen($s)?"?":"").$s;
	}

	// DEPRECATED: suprime un parámetro de una QueryString
	function removeFromQueryString($qs, $p) {
		$a=$this->arrayFromQueryString($qs);
		unset($a[$p]);
		return ($a?$this->queryStringFromarray($a):"");
	}

	// DEPRECATED: obtener mi yo real
	function realme() {
		$me=$_SERVER["REQUEST_URI"];
		if ($i=strpos($me, "?")) $me=substr($me, 0, $i);
		return $me;
	}

	// DEPRECATED: alter link: modificar parámetros de URL
	function alink($parametros=array(), $o=array()) {
		$lnk=(isset($o["link"])?$o["link"]:$this->realme());
		$entities=(isset($o["entities"])?$o["entities"]:true);
		if ($qs=$_SERVER["QUERY_STRING"])
			foreach ($parametros as $p=>$v)
				$qs=$this->removeFromQueryString($qs,$p);
		foreach ($parametros as $p=>$v)
			if ($v!==null)
				$qs.=($qs?"&":"?").urlencode($p).(strlen($v)?"=".urlencode($v):"");
		$qs=($qs && substr($qs,0,1)!="?"?"?":"").$qs;
		return $lnk.($entities?str_replace("&","&amp;",$qs):$qs);
	}

	// DEPRECATED: validate a string for valid bytes
	static function validate($s, $validCharset) {
		for ($i=0; $i<strlen($s); $i++)
			if (strpos($validCharset, $s[$i]) === false)
				return false;
		return true;
	}

	// DEPRECATED: log a message
	function log($msg) {
		if ($this->o["log"]) {
			$s="[".($this->o["log"]["app"]?$this->o["log"]["app"]." ":"").date("YmdHis")."] ".$msg."\n";
			if ($this->o["log"]["file"]) {
				$d=dirname($this->o["log"]["file"]);
				if (!file_exists($d)) @mkdir($d, 0775, true);
				@file_put_contents($this->o["log"]["file"], $s, FILE_APPEND | LOCK_EX);
			}
		}
	}

	// DEPRECATED: log a warning message
	function warn($msg) {
		$this->log("[WARN] ".$msg);
	}

	// DEPRECATED: log an error message
	function err($msg) {
		$this->log("[ERROR] ".$msg);
	}

	// DEPRECATED: launch error
	function perror($o) {
		if (!$o["error"]) $o["error"]="Unknown";
		if (isset($o["visible"]) && $o["visible"] || !isset($o["visible"])) {
			echo "<div style='font-family:FiraSans,FreeSans,Arial;font-size:14px;border-bottom:2px solid #822;color:#222;background:#F0F0F0;border-radius:2px;padding:6px;margin:6px 0;'>"
				.($o["type"] || $o["code"]?"[<b>".$o["type"].($o["code"]?" #".$o["code"]:"")."</b>]":"")
				.($o["file"]?" in <span style='color:#05A;'>".$o["file"].($o["line"]?":".$o["line"]:"")."</span>":"")
				.": <b>".$o["error"]."</b>"
			;
			if ($o["trace"]) { echo "<pre style='padding-left:20px;'>"; print_r($o["trace"]); echo "</pre>"; }
			echo "</div>\n";
		}
		$this->log(trim(
			($o["type"]?" [".$o["type"]."]":"")
			.($o["code"]?" #".$o["code"]:"")
			.($o["file"]?" in ".$o["file"].($o["line"]?":".$o["line"]:""):"")
			.": ".$o["error"]
		));
		if (!isset($o["exit"]) || (isset($o["exit"]) && $o["exit"])) exit(1);
	}

	// DEPRECATED: setup logging to file
	function setuplog($o) {
		if ($o["file"]===true) $o["file"]=dirname(__DIR__)."/data/log/error.log";
		if (!$o["level"]) $o["level"]=E_ALL & ~E_NOTICE;
		$this->o["log"]=$o;
		error_reporting($o["level"]);
		if ($o["capture"]) {
			set_error_handler(function($c, $m, $f, $l) {
				$this->perror(array("visible"=>$this->o["log"]["visible"], "type"=>"ERROR", "code"=>$c, "file"=>$f, "line"=>$l, "error"=>$m));
			}, $o["level"]);
			set_exception_handler(function($e){
				$this->perror(array("visible"=>$this->o["log"]["visible"], "type"=>"EXCEPTION", "code"=>$e->getCode(), "file"=>$e->getFile(), "line"=>$e->getLine(), "error"=>$e->getMessage(), "trace"=>$e->getTraceAsString()));
			});
		}
	}

}
