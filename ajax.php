<?php

/*

	Título..: VeryTinyAJAX2 0.2c, Wrapper PHP simple a funciones XMLHTTP para AJAX.
	Licencia: GPL (http://www.gnu.org/licenses/gpl.txt)
	Autores.: Pablo Rodríguez Rey (mr -en- xkr -punto- es)
	          https://mr.xkr.es/
	          Javier Gil Motos (cucaracha -en- inertinc -punto- org)
	          http://cucaracha.inertinc.org/

	Agradecimientos a Cucaracha, por darme interés en el desarrollo de webs usando
	AJAX y proveerme del ejemplo básico con el que está desarrollada esta librería.

	Genera las variables globales siguientes (si no generadas previamente):
		$me    - PHP_SELF
		$ajax  - Parámetro ajax recibido por GET o POST.
		$adata - Parámetro data parseado desde JSON.

	Funciones no auxiliares:
		ws($url, $fields=array(), $options=array()) {
			Realiza una petición a un webservice en una URL mediante POST usando los parámetros establecidos
			en $fields con las opciones indicadas en $options.
		ajax($values)
			Envía los datos pasados como parámetro por AJAX y termina la ejecución, cambiando el charset a UTF-8.
			De no recibir parámetros, se asume que los datos han sido previamente preparados con llamadas a aput.
		ajson($values)
			Idéntica a json_encode($values, true), pero también permite charsets diferentes a UTF-8.
		aget($value)
			Devuelve un parámetro parseado como JSON, eliminando las magic_quotes si estuviesen habilitadas.

	Ejemplo de uso:
		if ($ajax == "time") ajax(array("time"=>time(), "ok"=>true));

*/

// convertir datos de un parámetro enviado con la librería AJAX
function aget($data=null) {
	if ($data === "") return "";
	$json=(doubleval(phpversion()) < 5.4 && get_magic_quotes_gpc()?stripslashes($data):$data);
	return json_decode($json, true); // siempre es UTF-8
}

// función interna: comprueba si un array no es lineal
function aputio($a) {
	$i=0;
	foreach ($a as $n=>$v)
		if ($n !== $i++)
			return true;
	return false;
}

// cambiar quotes, \n y \r para devolver cadenas válidas JSON
function qcl2json($qcl) {
	return str_replace("<","\\x3C",str_replace(">","\\x3E",str_replace('"','\"',str_replace("\n",'\n',str_replace("\r",'\r',str_replace('\\','\\\\',$qcl))))));
}

// pasar variable en formato json
function ajson($av, $level=0, $utf8=false) {
	if ($utf8) return json_encode($av);
	if (($av===null) && !$level) return "null";
	if (!is_array($av)) return (gettype($av) == "integer"?$av:'"'.qcl2json($av).'"');
	$isobj=aputio($av);
	$i=0;
	if (!$level) $e=($isobj?"{":"["); else $e="";
	foreach ($av as $n=>$v) {
		if ($i) $e.=",";
		if ($isobj) $e.=(is_numeric($n) && !is_string($n)?$n:"\"".qcl2json($n)."\"").":";
		if (!is_array($v)) {
			if (is_bool($v)) $e.=($v?"true":"false");
			else if (is_int($v)||is_double($v)) $e.=$v;
			else if ($v==NULL) $e.='""';
			else $e.='"'.qcl2json($v).'"';
		} else {
			$e.=(count($v)
				?(aputio($v)
					?"{".ajson($v, $level+1)."}"
					:"[".ajson($v, $level+1)."]")
				:"{}");
		}
		$i++;
	}
	if (!$level) $e.=($isobj?"}":"]");
	return $e;
}

// petición AJAX de WebService
function ajaxws($action, $data, $url=null) {
	$p="";
	if ($url===null) {
		$parseurl=parse_url($_SERVER["REQUEST_URI"]);
		$url="http".($_SERVER["HTTPS"]?"s":"")."://".$_SERVER["SERVER_NAME"].$parseurl["path"];
		foreach ($_GET as $k=>$v)
			if ($k!="ajax")
				$p=($p?"&":"?").$k."=".urlencode($v);
	}
	$url_ajax=$url.$p.(strpos($url.$p,"?")===false?"?":"&")."ajax=".urlencode($action);
	$r=ws($url_ajax, array("data"=>json_encode($data)));
	return json_decode($r, true);
}

// petición WebService
function ws($url, $fields=null, $options=array()) {
	// preparar URL si es relativa a la actual
	if (strpos($url, "://") === false)
		$url="http".($_SERVER["HTTPS"]?"s":"")."://".$_SERVER["SERVER_NAME"].(substr($url, 0, 1)=="/"?"":"/").$url;
	// acciones CURL
	if (!function_exists("curl_init")) return false;
	$ch=curl_init();
	$o=array(
		CURLOPT_URL=>$url,
		CURLOPT_RETURNTRANSFER=>1,
		//CURLOPT_FRESH_CONNECT=>1,
		//CURLOPT_FORBID_REUSE=>1,
	);
	if ($fields != null) {
		$o[CURLOPT_POST]=1;
		$o[CURLOPT_POSTFIELDS]=(is_array($fields)?http_build_query($fields):$fields);
	}
	foreach ($options as $n=>$v)
		$o[$n]=$v;
	curl_setopt_array($ch, $o);
	$r=curl_exec($ch);
	if ($options[CURLINFO_HEADER_OUT]) {
		$rinfo=curl_getinfo($ch);
		$rinfo["content"]=$r;
	}
	curl_close($ch);
	return ($rinfo?$rinfo:$r);
}

// envío de datos AJAX
function ajax($data) {
	header("Content-Type: application/json; charset=UTF-8");
	echo (is_string($data)?'"'.qcl2json($data).'"':json_encode($data));
	exit;
}

// generar variables útiles
if (!isset($ajax)) $ajax=(isset($_REQUEST["ajax"])?$_REQUEST["ajax"]:null);
if (!isset($me)) $me=(isset($_SERVER["PHP_SELF"])?$_SERVER["PHP_SELF"]:null);

// comprobar si se ha usado el método genérico de paso de datos AJAX,
// y de ser así, crear variable $adata con todos los datos enviados.
if ($ajax && isset($_REQUEST["data"])) $adata=aget($_REQUEST["data"]);
