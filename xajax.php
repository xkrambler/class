<?php

/*

	VeryTinyAJAX3 0.3a
	Manage simple AJAX responses and webservice calls.
	License: GPL (http://www.gnu.org/licenses/gpl.txt)
	Authors: Pablo Rodríguez Rey (mr -at- xkr -dot- es)
	         https://mr.xkr.es/
	         Javier Gil Motos (cucaracha -at- inertinc -dot- org)
	         http://cucaracha.inertinc.org/

	Thanks to Cucaracha for motivate me in AJAX web development and give me
	the basic example wich started the implementation of this library.

	Globals:

		$ajax   If not set, sets it to $_REQUEST["ajax"] or null.
		$adata  Parsed JSON from $_REQUEST["data"].

	Functions:

		aget($data)
		Parse JSON, removing magic_quotes if needed.

		ajax($data)
		Output $data as application/json UTF-8 encoded.

		ws($url, [$fields], [$options])
		WebService caller.
			Parameters:
				$url      (required) URL to be pased to CURL.
				$fields   (optional) POST fields to be sent, as string or array. Can be nulled.
				$options  (optional) Array of CURL options.
			Returns:
				false
					If CURL library not enabled.
				string
					Retrieved content.
				array
					With curl_getinfo plus "content" with the retrieved content if CURLINFO_HEADER_OUT specified.

	Examples:

		// returns time if requested
		if ($ajax == "time") ajax(["time"=>time(), "ok"=>true]);

		// proxifies URL request
		echo ws("http://127.0.0.1/index.html");

*/

// WebService request
function ws($url, $fields=null, $options=[]) {
	if (!function_exists("curl_init")) return false;
	$ch=curl_init();
	$o=[
		CURLOPT_URL=>$url,
		CURLOPT_RETURNTRANSFER=>1,
		//CURLOPT_FRESH_CONNECT=>1,
		//CURLOPT_FORBID_REUSE=>1,
	];
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

// convert ajax data from JSON
function aget($data=null) {
	if ($data === "") return "";
	return json_decode((get_magic_quotes_gpc()?stripslashes($data):$data), true); // siempre es UTF-8
}

// output AJAX data as JSON
function ajax($data) {
	header("Content-Type: application/json; charset=UTF-8");
	echo json_encode($data);
	exit;
}

// generate $ajax and $adata global variables
if (!isset($ajax)) $ajax=(isset($_REQUEST["ajax"])?$_REQUEST["ajax"]:null);
if ($ajax && isset($_REQUEST["data"])) $adata=aget($_REQUEST["data"]);
