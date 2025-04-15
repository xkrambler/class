<?php

// if init not loaded, cancel startup
if (!class_exists("x")) return false;

// load page settings
$page=x::page();

// me+base
if (!isset($page["me"])) $page["me"]=x::me();
if (!isset($page["base"])) $page["base"]=x::base();

// restrictions
if (isset($page["shorttags"]) && ((bool)$page["shorttags"] != (bool)ini_get("short_open_tag"))) perror("short_open_tag = ".(x::page("shorttags")?"On":"Off")." required");

// classes/include CSS/JS
$_css=array();
$_js=array();
if ($_b=x::inc()) foreach ($_b as $_c) {
	if (strpos($_c, "/")) {
		$_f="";
	} else {
		$_f=__DIR__;
		$_c="/".$_c;
	}
	if (file_exists($_f.$_c.".css")) $_css[$_c.".css"]=false;
	if (file_exists($_f.$_c.".js")) $_js[$_c.".js"]=false;
}

// kernel autoloads
if (file_exists("kernel.css") && !isset($css["kernel.css"])) $_css["kernel.css"]=false;
if (file_exists("kernel.js") && !isset($js["kernel.js"])) $_js["kernel.js"]=false;

// page basefile and page autoloads
if (($_v=x::_server("SCRIPT_FILENAME")) && file_exists($_v) && substr($_v, -4, 4) == ".php") {
	$page["basefile"]=substr(basename($_v), 0, -4);
	if (file_exists($page["basefile"].".css") && !isset($css[$page["basefile"].".css"])) $_css[$page["basefile"].".css"]=false;
	if (file_exists($page["basefile"].".js") && !isset($js[$page["basefile"].".js"])) $_js[$page["basefile"].".js"]=false;
}

// crypt, if enabled
$_pc=false;
if ($page["crypt"]) {
	require_once(__DIR__."/crypt.php");
	$_pc=new $page["crypt"]["type"]($page["crypt"]);
	if (!$_pc->available()) $_pc->err();
}

// load CSS/JS: false=internal true=external 0=disabled
foreach ($_e=array("css", "js") as $_i) {
	$_b="";
	$_t="_".$_i;
	$$_t=$$_t+($$_i?$$_i:array());
	foreach ($$_t as $_n=>$_v)
		if ($_v !== 0)
			if (!$_v && substr($_n, -strlen($_i)-1) == ".".$_i)
				$_b.=($_b?"|":"").substr($_n, 0, -strlen($_i)-1);
	if ($_b) {
		if ($_pc && ($_b=$_pc->encrypt($_b)) === false) $_pc->err();
		$page[$_i]=(x::page("relative")
			?array(x::alink(array($_i=>$_b))=>false)
			:array(x::link([$_i=>$_b], "")=>false)
		);
	}
	foreach ($$_t as $_n=>$_v) if ($_v) $page[$_i][$_n]=$_v;
	unset($$_t);
}

// extra common data
if (!isset($data["me"]) && $page["me"]) $data["me"]=$page["me"];
if (!isset($data["base"]) && $page["base"]) $data["base"]=$page["base"];
if (!isset($data["uri"]) && ($_v=$_SERVER["REQUEST_URI"])) $data["uri"]=$_v;
if (!isset($data["server"]) && ($_v=x::server())) $data["server"]=$_v;

// ensure charset and document type by default
if ($_b=x::page("content-type")) {
	foreach ($_e=explode(";", $_b) as $_i) {
		$_i=trim($_i);
		if (strtolower(substr($_i, 0, 8)) === "charset=") $page["charset"]=strtoupper(substr($_i, 8));
	}
	header("Content-Type: ".$page["content-type"]);
}
$page["charset"]=x::charset();

// save page settings, this will ensure that settings are global
x::page($page);

// render HTML5 page head
echo "<!doctype html>\n";
echo "<html".(($_v=x::page("lang"))?' lang="'.$_v.'"':'').">\n";
echo "<head>\n";
if ($_v=x::page("content-type")) echo "\t".'<meta http-equiv="Content-Type" content="'.x::entities($_v).'" />'."\n";
foreach ($_b=array("description", "generator", "keywords", "viewport", "theme-color") as $_n) if ($_v=x::page($_n)) $page["meta"][$_n]=$_v;
if (is_array($page["meta"])) foreach ($page["meta"] as $_n=>$_v) if (is_string($_v)) echo "\t".'<meta name="'.x::entities($_n).'" content="'.x::entities($_v).'" />'."\n";
if (isset($page["title"]) || isset($title)) echo "\t".'<title>'.x::entities((isset($title)?(string)$title." - ":"").(isset($page["title"])?$page["title"]:"")).'</title>'."\n";
if ($_v=x::page("base")) echo "\t".'<base href="'.$_v.'" />'."\n";
if ($_b=x::page("favicon")) {
	echo "\t".'<link rel="icon" type="image/x-icon" href="'.x::entities($_b).'" />'."\n";
	echo "\t".'<link rel="shortcut icon" href="'.x::entities($_b).'" />'."\n";
	echo "\t".'<link rel="apple-touch-icon" href="'.x::entities(($_v=x::page("apple-touch-icon"))?$_v:$_b).'" />'."\n";
}
if ($data) echo "\t".'<script type="text/javascript">var data='.json_encode($data).';</script>'."\n";
if ($_b=x::page("js")) foreach ($_b as $_n=>$_v) echo "\t".'<script type="text/javascript" src="'.x::entities($_n).'"'.(is_array($_v) && $_v["async"]?" async":"").'></script>'."\n";
if ($_b=x::page("css")) foreach ($_b as $_n=>$_v) echo "\t".'<link rel="stylesheet" media="all" href="'.x::entities($_n).'"'.(is_string($_v)?' title="'.x::entities($_v).'"':'').' />'."\n";
if ($_v=x::page("head")) echo "\t".$_v."\n";
echo "\t".'<!-- <all your="base" are="belong/to.us" /> -->'."\n";
echo "</head>\n";
echo "<body".(($_v=x::page("body"))?" ".$_v:"").">\n";

// remove temporal variables
unset($_b);
unset($_c);
unset($_i);
unset($_e);
unset($_f);
unset($_n);
unset($_t);
unset($_v);
