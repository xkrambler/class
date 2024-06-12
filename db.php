<?php

/*

	PHP fast&tiny class for access Databases, by Pablo Rodríguez Rey (http://mr.xkr.es/)
	Distributed under the GPL version 2 license (http://www.gnu.org/copyleft/gpl.html)

	@author Pablo Rodríguez Rey
	@link https://mr.xkr.es/
	@copyright GPL version 2 (http://www.gnu.org/copyleft/gpl.html)
	@version 0.2b
	@updated 2021-04-12
	@since 2009-05-08

	Global Constants:

		DB_SETUP   If not defined or true, autosetup is enabled for $db_setup.
		DB_ERRORS  Force show/hide database errores. Otherwise, it will follow "display_errors" PHP configuration.

	Example usage:

		// create instance
		$db=dbbase::create([
			"type"=>"mysqli",
			"host"=>"127.0.0.1",
			"user"=>"root",
			"pass"=>"",
			"db"=>"mysql",
		]);

		// connect and check if it is ready to query
		if ($db->connect() && $db->ready()) {

			// query SQL
			if (!($result=$db->query("SELECT COUNT(*) FROM user"))) $db->err();

			// var_dump rows
			while ($row=$db->row()) var_dump($row);

			// free memory from last query
			$db->freequery();

			// disconnect
			$db->close();

		} else {

			// otherwise, fail
			$db->err();

		}

*/

// constants
define("DB_ERR_NONE",     0);
define("DB_ERR_CONNECT",  1);
define("DB_ERR_DATABASE", 2);
define("DB_ERR_CLASS",    3);
define("DB_ERR_TYPE",     4);

// DBBase: base abstract class to define new database access implementations
abstract class dbbase {

	protected $setup=array();
	protected $connected=false;
	protected $idcon=null;
	protected $idquery=array();
	protected $lastqueryint=array();
	protected $lastqid=array();
	protected $lastrow=null;
	protected $afrows=array();
	protected $real_errnum=0;
	protected $real_error="";
	protected $field_delimiter="`";
	protected $lastquerynum=0;
	protected $dbselected=false;
	protected $debug=false;
	protected $sqltimelast=false;
	protected $sqltimes=array();
	protected $events=array();

	// required functions
	abstract function connect();
	abstract function close();
	abstract function ready();
	abstract function info();

	// get/set/isset
	function __get($n) { return (isset($this->setup[$n])?$this->setup[$n]:null); }
	function __set($n, $v) { $this->setup[$n]=$v; }
	function __isset($n) { return isset($this->setup[$n]); }

	// return database class by type
	static function dbclass($db_type) {
		return "db".$db_type;
	}

	// check if library is available by type and return filename if it exists
	static function lib($db_type) {
		$lib=__DIR__."/db.".$db_type.".php";
		return (file_exists($lib)?$lib:false);
	}

	// create database instance from database setup
	static function create($db) {
		if (!($lib=self::lib($db["type"]))) {
			return new dbVoid([
				"errnum"=>-1,
				"error"=>"Library ".$db["type"]." not available."
			]);
		}
		require_once($lib);
		$dbc=self::dbclass($db["type"]);
		if (!class_exists($dbc)) {
			return new dbVoid([
				"errnum"=>-2,
				"error"=>"Class ".$db["type"]." not available."
			]);
		}
		return new $dbc($db);
	}

	// return descriptive error for a database configuration
	static function dbconfigerror($db, $configerrnum) {
		switch ($configerrnum) {
		case DB_ERR_NONE: break;
		case DB_ERR_CONNECT: return "Connection error".($db?": ".$db->error():"");
		case DB_ERR_DATABASE: return "Database not selected".($db?": ".$db->error():"");
		case DB_ERR_CLASS: return "Class ".$config["type"]." not available.";
		case DB_ERR_TYPE: return "Type of database required.";
		default: return ($db?"Error".($db?": ".$db->error():""):"Database object not instanced.");
		}
		return "";
	}

	// get/set setup
	function setup($setup=null) {
		if ($setup!==null) $this->setup=$setup;
		return $this->setup;
	}

	// is database connected?
	function connected() {
		return $this->connected;
	}

	// get/set a previously database connection
	function connection($idcon=null) {
		if ($idcon !== null) $this->idcon=$idcon;
		return $this->idcon;
	}

