<?php

	/*

		Clase PHP de Acceso a ORACLE v0.3, desarrollado por Pablo Rodríguez Rey
		http://mr.xkr.es ~ mr-at-xkr-dot-es
		Bajo licencia de uso libre GPLv2 (https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

	*/
	class dbORACLE extends dbbase {

		protected $stmt;

		// información del driver y versión de la clase
		function driver() { return "Oracle"; }
		function version() { return 0.3; }
		function protocol() { return "oracle"; }

		// constructor/destructor
		function __construct($setup=Array()) { $this->setup($setup); }
		function __destruct() { if (isset($this->setup["persistent"]) && !$this->setup["persistent"]) $this->close(); }

		// conexión con el servidor (puede estar retardada)
		function connect() {
			return ($this->setup["delayed"]?true:$this->rconnect());
		}

		// inicia la conexión con el servidor
		function rconnect() {
			$oci_connect=(!isset($this->setup["persistent"]) || $this->setup["persistent"]?"oci_pconnect":"oci_connect");
			if (!function_exists($oci_connect)) die("ERROR: La librería dbOracle no iniciada: Función ".$oci_connect." inexistente.");
			// comprobar si ha especificado un puerto dentro de la cadena del servidor
			if (!$this->setup["connection_string"]) {
				$i=strpos($this->setup["host"],":");
				if (strlen($i)) {
					$this->setup["port"]=substr($this->setup["host"],$i+1);
					$this->setup["host"]=substr($this->setup["host"],0,$i);
				}
				$this->setup["connection_string"]="
					(DESCRIPTION =
				    (ADDRESS_LIST =
				      (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->setup["host"].")(PORT = ".($this->setup["port"]?$this->setup["port"]:1521)."))
				    )
				    (CONNECT_DATA =
				      (SERVICE_NAME = ".$this->setup["db"].")
				    )
				  )";
			}
			$oci_connect="oci_connect";
			$this->idcon=(@$oci_connect($this->setup["user"], $this->setup["pass"], $this->setup["connection_string"], $this->setup["encoding"])); // por defecto era WE8MSWIN1252
			$this->connected=($this->idcon>0?true:false);
			if ($this->connected) {
				@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_DATE_FORMAT='yyyy-mm-dd hh24:mi:ss'"),OCI_DEFAULT);
				@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.,'"),OCI_DEFAULT);
				@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_COMP=LINGUISTIC"),OCI_DEFAULT);
				//@oci_execute(oci_parse($this->idcon,"ALTER SESSION SET NLS_LANGUAGE=SPANISH"),OCI_DEFAULT);
			}
			$this->dbselected=$this->connected;
			return $this->connected;
		}

		// cierra la conexión con el servidor
		function close() {
			if (!$this->idcon>0) return false;
			oci_close($this->idcon);
			$this->idcon=false;
			return true;
		}

		// reconectar
		function reconnect() {
			$this->close();
			return $this->rconnect();
		}

		// información de conexión
		function ready() {
			return ($this->setup["delayed"] || $this->idcon?true:false);
		}

		// selecciona la base de datos con la que trabajará la clase
		function select($database) {
			if (!$this->idcon>0) return false;
			$this->setup["db"]=$database;
			return $this->reconnect();
		}

		// efectua un checkeo (ping) del servidor
		function ping() { return (function_exists("oci_connect")?true:false); }

		// obtiene información sobre la consulta más reciente 
		function info() { return false; }

		// obtener el status actual del sistema
		function stat() { return false; }

		// ejecuta una consulta (select)
		function query($sqlquery,$querynum=null) {
			if ($this->setup["delayed"] && !$this->idcon>0 && !$this->rconnect()) return false;
			if (!$this->idcon>0) return false;

			if (!$querynum) {
				$this->lastquerynum++;
				$querynum="%".$this->lastquerynum;
			}
			$st=microtime(true);
			$this->lastqueryint[$querynum]=$sqlquery;
			$this->stmt[$querynum]=oci_parse($this->idcon,$sqlquery);
			if (!(@oci_execute($this->stmt[$querynum],OCI_DEFAULT))) return false;
			$this->lastqid[$querynum]=null;
			$this->afrows[$querynum]=oci_num_rows($this->stmt[$querynum]);
			$this->sqltimelast=microtime(true)-$st;
			$result=new dbresult($this,$querynum);
			return $result;
		}

		// commit
		function commit($querynum=null) {
			return $this->query("COMMIT");
		}

		// ejecuta una consulta (insert, update, alter, ...)
		function exec($sqlquery, $querynum=null) {
			return $this->query($sqlquery, $querynum); // idem
		}

		// limpia la consulta
		function freequery($querynum=null) {
			if (!$this->idcon>0) return false;
			if (!$querynum) $querynum="%".$this->lastquerynum;
			oci_free_statement($this->stmt[$querynum]);
			return(true);
		}

		// devuelve el número de filas afectadas
		function numrows($querynum=null) {
			if (!$this->idcon>0) return false;
			if (!$querynum) $querynum="%".$this->lastquerynum;
			return($this->afrows[$querynum]);
		}

		// devuelve array fila
		function row($querynum=null) {
			if (!$this->idcon>0) return false;
			if (!$querynum) $querynum="%".$this->lastquerynum;
			return(oci_fetch_assoc($this->stmt[$querynum]));
		}

		// devuelve de la primera fila el valor del campo deseado o el primero si se omite
		function field($field="", $querynum=null) {
			if (!$this->idcon>0) return false;
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$this->lastrow=oci_fetch_assoc($this->stmt[$querynum]);
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
			$s=str_replace("\n","'||chr(10)||'",str_replace("'","''",$s));
			return $s;
		}

		// devuelve la cadena de versión del servidor
		function dbversion() {
			if (!$this->idcon>0) return false;
			return oci_server_version($this->idcon);
		}

		// devuelve el último código de error producido en el servidor
		function errnum($querynum=null) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$err=oci_error($this->stmt[$querynum]);
			return ($err["code"]?intval($err["code"]):false);
		}

		// devuelve el último mensaje de error producido en el servidor
		function error($querynum=null) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$err=($this->stmt[$querynum]
				?oci_error($this->stmt[$querynum])
				:oci_error()
			);
			return ($err["code"]?$err["message"]:"");
		}

	}
