<?php

// UTF-8 compatibility helpers
if (!function_exists("utf8_encode_compat")) {
	function utf8_encode_compat($s) {
		$s=(string)$s;
		if (function_exists("mb_convert_encoding")) return mb_convert_encoding((string)($s ?? ""), "UTF-8", "ISO-8859-1");
		if (function_exists("iconv")) return iconv("ISO-8859-1", "UTF-8", (string)($s ?? ""));
		return $s;
	}
	function utf8_decode_compat($s) {
		$s=(string)$s;
		if (function_exists("mb_convert_encoding")) return mb_convert_encoding((string)($s ?? ""), "ISO-8859-1", "UTF-8");
		if (function_exists("iconv")) return iconv("UTF-8", "ISO-8859-1", (string)($s ?? ""));
		return $s;
	}
}

// UTF-8 to lower case converter
function strtolower_utf8($s) {
	$convert_to=[
		"a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u",
		"v","w","x","y","z","à","á","â","ã","ä","å","æ","ç","è","é","ê","ë","ì","í","î","ï",
		"ð","ñ","ò","ó","ô","õ","ö","ø","ù","ú","û","ü","ý","а","б","в","г","д","е","ё","ж",
		"з","и","й","к","л","м","н","о","п","р","с","т","у","ф","х","ц","ч","ш","щ","ъ","ы",
		"ь","э","ю","я"
	];
	$convert_from=[
		"A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U",
		"V","W","X","Y","Z","À","Á","Â","Ã","Ä","Å","Æ","Ç","È","É","Ê","Ë","Ì","Í","Î","Ï",
		"Ð","Ñ","Ò","Ó","Ô","Õ","Ö","Ø","Ù","Ú","Û","Ü","Ý","А","Б","В","Г","Д","Е","Ё","Ж",
		"З","И","Й","К","Л","М","Н","О","П","Р","С","Т","У","Ф","Х","Ц","Ч","Ш","Щ","Ъ","Ъ",
		"Ь","Э","Ю","Я"
	];
	return str_replace($convert_from, $convert_to, $s);
}