	// DEPRECATED: get a previously database connection
	function getConnection() {
		return $this->idcon;
	}

	// DEPRECATED: set a previously database connection
	function setConnection($idcon) {
		$this->idcon=$idcon;
	}

	// driver, version and protocol information
	function driver() { return "Unknown"; }
	function version() { return 0; }
	function protocol() { return "none"; }

	// get/set debugging
	function debug($enabled=null) {
		if ($enabled !== null) $this->debug=$enabled;
		return $this->debug;
	}

	// return last query timming (in seconds)
	function timelast() {
		return $this->sqltimelast;
	}

	// dump query times in HTML
	function timesdump() {
		?><style>

			.sqltimesdump_head {
				padding: 4px 9px;
				background-color: #EEE;
				border: 1px solid #CCC;
				border-bottom: 0px;
			}
			.sqltimesdump_box {
				margin: 0px;
				padding: 4px 9px;
				border: 1px solid #CCC;
			}
			.sqltimesdump_sql {
				color: #00A;
				font-family: Courier New;
				font-size: 11px;
			}
			.sqltimesdump_explain {
			}
			.sqltimesdump_explaintable {
				min-width: 100%;
				border-collapse: collapse;
			}
			.sqltimesdump_explaintable th,
			.sqltimesdump_explaintable td {
				border: 1px solid #CCC;
			}
			.sqltimesdump_explaintable th {
				background-color: #EEE;
				color: #444;
				font-weight: normal;
			}

		</style><?php
		foreach ($this->sqltimes as $i=>$t) {
			$ms=round($t["time"]*1000);
			echo "<div class='sqltimesdump_head'>"
					."<b style='color:#690;'>".($i+1)."</b> - "
					." id(<b>".$t["id"]."</b>)"
					." time(<b style='background-color:".($ms<10?"#FFF":($ms<500?"#FF0":"#F88"))."'>".$ms."ms</b>)"
					." rows(<b>".$t["rows"]."</b>)"
					." lastid(<b>".$t["lastid"]."</b>):"
					."</div>";
			echo "<pre class='sqltimesdump_box sqltimesdump_sql'>".htmlentities($t["sql"])."</pre>";
			if ($t["explain"]) {
				echo "<div class='sqltimesdump_explain'>";
				echo "<table class='sqltimesdump_explaintable'><tr>";
				foreach ($t["explain"][0] as $n=>$e)
					echo "<th>".$n."</th>";
				echo "</tr>";
				foreach ($t["explain"] as $e) {
					echo "<tr>";
					foreach ($e as $v)
						echo "<td>".str_replace(",",", ",$v)."</td>";
					echo "</tr>";
				}
				echo "</table>";
				echo "</div>";
			}
		}
	}

	// return database URL
	function url($hide_password=true) {
		return strtolower($this->protocol())."://"
			.$this->setup["user"]
			.($this->setup["pass"]?":".($hide_password?"*":$this->setup["pass"]):"")
			."@".$this->setup["host"]
			.(isset($this->setup["port"])?":".$this->setup["port"]:"")
			.(isset($this->setup["db"])?"/".$this->setup["db"]:"")."/"
		;
	}

	// clear error control
	function clear() {
		$this->real_errnum=false;
		$this->real_error=false;
	}

	// default behaviour for non-transactional databases
	function begin() { return true; }
	function commit() { return true; }
	function rollback() { return true; }

	// execute all SQL as a transaction
	function atomic($sqls, $querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		if (is_string($sqls)) $sqls=array($sqls);
		if (!$this->begin()) return false;
		foreach ($sqls as $sql) {
			if (!$this->query($sql, $querynum)) {
				$lastqueryint=$this->lastqueryint[$querynum];
				$real_errnum=$this->errnum();
				$real_error=$this->error();
				$this->rollback();
				$this->lastqueryint[$querynum]=$lastqueryint;
				$this->real_errnum=$real_errnum;
				$this->real_error=$real_error;
				return false;
			}
		}
		if (!$this->commit()) return false;
		return true;
	}

