<?php if (!class_exists("dbbase")) die();

/*

	PHP Class to access ORACLE databases v0.3b, by Pablo Rodríguez Rey
	http://mr.xkr.es ~ mr-at-xkr-dot-es
	Under GPLv2 license (https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

*/
class dbORACLE extends dbbase {

	protected $stmt;

	// drver, version and protocol
	function driver() { return "Oracle"; }
	function version() { return 0.3; }
	function protocol() { return "oracle"; }

	// constructor/destructor
	function __construct($setup=array()) { $this->setup($setup); }
	function __destruct() { if (isset($this->setup["persistent"]) && !$this->setup["persistent"]) $this->close(); }

	// server connection (maybe delayed)
	function connect() {
		return ($this->setup["delayed"]?true:$this->rconnect());
	}

	// real connect to server
	function rconnect() {
		$oci_connect=(!isset($this->setup["persistent"]) || $this->setup["persistent"]?"oci_pconnect":"oci_connect");
		if (!function_exists($oci_connect)) die("ERROR: La librería dbOracle no iniciada: Función ".$oci_connect." inexistente.");
		if (!$this->setup["connection_string"]) {
			// check if port is specified in host
			$i=strpos($this->setup["host"], ":");
			if ($i !== false) {
				$this->setup["port"]=substr($this->setup["host"], $i+1);
				$this->setup["host"]=substr($this->setup["host"], 0, $i);
			}
			// generate connection string
			$this->setup["connection_string"]="
			  (DESCRIPTION =
			    (ADDRESS_LIST =
			      (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->setup["host"].")(PORT = ".($this->setup["port"]?$this->setup["port"]:1521)."))
			    )
			    (CONNECT_DATA =
			      (SERVICE_NAME = ".$this->setup["db"].")
			    )
			  )
			";
		}
		$oci_connect="oci_connect";
		$this->idcon=(@$oci_connect($this->setup["user"], $this->setup["pass"], $this->setup["connection_string"], $this->setup["encoding"])); // por defecto era WE8MSWIN1252
		$this->connected=($this->idcon>0?true:false);
		if ($this->connected) {
			@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_DATE_FORMAT='yyyy-mm-dd hh24:mi:ss'"), OCI_DEFAULT);
			@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.,'"), OCI_DEFAULT);
			@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_COMP=LINGUISTIC"), OCI_DEFAULT);
			//@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_LANGUAGE=SPANISH"), OCI_DEFAULT);
		}
		$this->dbselected=$this->connected;
		return $this->connected;
	}

	// close server connection
	function close() {
		if (!$this->idcon>0) return false;
		oci_close($this->idcon);
		$this->idcon=false;
		return true;
	}

	// reconnect
	function reconnect() {
		$this->close();
		return $this->rconnect();
	}

	// connection is ready
	function ready() {
		return ($this->setup["delayed"] || $this->idcon?true:false);
	}

	// select default scheme
	function select($database) {
		if (!$this->idcon>0) return false;
		$this->setup["db"]=$database;
		return $this->reconnect();
	}

	// ping implemented as boolean
	function ping() { return (function_exists("oci_connect")?true:false); }

	// no info implemented
	function info() { return false; }

	// no status implemented
	function stat() { return false; }

	// run query (select)
	function query($sqlquery, $querynum=null) {
		if ($this->setup["delayed"] && !$this->idcon>0 && !$this->rconnect()) return false;
		if (!$this->idcon>0) return false;
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sqlquery;
		$this->stmt[$querynum]=oci_parse($this->idcon, $sqlquery);
		if (!(@oci_execute($this->stmt[$querynum], OCI_DEFAULT))) return false;
		$this->lastqid[$querynum]=null;
		$this->afrows[$querynum]=oci_num_rows($this->stmt[$querynum]);
		$this->sqltimelast=microtime(true)-$st;
		$result=new dbresult($this,$querynum);
		return $result;
	}

	// commit transaction
	function commit() {
		$r=$this->query("COMMIT");
		$this->freequery();
		return $r;
	}

	// rollback transaction
	function rollback() {
		$r=$this->query("ROLLBACK");
		$this->freequery();
		return $r;
	}

	// execute a query (insert, update, alter, ...)
	function exec($sqlquery, $querynum=null) {
		return $this->query($sqlquery, $querynum); // idem
	}

	// clear memory from query
	function freequery($querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		oci_free_statement($this->stmt[$querynum]);
		return true;
	}

	// return number of affected rows from last query
	function numrows($querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->afrows[$querynum];
	}

	// return next row
	function row($querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return oci_fetch_assoc($this->stmt[$querynum]);
	}

	// returns from first row first field or requested field
	function field($field="", $querynum=null) {
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->lastrow=oci_fetch_assoc($this->stmt[$querynum]);
		if (strlen($field)) return $this->lastrow[$field];
		else {
			if ($this->lastrow)
				foreach ($this->lastrow as $field=>$value)
					return $this->lastrow[$field];
			else
				return false;
		}
	}

	// escape string to be used in query
	function escape($s) {
		$s=str_replace("\n","'||chr(10)||'",str_replace("'","''",$s));
		return $s;
	}

	// return server database version
	function dbversion() {
		if (!$this->idcon>0) return false;
		return oci_server_version($this->idcon);
	}

	// return last error code
	function errnum($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$err=($this->stmt[$querynum]?oci_error($this->stmt[$querynum]):oci_error());
		return ($err["code"]?intval($err["code"]):false);
	}

	// return last error message
	function error($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$err=($this->stmt[$querynum]?oci_error($this->stmt[$querynum]):oci_error());
		return ($err["code"]?$err["message"]:"");
	}

}
