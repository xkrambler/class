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
if ($_SERVER["SCRIPT_FILENAME"] && file_exists($_SERVER["SCRIPT_FILENAME"]) && substr($_SERVER["SCRIPT_FILENAME"], -4, 4) == ".php") {
	$page["basefile"]=substr(basename($_SERVER["SCRIPT_FILENAME"]), 0, -4);
	if (file_exists($page["basefile"].".css") && !isset($css[$page["basefile"].".css"])) $_css[$page["basefile"].".css"]=false;
	if (file_exists($page["basefile"].".js") && !isset($js[$page["basefile"].".js"])) $_js[$page["basefile"].".js"]=false;
}

// crypt, if enabled
if ($page["crypt"]) {
	require_once(__DIR__."/crypt.php");
	$_pc=new $page["crypt"]["type"]($page["crypt"]);
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
		if ($page["crypt"]) $_b=$_pc->encrypt($_b);
		$page[$_i]=($page["relative"]
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

// set charset and document type by default
if ($page["content-type"]) {
	foreach ($_e=explode(";", $page["content-type"]) as $_i) {
		$_i=trim($_i);
		if (strtolower(substr($_i, 0, 8)) === "charset=") $page["charset"]=strtoupper(substr($_i, 8));
	}
	header("Content-Type: ".$page["content-type"]);
}
if (!$page["charset"]) $page["charset"]="UTF-8";

// save page settings, this will ensure that settings are global
x::page($page);

// render HTML5 page head
echo "<!doctype html>\n";
echo "<html".($page["lang"]?' lang="'.$page["lang"].'"':'').">\n";
echo "<head>\n";
if ($page["content-type"]) echo "\t".'<meta http-equiv="Content-Type" content="'.x::entities($page["content-type"]).'" />'."\n";
foreach ($_b=array("description", "generator", "keywords", "viewport", "theme-color") as $_n) if ($_v=$page[$_n]) $page["metas"][$_n]=$_v;
if (is_array($page["metas"])) foreach ($page["metas"] as $_n=>$_v) if (is_string($_v)) echo "\t".'<meta name="'.x::entities($_n).'" content="'.x::entities($_v).'" />'."\n";
if ($page["title"] || $title) echo "\t".'<title>'.x::entities(($title?$title." - ".$page["title"]:$page["title"])).'</title>'."\n";
if ($page["base"]) echo "\t".'<base href="'.$page["base"].'" />'."\n";
if ($page["favicon"]) {
	echo "\t".'<link rel="icon" type="image/x-icon" href="'.x::entities($page["favicon"]).'" />'."\n";
	echo "\t".'<link rel="shortcut icon" href="'.x::entities($page["favicon"]).'" />'."\n";
}
if ($data) echo "\t".'<script type="text/javascript">var data='.json_encode($data).';</script>'."\n";
if ($page["js"]) foreach ($page["js"] as $_n=>$_v) echo "\t".'<script type="text/javascript" src="'.x::entities($_n).'"></script>'."\n";
if ($page["css"]) foreach ($page["css"] as $_n=>$_v) echo "\t".'<link rel="stylesheet" media="all" href="'.x::entities($_n).'"'.(is_string($_v)?' title="'.x::entities($_v).'"':'').' />'."\n";
if ($page["head"]) echo "\t".$page["head"]."\n";
echo "\t".'<!-- <all your="base" are="belong/to.us" /> -->'."\n";
echo "</head>\n";
echo "<body".($page["body"]?" ".$page["body"]:"").">\n";

// remove temporal variables
unset($_b);
unset($_c);
unset($_i);
unset($_e);
unset($_f);
unset($_n);
unset($_t);
unset($_v);