	// return query as an array
	function aquery($querynum=null) {
		if (!$this->idcon > 0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$a=array();
		for ($i=0; $row=$this->row($querynum); $i++)
			$a[$i]=$row;
		$this->freequery();
		return($a);
	}

	// return query as an associative array based on a key field
	function asocquery($fieldkey="id", $querynum=null) {
		if (!$this->idcon > 0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$a=array();
		for ($i=0; $row=$this->row($querynum); $i++)
			$a[$row[$fieldkey]]=$row;
		$this->freequery();
		return($a);
	}

	// return query as an associative array of multiple rows based on a key field
	function asocaquery($fieldkey="id", $querynum=null) {
		if (!$this->idcon > 0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$a=array();
		for ($i=0; $row=$this->row($querynum); $i++) {
			if (!$a[$row[$fieldkey]]) $a[$row[$fieldkey]]=array();
			array_push($a[$row[$fieldkey]], $row);
		}
		$this->freequery();
		return($a);
	}

	// return a query as an key->value array
	function hashquery($k, $v, $querynum=null) {
		if (!$this->idcon > 0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$a=array();
		for ($i=0; $row=$this->row($querynum); $i++)
			$a[$row[$k]]=$row[$v];
		$this->freequery();
		return $a;
	}

	// return complete or partial ISO date/date-time as an Spanish notation dd/mm/yyyy [hh:mm:ss]
	static function spdate($sqldate) {
		if (strlen($sqldate) == 19) return substr($sqldate, 8, 2)."/".substr($sqldate, 5, 2)."/".substr($sqldate, 0, 4)." ".substr($sqldate, 11);
		if (strlen($sqldate) == 16) return substr($sqldate, 8, 2)."/".substr($sqldate, 5, 2)."/".substr($sqldate, 0, 4)." ".substr($sqldate, 11).":00";
		if (strlen($sqldate) == 13) return substr($sqldate, 8, 2)."/".substr($sqldate, 5, 2)."/".substr($sqldate, 0, 4)." ".substr($sqldate, 11).":00:00";
		if (strlen($sqldate) == 10) return substr($sqldate, 8, 2)."/".substr($sqldate, 5, 2)."/".substr($sqldate, 0, 4);
		if (strlen($sqldate) == 8) return $sqldate;
	}

	// parses Spanish date-time notation to ISO date[time]
	static function sqldate($spdate) {
		if (strlen($spdate) < 10) return($spdate);
		$t=substr($spdate, 11);
		if (strlen($t) == 2) $t.=":00:00";
		if (strlen($t) == 5) $t.=":00";
		return substr($spdate, 6, 4)."-".substr($spdate, 3, 2)."-".substr($spdate, 0, 2).($t?" ".$t:"");
	}

	// returns age between one date (timestamp/ISO) and other or current date (timestamp)
	static function sqlage($sqldate, $dateref=null) {
		if ($dateref === null) $dateref=time();
		list($Y, $m, $d)=(is_numeric($sqldate)?array(date("Y", $sqldate), date("m", $sqldate), date("d", $sqldate)):explode("-", substr($sqldate, 0, 10)));
		return ((date('m', $dateref)-$m) >0 || (date('m', $dateref)-$m) == 0 && (date('d', $dateref)-$d) >= 0) ? (date('Y', $dateref)-$Y) : ((date('Y', $dateref)-$Y) - 1);
	}

	// convert ISO date[+time] to UNIX timestamp
	static function timestamp($sqldate) {
		if (strlen($sqldate) == 10) {
			$f=explode("-", $sqldate);
			return mktime(0, 0, 0, $f[1], $f[2], $f[0]);
		} else if (strlen($sqldate) == 19) {
			$f=explode(" ", $sqldate);
			$h=explode(":", $f[1]);
			$d=explode("-", $f[0]);
			return mktime($h[0], $h[1], $h[2], $d[1], $d[2], $d[0]);
		}
		return false;
	}

	// dump last/selected query
	function dump($querynum=null) {
		return $this->adump($this->aquery($querynum));
	}

	// dump a resultset array as an HTML table
	function adump($aquery) {
		?><style>
			.db_query_results {
				font-family: 'bitstream vera sans','arial','sans','sans serif';
				font-size: 11px;
				border-collapse: collapse;
				background-color: #FFF;
			}
			.db_query_results th,
			.db_query_results td {
				border: 1px solid #ddd;
				padding: 1px 9px;
			}
			.db_query_results th {
				border-bottom: 2px solid #ddd;
				padding: 4px 9px;
				color: #444;
				background-color: #F8F8F8;
			}
		</style>
		<table class='db_query_results'>
		<thead><?php
		if ($aquery) foreach ($aquery as $i=>$row) {
			if (!$i) {
				echo "<tr>";
				foreach ($row as $field=>$value)
					echo "<th>".$field."</th>";
				?></tr>
				</thead>
				<tbody><?php
			}
			echo "<tr>\n";
			foreach ($row as $value) {
				switch (gettype($value)) {
				case "string":  $s="color:#00F;text-align:left;"; break;
				case "integer": $s="color:#F00;text-align:right;"; break;
				case "double":  $s="color:#F80;text-align:right;"; break;
				case "array":   $s="color:#080;text-align:center;"; break;
				case "object":  $s="color:#06D;text-align:center;"; break;
				case "null":
				case "NULL":    $s="color:#F0F;text-align:center;"; $value="NULL"; break;
				default:        $s="color:#444;text-align:left;"; break;
				}
				echo "<td style='".$s."' title='".gettype($value)."'>".$value."</td>\n";
			}
			echo "</tr>\n";
		}
		?></tbody>
		</table><?php
	}

	// is a name a keyword? default: false
	function iskeyword($n) {
		return false;
	}

	// escape value, default: return "as is"
	function escape($v) {
		return $v;
	}

	// return escaped table name
	function sqltable($t) {
		return $this->escape($t);
	}

	// return escaped field name
	function sqlfield($f) {
		return $this->escape($f);
	}

	// return escaped fields (keys)
	function sqlfields($fields) {
		$sql="";
		if ($fields)
			foreach ($fields as $f=>$v)
				$sql.=($sql?",":"").$this->sqlfield($f);
		return $sql;
	}

	// return escaped value
	function sqlvalue($value) {
		return (is_null($value)?"NULL":(is_a($value, 'dbrawvalue')?$value->value():"'".$this->escape($value)."'"));
	}

	// return escaped values
	function sqlvalues($values) {
		$sql="";
		if ($values)
			foreach ($values as $f=>$v)
				$sql.=($sql?",":"").$this->sqlvalue($v);
		return $sql;
	}

	// return WHERE as SQL, can provide array of AND fields
	function sqlwhere($keys) {
		$sql="";
		if (is_array($keys)) {
			foreach ($keys as $f=>$v)
				$sql.=($sql?" AND ":"").$this->sqlfield($f).(is_null($v)?" IS NULL":"=".$this->sqlvalue($v));
		} else if (is_string($keys)) {
			$sql.=$keys;
		}
		return $sql;
	}

	// return SQL INSERT INTO table sentence
	function sqlinsert($table, $fields) {
		if (!is_string($fields) && !is_array($fields)) return false;
		return "INSERT INTO ".$this->sqltable($table)
			.(is_array($fields)
				?" (".$this->sqlfields($fields).") VALUES (".$this->sqlvalues($fields).")"
				:" ".$fields
			)
		;
	}

	// return SQL REPLACE INTO table sentence
	function sqlreplace($table, $fields) {
		if (!is_string($fields) && !is_array($fields)) return false;
		return "REPLACE INTO ".$this->sqltable($table)
			.(is_array($fields)
				?" (".$this->sqlfields($fields).") VALUES (".$this->sqlvalues($fields).")"
				:" ".$fields
			)
		;
	}

	// return SQL UPDATE table sentence
	function sqlupdate($table, $fields, $keys=null) {
		$sql="";
		if (is_array($fields)) {
			foreach ($fields as $f=>$v)
				$sql.=($sql?",":"").$this->sqlfield($f)."=".$this->sqlvalue($v);
		} else {
			$sql=$fields;
		}
		if ($where=$this->sqlwhere($keys)) $sql.=" WHERE ".$where;
		return "UPDATE ".$this->sqltable($table)." SET ".$sql;
	}

	// return SQL DELETE table sentence
	function sqldelete($table, $keys=null) {
		$sql="DELETE FROM ".$this->sqltable($table);
		if ($where=$this->sqlwhere($keys)) $sql.=" WHERE ".$where;
		return $sql;
	}

	// return a list of values escaped or by field key to be used for "IN" clause
	function sqlin($values, $field=null) {
		$in="";
		if ($values) {
			if ($field) {
				foreach ($values as $v)
					$in.=($in?",":"")."'".$this->escape($v[$field])."'";
			} else {
				foreach ($values as $v)
					$in.=($in?",":"")."'".$this->escape($v)."'";
			}
		}
		return (strlen($in)?$in:"NULL");
	}

	// return SQL UPDATE or INSERT table sentence
	function sqlsave($table, $fields, $keys=null) {
		return ($keys?$this->sqlupdate($table, $fields, $keys):$this->sqlinsert($table, $fields));
	}

	// search by words in such fields (partial/total)
	function sqlwordsearch($search, $fields, $total=false) {
		$sql="";
		if ($fields && strlen($search)) {
			$s=explode(" ", $search);
			foreach ($s as $i=>$w) {
				$sql.=($sql?" AND ":"")."(";
				foreach ($fields as $i=>$f)
					$sql.=($i?" OR ":"").$this->sqlfield($f).($total?"=":" LIKE ")."'".$this->escape($total?$w:(substr_count($w,"%")?$w:"%".$w."%"))."'";
				$sql.=")";
			}
		}
		return $sql;
	}

	// get/set error action events
	function event($event, $action=null) {
		if ($action === null) return $this->events[$event];
		$this->events[$event]=$action;
	}

	// dump error message depending of current output media
	function err($querynum=null, $doexit=1) {
		global $ajax, $argv;
		// if error handler defined, call it
		if ($this->events["error"]) {
			$this->events["error"](array(
				"db"=>$this,
				"ajax"=>$ajax,
				"doexit"=>$doexit,
				"num"=>$this->errnum(),
				"error"=>$this->error(),
				"info"=>$this->info($this->idcon),
				"querynum"=>$querynum,
				"lastquerynum"=>$this->lastquerynum,
				"lastquery"=>$this->lastquery($querynum),
			));
		} else {
			// default error output handlers
			if ($ajax) $this->aerr($querynum, $doexit); // ajax: json
			else if ($argv) $this->perr($querynum, $doexit); // cli: plain
			else $this->herr($querynum, $doexit); // default: html
		}
		// ensure exit with code, if defined
		if ($doexit) exit($doexit);
	}

	// HTML error output handler
	function herr($querynum=null, $doexit=1) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$num=$this->errnum();
		$error=$this->error();
		$info=$this->info($this->idcon);
		echo "<div style='font-family: Arial;'>\n"
				."<h3 style='display: block; color: red; font-size: 19px;'>".$this->driver().": error ".$num."</h3>\n"
				."<div style='font-size: 13px; position: relative; top: -23px; border: 1px solid #F00; padding: 0px 9px 0px 9px;'>\n"
				."<p><b>Descripción:</b><br><span style='color: green;'>".$error."</span></p>\n"
				.($info?"<p><b>Información:</b><br><span style='color: green;'>".(is_array($info)?serialize($info):$info)."</span></p>":"")."\n"
				.($this->lastquery($querynum)?"<p><b>Consulta(".$querynum."):</b><br><pre style='font-size:0.9em;color: blue;'>".htmlentities($this->lastquery($querynum))."</pre></p>":"")."\n"
				."</div>\n"
				."</div>\n"
		;
		if ($doexit) exit($doexit);
	}

	// plain text error output handler
	function perr($querynum=null,$doexit=1) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		echo $this->driver()." error ".$this->errnum().": ".$this->error().($this->lastquery($querynum)?"\n * last query: ".$this->lastquery($querynum):"")."\n";
		if ($doexit) exit($doexit);
	}

	// AJAX/JSON error output handler (using library VeryTinyAJAX2 if defined)
	function aerr($querynum=null,$doexit=1) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$out=array("err"=>"db(".$this->driver().") Error ".$this->errnum().":\n".$this->error()
			."\n\n"."query(".$querynum."): ".$this->lastquery($querynum));
		if (function_exists("ajax")) ajax($out);
		echo json_encode($out);
		if ($doexit) exit($doexit); // nunca se ejecuta con ajax()
	}

	// return SQL of the last query
	function lastquery($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->lastqueryint[$querynum];
	}

	// return identifier of the last query
	function lastid($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->lastqid[$querynum];
	}

	// get/set field delimiter
	function fielddelimiter($d=null) {
		if ($d !== null) $this->field_delimiter=$d;
		return $this->field_delimiter;
	}

	// return class information as string
	function __toString() {
		return "(".get_class($this).") ".$this->url()." - ".($this->connected()?($this->ready()?"Ready for Query":"Ready for Database Selection"):"Connect pending");
	}

}

// DBResult: class definition for a returned result
class dbresult {

