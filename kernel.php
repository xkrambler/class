<?php

// controlador general
class Kernel {

	static $id=0;
	public $db;
	public $dbref;
	public $conf_table;
	public $process_table;
	protected $o;

	// constructor: se requiere especificar la clase de base de datos que se usará
	function __construct($db=null) {
		$this->db($db);
		$this->setup();
	}

	// get/set de la base de datos asociada
	function db($db=null) {
		if ($db!==null) $this->db=$db;
		return $db;
	}

	// inicializa variables base del núcleo
	function setup() {
		$this->dbref=__CLASS__.++self::$id;
		$this->conf_table="conf";
		$this->process_table="process";
	}

	/**
	 * conf.
	 * Obtiene o establece un parámetro en la configuración del sistema.
	 *
	 * @param String Nombre del parámetro a obtener/establecer.
	 * @param Mixed Datos a almacenar en la base de datos.
	 * @return Mixed Datos obtenidos desde la base de datos.
	 */
	function conf($name, $data=null) {
		if (!$this->db->query("SELECT data FROM ".$this->conf_table." WHERE name='".$this->db->escape($name)."'", $this->dbref)) return(false);
		if ($data===null) {
			if ($this->db->numrows($this->dbref)) return($this->db->field(null, $this->dbref));
		} else {
			if ($this->db->numrows($this->dbref)) $sql=$this->db->sqlupdate($this->conf_table,array("data"=>$data),array("name"=>$name));
			else $sql=$this->db->sqlinsert($this->conf_table,array("name"=>$name,"data"=>$data));
			return $this->db->query($sql, $this->dbref);
		}
		return(false);
	}

	/**
	 * confdel.
	 * Borrar un parámetro de la configuración del sistema.
	 *
	 * @param String Nombre del parámetro a eliminar.
	 */
	function confdel($name) {
		return $this->db->query($this->db->sqldelete($this->conf_table,array("name"=>$name)), $this->dbref);
	}

	/**
	 * process.
	 * Obtiene/establece los datos de un proceso en la base de datos.
	 *
	 * @param String Identificador de sesión.
	 * @param String Nombre de la variable almacenada.
	 * @param Mixed (optional) Datos que se almacenarán en la base de datos.
	 */
	function process($sid,$name,$data=null) {
		if (!$this->db->query("SELECT * FROM ".$this->process_table." WHERE sessionid='".$this->db->escape($sid)."' AND name='".$this->db->escape($name)."'", $this->dbref)) return false;
		$row=$this->db->row($this->dbref);
		if ($data==null) {
			return(unserialize($row["data"]));
		} else {
			$fields=array("sessionid"=>$sid,"updated"=>date("Y-m-d H:i:s"),"name"=>$name,"data"=>serialize($data));
			$sql=($row["sessionid"]
				?$this->db->sqlupdate($this->process_table,$fields,array("sessionid"=>$sid,"name"=>$name))
				:$this->db->sqlinsert($this->process_table,$fields)
			);
			return $this->db->query($sql, $this->dbref);
		}
	}

	/**
	 * processRemove.
	 * Elimina datos de un proceso existente en la base de datos.
	 *
	 * @param String Identificador de sesión.
	 * @param String Nombre de la variable almacenada.
	 */
	function processRemove($sid,$name) {
		return $this->db->query($this->db->sqldelete($this->process_table,array("sessionid"=>$sid,"name"=>$name)), $this->dbref);
	}

	/**
	 * removeMagicQuotes.
	 * Obtiene un dato AJAX y elimina magic quotes (si es necesario).
	 *
	 * @return string Datos sin las magic quotes.
	 */
	function removeMagicQuotes($data) {
		return(ini_get("magic_quotes_gpc")
			?str_replace("\\\\","\\",str_replace("\\\"","\"",str_replace("\\'","'",$data)))
			:$data
		);
	}

	/**
	 * get.
	 * Obtiene un dato por usando $_REQUEST y lo filtra,
	 * eliminando Magic Quotes si las posee.
	 *
	 * @param String Nombre de variable requerida.
	 * @return String Datos recibidos y filtrados.
	 */
	function get($variable) {
		return $this->removeMagicQuotes($_REQUEST[$variable]);
	}

