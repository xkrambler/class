<?php if (!class_exists("dbbase")) die();

/*

	Clase PHP de Acceso a MySQL Improved v0.2, desarrollado por Pablo Rodríguez Rey
	http://mr.xkr.es ~ mr-at-xkr-d0t-es
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)

*/
class dbMySQLi extends dbbase {

	// información del driver y versión de la clase
	function driver() { return "MySQLi"; }
	function version() { return 0.2; }
	function protocol() { return "mysql"; }

	// constructor
	function __construct($setup=Array()) {
		$this->setup($setup);
		if ($this->setup["resource"]) $this->rconnect();
	}

	// inicia la conexión con el servidor o reutiliza una conexión existente (puede estar retardada)
	function connect() {
		return ($this->setup["delayed"]?true:$this->rconnect());
	}

	// inicia la conexión con el servidor
	function rconnect() {
		if (!class_exists("mysqli")) die("ERROR: La librería MySQLi no iniciada: Clase mysqli() inexistente.");
		if (is_object($this->setup["resource"])) {
			$this->idcon=$this->setup["resource"];
			$this->setup["db"]=$this->database();
		} else {
			$this->clear();
			$this->idcon=@new mysqli(
				($this->setup["persistent"] || !isset($this->setup["persistent"])?"p:":"").$this->setup["host"],
				$this->setup["user"],
				$this->setup["pass"],
				($this->setup["db"]?$this->setup["db"]:""),
				($this->setup["port"]?$this->setup["port"]:ini_get("mysqli.default_port"))
			);
		}
		if ($this->connected=($this->idcon && !$this->idcon->connect_errno?true:false)) {
			if ($this->setup["encoding"]) $this->idcon->query("SET NAMES ".$this->setup["encoding"]);
			if (isset($this->setup["autocommit"])) $this->idcon->query("SET autocommit=".($this->setup["autocommit"]?1:0));
			if ($this->setup["db"]) $this->select();
		}
		$this->real_errnum=($this->connected?0:$this->idcon->connect_errno);
		$this->real_error=($this->connected?"":$this->idcon->connect_error);
		return $this->connected;
	}

	// cierra la conexión con el servidor
	function close() {
		$this->clear();
		if (!$this->idcon) return false;
		$this->dbselected=false;
		$this->idcon->close();
		return true;
	}

	// reconectar
	function reconnect() {
		$this->close();
		return $this->rconnect();
	}

	// información de conexión
	function ready() {
		return ($this->setup["delayed"] || ($this->idcon && $this->ping())?true:false);
	}

	// obtener base de datos actual
	function database() {
		$this->clear();
		if (!$this->idcon) return false;
		if ($q=$this->idcon->query("SELECT DATABASE()"))
			if ($r=$q->fetch_array())
				return $r[0];
		return false;
	}

	// listar bases de datos
	function databases() {
		$this->clear();
		if ($q=$this->idcon->query("SHOW DATABASES")) {
			$a=array();
			while ($irow=$q->fetch_array())
				$a[]=$irow[0];
			return $a;
		}
		return false;
	}

	// selecciona la base de datos con la que trabajará la clase
	function select($db=null) {
		$this->clear();
		if ($db) $this->setup["db"]=$db;
		if (!$this->setup["db"]) return true;
		$this->dbselected=false;
		return true;
	}

	// comprueba si previamente había sido seleccionada la base de datos, y si no, lo intenta varias veces
	function selectedcheck() {
		if (!$this->dbselected && $this->setup["db"]) {
			$numretries=0;
			do {
				if ($this->dbselected=(@$this->idcon->select_db($this->setup["db"])?true:false)) break;
				if ($this->idcon->errno!=2006 && $this->idcon->errno!=2013) break;
				if (!$this->reconnect()) return false;
			} while (++$numretries < 3);
			if (!$this->dbselected) return false;
		}
		return true;
	}

	// efectua un checkeo (ping) del servidor
	function ping() {
		if (!$this->idcon) return false;
		return @$this->idcon->ping();
	}

	// obtiene información sobre la consulta más reciente 
	function info() {
		if (!$this->idcon || $this->idcon->connect_errno) return false;
		return $this->idcon->info;
	}