	public $result;
	protected $db;

	function __construct($db, $result) {
		$this->db=$db;
		$this->result=$result;
	}
	function dump() { return $this->db->dump($this->result); }
	function freequery() { return $this->db->freequery($this->result); }
	function row() { return $this->db->row($this->result); }
	function numrows() { return $this->db->numrows($this->result); }
	function field($field="") { return $this->db->field($field, $this->result); }
	function atomic($sqls) { return $this->db->atomic($sqls, $this->result); }
	function aquery() { return $this->db->aquery($this->result); }
	function asocquery($fieldkey="id") { return $this->db->asocquery($fieldkey, $this->result); }
	function asocaquery($fieldkey="id") { return $this->db->asocaquery($fieldkey, $this->result); }
	function hashquery($k, $v) { return $this->db->hashquery($k, $v, $this->result); }
	function next($store=true) { return $this->db->next($store, $this->result); }
	function store() { return $this->db->store($this->result); }
	function err($doexit=1) { return $this->db->err($this->result, $doexit); }
	function aerr($doexit=1) { return $this->db->aerr($this->result, $doexit); }
	function herr($doexit=1) { return $this->db->herr($this->result, $doexit); }
	function perr($doexit=1) { return $this->db->perr($this->result, $doexit); }
	function lastquery() { return $this->db->lastquery($this->result); }
	function lastid() { return $this->db->lastid($this->result); }

}

// DBRAWValue: class to define unescaped values like functions
class dbrawvalue {

