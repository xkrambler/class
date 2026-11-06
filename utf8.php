<?php

// UTF-8 compatibility helpers
if (!function_exists("utf8_encode_compat")) {
	function utf8_encode_compat($s) {
		$s=(string)$s;
		if (function_exists("mb_convert_encoding")) return mb_convert_encoding($s, "UTF-8", "ISO-8859-1");
		if (function_exists("iconv")) return iconv("ISO-8859-1", "UTF-8", $s);
		return $s;
	}
	function utf8_decode_compat($s) {
		$s=(string)$s;
		if (function_exists("mb_convert_encoding")) return mb_convert_encoding($s, "ISO-8859-1", "UTF-8");
		if (function_exists("iconv")) return iconv("UTF-8", "ISO-8859-1", $s);
		return $s;
	}
}