	// obtener el status actual del sistema
	function stat() {
		if (!$this->idcon || $this->idcon->connect_errno) return false;
		return $this->idcon->stat();
	}

	// realiza una consulta con reconexión
	private function pquery($sqlquery) {
		if ($this->setup["delayed"] && !$this->idcon>0 && !$this->rconnect()) return false;
		if (!$this->idcon) return false;
		// realizar la consulta
		$numretries=0;
		do {
			if (!$this->idcon) return false;
			unset($this->atimedout);
			if ($this->atimeout) {
				// async with timeout query
				if (!$this->selectedcheck()) return false;
				$result=@$this->idcon->query($sqlquery, MYSQLI_ASYNC);
				$errors=$reject=array();
				$links=array($this->idcon);
				$sec =(int)$this->atimeout;
				$msec=(int)(($this->atimeout-$sec)*1000000);
				if (mysqli_poll($links, $errors, $reject, $sec, $msec) > 0) {
					$result=$this->idcon->reap_async_query();
					$this->atimedout=false;
				} else {
					$this->atimedout=true;
				}
			} else {
				// sync query
				if (!$this->selectedcheck()) return false;
				$result=$this->idcon->query($sqlquery);
			}
			if ($result || ($this->idcon->errno!=2006 && $this->idcon->errno!=2013)) break;
			if (!$this->reconnect()) return false;
		} while (++$numretries < 3);
		if ($this->idcon->errno) return false;
		return ($result?$result:false);
	}

	// establecer el modo de depuración
	function debug($enabled=null) {
		parent::debug($enabled);
		if ($this->idcon) $this->pquery("SET SESSION query_cache_type = OFF;");
	}

	// iniciar transacción
	function begin() {
		return ($this->pquery("BEGIN")?true:false);
	}

	// finalizar transacción
	function commit() {
		return ($this->pquery("COMMIT")?true:false);
	}

	// cancelar transacción
	function rollback() {
		return ($this->pquery("ROLLBACK")?true:false);
	}

	// devolver campo con la hora actual
	function now() {
		return new dbrawvalue("NOW()");
	}

	// obtener fecha en formato ISO
	function date($date) {
		return date("Y-m-d", $date);
	}

	// obtener fecha y hora en formato ISO
	function datetime($datetime) {
		return date("Y-m-d H:i:s", $datetime);
	}

	// realiza una consulta
	function query($sqlquery, $querynum=null) {
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$this->clear();
		$numretries=0;
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sqlquery;
		$this->idquery[$querynum]=$this->pquery($sqlquery);
		if ($this->idcon->errno) return false;
		if (!$this->idquery[$querynum]) return false;
		$this->lastqid[$querynum]=$this->idcon->insert_id;
		$this->afrows[$querynum]=$this->idcon->affected_rows;
		$this->sqltimelast=microtime(true)-$st;
		if ($this->debug) {
			$q=$this->idcon->query("EXPLAIN EXTENDED ".$sqlquery);
			$explain=Array(); while ($r=($q->fetch_assoc())) $explain[]=$r;
			$this->sqltimes[]=Array(
				"id"=>$querynum,
				"time"=>microtime(true)-$st,
				"lastid"=>$this->lastqid[$querynum],
				"rows"=>$this->afrows[$querynum],
				"sql"=>$sqlquery,
				"explain"=>$explain,
			);
		}
		return new dbresult($this, $querynum);
	}

