<?php

/**
 *
 * View.
 * Objeto vista que tiene métodos para generar vistas HTML generales (Español).
 *
 * @author Pablo Rodríguez Rey
 * @since 2009-03-17
 * @copyright (c)2009 Pablo Rodríguez Rey
 * @version 0.1
 * @link http://mr.xkr.es/
 *
 */	
class View {
	
	protected $kernel;
	protected $base;
	protected $skin;
	protected $media;
	
	/**
	 * Constructor.
	 * Se puede especificar una ruta base de la que partirán las carpetas de medios.
	 *
	 * @param string (opcional) Ruta base.
	 */
	function __construct($kernel, $base="", $skin="") {
		$this->kernel=$kernel;
		$this->base=$base;
		$this->skin=$skin;
		$this->media();
	}
	
	/**
	 * Selección de medio.
	 * Seleccionar un medio o, si no se especifica, se detecta automáticamente.
	 *
	 * @param String (Opcional) Medio
	 */
	function media($newMedia=null) {
		if ($newMedia) {
			$this->media=$newMedia;
		} else {
			// comprobación de medio de representación
			$agent=(isset($_SERVER["HTTP_USER_AGENT"])?$_SERVER["HTTP_USER_AGENT"]:"");
			// por defecto el medio es PC
			$this->media="pc";
			// HTC P3300/1.35.621.1 Mozilla/4.0 Profile/MIDP-2.0 Configuration/CLDC-1.1 (compatible; MSIE 4.01; Windows CE; PPC; 240x320)
			if (strpos($agent,"PPC;")) $this->media="pda";
			// BlackBerry8800/4.2.1 Profile/MIDP-2.0 Configuration/CLDC-1.1 VendorID/129
			if (strpos(".".$agent,"BlackBerry")) $this->media="bb";
			// Opera/9.50 (J2ME/MIDP; Opera Mini/4.0.9800/230; U; es)
			if (strpos($agent,"Opera Mini")) $this->media="mobile";
			// Android
			if (strpos($agent,"Android")) $this->media="android";
		}
	}
	
	/**
	 * Propiedades consultables directamente.
	 * Devuelve una propiedad de la vista consultable de forma directa.
	 *
	 * @param mixed Propiedad a consultar
	 */
	function __get($k) {
		switch ($k) {
		case "base": return $this->base;
		}
	}
	
	/**
	 * Propiedades modificables directamente.
	 * Establece una propiedad de la vista modificable de forma directa.
	 *
	 * @param mixed Propiedad a modificar
	 * @param mixed Valor de la propiedad a modificar
	 */
	function __set($k,$v) {
		switch ($k) {
		case "base": $this->base=$v; break;
		}
	}
	
	// redirección HTTP
	function redir($location=null) {
		header("Location: ".($location?$location:x::base()));
		exit;
	}
	
	/**
	 * Generar campos ocultos.
	 * Genera automáticamente campos INPUT type="hidden"
	 * con los valores especificados en un array asociativo.
	 *
	 * @param array Lista de nombres y valores asociados que se generarán.
	 */
	function hiddenFields($fields) {
		foreach ($fields as $name=>$value)
			echo '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars($value).'" />'."\n";
	}
	
	/**
	 * afecha.
	 * Obtener cadena que muestra el tiempo transcurrido desde la fecha indicada hasta ahora.
	 *
	 * @param String Fecha en formato SQL (YYYY-MM-DD HH:MM:SS) que se comparará con la actual
	 *               o también puede indicarse los segundos transcurridos desde el momento actual.
	 * @param Boolean (opcional) TRUE si se desea en formato HTML o FALSE si se desea sólo texto.
	 * @return String Cadena que muestra el tiempo transcurrido en formato legible.
	 */
	function afecha($fecha,$html=true) {
		// comprobar si en vez de una fecha, nos pasan los segundos transcurridos y,
		// si es así, convertir al formato de fecha
		if (!strpos($fecha," ")) {
			$fecha=date("Y-m-d H:i:s",(time()-$fecha));
		}
		// obtener la fecha y la hora por separado
		$f=explode(" ",$fecha); $d=$f[0]; $h=substr($f[1],0,5);
		// si es de hoy...
		if ($d==date("Y-m-d")) {
			$t=", hoy";
			$ds=explode("-",$f[0]);
			$hs=explode(":",$f[1]);
			$a=time()-mktime($hs[0],$hs[1],$hs[2],$ds[1],$ds[2],$ds[0]);
			if ($a<60) $t=", <b>ahora</b>";
			if ($a>=60 && $a<3600) $t=" (hace <b>".floor($a/60)."</b> minuto".(floor($a/60)!=1?"s":"").")";
			if ($a>=3600) $t=" (hace <b>".floor($a/3600)."</b> hora".(floor($a/3600)!=1?"s":"").")";
		}
		// si es de ayer...
		if (!$t && $d==date("Y-m-d",time()-86400)) $t=" (<b>ayer</b>)";
		// si no es ni de ayer ni de hoy...
		if (!$t) $t=" el <b>".substr($d,8,2)."/".substr($d,5,2)."/".substr($d,0,4)."</b>";
		// formar cadena final
		$t="a las <b>".$h."</b>".$t;
		// devolver HTML o texto plano
		return($html?$t:strip_tags($t));
	}

