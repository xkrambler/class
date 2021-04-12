<?php if (!class_exists("dbbase")) die();

/*
	Clase PHP de Acceso a MySQL v0.4, desarrollado por Pablo Rodríguez Rey
	http://mr.xkr.inertinc.org ~ mr.xkr-at-inertinc.org
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)
*/

class dbMySQL extends dbbase {

	// información del driver y versión de la clase
	function driver() { return "MySQL"; }
	function version() { return 0.4; }
	function protocol() { return "mysql"; }

	// constructor
	function __construct($setup=array()) {
		$this->setup($setup);
		if ($this->setup["resource"]) $this->connect();
	}

	// inicia la conexión con el servidor o reutiliza una conexión existente
	function connect() {
		$this->clear();
		if (!function_exists("mysql_connect")) die("ERROR: La librería MySQL no iniciada: Función mysql_connect inexistente.");
		if (is_resource($this->setup["resource"])) {
			$this->idcon=$this->setup["resource"];
			//$this->setup["db"]=$this->database();
		} else {
			$this->idcon=($this->setup["persistent"] || !isset($this->setup["persistent"])
				?(@mysql_pconnect($this->setup["host"], $this->setup["user"], $this->setup["pass"]))
				:(@mysql_connect($this->setup["host"], $this->setup["user"], $this->setup["pass"]))
			);
		}
		$this->connected=($this->idcon>0?true:false);
		if ($this->connected && $this->setup["encoding"]) mysql_query("SET NAMES ".$this->setup["encoding"], $this->idcon);
		if ($this->connected && isset($this->setup["autocommit"])) mysql_query("SET autocommit=".($this->setup["autocommit"]?1:0), $this->idcon);
		if ($this->setup["db"]) $this->select();
		$this->real_errnum=($this->connected?0:mysql_errno());
		$this->real_error=($this->connected?"":mysql_error());
		return $this->connected;
	}

	// cierra la conexión con el servidor
	function close() {
		$this->clear();
		if (!$this->idcon>0) return false;
		mysql_close($this->idcon);
		return true;
	}

	// reconectar
	function reconnect() {
		$this->close();
		return $this->connect();
	}

	// información de conexión
	function ready() {
		if (!$this->idcon>0) return false;
		return ($this->ping() && $this->dbselected?true:false);
	}

	// obtener base de datos actual
	function database() {
		if ($q=mysql_query("SELECT DATABASE()", $this->idcon))
			if ($r=mysql_fetch_array($q))
				return $r[0];
		return false;
	}

	// listar bases de datos
	function databases() {
		$a=array();
		if ($q=mysql_query("SHOW DATABASES",$this->idcon)) {
			while ($irow=mysql_fetch_array($q))
				$a[]=$irow[0];
			return $a;
		}
		return false;
	}

	// selecciona la base de datos con la que trabajará la clase
	function select($database=null) {
		$this->clear();
		if (!$this->idcon>0) return false;
		if ($database) $this->setup["db"]=$database;
		if (!$this->setup["db"]) return true;
		$this->dbselected=($this->setup["db"] && mysql_select_db($this->setup["db"], $this->idcon)?true:false);
		return $this->dbselected;
	}

	// efectua un checkeo (ping) del servidor
	function ping() {
		return @mysql_ping($this->idcon);
	}

	// obtiene información sobre la consulta más reciente 
	function info() {
		return @mysql_info($this->idcon);
	}

	// obtener el status actual del sistema
	function stat() {
		return @mysql_stat($this->idcon);
	}

	// establecer el modo de depuración
	function debug($enabled=null) {
		parent::debug($enabled);
		if ($this->idcon) mysql_query("SET SESSION query_cache_type = OFF;", $this->idcon);
	}

	// iniciar transacción
	function begin() {
		return (mysql_query("BEGIN", $this->idcon)?true:false);
	}

	// finalizar transacción
	function commit() {
		return (mysql_query("COMMIT", $this->idcon)?true:false);
	}