	// realiza una consulta múltiple
	function multi($sqlquery, $querynum=null) {
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$this->clear();
		$numretries=0;
		$st=microtime(true);
		do {
			$tryagain=false;
			if (!$this->idcon) return false;
			$this->lastqueryint[$querynum]=$sqlquery;
			if (!$this->ready() && !$this->reconnect()) return false;
			if (!$this->selectedcheck()) return false;
			$this->idquery[$querynum]=$this->idcon->multi_query($sqlquery);
			$numretries++;
			// reintentarlo 2 veces más en caso de desconexión
			if (($this->idcon->errno==2006 || $this->idcon->errno==2013) && $numretries<3) {
				$this->reconnect();
				$tryagain=true;
			}
		} while ($tryagain);
		if ($this->idcon->errno) return false;
		if (!$this->idquery[$querynum]) return false;
		$this->lastqid[$querynum]=$this->idcon->insert_id;
		$this->afrows[$querynum]=$this->idcon->affected_rows;
		$this->sqltimelast=microtime(true)-$st;
		if ($this->debug) {
			$q=$this->idcon->multi_query("EXPLAIN EXTENDED ".$sqlquery);
			$explain=Array(); while ($r=($q->fetch_assoc())) $explain[]=$r;
			$this->sqltimes[]=Array(
				"id"=>$querynum,
				"time"=>microtime(true)-$st,
				"lastid"=>$this->lastqid[$querynum],
				"rows"=>$this->afrows[$querynum],
				"sql"=>$sqlquery,
				"explain"=>$explain,
			);
		}
		return new dbresult($this, $querynum);
	}

	// siguiente resultado
	function next($store=true, $querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->clear();
		if ($store) {
			if (!$this->idcon->more_results()) return false;
			$this->idcon->next_result();
			return $this->store($querynum);
		} else {
			return $this->idcon->next_result();
		}
	}

	// guardar resultado
	function store($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->clear();
		if ($result=$this->idcon->store_result()) {
			$this->idquery[$querynum]=$result;
			$this->lastqid[$querynum]=$this->idcon->insert_id;
			$this->afrows[$querynum]=$this->idcon->affected_rows;
			return new dbresult($this, $querynum);
		} else {
			return false;
		}
	}

	// limpia la consulta
	function freequery($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->clear();
		if (!$this->idcon) return false;
		if (method_exists($this->idquery[$querynum], "free")) $this->idquery[$querynum]->free();
		unset($this->idquery[$querynum]);
		unset($this->lastqueryint[$querynum]);
		unset($this->lastqid[$querynum]);
		unset($this->afrows[$querynum]);
		return true;
	}

	// devuelve el número de filas afectadas
	function numrows($querynum=null) {
		$this->clear();
		if (!$this->idcon) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->afrows[$querynum];
	}

	// devuelve array fila
	function row($querynum=null) {
		$this->clear();
		if (!$this->idcon) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->idquery[$querynum]->fetch_assoc();
	}

	// devuelve de la primera fila el valor del campo deseado o el primero si se omite
	function field($field="", $querynum=null) {
		$this->clear();
		if (!$this->idcon) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->lastrow=$this->idquery[$querynum]->fetch_assoc();
		if (strlen($field)) return $this->lastrow[$field];
		else {
			if ($this->lastrow)
				foreach ($this->lastrow as $field=>$value)
					return($this->lastrow[$field]);
			else
				return false;
		}
	}

	// devuelve una cadena transformada para su uso en querys
	function escape($s) {
		if ($this->setup["delayed"] && !$this->idcon>0 && !$this->rconnect()) return false;
		return $this->idcon->real_escape_string($s);
	}