	/**
	 * isie.
	 * Comprueba si el navegador activo es Internet Explorer.
	 *
	 * @return Boolean TRUE si es Internet Explorer, FALSE en caso contrario.
	 */
	function isie() {
		return (strpos($_SERVER["HTTP_USER_AGENT"],"MSIE")>0?true:false);
	}

	/**
	 * varName.
	 * Devuelve el nombre de una variable en PHP.
	 *
	 * @return String Cadena con el nombre de la variable.
	 */
  function var_name(&$var, $scope=false, $prefix='unique', $suffix='value') {
		$vals=($scope?$scope:$GLOBALS);
		$old=$var;
		$var=$new=$prefix.rand().$suffix;
		$vname=FALSE;
		foreach ($vals as $key=>$val)
			if($val===$new) $vname=$key;
		$var=$old;
		return $vname;
	}
	
	/**
	 * adump.
	 * Vuelca el contenido de una variable PHP en HTML.
	 */
	function adump(&$v, $level=0) {
		if (!$level) echo "<div style='font-family:FiraSans,Arial;font-size:11px;line-height:12px;color:#333;background:#EEE;'><b>".$this->var_name($v)."</b> ";
		if (is_array($v)) {
			echo "{<br>";
			foreach ($v as $i=>$nv) {
				echo "<span style='color:#23A;padding-left:".(20*($level+1))."px;'>".$i."</span>".(is_array($nv)?" ":"=");
				$this->adump($nv,$level+1);
			}
			echo "<span style='padding-left:".(20*$level)."px;'></span>}<br>";
		} else {
			echo "<span style='color:#820;'>".$v."</span><br>";
		}
		if (!$level) echo "</div>";
	}

	// render calendar
	function calendar($o) {
		
		$desde=$o["from"];
		$hasta=$o["to"];
		
		$meses=Array(
			1=>"Enero",
			2=>"Febrero",
			3=>"Marzo",
			4=>"Abril",
			5=>"Mayo",
			6=>"Junio",
			7=>"Julio",
			8=>"Agosto",
			9=>"Septiembre",
			10=>"Octubre",
			11=>"Noviembre",
			12=>"Diciembre",
		);
		
		$stylei=Array();
		if ($o["style"])
			foreach ($o["style"] as $i=>$v)
				foreach ($v["dias"] as $d)
					$stylei[date("Ymd",$d)]=array_merge($v,Array("title"=>$i));

		$enlazari=Array();
		if ($o["links"])
			foreach ($o["links"] as $i=>$v)
				foreach ($v as $d)
					$enlazari[date("Ymd",$d)]=$i;
	
		// dibujar celdas vacías para el día inicial
		?><table class='xcalendar'>
		<thead><tr><th>Lu</th><th>Ma</th><th>Mi</th><th>Ju</th><th>Vi</th><th>Sa</th><th>Do</th></thead>
		<tbody><tr><?php
		// inicializar
		$actual=$desde;
		$weekday=((date("w",$actual)+6)%7);
		$ymd=date("Ymd",$actual);
		$month=0;
		// máximo dibujar un año
		for ($i=0;$i<366;$i++) {
			if ($i && !$weekday) echo "</tr>\n<tr>";
			$newmonth=date("n",$actual);
			if ($month!=$newmonth) {
				if ($i && $weekday)
					for ($i=$weekday;$i<7;$i++) {
						echo "<td></td>";
					}
				if ($i) echo "</tr>\n<tr>";
				echo "<th colspan='7'>".$meses[$newmonth]." ".date("Y",$actual)."</th>";
				echo "</tr>\n<tr>";
				for ($i=0;$i<$weekday;$i++) {
					echo "<td></td>";
				}
			}
			$month=$newmonth;
			$d=date("j",$actual);
			echo "<td"
				.($stylei[$ymd]["style"]?" style='".$stylei[$ymd]["style"]."'":"")
				.($stylei[$ymd]["title"]?" title='".$stylei[$ymd]["title"]."'":"")
				.">"
				.($enlazari[$ymd]?"<a href=\"".$enlazari[$ymd]."\">":"<span>")
				.$d
				.($enlazari[$ymd]?"</a>":"</span>")
				."</td>";
			if ($ymd==date("Ymd",$hasta)) break;
			$actual=mktime(0,0,0,date("m",$actual),date("d",$actual)+1,date("Y",$actual));
			$weekday=((date("w",$actual)+6)%7);
			$ymd=date("Ymd",$actual);
		}
		for ($i=$weekday;$i<6;$i++) {
			echo "<td></td>";
		}
		?></tr></tbody></table><?php
	}

