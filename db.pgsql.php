<?php if (!class_exists("dbbase")) die();

/*

	Clase PHP de Acceso a PostgreSQL v0.1b, desarrollado por Pablo Rodríguez Rey
	http://mr.xkr.es ~ mr.xkr-at-xkr.es
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)

*/
class dbPgSQL extends dbbase {

	protected $pg_connect_last_error;

	// información del driver y versión de la clase
	function driver() { return "PostgreSQL"; }
	function version() { return 0.1; }
	function protocol() { return "pgsql"; }

	// constructor
	function __construct($setup=array()) { $this->setup($setup); }

	// inicia la conexión con el servidor
	function connect() {
		global $db_pgsql_captured_errstr;
		$pg_connect=(!isset($this->setup["persistent"]) || $this->setup["persistent"]?"pg_pconnect":"pg_connect");
		if (!function_exists("db_pgsql_error_capture")) {
			function db_pgsql_error_capture($errno, $errstr, $errfile, $errline, $errctx) {
				global $db_pgsql_captured_errstr;
				$db_pgsql_captured_errstr=$errstr;
			}
		}
		set_error_handler('db_pgsql_error_capture');
		if (!$this->setup["connection_string"]) {
			$this->setup["connection_string"]
				=($this->setup["host"]?" host=".$this->setup["host"]:"")
				.($this->setup["port"]?" port=".$this->setup["port"]:"")
				.($this->setup["user"]?" user=".$this->setup["user"]:"")
				.($this->setup["pass"]?" password=".$this->setup["pass"]:"")
				.($this->setup["db"]?" dbname=".$this->setup["db"]:"")
				.($this->setup["encoding"]?" options='--client_encoding=".$this->setup["encoding"]."'":"")
			;
		}
		$this->idcon=(@$pg_connect($this->setup["connection_string"]));
		$this->pg_connect_last_error=$db_pgsql_captured_errstr;
		restore_error_handler();
		$this->connected=($this->idcon>0?true:false);
		$this->dbselected=$this->connected;
		return $this->connected;
	}

	// cierra la conexión con el servidor
	function close() {
		if (!$this->idcon>0) return false;
		pg_close($this->idcon);
		return true;
	}

	// información de conexión
	function ready() {
		if (!$this->idcon>0) return false;
		return (pg_connection_status($this->idcon) == PGSQL_CONNECTION_OK);
	}

	// selecciona la base de datos con la que trabajará la clase
	function select($database) {
		if (!$this->idcon>0) return false;
		return $this->connect($this->server,$this->user,$this->password,$database);
	}

	// efectua un checkeo (ping) del servidor
	function ping() { return (@pg_ping($this->idcon)); }

	// obtiene información sobre la consulta más reciente 
	function info() { return false; }

	// obtener el status actual del sistema
	function stat() { return false; }

	// ejecuta una consulta (select)
	function query($sqlquery, $querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sqlquery;
		$this->idquery[$querynum]=(@pg_query($this->idcon, $sqlquery));
		if (!$this->idquery[$querynum]) return false;
		$this->lastqid[$querynum]=pg_last_oid($this->idquery[$querynum]);
		$this->afrows[$querynum]=pg_num_rows($this->idquery[$querynum]);
		$this->sqltimelast=microtime(true)-$st;
		$result=new dbresult($this, $querynum);
		return $result;
	}

	// alias de query
	function exec($sqlquery, $querynum=null) {
		return $this->query($sqlquery,$querynum);
	}

	// limpia la consulta
	function freequery($querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		@pg_free_result($this->idquery[$querynum]);
		return true;
	}

	// devuelve el número de filas afectadas
	function numrows($querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->afrows[$querynum];
	}

	// devuelve array fila
	function row($querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return pg_fetch_assoc($this->idquery[$querynum]);
	}

	// devuelve de la primera fila el valor del campo deseado o el primero si se omite
	function field($field="", $querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->lastrow=pg_fetch_assoc($this->idquery[$querynum]);
		if (strlen($field)) return $this->lastrow[$field];
		else {
			if ($this->lastrow)
				foreach ($this->lastrow as $field=>$value)
					return $this->lastrow[$field];
			else
				return false;
		}
	}

	// devuelve una cadena transformada para su uso en querys
	function escape($s) {
		return pg_escape_string($s);
	}

	// devuelve la cadena de versión del servidor
	function dbversion() {
		if (!($this->idcon > 0)) return "";
		if (!($q=db_query("SELECT VERSION()", $this->idcon))) return false;
		$irow=db_fetch_array($q);
		return $irow[0];
	}

	// devuelve el último código de error producido en el servidor
	function errnum() { return 1; }

	// devuelve el último mensaje de error producido en el servidor
	function error() {
		return ($this->idcon?pg_last_error($this->idcon):$this->pg_connect_last_error);
	}

	// importar SQL a la base de datos
	function import($sql) {
		$i=0;
		$sqlpartial="";
		while (true) {
			$j=strpos($sql,"\n",$i);
			$line=(!$j?substr($sql,$i):substr($sql,$i,$j-$i));
			if (substr(rtrim($line),-1,1)!=";") $sqlpartial.=$line;
			else {
				$sqlpartial.=$line;
				if (!$this->execute($sqlpartial,"int-1")) return false;
				$sqlpartial="";
			}
			if (!$j) break;
			$i=$j+1;
		}
		return true;
	}

	// exporta todas las tablas de la base de datos a SQL
	function export($filename="", $infile=false, $limit=1000) {
		if ($filename) {
			if (!$infile) {
				header("Content-type: text/plain");
				header("Content-disposition: attachment; filename=\"".$filename."\"");
			} else {
				$f=fopen($filename,"w");
				if (!$f) return false;
			}
		}
		$all="";
		$this->query("SHOW TABLES;","int-1");
		while ($table=$this->field(null,"int-1")) {
			$drop="DROP TABLE IF EXISTS `".$table."`;\n";
			$optimize="OPTIMIZE TABLE `".$table."`;\n";
			$this->query("SHOW CREATE TABLE `".$table."`;","int-2");
			$data=$this->row("int-2");
			$create=$data["Create Table"].";\n";
			$sql="";
			$this->query("SELECT * FROM `".$table."`;","int-2");
			$sql="";
			for ($i=0;$row=$this->row("int-2");$i++) {
				if (!$i) {
					$sqlhead="";
					foreach ($row as $field=>$data)
						$sqlhead.=($sqlhead?",":"")."`".$this->escape($field)."`";
				}
				if (!$i || !($i%$limit)) {
					$sql.=($sql?");\n":"")."INSERT INTO `".$table."` (".$sqlhead.") VALUES (";
					$first=true;
				}
				if (!$first) $sql.="),(";
				$first=false;
				$sqldata="";
				foreach ($row as $field=>$data) {
					$sqldata.=($sqldata?",":"").(is_null($data)?"NULL":"'".$this->escape($data)."'");
				}
				$sql.=$sqldata;
			}
			if ($sql) $sql.=");\n";
			$sql=$drop.$create.$sql.$optimize."\n";
			if ($filename) {
				if ($f) fputs($f,$sql);
				else echo $sql;
			} else {
				$all.=$sql;
			}
		}
		if ($f) fclose($f);
		return ($filename?true:$all);
	}

}
