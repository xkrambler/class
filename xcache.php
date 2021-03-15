<?php
	
	/**
	 * xCache, acceso r�pido a funcionalidad de Cach� de Navegador
	 * (cc) 2011 por mr.xkr at http://mr.xkr.es/
	 * 
	 * Ejemplo r�pido de uso:
	 * 
	 * $xcache=new xCache(Array(
	 * 	"expiration"=>3600, // tiempo (en segundos relativos) de expiraci�n futura
	 * 	"content-type"=>"text/html", // content-type de lo que se va a generar
	 * 	"path"=>"data/cache/", // establece ruta base de la cach�
	 * 	"file"=>"html/prueba_de_ejemplo.html", // esta l�nea verifica la cach�
	 * ));
	 * //$xcache->fileDelete("html/prueba_de_ejemplo.html");
	 * $xcache->fileSave("<!doctype><html><body>Ejemplo!</body></html>");
	 *
	 * Explicaci�n de funcionamiento: La cach� expirar� en 3600 segundos,
	 * en los que el navegador volver� a solicitar el elemento sin cachear,
	 * antes de que expire, el objeto no ser� solicitado, y de ser solicitado
	 * (por ejemplo, pulsando F5 por el usuario), no se enviar�n los datos,
	 * si no un c�digo HTTP 304 Not modified hasta que el fichero en la cach�
	 * se modifique, excepto que el usuario fuerce la actualizaci�n con CTRL+F5,
	 * en la que se enviar� sin contar con la cach�.
	 *
	 * Para cachear p�ginas que se generen, se puede usar filePage,
	 * en el ejemplo se cambiar�a la linea de $xcache->fileSave por:
	 *
	 * echo "<!doctype><html><body>Ejemplo!</body></html>";
	 * $xcache->filePage();
	 *
	 */
	class xCache {
		
		public $path="";
		protected $lastfile=null;
		protected $mask=0770;
		
		// constructor, con abreviaciones b�sicas
		function __construct($o=Array()) {
			if ($o["nocache"]) $this->noCache();
			if ($o["expiration"]) $this->expiration($o["expiration"]);
			if ($o["modified"]) $this->modified($o["modified"]);
			if ($o["content-type"]) $this->setContentType($o["content-type"]);
			if ($o["path"]) $this->filePath($o["path"]);
			if ($o["file"]) $this->fileCache($o["file"]);
			ob_start();
		}
		
		// cambia la m�scara por defecto
		function setMask($mask) { $this->mask=$mask; }
		
		// cambiar content-type del fichero
		function setContentType($contentType) { header("Content-type: ".$contentType); }
		
		// suprime la cach� del navegador de esta p�gina
		function noCache() {
			Header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			Header("Cache-Control: no-cache, must-revalidate");
			Header("Pragma: no-cache"); 
		}
		
		// establece un tiempo de expiraci�n futuro
		function expiration($seconds=3600) {
			header("Expires: ".date(DATE_RFC822,($seconds?(is_string($seconds)?strtotime($seconds):time()+$seconds):time())));
			header("Cache-Control: private, max-age=".$seconds);
		}
		
		// control de modificaci�n de la p�gina, si no est� modificada, se manda un not modified
		function modified($lastModifiedTime=null) {
			if ($lastModifiedTime) {
				if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedTime) {
					header(
						php_sapi_name()=='CGI'
						?"Status: 304 Not Modified"
						:"HTTP/1.1 304 Not Modified"
					);
					exit;
				} else {
					header('Last-Modified: '.date(DATE_RFC822,$lastModifiedTime));
				}
			} else {
				header('Last-Modified: '.date(DATE_RFC822,time()));
			}
		}
		
		// establece una ruta donde se guardar� una cach� de ficheros f�sicos
		// debe terminar en /
		function filePath($path="cache/") {
			if (substr($path,-1,1)!="/") return false;
			$this->path=$path;
			return (@mkdir($path,$this->mask,true));
		}
		
		// actualiza un fichero de cach�
		function fileUpdate($file=null,$data=null) {
			// si no se especifica segundo par�metro, asumir que el fichero ya hab�a sido
			// especificado en otros m�todos, y el primer par�metro son los datos
			if ($data===null) {
				$data=$file;
				$file=$this->lastfile;
			}
			if ($file===null) $file=$this->lastfile;
			// si aun as� no hay fichero, fallar
			if (!$file) return false;
			// comprobar si el nombre del fichero incluye rutas, y crear rutas de acceso
			// en caso de no existir
			$subpath=dirname($file);
			if ($subpath)
				if (!file_exists($this->path.$subpath))
					@mkdir($this->path.$subpath,$this->mask,true);
			// guarda el fichero en la cach�
			$ok=(@file_put_contents($this->path.$file,$data));
			// devolver fecha de modificaci�n de ahora mismo
			$this->modified(time());
			// devuelve el resultado
			return $ok;
		}
		
		// guarda un fichero de cach� y lo saca por la pantalla
		function fileSave($file=null,$data=null) {
			if ($ok=$this->fileUpdate($file,$data))
				echo ($data===null?$file:$data);
			return $ok;
		}

		// guarda un fichero de cach� generado con el contenido de la p�gina
		function filePage($file=null) {
			return $this->fileUpdate($file,ob_get_contents());
		}
		
		// suprime un fichero de cach�
		function fileDelete($file) {
			return (unlink($this->path.$file));
		}
		
		// obtiene la ruta completa del �ltimo fichero de cach� buscado
		// puede ser interesante para regenerar el fichero de cach�
		function fileLast() {
			if (!$this->lastfile) return false;
			return $this->path.$this->lastfile;
		}
		
		// usar cach� de fichero comprobando la fecha de modificaci�n
		function fileCache($file) {
			// actualizar lastfile
			$this->lastfile=$file;
			// si se solicita que no haya cach�, regenerar
			if ($_SERVER["HTTP_CACHE_CONTROL"]=="no-cache" || $_SERVER["HTTP_PRAGMA"]=="no-cache") return false;
			// si no hay fichero de cach�, sale de la funci�n para permitir guardar uno
			if (!file_exists($this->path.$file)) return false;
			// buscar fecha de modificaci�n
			$mtime=filemtime($this->path.$file);
			// cotejar modificaci�n con la especificada por el navegador
			$this->modified($mtime);
			// si ha sido modificado, devolver fichero
			readfile($this->path.$file);
			exit;
		}
		
	}
