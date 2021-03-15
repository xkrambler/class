<?php

	// constantes predefinidas
	define("DB_ERR_NONE",     0);
	define("DB_ERR_CONNECT",  1);
	define("DB_ERR_DATABASE", 2);
	define("DB_ERR_CLASS",    3);
	define("DB_ERR_TYPE",     4);

	/*

		dbbase. PHP fast&tiny class for access Databases, by Pablo Rodríguez Rey (http://mr.xkr.es/)
		Distributed under the GPL version 2 license (http://www.gnu.org/copyleft/gpl.html)

		Constantes:
			DB_SETUP   Si no definida o true, se autoconfiguran las bases de datos
			DB_ERRORS  Si no definida o true, se detiene con errores

		Ejemplo de uso:

			// crear una instancia de la clase
			$db=new dbMySQL(array("host"=>"127.0.0.1","user"=>"root","pass"=>"","db"=>"database"));

			// iniciar la conexión con el servidor
			if (!$db->connect()) $db->err();
			else {

				// seleccionar la BBDD y realizar una consulta SELECT
				if (!$db->ready()) $db->err();
				else if (!$db->query("SELECT * FROM tabla")) $db->err();
				else {

					// no hay errores, buscar en todas las
					// filas afectadas por la consulta
					echo "<html><body><pre>";
					for ($i=0;$i<$db->numrows();$i++) {

						// mostrar la fila completa
						echo "<b>Registro número ".$i.":</b><br>";
						var_dump($db->row());

					}

					// fin de consulta, liberar memoria
					$db->freequery();
					echo "</pre></body></html>";

				}

				// cerrar la conexión con el servidor MySQL
				$db->close();

			}

		@author Pablo Rodríguez Rey
		@link http://mr.xkr.es/
		@since 2009-05-08
		@copyright GPL version 2
		@version 0.2

	*/
	abstract class dbbase {

		protected $connected=false;
		protected $idcon;
		protected $idquery;
		protected $lastqueryint;
		protected $lastqid;
		protected $lastrow;
		protected $afrows;
		protected $iidquery;
		protected $real_errnum;
		protected $real_error;
		protected $field_delimiter="`";
		protected $lastquerynum=0;
		protected $dbselected=false;
		protected $debug=false;
		protected $sqltimelast=false;
		protected $sqltimes=array();
		protected $events;
		protected $setup=array();

		// funciones obligatorias
		abstract function connect();
		abstract function close();
		abstract function ready();
		abstract function info();

		// devolver texto descriptivo de error para una error de configuración
		static function dbconfigerror($db, $config) {
			switch ($config["err"]) {
			case DB_ERR_NONE: break;
			case DB_ERR_CONNECT: return "Error en conexión: ".$db->error();
			case DB_ERR_DATABASE: return "Base de datos no seleccionada: ".$db->error();
			case DB_ERR_CLASS: return "Clase ".$config["type"]." no disponible.";
			case DB_ERR_TYPE: return "Parámetro tipo de base de datos requerido (type).";
			default: return ($db?"Error: ".$db->error():"No se ha creado objeto.");
			}
			return "";
		}

		// setup getter/setter
		function __get($n) { return $this->setup[$n]; }
		function __set($n, $v) { $this->setup[$n]=$v; }

		// get/set setup
		function setup($setup=null) {
			if ($setup!==null) $this->setup=$setup;
			return $this->setup;
		}

		// is connected?
		function connected() {
			return $this->connected;
		}

		// obtiene/establece una conexión previamente creada
		function connection($idcon=null) {
			if ($idcon !== null) $this->idcon=$idcon;
			return $this->idcon;
		}

		// DEPRECATED: obtiene una conexión previamente creada
		function getConnection() {
			return $this->idcon;
		}

		// DEPRECATED: especifica una conexión previamente creada
		function setConnection($idcon) {
			$this->idcon=$idcon;
		}

		// información del driver, versión de la clase y protocolo
		function driver() { return "Unknown"; }
		function version() { return 0; }
		function protocol() { return "none"; }

		// habilitar/deshabilitar depuración
		function debug($enabled=null) {
			if ($enabled!==null) $this->debug=$enabled;
			return $this->debug;
		}

		// devolver información de tiempo (en segundos) de la última consulta
		function timelast() {
			return $this->sqltimelast;
		}

		// volcado de tiempos de consulta
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

		// devolver la URL de conexión
		function url($hide_password=true) {
			return strtolower($this->protocol())."://"
				.$this->setup["user"]
				.($this->setup["pass"]?":".($hide_password?"*":$this->setup["pass"]):"")
				."@".$this->setup["host"]
				.($this->setup["port"]?":".$this->setup["port"]:"")
				.($this->setup["db"]?"/".$this->setup["db"]:"")."/"
			;
		}

		// limpiar datos de control
		function clear() {
			$this->real_errnum=false;
			$this->real_error=false;
		}

		// dummy para bases de datos no transaccionales
		function begin() { return true; }
		function commit() { return true; }
		function rollback() { return true; }

		// ejecutar consultas SQL transaccionalmente
		function atomic($array_sql, $querynum=null) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			if (is_string($array_sql)) $array_sql=array($array_sql);
			if (!$this->begin()) return false;
			foreach ($array_sql as $sql) {
				if (!$this->query($sql,$querynum)) {
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

		// devuelve un array de filas AJAX
		function aquery($querynum=null) {
			if (!$this->idcon>0) return(false);
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$a=Array();
			for ($i=0;$row=$this->row($querynum);$i++) {
				$a[$i]=$row;
			}
			$this->freequery();
			return($a);
		}

		// devuelve un array asociativo de filas dado un campo clave
		function asocquery($fieldkey="id", $querynum=null) {
			if (!$this->idcon>0) return(false);
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$a=Array();
			for ($i=0;$row=$this->row($querynum);$i++)
				$a[$row[$fieldkey]]=$row;
			$this->freequery();
			return($a);
		}

		// devuelve un array asociativo de filas múltiples dado un campo clave
		function asocaquery($fieldkey="id", $querynum=null) {
			if (!$this->idcon>0) return(false);
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$a=Array();
			for ($i=0;$row=$this->row($querynum);$i++) {
				if (!$a[$row[$fieldkey]]) $a[$row[$fieldkey]]=Array();
				array_push($a[$row[$fieldkey]],$row);
			}
			$this->freequery();
			return($a);
		}

		// devuelve un hash de filas clave->valor AJAX
		function hashquery($k, $v, $querynum=null) {
			if (!$this->idcon>0) return(false);
			if (!$querynum) $querynum="%".$this->lastquerynum;
			$a=Array();
			for ($i=0;$row=$this->row($querynum);$i++)
				$a[$row[$k]]=$row[$v];
			$this->freequery();
			return $a;
		}

		// provista la fecha y hora, se transforma a notación dd/mm/yyyy hh:mm:ss
		// provista la fecha, se convierte a notación dd/mm/yyyy
		// provista la hora, se devuelve de la misma forma, hh:mm:ss
		static function spdate($sqldate) {
			if (strlen($sqldate)==19) return(substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11));
			if (strlen($sqldate)==16) return(substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11).":00");
			if (strlen($sqldate)==13) return(substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4)." ".substr($sqldate,11).":00:00");
			if (strlen($sqldate)==10) return(substr($sqldate,8,2)."/".substr($sqldate,5,2)."/".substr($sqldate,0,4));
			if (strlen($sqldate)==8) return($sqldate);
		}

		// provisto la fecha en formato dd/mm/yyyy RESTO se pasa a yyyy-mm-dd RESTO
		static function sqldate($spdate) {
			if (strlen($spdate)<10) return($spdate);
			$t=substr($spdate,11);
			if (strlen($t)==2) $t.=":00:00";
			if (strlen($t)==5) $t.=":00";
			return(substr($spdate,6,4)."-".substr($spdate,3,2)."-".substr($spdate,0,2).($t?" ".$t:""));
		}

		// devuelve la edad en años transcurrida de una fecha dada
		static function sqlage($sqldate, $dateref=null) {
			if ($dateref===null) $dateref=time();
			list($Y, $m, $d) = explode("-", substr($sqldate,0,10));
			return ((date('m',$dateref) - $m) >0 || (date('m',$dateref) - $m) == 0 && (date('d',$dateref) - $d) >= 0) ? (date('Y',$dateref) - $Y) : ((date('Y',$dateref) - $Y) - 1);
		}

		// convertir formato de fecha SQL a timestamp
		static function timestamp($sqldate) {
			if (strlen($sqldate)==10) {
				$f=explode("-",$sqldate);
				return(mktime(0,0,0,$f[1],$f[2],$f[0]));
			}
			if (strlen($sqldate)==19) {
				$f=explode(" ",$sqldate);
				$h=explode(":",$f[1]);
				$f=explode("-",$f[0]);
				return(mktime($h[0],$h[1],$h[2],$f[1],$f[2],$f[0]));
			}
			return(false);
		}

		// muestra una tabla de resultados de una consulta realizada
		function dump($querynum=null) {
			return $this->adump($this->aquery($querynum));
		}

		// muestra una tabla de resultados de una consulta en un array
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
			foreach ($aquery as $i=>$row) {
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
					case "NULL":  $s="color:#F0F;text-align:center;"; $value="NULL"; break;
					default:        $s="color:#444;text-align:left;"; break;
					}
					echo "<td style='".$s."' title='".gettype($value)."'>".$value."</td>\n";
				}
				echo "</tr>\n";
			}
			?></tbody>
			</table><?php
		}

		// es keyword? por defecto: false
		function iskeyword($n) {
			return false;
		}

		// devolver valor filtrado de nombre de tabla
		function sqltable($t) {
			return $this->escape($t);
		}

		// devolver valor filtrado de campo
		function sqlfield($f) {
			return $this->escape($f);
		}

		// devolver campos filtrados
		function sqlfields($fields) {
			$i=0;
			$sql="";
			if ($fields) foreach ($fields as $n=>$v) {
				$sql.=($i?",":"").$this->sqlfield($n);
				$i++;
			}
			return $sql;
		}

		// devolver valor filtrado
		function sqlvalue($value) {
			return (is_null($value)?"NULL":(is_a($value,'dbrawvalue')?$value->value():"'".$this->escape($value)."'"));
		}

		// devolver valores filtrados
		function sqlvalues($fields) {
			$sql="";
			if ($fields)
				foreach ($fields as $n=>$v)
					$sql.=($sql?",":"").$this->sqlvalue($v);
			return $sql;
		}

		// devolver where
		function sqlwhere($keys) {
			$sql="";
			if (is_array($keys)) {
				foreach ($keys as $n=>$v)
					$sql.=($sql?" AND ":"").$this->sqlfield($n).(is_null($v)?" IS NULL":"=".$this->sqlvalue($v));
			} else if (strlen($keys)) {
				$sql.=$keys;
			}
			return $sql;
		}

		// crea una sentencia SQL de inserción
		function sqlinsert($table, $fields) {
			if (!is_string($fields) && !is_array($fields)) return false;
			return "INSERT INTO ".$this->sqltable($table)
				.(is_array($fields)
					?" (".$this->sqlfields($fields).") VALUES (".$this->sqlvalues($fields).")"
					:" ".$fields
				)
			;
		}

		// crea una sentencia SQL de reemplazo
		function sqlreplace($table, $fields) {
			if (!is_string($fields) && !is_array($fields)) return false;
			return "REPLACE INTO ".$this->sqltable($table)
				.(is_array($fields)
					?" (".$this->sqlfields($fields).") VALUES (".$this->sqlvalues($fields).")"
					:" ".$fields
				)
			;
		}

		// crea una sentencia SQL de actualización
		function sqlupdate($table, $fields, $keys=null) {
			$sql="";
			if (is_array($fields)) {
				foreach ($fields as $n=>$v)
					$sql.=($sql?",":"").$this->sqlfield($n)."=".$this->sqlvalue($v);
			} else {
				$sql=$fields;
			}
			if ($where=$this->sqlwhere($keys)) $sql.=" WHERE ".$where;
			return "UPDATE ".$this->sqltable($table)." SET ".$sql;
		}

		// crea una sentencia SQL de eliminación
		function sqldelete($table, $keys=null) {
			$sql="DELETE FROM ".$this->sqltable($table);
			if ($where=$this->sqlwhere($keys)) $sql.=" WHERE ".$where;
			return $sql;
		}

		// búsqueda por palabras en campos (busqueda total o parcial)
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

		// devuelve una lista de valores filtrados y separados por comas para ser usado en cláusula IN
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

		// establecer/devolver evento de error
		function event($event, $action=null) {
			if ($action===null) return $this->events[$event];
			$this->events[$event]=$action;
		}

		// muestra un mensaje de error dependiendo del medio (librería VeryTinyAJAX)
		function err($querynum=null, $doexit=1) {
			global $ajax;
			// si hay manejador de errores, llamar
			if ($this->events["error"]) {
				$this->events["error"](Array(
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
				if ($doexit) exit($doexit);
			} else {
				// acciones para mostrar error por defecto
				if ($ajax) $this->aerr($querynum, $doexit);
				else $this->herr($querynum, $doexit);
			}
		}

		// devolver error en formato HTML
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

		// muestra un mensaje de error en texto plano
		function perr($querynum=null,$doexit=1) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			echo $this->driver()." error ".$this->errnum().": ".$this->error().($this->lastquery($querynum)?"\n * last query: ".$this->lastquery($querynum):"")."\n";
			if ($doexit) exit($doexit);
		}

		// devolver error en formato AJAX (librería VeryTinyAJAX2)
		function aerr($querynum=null,$doexit=1) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			ajax(Array("err"=>"Error al realizar petición a la base de datos:"
				."\n\n"."db(".$this->driver().") Error ".$this->errnum().":\n".$this->error()
				."\n\n"."Consulta(".$querynum."): ".$this->lastquery($querynum)));
			if ($doexit) exit($doexit); // nunca se ejecuta con ajax()
		}

		// devuelve la última consulta ejecutada en el servidor
		function lastquery($querynum=null) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			return($this->lastqueryint[$querynum]);
		}

		// devuelve el número de id insertado
		function lastid($querynum=null) {
			if (!$querynum) $querynum="%".$this->lastquerynum;
			return($this->lastqid[$querynum]);
		}

		// delimitador de campos
		function fielddelimiter($n=null) {
			if ($n===null) return $this->field_delimiter;
			else $this->field_delimiter=$n;
		}

		// devolver una cadena con información sobre la base de datos
		function __toString() {
			return "(".get_class($this).") ".$this->url()." - ".($this->connected()?($this->ready()?"Ready for Query":"Ready for Database Selection"):"Connect pending");
		}

	}

	/*

		DB query result set.
		Distributed under the GPL version 2 license (http://www.gnu.org/copyleft/gpl.html)

		@author Pablo Rodríguez Rey
		@link http://mr.xkr.es/
		@since 2009-05-08
		@copyright GPL version 2
		@version 0.2

	*/
	class dbresult {

		protected $db;
		public $result;

		function __construct($db, $result) {
			$this->db=$db;
			$this->result=$result;
		}
		function __destruct() {}
		function dump() { return $this->db->dump($this->result); }
		function freequery() { return $this->db->freequery($this->result); }
		function row() { return $this->db->row($this->result); }
		function numrows() { return $this->db->numrows($this->result); }
		function field($field="") { return $this->db->field($field, $this->result); }
		function atomic($array_sql) { return $this->db->atomic($array_sql, $this->result); }
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

	/*

		DB RAW value
		Distributed under the GPL version 2 license (http://www.gnu.org/copyleft/gpl.html)

		@author Pablo Rodríguez Rey
		@link http://mr.xkr.es/
		@since 2009-05-08
		@copyright GPL version 2
		@version 0.2

	*/
	class dbrawvalue {
		protected $value;
		function __construct($value) {
			$this->value=$value;
		}
		function value($value=null) {
			if ($value===null) return $this->value;
			$this->value=$value;
		}
		function __toString(){
			return $this->value;
		}
	}

	// asignar ficheros de clase
	if (@$db_setup) foreach ($db_setup as $db_name=>&$db_config) {
		$db_config["class"]="db".$db_config["type"];
		$db_config["lib"]=__DIR__."/"."db.".$db_config["type"].".php";
	} unset($db_config);

	// si hay alguna configuración predefinida, cargarla
	if (@$db_setup && (!defined("DB_SETUP") || DB_SETUP)) {

		// crear todas las instancias configuradas
		foreach ($db_setup as $db_name=>&$db_config) if ($db_config) {
			if ($db_config["type"]) {
				if (file_exists($db_config["lib"])) {
					require_once($db_config["lib"]);
					$_db_class=$db_config["class"];
					$$db_name=new $_db_class($db_config);
					unset($_db_class);
					if (isset($$db_name) && (!isset($db_config["connect"]) || $db_config["connect"])) {
						$$db_name->connect();
						$db_config["err"]=(isset($$db_name)?($$db_name->ready()?DB_ERR_NONE:DB_ERR_CONNECT):DB_ERR_CLASS);
					}
				} else {
					$db_config["err"]=DB_ERR_CLASS;
				}
			} else {
				$db_config["err"]=DB_ERR_TYPE;
			}
			if ($db_config["err"]) $db_errors=true;
		} unset($db_config);

		// si ha ocurrido algún error, mostrar información de errores y detener proceso
		if ($db_errors && (!defined("DB_ERRORS") || DB_ERRORS)) {
			$_ishtml=true;
			foreach (headers_list() as $_h)
				if (strtolower(substr($_h, 0, 24))=="content-type: text/plain") {
					$_ishtml=false;
					break;
				}
			unset($_h);
			foreach ($db_setup as $db_name=>$db_config) if ($db_config["err"]) {
				$db_url=($db_name && $$db_name?$$db_name->url():"");
				$_err='$'.$db_name."[".$db_config["type"]."](".$db_url."): ".dbbase::dbconfigerror($$db_name, $db_config);
				if (function_exists("perror")) perror($_err);
				else echo $_err.($_ishtml?"<br />":"\n");
				exit;
			}
		}

	}
