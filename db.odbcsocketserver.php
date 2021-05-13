<?php if (!class_exists("dbbase")) die();

/*

	Clase PHP de Acceso a un servidor ODBC Socket Server v0.1, desarrollado por Javier Gil Motos
	http://javiergm.com ~ 
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)

*/

class dbOdbcSocketServer extends dbbase {
	private $data = null;
	private $row_count = null;

	// información del driver y versión de la clase
	function driver() { return "ODBCSocketServer"; }
	function version() { return 0.1; }
	function protocol() { return "odbcsocketserver"; }
	
	// constructor
	function __construct($setup=Array()) {
		$this->setup($setup);
		$this->connected = true;
		$this->idcon = 1;
	}
	
	// ODBCSocketServer es stateless, luego no tiene sentido hacer un connect como tal
	function connect() {
		return $this->connected;
	}

	// ODBCSocketServer es stateless, luego no tiene sentido cerrar una conexión, puesto que no existe
	function close() {
		return true;
	}
	
	// información de conexión
	function ready() {
		return true;
	}
	
	// selecciona la base de datos con la que trabajará la clase
	function select($db=null) {
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
		// Variable temporal para almacenar el contenido devuelto por el socket hacia el servidor
		$sreturn = "";
		$this->clear();
		if (!$querynum) {
			$this->lastquerynum++;
			$querynum="%".$this->lastquerynum;
		}
		$st=microtime(true);
		$this->lastqueryint[$querynum]=$sqlquery;
		// Poner a cero el contador interno de filas devueltas del resultado de la consulta
		$this->row_count[$querynum] = 0;
		// Abrir el socket hacia el servidor
		$this->idquery[$querynum] = fsockopen($this->setup["host"],
														$this->setup["port"],
														$errno,
														$errstr,
														30);
		// Si falla la conexión al servidor, rellenamos las variables de error
		if (!$this->idquery[$querynum]){
			$this->real_errnum = $errno;
			$this->real_error = utf8_encode($errstr);
			return false;
		}else{
			// En ODBC las contraseñas con el carácter ';' dan problemas. Avisamos al usuario para que no se rompa la cabeza intentando saber qué ocurre.
			if(strpos($this->setup["pass"],";")!==false){
				$this->real_errnum = -1;
				$this->real_error = utf8_encode("Database user's password must not contain a semicolon.");
				return false;
			}
			// Construir el XML de la petición al servidor
			$sSend = "<?xml version=\"1.0\"?>\r\n<request>\r\n<connectionstring>DSN=".$this->setup["dsn"].";UID=".$this->setup["user"].";PWD=".$this->setup["pass"]."</connectionstring>\r\n<sql>".HTMLSpecialChars($sqlquery)."</sql>\r\n</request>\r\n";
			// Enviar el XML
			fputs($this->idquery[$querynum], $sSend);
			// Leer la respuesta
			while (!feof($this->idquery[$querynum])){
				$sreturn = $sreturn . fgets($this->idquery[$querynum], 128);
			}
			// Cerrar socket
			fclose($this->idquery[$querynum]);
		}
		
		// Procesar el xml de resultado
		$ob = simplexml_load_string($sreturn);
		$this->data[$querynum] = NULL;

		// Si la petición ha sido correcta....
		// <?xml version="1.0"? ><result state="success"><row><column name="nombre">Valor</column></row></result> 
		if(strval($ob["state"]) == "success"){
			$this->data[$querynum] = Array();

			// La consulta debe devolver al menos una fila, o dejamos el atributo data a NULL
			if($ob->row->count() > 0){
				$this->data[$querynum][0] = Array();
				foreach($ob->row[0]->column as $c){
					// Rellenar la primera fila, con las claves asociativas y sus valores
					$this->data[$querynum][0][strval($c["name"])] = strval($c);
				}

				// Ahora hay que procesar el resto de filas, si las hay
				for($i = 1;$i < $ob->row->count(); $i++){
					$j = 0;
					foreach(array_keys($this->data[$querynum][0]) as $k){
						$this->data[$querynum][$i][$k] = strval($ob->row[$i]->column[$j++]);
					}
				}
			}
		}else{
			// Si la respuesta del servidor no ha sido "success", extraemos el texto del error
			// <?xml version="1.0"? ><result state="failure"><error>Texto del error</error></result>
			$this->real_errnum = -1;
			$this->real_error = utf8_encode(strval($ob->error));
			return false;
		}
		
		// Rellenar el número de filas devueltas por la consulta (el tamaño de la primera dimensión del array)
		$this->afrows[$querynum] = count($this->data[$querynum]);
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
		return false;
	}

	// devuelve el número de filas afectadas
	function numrows($querynum=null) {
		$this->clear();
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->afrows[$querynum];
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
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return(($this->row_count[$querynum] >= count($this->data[$querynum]))?false:$this->rowtoutf8($this->data[$querynum][$this->row_count[$querynum]++]));
	}

	// devuelve de la primera fila el valor del campo deseado o el primero si se omite
	function field($field="",$querynum=null) {
		$this->clear();
		if (!$querynum) $querynum="%".$this->lastquerynum;
		$this->lastrow=$this->rowtoutf8($this->row($querynum));
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
		return false;
	}

	// devuelve el último mensaje de error producido en el servidor
	function error() {
		return $this->real_error;
	}
	
}