	// cancelar transacción
	function rollback() {
		return (mysql_query("ROLLBACK", $this->idcon)?true:false);
	}

	// devolver campo con la hora actual
	function now() {
		return new dbrawvalue("NOW()");
	}

	// ejecuta una consulta (select)
	function query($sqlquery,$querynum=null) {
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$this->clear();
		$numretries=0;
		$st=microtime(true);
		do {
			$tryagain=false;
			if (!$this->idcon>0) return false;
			$this->lastqueryint[$querynum]=$sqlquery;
			if (!mysql_ping($this->idcon) && !$this->reconnect()) return false;
			if (!$this->select()) return false;
			$this->idquery[$querynum]=mysql_query($sqlquery, $this->idcon);
			$numretries++;
			// reintentarlo 2 veces más en caso de desconexión
			if ((mysql_errno($this->idcon)==2006 || mysql_errno($this->idcon)==2013) && $numretries<3) {
				$this->reconnect();
				$tryagain=true;
			}
		} while ($tryagain);
		if (mysql_errno($this->idcon)>0) return false;
		if (!$this->idquery[$querynum]) return false;
		$this->lastqid[$querynum]=mysql_insert_id($this->idcon);
		//$this->afrows[$querynum]=mysql_num_rows($this->idquery[$querynum]);
		$this->afrows[$querynum]=mysql_affected_rows($this->idcon);
		$this->sqltimelast=microtime(true)-$st;
		if ($this->debug) {
			$q=mysql_query("EXPLAIN EXTENDED ".$sqlquery,$this->idcon);
			$explain=Array(); while ($r=(@mysql_fetch_assoc($q))) $explain[]=$r;
			$this->sqltimes[]=Array(
				"id"=>$querynum,
				"time"=>microtime(true)-$st,
				"lastid"=>$this->lastqid[$querynum],
				"rows"=>$this->afrows[$querynum],
				"sql"=>$sqlquery,
				"explain"=>$explain,
			);
		}
		return new dbresult($this,$querynum);
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
		@mysql_free_result($this->idquery[$querynum]);
		unset($this->idquery[$querynum]);
		unset($this->lastqueryint[$querynum]);
		unset($this->lastqid[$querynum]);
		unset($this->afrows[$querynum]);
		return true;
	}

	// devuelve el número de filas afectadas
	function numrows($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->afrows[$querynum];
	}

	// devuelve array fila
	function row($querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return mysql_fetch_assoc($this->idquery[$querynum]);
	}

	// devuelve de la primera fila el valor del campo deseado o el primero si se omite
	function field($field="",$querynum=null) {
		$this->clear();
		if (!$this->idcon>0) return false;
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->lastrow=mysql_fetch_assoc($this->idquery[$querynum]);
		if (strlen($field)) return($this->lastrow[$field]);
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
		return mysql_real_escape_string($s);
	}

	// escapar nombre de campo
	function sqlfield($f) {
		return "`".str_replace("`", "", $f)."`";
	}

	// escapar nombre de tabla
	function sqltable($t) {
		return "`".str_replace("`", "", $t)."`";
	}

	// devuelve la cadena de versión del servidor
	function dbversion() {
		$this->clear();
		if (!$this->idcon>0) return false;
		$this->iidquery=mysql_query("SELECT VERSION()",$this->idcon);
		$irow=mysql_fetch_array($this->iidquery);
		return $irow[0];
	}

	// devuelve el último código de error producido en el servidor
	function errnum() {
		return ($this->real_errnum?$this->real_errnum:mysql_errno($this->idcon));
	}

	// devuelve el último mensaje de error producido en el servidor
	function error() {
		return ($this->real_error?$this->real_error:mysql_error($this->idcon));
	}

	// importar SQL a la base de datos
	function import($sql) {
		$this->clear();
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
		$this->clear();
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
		if ($filename) return true;
		else return($all);
	}
	
}
