<?php if (!class_exists("dbbase")) die();

die("ToDo: Class must be adapted to dbbase 0.2");

/*

	Clase PHP de Acceso a Access v0.1, desarrollado por Pablo Rodríguez Rey
	http://mr.xkr.inertinc.org ~ mr.xkr-at-inertinc.org
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)

*/

class dbAccess extends dbbase {

	// información del driver y versión de la clase
	function driver() { return "Access"; }
	function version() { return 0.1; }
	function protocol() { return "access"; }
	
	// constructor
	function __construct($server="",$user="",$password="",$database="",$encoding="") {
		return $this->connect($server,$user,$password,$database,$encoding);
	}
	
	// inicia la conexión ODBC
	function connect($server="",$user="",$password="",$database="",$encoding="") {
		$this->clear();
		if (!function_exists("odbc_connect")) die("ERROR: La librería ODBC no iniciada: Función odbc_connect inexistente.");
		$this->idcon=odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=".$server.";", $user, $password);
		$this->server=$server;
		$this->user=$user;
		$this->password=$password;
		//if ($database) $this->select($database);
		$this->connected=($this->idcon>0?true:false);
		return $this->connected;
	}

	// cierra la conexión con el servidor
	function close() {
		$this->clear();
		if (!$this->idcon>0) return(false);
		odbc_close($this->idcon);
		$this->idcon=false;
		return(true);
	}
	
	// información de conexión
	function ready() {
		$this->clear();
		if (!$this->idcon>0) return(false);
		return true;
	}
	
	// selecciona la base de datos con la que trabajará la clase
	function select($database) {
		$this->clear();
		return false;
	}
	
	// efectua un checkeo (ping) del servidor
	function ping() {
		return true;
	}

	// obtiene información sobre la consulta más reciente 
	function info() {
		return false;
	}

	// obtener el status actual del sistema
	function stat() {
		return false;
	}

	// ejecuta una consulta (select)
	function query($sqlquery,$querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sqlquery;
		$this->idquery[$querynum]=odbc_exec($this->idcon, $sqlquery);
		if (!$this->idquery[$querynum]) return(false);
		$this->lastqid[$querynum]=odbc_cursor($this->idquery[$querynum]);
		$this->afrows[$querynum]=odbc_num_rows($this->idquery[$querynum]);
		$this->sqltimelast=microtime(true)-$st;
		$result=new dbresult($this, $querynum);
		return $result;
	}

	// ejecuta una consulta (insert, update, alter, ...)
	function exec($sqlquery,$querynum=null) {
		return $this->query($sqlquery,$querynum);
	}

	// limpia la consulta
	function freequery($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) $querynum="%".$this->lastquerynum;
		odbc_free_result($this->idquery[$querynum]);
		return(true);
	}

	// devuelve el número de filas afectadas
	function numrows($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return($this->afrows[$querynum]);
	}
	
	// transformar charset de la fila a UTF8
	function rowtoutf8($a) {
		if (!$a) return $a;
		$b=Array();
		foreach ($a as $i=>$v)
			$b[$i]=utf8_encode($v);
		return $b;
	}

	// devuelve array fila
	function row($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return($this->rowtoutf8(odbc_fetch_array($this->idquery[$querynum])));
	}

	// devuelve de la primera fila el valor del campo deseado o el primero si se omite
	function field($field="",$querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->lastrow=$this->rowtoutf8(odbc_fetch_array($this->idquery[$querynum]));
		if (strlen($field)) return($this->lastrow[$field]);
		else {
			if ($this->lastrow)
				foreach ($this->lastrow as $field=>$value)
					return($this->lastrow[$field]);
			else
				return(false);
		}
	}

	// devuelve una cadena transformada para su uso en querys
	function escape($s) {
		$s=str_replace("'","''",$s);
		return $s;
	}

	// devuelve la cadena de versión del servidor
	function dbversion() {
		return false;
	}

	// devuelve el último código de error producido en el servidor
	function errnum() {
		return ($this->real_errnum?$this->real_errnum:odbc_error($this->idcon));
	}

	// devuelve el último mensaje de error producido en el servidor
	function error() {
		return ($this->real_error?$this->real_error:odbc_errormsg($this->idcon));
	}

	// ESPECÍFICA: Convertir un UID de binario al formato hexadecimal de Microsoft
	function bin2uid($bin) {
		$hex=bin2hex($bin);
		return substr($hex,6,2).substr($hex,4,2).substr($hex,2,2).substr($hex,0,2)
		       ."-".substr($hex,10,2).substr($hex,8,2)
		       ."-".substr($hex,14,2).substr($hex,12,2)
		       ."-".substr($hex,16,4)
		       ."-".substr($hex,20,12)
		;
	}
	
}