	protected $value;

	function __construct($value) {
		$this->value=$value;
	}

	function value($value=null) {
		if ($value === null) return $this->value;
		$this->value=$value;
	}

	function __toString(){
		return $this->value;
	}

}

// void database class to throw errors on creation
class dbVoid extends dbbase {
	function __construct($o) {
		$this->real_errnum=$o["errnum"];
		$this->real_error=$o["error"];
	}
	function driver() { return "void"; }
	function version() { return 0.1; }
	function protocol() { return "void"; }
	function connect() { return false; }
	function close() { return false; }
	function info() { return false; }
	function ready() { return false; }
	function select() { return false; }
	function errnum() { return $this->real_errnum; }
	function error() { return $this->real_error; }
}

// DEPRECATED: if db_setup defined, fill values
if (isset($db_setup) && is_array($db_setup)) foreach ($db_setup as $db_name=>&$db_config) {
	$db_config["class"]=dbbase::dbclass($db_config["type"]);
	$db_config["lib"]=dbbase::lib($db_config["type"]);
} unset($db_name); unset($db_config);

// if any database setup defined...
if ((!defined("DB_SETUP") || DB_SETUP) && (isset($db_setup) && is_array($db_setup))) {

	// ...create all defined databases
	foreach ($db_setup as $db_name=>&$db_config) if ($db_config) {
		if ($db_config["type"]) {
			if (dbbase::lib($db_config["type"])) {
				$$db_name=dbbase::create($db_config);
				if ($$db_name && (!isset($db_config["connect"]) || $db_config["connect"])) {
					$$db_name->connect();
					$db_config["err"]=(isset($$db_name)?($$db_name->ready()?DB_ERR_NONE:DB_ERR_CONNECT):DB_ERR_CLASS);
				}
			} else {
				$db_config["err"]=DB_ERR_CLASS;
			}
		}
		if ($db_config["err"]) $db_errors=true;
	} unset($db_name); unset($db_config);

	// if any error occurred, display errors and stop process (if display_errors or DB_ERRORS enabled)
	if ((!defined("DB_ERRORS") || DB_ERRORS || (!defined("DB_ERRORS") && ini_get("display_errors"))) && isset($db_errors) && $db_errors) {
		$_ishtml=true;
		foreach (headers_list() as $_h)
			if (strtolower(substr($_h, 0, 24)) == "content-type: text/plain") {
				$_ishtml=false;
				break;
			}
		unset($_h);
		foreach ($db_setup as $db_name=>$db_config) if ($db_config["err"]) {
			$db_url=($db_name && $$db_name?$$db_name->url():$db_config["type"]);
			$_err='$'.$db_name."(".$db_url."): ".dbbase::dbconfigerror($$db_name, $db_config["err"]);
			if (function_exists("perror")) perror($_err);
			else echo $_err.($_ishtml?"<br />":"\n");
			exit;
		} unset($db_name); unset($db_config);
	}

}
