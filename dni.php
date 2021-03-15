<?php
	
	// funciones para comprobación del DNI por mr.xkr

	// arregla del DNI, si no es un DNI, devuelve null, si es incorrecto, false
	// en caso de ser correcto, devuelve DNI y LETRA, sin puntuaciones
	function arreglarDNI($cif) {
		$cif=str_replace("-","",str_replace(".","",str_replace(" ","",trim(strtoupper($cif)))));
		if (strlen($cif)!=9) return null;
		$letra=substr($cif,-1,1);
		$dni=substr($cif,0,8);
		if (!is_numeric($dni)) return false;
		return $dni.$letra;
	}

	// obtiene la letra de un DNI
	// mismas salida de error que arreglarDNI, o LETRA calculada si correcto
	function letraDNI($cif) {
		if (!$info=arreglarDNI($cif)) return $info;
		$dni=substr($info,0,8);
		$letra=substr($info,8,1);
		$letras="TRWAGMYFPDXBNJZSQVHLCKE";
		return substr($letras,(intval($dni)%23),1);
	}

	// verifica la letra de un DNI
	// mismas salida de error que arreglarDNI, o TRUE si correcto
	function verificarDNI($cif) {
		if (!$info=arreglarDNI($cif)) return $info;
		$dni=substr($info,0,8);
		$letra=substr($info,8,1);
		if (!$info=letraDNI($cif)) return $info;
		return ($letra==$info?true:false);
	}
