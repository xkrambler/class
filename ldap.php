<?php

// constantes
define("LDAP_ERR_NONE", 0);
define("LDAP_ERR_AUTH", 1);
define("LDAP_ERR_CLONED", 2);
define("LDAP_ERR_NOUSER", 3);
define("LDAP_ERR_PUBLIC", 4);
define("LDAP_ERR_PRIVATE", 5);
define("LDAP_ERR_HOST", 6);
define("LDAP_ERR_DISABLED", 7);
define("LDAP_ERR_NODC", 8);
define("LDAP_ERR_QUERYSTRING", 9);
define("LDAP_ERR_NOMODULE", 10);

/**
 * LDAP.
 * Clase de manejo de LDAP, developed by Pablo Rodríguez Rey (http://mr.xkr.es/)
 * Distributed under the GPL version 2 license (http://www.gnu.org/copyleft/gpl.html)
 *
 * @author Pablo Rodríguez Rey
 * @link http://mr.xkr.es/
 * @since 2009-05-08
 * @copyright GPL version 2
 * @version 0.2.1
 */
class LDAP {

	public $setup;
	public $ds;
	public $info;
	protected $isconnected=false;
	protected $err;
	protected $ldaperrno;
	protected $ldaperror;

	// constructor
	function __construct($setup) {
		$this->setup=$setup;
	}

	// conectar al servidor LDAP
	function connect() {

		// si ya estaba conectado, no reintentar
		if ($this->isConnected()) return true;

		// verificar que la librería php-ldap esté habilitada
		if (!function_exists("ldap_connect")) {
			$this->err=LDAP_ERR_NOMODULE;
			$this->isconnected=false;
			return false;
		}

		// conectar al servidor LDAP
		$this->ds=(@ldap_connect($this->setup["host"],($this->setup["port"]?$this->setup["port"]:389)));
		if (!$this->ds) {
			$this->err=LDAP_ERR_HOST;
			$this->isconnected=false;
			return false;
		}

		// opciones
		ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		//ldap_set_option($this->ds, LDAP_OPT_REFERRALS, 0);

		// realizar autentificación privada al portal (si está especificada)
		if ($this->setup["authstring"] && $this->setup["authpass"]) {
			$retv=(@ldap_bind($this->ds, $this->setup["authstring"], $this->setup["authpass"]));
			if ($this->ldaperrno=ldap_errno($this->ds)) $this->ldaperror=ldap_error($this->ds);
			if (!$retv) {
				ldap_close($this->ds);
				$this->err=LDAP_ERR_PRIVATE;
				$this->isconnected=false;
				return false;
			}
		} else {
			/*$r=(@ldap_bind($this->ds));
			if (ldap_errno($this->ds)>0) {
				$this->err=LDAP_ERR_PUBLIC;
				$this->isconnected=false;
				return false;
			}*/
		}

		// conectar
		$this->isconnected=true;
		return true;

	}

	// desconectar del servidor LDAP
	function close() {
		if ($this->ds) @ldap_close($this->ds);
		$this->ds=null;
		$this->isconnected=false;
	}

	// devolver si se ha establecido la conexión previamente
	function isConnected() {
		return $this->isconnected;
	}

	// realiza la autenticación contra el servidor LDAP
	function auth($user, $pass) {

		// inicialmente no ha ocurrido ningún error
		$this->err=LDAP_ERR_NONE;

		// comprobar si está habilitada la autentificación LDAP
		if (!$this->setup["enabled"]) {
			$this->err=LDAP_ERR_DISABLED;
			return false;
		}

		// conectar con servidor LDAP
		if (!$this->connect()) return false;

		// buscar usuario
		$sr=(@ldap_search($this->ds, $this->setup["querystring"], $this->setup["userquery"].$user));
		if (!$sr) {
			$this->err=LDAP_ERR_QUERYSTRING;
			return false;
		}
		$info=ldap_get_entries($this->ds, $sr);
		if ($this->ldaperrno=ldap_errno($this->ds)) $this->ldaperror=ldap_error($this->ds);
		if ($info["count"] == 1) {

			// devolver autentificación (0=fallada, 1=correcta)
			$retv=(@ldap_bind($this->ds, $info[0]["dn"], $pass)?1:0);
			if ($this->ldaperrno=ldap_errno($this->ds)) $this->ldaperror=ldap_error($this->ds);
			if ($retv) {

				// una vez autentificado, volver a buscar el mismo dn
				// para obtener información no disponible sin la autentificación
				$sr=ldap_search($this->ds,$info[0]["dn"], "uid=*");  
				$info=ldap_get_entries($this->ds, $sr);
				$this->info=$info[0];
				return $this->info;

			} else {
				
				// autenticación erronea
				$this->close();
				$this->err=LDAP_ERR_AUTH;
				return false;
				
			}
			
		} elseif ($info["count"]>1) {
			
			// más de un usuario con el mismo identificador
			$this->close();
			$this->err=LDAP_ERR_CLONED;
			return false;
			
		} else {
			
			// no existe el identificador
			$this->close();
			$this->err=LDAP_ERR_NOUSER;
			return false;
			
		}
		
	}