	/**
	 * dbget.
	 * Obtiene un dato por usando $_REQUEST y lo filtra,
	 * eliminando Magic Quotes si las posee y además escapando caracteres SQL.
	 *
	 * @param String Nombre de variable requerida.
	 * @return String Datos recibidos y filtrados.
	 */
	function dbget($variable) {
		return $this->db->escape($this->get($variable));
	}

	/**
	 * isHash.
	 * Comprueba si un array es asociativo o no lo es.
	 *
	 * @return boolean Verdadero si es asociativo o False si es un array puro.
	 */
	static function isHash($data) {
		foreach ($data as $j=>$v)
			return ($j?true:false);
	}

	/**
	 * arrayPure.
	 * Devuelve un array no asociativo desde uno asociativo.
	 * Si se pasa un array no asociativo, no hace nada y devuelve el mismo.
	 *
	 * @return array Array no asociativo puro.
	 */
	static function arrayPure($data) {
		if (self::isHash($data)) {
			$newdata=array();
			foreach ($data as $i=>$v)
				$newdata[count($newdata)]=$i;
			return $newdata;
		} else {
			return $data;
		}
	}

	/**
	 * arrayGet.
	 * Devuelve las claves especificadas de una matriz asociativa.
	 *
	 * @return array Devuelve únicamente las claves especificadas en $keys que existan en $data.
	 */
	static function arrayGet($data,$keys) {
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

	/**
	 * arrayRemove.
	 * Elimina las claves especificadas de una matriz asociativa.
	 *
	 * @return array Devuelve el mismo array cuyas claves especificadas en $keys han sido eliminadas de $data.
	 */
	static function arrayRemove($data, $keys) {
		$keys_aux=self::arrayPure($keys);
		foreach ($data as $k=>$v)
			if (!in_array($k, $keys_aux))
				$newdata[$k]=$data[$k];
		return $newdata;
	}

	/**
	 * keys.
	 * Obtiene las claves únicas de un array por su nombre de clave.
	 *
	 * @param Array Array con la lista de elementos.
	 * @param String Cadena con el nombre de la clave.
	 * @return array Devuelve un array con las claves únicas.
	 */
	static function keys($data, $key) {
		$a=array();
		if (is_array($data))
			foreach ($data as $v)
				$a[$v[$key]]=true;
		return array_keys($a);
	}

	/**
	 * IP Ascendent Sort Function.
	 * Función de ordenación por IP ascendente para usar con uasort.
	 *
	 * @param String Primera dirección IP a comparar.
	 * @param String Segunda dirección IP a comparar.
	 * @return Integer 1 si primero es mayor que segundo, -1 en caso contrario, 0 en caso de ser iguales
	 */
	function ipAscSortFunction($a, $b) {
		$ipa=sprintf("%u", ip2long($a));
		$ipb=sprintf("%u", ip2long($b));
		return ($ipa > $ipb?1:($ipa < $ipb?-1:0));
	}

	/**
	 * IP Descendent Sort Function.
	 * Función de ordenación por IP descendente para usar con uasort.
	 * Puede usarse el método ipArraySort.
	 *
	 * @param String Primera dirección IP a comparar.
	 * @param String Segunda dirección IP a comparar.
	 * @return Integer 1 si primero es mayor que segundo, -1 en caso contrario, 0 en caso de ser iguales
	 */
	function ipDescSortFunction($b, $a) {
		$ipa=sprintf("%u", ip2long($a));
		$ipb=sprintf("%u", ip2long($b));
		return ($ipa > $ipb?1:($ipa < $ipb?-1:0));
	}
	
	/**
	 * IP Array Sort.
	 * Función de ordenación de arrays con IPs con preservación de claves.
	 *
	 * @param Array Array que desea ser ordenado
	 * @param Boolean TRUE si se desea invertir la ordenación ascendente y convertirla a descendente.
	 */
	function ipArraySort(&$arrayToSort, $desc=false) {
		if (!$desc) uasort($arrayToSort, array($this, "ipAscSortFunction"));
		else uasort($arrayToSort, array($this, "ipDescSortFunction"));
	}

	// obtener todos los parámetros como array
	function arrayFromQueryString($qs=null) {
		if ($qs === null) $qs=$_SERVER["QUERY_STRING"];
		$a=[];
		if (substr($qs, 0, 1)=="?") $qs=substr($qs, 1);
		parse_str($qs, $a);
		//debug($a);
		return $a;
	}

	// forma una Query String de un array asociativo
	function queryStringFromarray($a) {
		$s=http_build_query($a);
		return (strlen($s)?"?":"").$s;
	}

	// suprime un parámetro de una QueryString
	function removeFromQueryString($qs, $p) {
		$a=$this->arrayFromQueryString($qs);
		unset($a[$p]);
		return ($a?$this->queryStringFromarray($a):"");
	}

	// obtener mi yo real
	function realme() {
		$me=$_SERVER["REQUEST_URI"];
		if ($i=strpos($me, "?")) $me=substr($me, 0, $i);
		return $me;
	}

	// alter link: modificar parámetros de URL
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

	// contar el tiempo desde la primera vez que se llama hasta la segunda
	function ctime($doend=true) {
		static $ctime_called;
		$t=(time()+microtime());
		if ($ctime_called) {
			echo "(ctime) Tiempo: ".($t-$ctime_called)."s<br />\n";
			if ($doend) exit;
		}
		$ctime_called=$t;
	}

	// función para simplificar el envío de emails, admite adjuntos
	/*
		Ejemplo:
			Kernel::mailto(array(
				"to"=>"recipient@domain",
				"from"=>"sender@domain2",
				"subject"=>"Subject",
				"html"=>"Message with <b>HTML</b> <img src='cid:testid' />",
				"text"=>"Message with text fallback",
				"attachments"=>array(
					array("name"=>"test.txt", "data"=>"File data", "id"=>"testid"),
					//array("file"=>"test.txt", "type"=>"application/octet-stream"),
				),
			));
	*/
	static function mailto($o) {
		// prepare boundaries
		$mime_boundary_alt="==awesome_ALT".strtoupper(sha1(uniqid()))."_";
		$mime_boundary_mix="==awesome_MIX".strtoupper(sha1(uniqid()))."_";
		// prepare headers
		$headers
			="From: ".$o["from"]."\r\n"
			."Reply-To: ".($o["reply"]?$o["reply"]:$o["from"])."\r\n"
			.($o["cc"]?"Cc: ".$o["cc"]."\r\n":"")
			.($o["bcc"]?"Bcc: ".$o["bcc"]."\r\n":"")
			."Date: ".date("r")."\r\n"
			."MIME-Version: 1.0\r\n"
			."Content-Type: multipart/related;\r\n"
			." boundary=\"{$mime_boundary_mix}\"\r\n"
			."X-Mailer: fsme_mailer/1.0.1"
		;
		// prepare body
		$body="Scrambled for your security by the Flying Spaguetti Monster Engine Mailer.\r\n\r\n";
		$body.="--{$mime_boundary_mix}\r\n"
			."Content-Type: multipart/alternative;\r\n"
			." boundary=\"{$mime_boundary_alt}\"\r\n"
			."\r\n\r\n"
		;
		if ($o["text"]) {
			$body.="--{$mime_boundary_alt}\r\n"
				."Content-Type: text/plain; charset=UTF-8\r\n"
				."Content-Transfer-Encoding: base64\r\n"
				."\r\n".rtrim(chunk_split(base64_encode($o["text"])))."\r\n\r\n"
			;
		}
		if ($o["html"]) {
			$body.="--{$mime_boundary_alt}\r\n"
				."Content-Type: text/html; charset=UTF-8\r\n"
				."Content-Transfer-Encoding: base64\r\n"
				."\r\n".rtrim(chunk_split(base64_encode($o["html"])))."\r\n\r\n"
			;
		}
		$body.="--{$mime_boundary_alt}--\r\n\r\n";
		if ($o["attachments"]) foreach ($o["attachments"] as $a) {
			$name=basename($a["name"]);
			if ($a["file"]) {
				if (!($a["data"]=file_get_contents($a["file"]))) {
					// failed to load file, PHP warning must appear if enabled
					return false;
				}
				if (!$name) $name=basename($a["file"]);
			}
			if (!$a["type"]) $a["type"]=self::getMimetype($name);
			if ($name) $name=str_replace('"', '_', str_replace(['\r','*','?','/','\\','\n'], '', $name));
			$temp=rtrim(chunk_split(base64_encode($a["data"])));
			$body
				.="--{$mime_boundary_mix}\r\n"
				."Content-Type: ".($a["type"]?$a["type"]:"application/octet-stream").($name?";\r\n name=\"".$name."\"":"")."\r\n"
				.($a["id"]?"Content-ID: <".$a["id"].">\r\n":"")
				."Content-Transfer-Encoding: base64\r\n"
				//."Content-Length: ".strlen($temp)."\r\n"
				.($name?"Content-Disposition: attachment;\r\n filename=\"".$name."\"\r\n":"")
				."\r\n".$temp."\r\n\r\n"
			;
			unset($temp);
		}
		$body.="--{$mime_boundary_mix}--";
		// prepare destinations
		$to="";
		if ($o["to"]) {
			$tos=explode(",",$o["to"]);
			foreach ($tos as $rcpt) {
				if ($i=strpos($rcpt,"<")) $rcpt=substr($rcpt,$i+1);
				if ($i=strpos($rcpt,">")) $rcpt=substr($rcpt,0,$i);
				$to.=($to?",":"").$rcpt;
			}
		} else {
			if ($o["bcc"]) $to="undisclosed-recipients:";
		}
		// prepare subject: if not 7-bit, base64_encode(UTF-8)
		$subject=$o["subject"];
		if (!preg_match("/^[\\040-\\176]+$/", $subject))
			$subject="=?UTF-8?B?".base64_encode($o["subject"])."?=";
		// send it!
		//header("Content-type: text/plain");echo $subject."\r\n".$headers."\r\n-------------------------\r\n".$body;exit;
		return (@mail($to, $subject, $body, $headers));
	}

	// ordenación de nombres
	static function name_sort($a,$b) {
		return (strtolower($a["name"]) > strtolower($b["name"])?1:(strtolower($a["name"]) < strtolower($b["name"])?-1:0));
	}

	// lee un directorio y lo devuelve como array ordenado con sus propiedades
	// (primero carpetas, luego ficheros)
	static function dir($p) {
		if (substr($p, -1, 1) != DIRECTORY_SEPARATOR) $p.=DIRECTORY_SEPARATOR;
		$fd=array();
		$fa=array();
		$d=dir($p);
		while ($e=$d->read()) {
			if ($e=="." || $e=="..") continue;
			if (is_dir($p.$e)) {
				$fd[]=array(
					"dir"=>true,
					"name"=>$e,
					"path"=>$p,
					"mtime"=>filemtime($p.$e),
				);
			} else {
				$fa[]=array(
					"name"=>$e,
					"path"=>$p,
					"size"=>sprintf("%u", filesize($p.$e)),
					"mtime"=>filemtime($p.$e),
				);
			}
		}
		$d->close();
		usort($fd, array(__CLASS__, "name_sort"));
		usort($fa, array(__CLASS__, "name_sort"));
		$a=array();
		if ($fd) foreach ($fd as $e) $a[]=$e;
		if ($fa) foreach ($fa as $e) $a[]=$e;
		return $a;
	}

	// localización de ficheros/directorios recursivamente
	function dir_recursive($root, $paths=false) {
		if (substr($root,-1,1)!="/") $root.="/";
		$a=array();
		$d=dir($root);
		while ($e=$d->read()) {
			if ($e=="." || $e=="..") continue;
			if (is_dir($root.$e)) {
				$res=$this->dir_recursive($root.$e."/",$paths);
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

	// borrado de ficheros de forma recursiva (USAR CON OJO)
	function rm_recursive($base) {
		if (!$base) return false;
		if (substr($base,-1,1)!="/") return false;
		if (!file_exists($base)) return false;
		foreach ($this->dir_recursive($base) as $f)
			@unlink($f);
		$rutas=$this->dir_recursive($base,true);
		rsort($rutas);
		foreach ($rutas as $f)
			@rmdir($f);
		return true;
	}

	// devuelve el nombre del fichero saneado sin comas, etc.
	// o bien devuelve una cadena vacía si encuentra indicios de hack
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
	static function dumpfile($o) {
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
		header("Content-Type: ".$o["type"].($o["charset"]?"; charset: ".$o["charset"]:""));
		header("Content-Disposition: ".($o["attachment"]?"attachment;":"inline;")."; filename=\"".str_replace("\"", "''", $f)."\"");
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

	// lanza un adjunto
	static function attachment($o) {
		return self::dumpfile($o+array("attachment"=>true));
	}

	// obtener mimetype de un nombre de fichero dada su extensión
	static function getMimetype($f) {
		require_once(__DIR__."/mimetypes.php");
		return Mimetypes::file($f);
	}

	// exportar datos a CSV
	static function csv($o) {
		$f="export_".time().".csv";
		if ($o["filename"]) $f=$o["filename"];
		if (!isset($o["delimiter"])) $o["delimiter"]='"';
		if (!isset($o["separator"])) $o["separator"]=';';
		if (!isset($o["eol"])) $o["eol"]="\n";
		if (!isset($o["headers"]) || $o["headers"]) {
			header("Content-Type: ".self::getMimetype(".csv"));
			header("Content-Disposition: attachment; filename=\"".str_replace("\"","''",$f)."\"");
		}
		if ($o["addBom"]) {
			// Esta modificación hace que los csv generados se vean bien en Excel para Windows, pero no en el de MAC
			// Para que funcione bien en el Excel for MAC, hay que generar el fichero en formato UTF-16.
			// - Añadir BOM para que el texto en UTF-8 se vea bien en Excel -
			echo "\xEF\xBB\xBF";
		}
		foreach ($o["data"] as $i=>$row) {
			$first=true;
			if ($o["filter"]) $row=$o["filter"]($row);
			if (!$i) {
				foreach ($row as $n=>$v) {
					echo ($first?"":$o["separator"]).$o["delimiter"].str_replace($o["delimiter"],"\\".$o["delimiter"],$n).$o["delimiter"];
					$first=false;
				}
				echo $o["eol"];
			}
			$first=true;
			foreach ($row as $n=>$v) {
				echo ($first?"":$o["separator"]).$o["delimiter"].str_replace($o["delimiter"],"\\".$o["delimiter"],$v).$o["delimiter"];
				$first=false;
			}
			echo $o["eol"];
		}
		exit;
	}

	// sustituye valores de un template por sus correspondientes en los datos
	static function template($template, $fields, $o=array()) {
		if (!$o["tags"]) $o["tags"]="%%";
		$j=0;
		while ($j < strlen($template)) {
			$i=strpos($template, substr($o["tags"], 0, 1), $j);   if ($i === false) break;
			$j=strpos($template, substr($o["tags"], 1, 1), $i+1); if ($j === false) break;
			$v=substr($template, $i+1, $j-$i-1);
			if ($v==="") {
				$template=substr($template, 0, $i).substr($template, $j);
			} else {
				$vs=explode(".", $v);
				$l=&$fields;
				foreach ($vs as $v) {
					if (!isset($l)) break;
					$l=&$l[$v];
				}
				if (isset($l)) {
					$r=(is_callable($l)?$l():$l);
					$template=substr($template, 0, $i).$r.substr($template, $j+1);
					$j=$i+strlen($r)-1;
				}
			}
			$j++;
		}
		return $template;
	}

	// verificación de email
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
			if (!(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) return array(false,"Dominio no existe.");
		}
		// all OK
		return array(true,"Dirección de correo electrónico ".$email." es correcta.");
	}

	// convertir a entidades HTML UTF-8 incluidas comillas dobles y simples
	static function entities($s) {
		return htmlentities($s, ENT_QUOTES, "UTF-8");
	}

	// sanea los campos de entrada, quitando espacios y tags
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
		Ejecutar mediante tuberías (piped exec).

		Parámetros:
	   cmd  Comando a ejecutar. Obligatorio.
	   out  Salida del comando, se pasa por referencia.
	   o    Opciones, un array que puede contener:
	     in        Datos de entrada a enviar al comando.
	     cwd       Directorio de ejecución.
	     env       Datos de entorno adicionales.
	     buffer    Buffer. Por defecto, 4KB.
	     callback  Función de callback, se llama con los parámetros $data, $pipes y $proc.
	 Devuelve:
	   Código de retorno, o false si ha ocurrido algún error
	*/
	static function pexec($cmd, &$out=null, $o=array()) {

		// lanzar comando
		$proc=proc_open(
			$cmd,
			array(
				0=>array("pipe", 'r'),
				1=>array("pipe", 'w'),
				2=>array('pipe', 'w'),
			),
			$pipes,
			($o["cwd"]?$o["cwd"]:null),
			($o["env"]?$o["env"]:null),
			($o["options"]?$o["options"]:null)
		);
		if (is_resource($proc)) {

			// escribir datos iniciales en entrada del comando
			if ($o["in"]) fwrite($pipes[0], $o["in"]);

			// leer lineas en bloques de 4KB
			while (!feof($pipes[1])) {
				$data=fread($pipes[1], ($o["buffer"]?$o["buffer"]:4096));
				if ($o["callback"]) {
					$in=$o["callback"]($data, $pipes, $proc);
					if (strlen($in)) fwrite($pipes[0], $in);
				}
				$out.=$data;
			}

			// cerrar tuberías
			fclose($pipes[0]);
			fclose($pipes[1]);

			// terminar proceso y obtener código de retorno
			$ret=proc_close($proc);

		} else {
			$out="";
			$ret=false;
		}

		// devolver código de error
		return $ret;

	}

	/**
	 * getEncoding.
	 * Detecta la codificación de un texto dado.
	 * Thanks to the public code ofered by Clbustos in http://php.apsique.com/node/536
	 * and modified by mape367 in http://www.forosdelweb.com/f18/como-detectar-codificacion-string-448344/
	 *
	 * @param String Texto cuya codificación se va a detectar.
	 * @return Encoding (UTF-8, ASCII o ISO-8859-1)
	 */
	static function getEncoding($text) {
		$c = 0;
		$ascii = true;
		for ($i=0;$i<strlen($text);$i++) {
			$byte = ord($text[$i]);
			if ($c>0) {
				if (($byte>>6) != 0x2) {
					return "ISO-8859-1";
				} else {
					$c--;
				}
			} elseif ($byte&0x80) {
				$ascii = false;
				if (($byte>>5) == 0x6) {
					$c = 1;
				} elseif (($byte>>4) == 0xE) {
					$c = 2;
				} elseif (($byte>>3) == 0x14) {
					$c = 3;
				} else {
					return "ISO-8859-1";
				}
			}
		}
		return ($ascii?"ASCII":"UTF-8");
	}

	/**
	 * validate.
	 * Comprueba si una cadena contiene sólo caracteres válidos
	 * especificados en la segunda cadena.
	 *
	 * @param String Cadena a verificar.
	 * @param String Cadena de caracteres válidos.
	 * @return Boolean true si cadena correcta, false en caso contrario.
	 */
	static function validate($s, $validCharset) {
		for ($i=0; $i<strlen($s); $i++)
			if (strpos($validCharset, $s[$i]) === false)
				return false;
		return true;
	}

	// devolver un fichero via HTTP aceptando rangos
	static function httpOutput($o) {
		if ($o["file"]) {
			if (!$o["type"]) $o["type"]=self::getMimetype($o["file"]);
			if (!$o["len"]) $o["len"]=($o["data"]?strlen($o["data"]):filesize($o["file"]));
			if (!isset($o["name"])) $o["name"]=basename($o["file"]);
		}
		if ($o["name"] || $o["disposition"]) {
			header("Content-Disposition: "
				.($o["disposition"]?$o["disposition"]:"")
				.($o["name"]?($o["disposition"]?"; ":"")."filename=\"".str_replace("\"","''",$o["name"])."\"":"")
			);
		}
		// obtener inicio y fin de un rango especificado (leer solo el primero)
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
						echo $o["streamer"](array_merge($o,array("start"=>$start,"end"=>$end,"length"=>($end-$start))));
					} else if ($o["data"]) {
						echo substr($o["data"], $start, $end-$start);
					} else if ($o["file"]) {
						if ($f=fopen($o["file"], "r")) {
							fseek($f, $start);
							echo fread($f, $end-$start);
							fclose($f);
						}
					}
				}
			}
		} else {
			if ($o["type"]) header("Content-Type: ".$o["type"]);
			if ($o["len"]) header("Content-Length: ".$o["len"]);
			header('Accept-Ranges: bytes');
			if ($o["streamer"]) {
				echo $o["streamer"](array_merge($o,array("all"=>true)));
			} else if ($o["data"]) {
				echo $o["data"];
			} else if ($o["file"]) {
				if ($f=fopen($o["file"],"r")) {
					while (!feof($f))
						echo fread($f, 1024);
					fclose($f);
				}
			}
		}
		exit;
	}

	// registra un mensaje
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

	// registra un evento de advertencia
	function warn($msg) {
		$this->log("[WARN] ".$msg);
	}

	// registra un evento de error
	function err($msg) {
		$this->log("[ERROR] ".$msg);
	}

	// lanzar error
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

	// registrar eventos de error a fichero
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
