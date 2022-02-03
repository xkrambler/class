<?php if (!class_exists("dbbase")) die();

/*

	PHP Access Class to MySQL Improved v0.2, by Pablo Rodríguez Rey
	http://mr.xkr.es ~ mr-at-xkr-d0t-es
	Under GPL license (http://www.gnu.org/copyleft/gpl.html)

*/
class dbMySQLi extends dbbase {

	protected $queryflags=0;
	protected $reconnect_errnums=array(
		2006, // MySQL server has gone away
		2013, // Lost connection to MySQL server during query
	);
	protected $keywords=array(
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
	);

	// definition
	function driver() { return "MySQLi"; }
	function version() { return 0.2; }
	function protocol() { return "mysql"; }

	// constructor
	function __construct($setup=Array()) {
		$this->setup($setup);
		if ($this->setup["resource"]) $this->rconnect();
	}

	// virtual connect (can be a dummy connect if connection is marked as delayed)
	function connect() {
		return ($this->setup["delayed"]?true:$this->rconnect());
	}

	// real connect
	function rconnect() {
		if (!class_exists("mysqli")) {
			$this->real_errnum=-1;
			$this->real_error="ERROR: MySQL improved library not installed, mysqli() class does not exist.";
			return false;
		}
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

	// close server connection
	function close() {
		$this->clear();
		if (!$this->idcon) return false;
		$this->dbselected=false;
		$this->idcon->close();
		return true;
	}

	// reconnect
	function reconnect() {
		$this->close();
		return $this->rconnect();
	}

	// check if connection is ready
	function ready() {
		return ($this->setup["delayed"] || ($this->idcon && $this->ping())?true:false);
	}

	// get current database selection
	function database() {
		$this->clear();
		if (!$this->idcon) return false;
		if ($q=$this->idcon->query("SELECT DATABASE()"))
			if ($r=$q->fetch_array())
				return $r[0];
		return false;
	}

	// list databases
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

	// select current working database
	function select($db=null) {
		$this->clear();
		if ($db) $this->setup["db"]=$db;
		if (!$this->setup["db"]) return true;
		$this->dbselected=false;
		return true;
	}

	// check if there is a database selected, and try to select it
	function selectedcheck() {
		if (!$this->dbselected && $this->setup["db"]) {
			$retries=0;
			do {
				if ($this->dbselected=(@$this->idcon->select_db($this->setup["db"])?true:false)) break;
				if (!in_array($this->idcon->errno, $this->reconnect_errnums)) break;
				else if (!$this->reconnect()) return false;
			} while (++$retries < 3);
			if (!$this->dbselected) return false;
		}
		return true;
	}

	// do a ping check
	function ping() {
		if (!$this->idcon) return false;
		return @$this->idcon->ping();
	}

	// get information about last query
	function info() {
		if (!$this->idcon || $this->idcon->connect_errno) return false;
		return $this->idcon->info;
	}

	// get current driver status
	function stat() {
		if (!$this->idcon || $this->idcon->connect_errno) return false;
		return $this->idcon->stat();
	}

	// get/set buffered flag
	function buffered($enabled=null) {
		$flag=MYSQLI_USE_RESULT;
		if ($enabled !== null) {
			$this->queryflags|=$flag;
			if ($enabled) $this->queryflags^=$flag;
		}
		return ($this->queryflags & $flag);
	}

	// get/set query flags
	function queryflags($queryflags=null) {
		if ($queryflags !== null) $this->queryflags=$queryflags;
		return $this->queryflags;
	}

	// query with reconnection/timeout
	protected function pquery($sql) {
		if ($this->setup["delayed"] && !($this->idcon > 0) && !$this->rconnect()) return false;
		if (!$this->idcon) return false;
		// start query
		$retries=0;
		do {
			if (!$this->idcon) return false;
			unset($this->atimedout);
			if ($this->atimeout) {
				// async with timeout query
				if (!$this->selectedcheck()) return false;
				$result=(@$this->idcon->query($sql, MYSQLI_ASYNC | $this->queryflags));
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
				$result=(@$this->idcon->query($sql, $this->queryflags));
			}
			if ($result || !in_array($this->idcon->errno, $this->reconnect_errnums)) break;
			else if (!$this->reconnect()) return false;
		} while (++$retries < 3);
		if ($this->idcon->errno) return false;
		return ($result?$result:false);
	}

	// set debug mode
	function debug($enabled=null) {
		parent::debug($enabled);
		$this->pquery("SET SESSION query_cache_type=OFF");
	}

	// begin transaction
	function begin() {
		return $this->query("BEGIN");
	}

	// commit transaction
	function commit() {
		return $this->query("COMMIT");
	}

	// rollback transaction
	function rollback() {
		return $this->query("ROLLBACK");
	}

	// return current date/time field
	function now() {
		return new dbrawvalue("NOW()");
	}

	// get date in ISO format
	function date($timestamp) {
		return date("Y-m-d", $timestamp);
	}

	// get date and time in ISO format
	function datetime($timestamp) {
		return date("Y-m-d H:i:s", $timestamp);
	}

	// generate/return always a query number and clear errors
	protected function querynum($current, $next=false) {
		if ($current) return $current;
		if ($next) $this->lastquerynum++;
		$this->clear();
		return "%".$this->lastquerynum;
	}

	// perform query
	function query($sql, $querynum=null) {
		$querynum=$this->querynum($querynum, true);
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sql;
		$this->idquery[$querynum]=$this->pquery($sql);
		if (!$this->idcon || @$this->idcon->errno) return false;
		if (!$this->idquery[$querynum]) return false;
		$this->lastqid[$querynum]=$this->idcon->insert_id;
		$this->afrows[$querynum]=$this->idcon->affected_rows;
		$this->sqltimelast=microtime(true)-$st;
		if ($this->debug) {
			$q=$this->idcon->query("EXPLAIN EXTENDED ".$sql);
			$explain=Array(); while ($r=($q->fetch_assoc())) $explain[]=$r;
			$this->sqltimes[]=Array(
				"id"=>$querynum,
				"time"=>microtime(true)-$st,
				"lastid"=>$this->lastqid[$querynum],
				"rows"=>$this->afrows[$querynum],
				"sql"=>$sql,
				"explain"=>$explain,
			);
		}
		return new dbresult($this, $querynum);
	}

	// perform multiple query
	function multi($sql, $querynum=null) {
		if ($this->setup["delayed"] && !($this->idcon > 0) && !$this->rconnect()) return false;
		$querynum=$this->querynum($querynum, true);
		$st=microtime(true);
		$retries=0;
		do {
			if (!$this->idcon) return false;
			$this->lastqueryint[$querynum]=$sql;
			if (!$this->ready() && !$this->reconnect()) return false;
			if (!$this->selectedcheck()) return false;
			$this->idquery[$querynum]=$this->idcon->multi_query($sql);
			if ($result || !in_array($this->idcon->errno, $this->reconnect_errnums)) break;
			else if (!$this->reconnect()) return false;
		} while (++$retries < 3);
		if ($this->idcon->errno) return false;
		if (!$this->idquery[$querynum]) return false;
		$this->lastqid[$querynum]=$this->idcon->insert_id;
		$this->afrows[$querynum]=$this->idcon->affected_rows;
		$this->sqltimelast=microtime(true)-$st;
		if ($this->debug) {
			$q=$this->idcon->multi_query("EXPLAIN EXTENDED ".$sql);
			$explain=array(); while ($r=($q->fetch_assoc())) $explain[]=$r;
			$this->sqltimes[]=array(
				"id"=>$querynum,
				"time"=>microtime(true)-$st,
				"lastid"=>$this->lastqid[$querynum],
				"rows"=>$this->afrows[$querynum],
				"sql"=>$sql,
				"explain"=>$explain,
			);
		}
		return new dbresult($this, $querynum);
	}

	// next result
	function next($store=true, $querynum=null) {
		$querynum=$this->querynum($querynum);
		if ($store) {
			if (!$this->idcon->more_results()) return false;
			$this->idcon->next_result();
			return $this->store($querynum);
		} else {
			return $this->idcon->next_result();
		}
	}

	// store result
	function store($querynum=null) {
		$querynum=$this->querynum($querynum);
		if ($result=$this->idcon->store_result()) {
			$this->idquery[$querynum]=$result;
			$this->lastqid[$querynum]=$this->idcon->insert_id;
			$this->afrows[$querynum]=$this->idcon->affected_rows;
			return new dbresult($this, $querynum);
		} else {
			return false;
		}
	}

	// free query result
	function freequery($querynum=null) {
		if (!$this->idcon) return false;
		$querynum=$this->querynum($querynum);
		if (method_exists($this->idquery[$querynum], "free")) $this->idquery[$querynum]->free();
		unset($this->idquery[$querynum]);
		unset($this->lastqueryint[$querynum]);
		unset($this->lastqid[$querynum]);
		unset($this->afrows[$querynum]);
		return true;
	}

	// return affected rows
	function numrows($querynum=null) {
		if (!$this->idcon) return false;
		$querynum=$this->querynum($querynum);
		return $this->afrows[$querynum];
	}

	// return row as an array
	function row($querynum=null) {
		if (!$this->idcon) return false;
		$querynum=$this->querynum($querynum);
		return $this->idquery[$querynum]->fetch_assoc();
	}

	// returns from first row first field or requested field
	function field($field="", $querynum=null) {
		if (!$this->idcon) return false;
		$querynum=$this->querynum($querynum);
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

	// escape string to be used in query
	function escape($s) {
		if ($this->setup["delayed"] && !($this->idcon > 0) && !$this->rconnect()) return false;
		return $this->idcon->real_escape_string($s);
	}

	// check if a word is a keyword
	function iskeyword($k) {
		return in_array(strtoupper($k), $this->keywords);
	}

	// return filtered field name
	function sqlfield($t) {
		$q="`";
		$s="";
		if ($t=explode(".", $t)) foreach ($t as $f)
			$s.=($s?".":"").$q.$this->escape(str_replace($q, "", $f)).$q;
		return $s;
	}

	// return filtered table name
	function sqltable($t) {
		return $this->sqlfield($t);
	}

	// return server database version
	function dbversion() {
		$this->clear();
		if (!$this->idcon) return false;
		if (!$q=$this->idcon->query("SELECT VERSION()")) return false;
		$irow=$q->fetch_array();
		return $irow[0];
	}

	// return last error code
	function errnum() {
		return ($this->real_errnum?$this->real_errnum:$this->idcon->errno);
	}

	// return last error message
	function error() {
		return ($this->real_error?$this->real_error:$this->idcon->error);
	}

}