	// búsqueda
	function search($o) {

		// asegurarse conexión
		if (!$this->connect()) return false;

		// realizar búsqueda
		if (!$r=@ldap_search(
			$this->ds,
			$o["dn"],
			(isset($o["filter"])?$o["filter"]:""),
			(isset($o["attr"])?$o["attr"]:array()),
			(isset($o["attrsonly"])?$o["attrsonly"]:null),
			(isset($o["sizelimit"])?$o["sizelimit"]:null),
			(isset($o["timelimit"])?$o["timelimit"]:null),
			(isset($o["deref"])?$o["deref"]:null)
		)) {
			if ($this->ldaperrno=ldap_errno($this->ds)) $this->ldaperror=ldap_error($this->ds);
			return false;
		}

		// obtener resultados
		$r=ldap_get_entries($this->ds, $r);

		// devolver resultados
		return ($o["normalize"]?($r?$this->normalize($r):array()):$r);

	}

	// búsqueda normalizada
	function nsearch($o) {
		return $this->search($o+array("normalize"=>true));
	}

	// normalizar array de entradas
	function normalize($a) {
		$r=array();
		if ($a["count"]) {
			for ($i=0;$i<$a["count"];$i++) {
				if (is_array($a[$i])) {
					$r[$i]=$this->normalize($a[$i]);
				} else {
					if (is_array($a[$a[$i]])) $r[$a[$i]]=$this->normalize($a[$a[$i]]);
					else $r[$i]=$a[$i];
				}
			}
		}
		return $r;
	}

	// devuelve información asociada a la autenticación
	function info() {
		return $this->info;
	}

	// muestra un mensaje de error dependiendo del medio (librería VeryTinyAJAX)
	function err($doexit=true) {
		global $ajax;
		if ($ajax) $this->aerr($doexit);
		else $this->herr($doexit);
	}

	// devolver error en formato HTML
	function herr($doexit=true) {
		echo "<div style='font-family: Arial;'><h3 style='display: block; color: red; font-size: 19px;'>Error #".$this->errnum()." en autenticación LDAP:</h3>"
				."<div style='font-size: 13px; position: relative; top: -23px; border: 1px solid #F00; padding: 0px 9px 0px 9px;'>"
				."<p><b>Descripción:</b><br><span style='color: green;'>".$this->error()."</span></p>"
				."</div></div>";
		if ($doexit) exit;
	}

	// muestra un mensaje de error en la consulta PostgreSQL en texto plano
	function perr($doexit=true) {
		echo "LDAP error ".$this->errnum().": ".$this->error()."\n";
		if ($doexit) exit;
	}

	// devolver error en formato AJAX (librería VeryTinyAJAX)
	function aerr($doexit=true) {
		aput("err","Error #".$this->errnum()." en la autenticación LDAP:\n\n".$this->error());
		exit;
	}

	// devuelve el código de error de la última autenticación
	function errnum() {
		return $this->err;
	}

	// devolver códigos de último error de LDAP
	function ldap_errno() {
		return $this->ldaperrno;
	}

	// devolver cadena descriptiva de último error de LDAP
	function ldap_error() {
		return $this->ldaperror;
	}

	// devuelve un mensaje de texto acorde al último error ocurrido
	function error() {
		$extra=($this->ldaperrno?" (LDAP #".$this->ldaperrno.($this->ldaperror?": ".$this->ldaperror:"").")":"");
		switch ($this->err) {
		case LDAP_ERR_AUTH: return "Contraseña incorrecta".$extra;
		case LDAP_ERR_CLONED: return "Hay más de un usuario con el mismo identificador, autenticación cancelada".$extra;
		case LDAP_ERR_NOUSER: return "No se encuentra el usuario".$extra;
		case LDAP_ERR_PUBLIC: return "Autenticación pública ha fallado".$extra;
		case LDAP_ERR_PRIVATE: return "Autenticación privada ha fallado".$extra;
		case LDAP_ERR_HOST: return "Servidor LDAP no responde".$extra;
		case LDAP_ERR_DISABLED: return "Autenticación LDAP deshabilitada".$extra;
		case LDAP_ERR_NODC: return "DC no encontrado en la rama que se esperaba".$extra;
		case LDAP_ERR_QUERYSTRING: return "No se encuentra objeto en ".$this->setup["querystring"]." usando ".$this->setup["userquery"];
		case LDAP_ERR_NOMODULE: return "No se ha cargado el módulo de ldap".$extra;
		case LDAP_ERR_NONE: default: return trim($extra);
		}
	}

	// nombre del protocolo
	function protocol() { return "ldap"; }

	// URL base
	function url() { return $this->protocol()."://".$this->setup["host"]."/"; }

	// conversión a cadena de la clase
	function __toString() { return "(".get_class($this).":".($this->isConnected()?"READY":"OFFLINE").") ".$this->url(); }

}

// crear todas las instancias configuradas
if ($ldap_setup) {
	foreach ($ldap_setup as $_i=>$_s)
		$$_i=new LDAP($_s);
	unset($_i);
	unset($_s);
}
