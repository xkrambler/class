<?php

	/*
		Soporte básico multidioma. DEPRECATED.
	*/

	// configuración automática
	if (!$xmultilang) {
		require_once("db.php");
		$xmultilang=Array(
			"db"=>$db,
		);
	}

	// obtener idiomas
	function xmultilangGetIdiomas() {
		global $xmultilang;
		$db=$xmultilang["db"];
		if (!$r=$db->query("SELECT * FROM idiomas ORDER BY orden,codigo")) $db->err();
		return $r->aquery();
	}

	// obtener idioma
	function xmultilangGet() {
		global $xmultilang;
		return $xmultilang["current_lang"];
	}

	// establecer idioma
	function xmultilangSet($idioma) {
		global $xmultilang;
		$_SESSION["xmultilang_current_lang"]=$xmultilang["current_lang"]=$idioma;
	}

	// text translator
	function _T($t) {
		global $xmultilang;
		if ($xmultilang["default_lang"]==$xmultilang["current_lang"]) return $t;
		$db=$xmultilang["db"];
		$idioma=$xmultilang["current_lang"];
		$original=substr($t,0,255);
		if (!$r=$db->query("SELECT traduccion FROM idiomas_textos WHERE idioma='".$db->escape($idioma)."' AND BINARY original='".$db->escape($original)."'")) $db->err();
		if ($row=$r->row()) {
			return $row["traduccion"];
		} else {
			if (!$db->query($db->sqlinsert("idiomas_textos",Array("idioma"=>$idioma,"original"=>$original,"traduccion"=>$t)))) $db->err();
			return $t;
		}
	}

	// template translator
	function _C($c) {
		global $xmultilang;
		$db=$xmultilang["db"];
		if (!$r=$db->query("SELECT plantilla FROM idiomas_plantillas WHERE idioma='".$db->escape($xmultilang["current_lang"])."' AND codigo='".$db->escape($c)."'")) $db->err();
		if ($row=$r->row()) {
			return $row["plantilla"];
		} else {
			return "[".$c."]";
		}
	}

	// por el momento, sólo está aceptado la selección por base de datos
	if ($xmultilang["db"]) {
		if (!($xmultilang["default_lang"]=$page["lang"])) die('xmultilang: $page["lang"] must be defined for setup default language!');
		$xmultilang["current_lang"]=$_SESSION["xmultilang_current_lang"];
		// conmutación automática de idioma dependiendo del idioma del navegador
		if (!$xmultilang["current_lang"]) {
			foreach (explode(";",$_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $l) {
				foreach (explode(",",$l) as $l) ;
				if (!$r=$xmultilang["db"]->query("SELECT * FROM idiomas WHERE codigo='".$l."'")) $xmultilang["db"]->err();
				if ($row=$xmultilang["db"]->row()) {
					$xmultilang["current_lang"]=$row["codigo"];
					break;
				}
			}
			if (!$xmultilang["current_lang"]) $xmultilang["current_lang"]=$xmultilang["default_lang"];
		}
	}

	// acciones AJAX globales
	switch ($ajax) {
	case "xmultilang.set":
		xmultilangSet($adata["lang"]);
		ajax(Array("ok"=>true, "lang"=>$adata["lang"]));

	}

	// set current language for current page
	$page["lang"]=$xmultilang["current_lang"];
