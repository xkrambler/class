<?php

// funciones para comprobación del DNI por mr.xkr 0.2.2
define("DNI_LETRAS", "TRWAGMYFPDXBNJZSQVHLCKE");

// comprobar si es un DNI (válidos con o sin letra)
function isDNI($dni) {
	if (!$info=arreglarDNI($dni)) return false;
	$info=completarDNI($info);
	$c=substr($info, 8, 1);
	return (is_numeric((string)substr($dni, 0, 8)) && is_string($c) && strpos(DNI_LETRAS, $c) !== false);
}

// arregla del DNI, suprime espacios, guiones y puntos para dejar el DNI limpio
function arreglarDNI($dni) {
	return str_replace(array(" ", "-", ".-"), "", trim(strtoupper((string)$dni)));
}

// completar el DNI con la letra, si faltase
function completarDNI($dni) {
	$dni=arreglarDNI($dni);
	if ((strlen($dni) == 8) && is_numeric($dni)) $dni.=letraDNI($dni);
	return $dni;
}

// obtiene la letra de un DNI, false en caso contrario
function letraDNI($dni) {
	if (!$info=arreglarDNI($dni)) return false;
	return substr(DNI_LETRAS, (intval(substr($info, 0, 8))%23), 1);
}

// verifica la letra de un DNI
function verificarDNI($dni) {
	if (!$info=arreglarDNI($dni)) return false;
	$letra=substr($info, 8, 1);
	if (!$info=letraDNI($dni)) return false;
	return ($letra == $info?true:false);
}
