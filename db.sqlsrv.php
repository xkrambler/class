<?php if (!class_exists("dbbase")) die();

/*
	Clase PHP de Acceso a SQLDRV v0.3b, desarrollado por Pablo Rodríguez Rey
	http://mr.xkr.inertinc.org ~ mr.xkr-at-inertinc.org
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)
*/

class dbSQLSRV extends dbbase {

	// información del driver y versión de la clase
	function driver() { return "SQLSRV"; }
	function version() { return 0.1; }
	function protocol() { return "sqlsrv"; }
	
	// constructor
	function __construct($setup=Array()) { $this->setup($setup); }
	
	// parámetros por defecto de setup
	function setup($setup=null) {
		parent::setup($setup);
		if (!isset($setup["options"])) $this->setup["options"]=array(
			"Scrollable"=>SQLSRV_CURSOR_KEYSET,
		);
	}
	
	// inicia la conexión con el servidor o reutiliza una conexión existente
	function connect() {
		$this->clear();
		if (!function_exists("sqlsrv_connect")) die("ERROR: La librería SQLSRV no iniciada: Función sqlsrv_connect inexistente.");
		if (is_resource($this->setup["resource"])) $this->idcon=$this->setup["resource"];
		else {
			$this->idcon=sqlsrv_connect($this->setup["host"],Array(
				"UID"=>$this->setup["user"],
				"PWD"=>$this->setup["pass"],
				"ConnectionPooling"=>(isset($this->setup["persistent"])?$this->setup["persistent"]:true),
				"CharacterSet"=>($this->setup["encoding"]?$this->setup["encoding"]:""),
				"Database"=>($this->setup["db"]?$this->setup["db"]:null),
				//"MultipleActiveResultSets"=>1,
				//'ReturnDatesAsStrings'=>true,
			));
		}
		if (!$this->query("SET dateformat ymd")) $this->err();
		//if (!$this->query("SET LANGUAGE Spanish")) $this->err();
		$this->connected=($this->idcon>0?true:false);
		return $this->connected;
	}

	// cierra la conexión con el servidor
	function close() {
		$this->clear();
		if (!$this->idcon>0) return(false);
		sqlsrv_close($this->idcon);
		return true;
	}
	
	// reconectar
	function reconnect() {
		$this->close();
		return $this->connect();
	}
	
	// información de la base de datos
	function info() {
		$this->clear();
		if (!$this->idcon>0) return false;
		return sqlsrv_server_info($this->idcon);
	}
	
	// información de conexión
	function ready() {
		$this->clear();
		if (!$this->idcon>0) return false;
		return true;
	}
	
	// selecciona la base de datos con la que trabajará la clase
	function select($database) {
		$this->clear();
		if (!$this->idcon>0) return false;
		if (!$database) return false;
		if ($this->query("USE ".$database)) {
			$this->setup["db"]=$database;
			$this->dbselected=true;
		} else {
			$this->dbselected=false;
		}
		return $this->dbselected;
	}
			
	// ejecuta una consulta (select)
	function query($sqlquery, $querynum=null) {
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$this->clear();
		if (!$this->idcon>0) return false;
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sqlquery;
		$this->idquery[$querynum]=sqlsrv_query($this->idcon, $sqlquery, ($this->setup["params"]?$this->setup["params"]:array()), ($this->setup["options"]?$this->setup["options"]:array()));
		if (!$this->idquery[$querynum]) return false;
		$this->afrows[$querynum]=sqlsrv_rows_affected($this->idquery[$querynum]);
		$this->sqltimelast=microtime(true)-$st;
		if (!$this->afrows[$querynum]) $this->afrows[$querynum]=sqlsrv_num_rows($this->idquery[$querynum]);
		return new dbresult($this, $querynum);
	}
	
	// alias de query
	function exec($sqlquery,$querynum=null) {
		return $this->query($sqlquery,$querynum);
	}
	
	// limpia la consulta
	function freequery($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->clear();
		if (!$this->idcon>0) return false;
		@sqlsrv_free_stmt($this->idquery[$querynum]);
		unset($this->idquery[$querynum]);
		unset($this->lastqueryint[$querynum]);
		unset($this->lastqid[$querynum]);
		unset($this->afrows[$querynum]);
		return true;
	}
	
	// implementar lastid como consulta
	function lastid($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		if ($res=sqlsrv_query($this->idcon, "SELECT SCOPE_IDENTITY()", ($this->setup["params"]?$this->setup["params"]:array()), ($this->setup["options"]?$this->setup["options"]:array()))) {
			$res=sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
			foreach ($res as $value) {
				$this->lastqid[$querynum]=$value;
				return $this->lastqid[$querynum];
			}
		}
		return false;
	}
	
	// devuelve el número de filas afectadas
	function numrows($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->afrows[$querynum];
	}
	
	// devuelve array fila
	function row($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		if (!$querynum) $querynum="%".$this->lastquerynum;
		if ($row=sqlsrv_fetch_array($this->idquery[$querynum], SQLSRV_FETCH_ASSOC)) {
			foreach ($row as $n=>&$v)
				if ($v instanceof DateTime)
					$v=$v->format("Y-m-d H:i:s");
		}
		return $row;
	}
	
	// devuelve de la primera fila el valor del campo deseado o el primero si se omite
	function field($field="",$querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return(false);
		$this->lastrow=$this->row($querynum);
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
		$this->clear();
		return str_replace("'","''",str_replace("\n","\\n",$s));
	}
	
	// devuelve el último código de error producido en el servidor
	function errnum() {
		$errors=sqlsrv_errors();
		return ($this->real_errnum?$this->real_errnum:$errors[0]["code"]);
	}
	
	// devuelve el último mensaje de error producido en el servidor
	function error() {
		$errors=sqlsrv_errors();
		return ($this->real_error?$this->real_error:utf8_encode($errors[0]["message"]));
	}
	
}