	// indica si un nombre es palabra clave o no
	function iskeyword($k) {
		return (in_array(strtoupper($k), array(
			"ACCESSIBLE","ADD","ALL","ALTER","ANALYZE","AND","AS","ASC",
			"ASENSITIVE","BEFORE","BETWEEN","BIGINT","BINARY","BLOB","BOTH",
			"BY","CALL","CASCADE","CASE","CHANGE","CHAR","CHARACTER","CHECK",
			"COLLATE","COLUMN","CONDITION","CONSTRAINT","CONTINUE","CONVERT",
			"CREATE","CROSS","CURRENT_DATE","CURRENT_TIME","CURRENT_TIMESTAMP",
			"CURRENT_USER","CURSOR","DATABASE","DATABASES","DAY_HOUR","DAY_MICROSECOND",
			"DAY_MINUTE","DAY_SECOND","DEC","DECIMAL","DECLARE","DEFAULT",
			"DELAYED","DELETE","DESC","DESCRIBE","DETERMINISTIC","DISTINCT",
			"DISTINCTROW","DIV","DOUBLE","DROP","DUAL","EACH","ELSE","ELSEIF",
			"ENCLOSED","ESCAPED","EXISTS","EXIT","EXPLAIN","FALSE","FETCH",
			"FLOAT","FLOAT4","FLOAT8","FOR","FORCE","FOREIGN","FROM","FULLTEXT",
			"GET","GRANT","GROUP","HAVING","HIGH_PRIORITY","HOUR_MICROSECOND",
			"HOUR_MINUTE","HOUR_SECOND","IF","IGNORE","IN","INDEX","INFILE",
			"INNER","INOUT","INSENSITIVE","INSERT","INT","INT1","INT2","INT3",
			"INT4","INT8","INTEGER","INTERVAL","INTO","IO_AFTER_GTIDS","IO_BEFORE_GTIDS",
			"IS","ITERATE","JOIN","KEY","KEYS","KILL","LEADING","LEAVE",
			"LEFT","LIKE","LIMIT","LINEAR","LINES","LOAD","LOCALTIME","LOCALTIMESTAMP",
			"LOCK","LONG","LONGBLOB","LONGTEXT","LOOP","LOW_PRIORITY","MASTER_BIND",
			"MASTER_SSL_VERIFY_SERVER_CERT","MATCH","MAXVALUE","MEDIUMBLOB",
			"MEDIUMINT","MEDIUMTEXT","MIDDLEINT","MINUTE_MICROSECOND","MINUTE_SECOND",
			"MOD","MODIFIES","NATURAL","NOT","NO_WRITE_TO_BINLOG","NULL",
			"NUMERIC","ON","OPTIMIZE","OPTION","OPTIONALLY","OR","ORDER",
			"OUT","OUTER","OUTFILE","PARTITION","PRECISION","PRIMARY","PROCEDURE",
			"PURGE","RANGE","READ","READS","READ_WRITE","REAL","REFERENCES",
			"REGEXP","RELEASE","RENAME","REPEAT","REPLACE","REQUIRE","RESIGNAL",
			"RESTRICT","RETURN","REVOKE","RIGHT","RLIKE","SCHEMA","SCHEMAS",
			"SECOND_MICROSECOND","SELECT","SENSITIVE","SEPARATOR","SET",
			"SHOW","SIGNAL","SMALLINT","SPATIAL","SPECIFIC","SQL","SQLEXCEPTION",
			"SQLSTATE","SQLWARNING","SQL_BIG_RESULT","SQL_CALC_FOUND_ROWS",
			"SQL_SMALL_RESULT","SSL","STARTING","STRAIGHT_JOIN","TABLE",
			"TERMINATED","THEN","TINYBLOB","TINYINT","TINYTEXT","TO","TRAILING",
			"TRIGGER","TRUE","UNDO","UNION","UNIQUE","UNLOCK","UNSIGNED",
			"UPDATE","USAGE","USE","USING","UTC_DATE","UTC_TIME","UTC_TIMESTAMP",
			"VALUES","VARBINARY","VARCHAR","VARCHARACTER","VARYING","WHEN",
			"WHERE","WHILE","WITH","WRITE","XOR","YEAR_MONTH","ZEROFILL",
		)));
	}

	// devolver valor filtrado de campo
	function sqlfield($t) {
		$q="`";
		$s="";
		if ($t=explode(".", $t)) foreach ($t as $f)
			$s.=($s?".":"").$q.$this->escape(str_replace($q, "", $f)).$q;
		return $s;
	}

	// escapar nombres de tabla
	function sqltable($t) {
		return $this->sqlfield($t);
	}

	// devuelve la cadena de versión del servidor
	function dbversion() {
		$this->clear();
		if (!$this->idcon) return false;
		if (!$q=$this->idcon->query("SELECT VERSION()")) return false;
		$irow=$q->fetch_array();
		return $irow[0];
	}

	// devuelve el último código de error producido en el servidor
	function errnum() {
		return ($this->real_errnum?$this->real_errnum:$this->idcon->errno);
	}

	// devuelve el último mensaje de error producido en el servidor
	function error() {
		return ($this->real_error?$this->real_error:$this->idcon->error);
	}

}