	// bytes to string format
	static function bytesToString($bytes) {
		if ($bytes >= 1099511627776) return number_format(round($bytes/10995116277.76)/100,2,",",".")." TB";
		if ($bytes >= 1073741824) return number_format(round($bytes/10737418.24)/100,2,",",".")." GB";
		if ($bytes >= 1048576) return number_format(round($bytes/10485.76)/100,2,",",".")." MB";
		if ($bytes >= 1024) return number_format(round($bytes/10.24)/100,2,",",".")." KB";
		return (strlen($bytes)?($bytes?number_format($bytes,0,",","."):"0")." bytes":"");
	}

	// returns formatted time
	static function secToString($t,$o=Array()) {
		$tx=round($t-intval($t), 3);
		$ts=floor($t%60);
		$tm=floor($t/60)%60;
		$th=floor($t/3600)%24;
		$td=floor($t/86400);
		return ($o["large"]
			?""
				.($td?$td." día".($td == 1?"":"s").", ":"")
				.($th?$th." hora".($th == 1?"":"s").", ":"")
				.($tm?$tm." minuto".($tm == 1?"":"s")." y ":"")
				.$ts.substr($tx, 1)." segundo".($ts == 1?"":"s")
			:""
				.($td?$td."d ":"")
				.($o["nohour"]?"":($th?$th."h ".($tm && $tm<10?"0":""):""))
				.($o["nomin"]?"":($tm?$tm."m ":""))
				.($o["nosec"]?"":($ts && $ts<10?"0":"").($ts || $tx?$ts.substr($tx, 1)."s":""))
		);
	}

	// returns formatted time as XX:XX:XX
	static function secToTime($t) {
		$ts=floor($t%60);
		$tm=floor($t/60)%60;
		$th=floor($t/3600);
		//$td=floor($t/86400);
		return str_pad($th, 2, "0", STR_PAD_LEFT)
			.":".str_pad($tm, 2, "0", STR_PAD_LEFT)
			.":".str_pad($ts, 2, "0", STR_PAD_LEFT)
		;
	}

	// formatear un número flotante como X.XXX,DD
	static function spf($n, $flo=2, $sflo=",", $sdec=".") {
		return number_format($n, $flo, $sflo, $sdec);
	}

	// formatear un número entero como X.XXX
	static function spd($n, $sflo=",", $sdec=".") {
		return number_format($n, 0, $sflo, $sdec);
	}

	// formatear un número flotante como X.XXX,DD, o bien, X.XXX si no tiene decimales
	static function spn($n, $flo=2, $sflo=",", $sdec=".") {
		return (intval($n) == doubleval($n)
			?self::spd($n, $sflo, $sdec)
			:self::spf($n, $flo, $sflo, $sdec)
		);
	}

	// muestra una tabla de resultados de un array
	function dump($a) {
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
		<p>
		<table class='db_query_results'>
		<thead><?php
			$first=true;
			foreach ($a as $i=>$row) {
				if ($first) {
					echo "<tr>";
					foreach ($row as $field=>$value)
						echo "<th>".$field."</th>";
					?></tr>
					</thead>
					</tbody><?php
					$first=false;
				}
				echo "<tr>";
				foreach ($row as $value) {
					switch (gettype($value)) {
					case "string":  $s="color:#00F;text-align:left;"; break;
					case "integer": $s="color:#F00;text-align:right;"; break;
					case "double":  $s="color:#F80;text-align:right;"; break;
					case "array":   $s="color:#080;text-align:center;"; break;
					case "object":  $s="color:#06D;text-align:center;"; break;
					default:        $s="color:#444;text-align:left;"; break;
					}
					echo "<td style='".$s.";' title='".gettype($value)."'>".$value."</td>";
				}
				echo "</tr>";
			}
		?></tbody>
		</table>
		</p><?php
	}
	
	// mejora los datos obtenidos por idir
	function dir($p,$o=Array()) {
		$files=$this->kernel->dir($p);
		foreach ($files as $i=>$f) {
			if (isset($f["size"]))
				$files[$i]["size_text"]=$this->bytesToString($f["size"]);
			if ($f["dir"]) $ico="images/ext16/folder.png";
			else {
				$x=strrpos($f["name"],".");
				if ($x!==false) $ico="images/ext16/".substr($f["name"],$x+1).".png";
				if ($x===false || !file_exists($ico)) $ico="images/ext16/file.png";
			}
			$files[$i]["ico"]=$ico;
		}
		return $files;
	}
	
}
