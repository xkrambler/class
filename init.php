<?php

// basic setup
error_reporting(E_ALL & ~E_NOTICE); // all but not notices
if (!defined("__DIR__")) define("__DIR__", dirname(__FILE__)); // PHP 5.2- compatibility

// base class
class x {

	// get/set page information
	static public function page($k=null, $v=null) {
		if (!isset($GLOBALS["page"])) $GLOBALS["page"]=array();
		if ($v !== null) $GLOBALS["page"][$k]=$v;
		if (is_array($k)) $GLOBALS["page"]=$k;
		else if ($k !== null) return (isset($GLOBALS["page"]) && isset($GLOBALS["page"][$k])?$GLOBALS["page"][$k]:null);
		return (isset($GLOBALS["page"])?$GLOBALS["page"]:null);
	}

	// get/set data information
	static public function data($k=null, $v=null) {
		if (!isset($GLOBALS["data"])) $GLOBALS["data"]=array();
		if ($v !== null) $GLOBALS["data"][$k]=$v;
		if (is_array($k)) $GLOBALS["data"]=$k;
		else if ($k !== null) return $GLOBALS["data"][$k];
		return $GLOBALS["data"];
	}

	// add include/set includes/return includes
	static public function inc($m=null) {
		$i=self::page("include");
		if ($m !== null) {
			if (is_array($m)) self::page("include", $m);
			else {
				if (!$GLOBALS["page"]["include"]) $GLOBALS["page"]["include"]=array();
				array_push($GLOBALS["page"]["include"], $m);
			}
		}
		return $i;
	}

	// check/set is included module
	static public function isinc($m=null, $isinc=null) {
		if ($isinc !== null) $GLOBALS["page"]["included"][$m]=$isinc;
		return $GLOBALS["page"]["included"][$m];
	}

	// load module/s css/js/php
	static public function module($_module) {
		global $css, $js;
		$_modulec=0;
		if (is_array($_module)) {
			foreach ($_module as $_m) $_modulec+=self::module($_m);
		} else {
			foreach ($GLOBALS as $i=>$d)
				if (substr($i, 0, 1) != "_" && $i != "GLOBALS")
					global $$i;
			if (file_exists($_module.".css")) { $_modulec++; $css[$_module.".css"]=false; }
			if (file_exists($_module.".js")) { $_modulec++; $js[$_module.".js"]=false; }
			if (file_exists($_module.".php")) { $_modulec++; require_once($_module.".php"); }
			$v=get_defined_vars();
			unset($v["_module"]);
			unset($v["_modulec"]);
			foreach ($v as $i=>&$d) $GLOBALS[$i]=$d; unset($d);
		}
		return $_modulec;
	}

	// _server
	static public function _server($k=null) {
		return (isset($k)?(isset($_SERVER) && isset($_SERVER[$k])?$_SERVER[$k]:null):$_SERVER);
	}

	// is HTTPS?
	static public function ishttps() {
		return (
			(self::_server("HTTPS") && strcasecmp((string)self::_server("HTTPS"), 'off'))
			|| !strcasecmp((string)self::_server("HTTP_X_FORWARDED_PROTO"), 'https')
			|| !strcasecmp((string)self::_server("HTTP_FRONT_END_HTTPS"), 'on')
			|| !strcasecmp((string)self::_server("HTTP_PROXY_SSL"), 'true')
		);
	}

	// get running script
	static public function self() {
		return self::_server("PHP_SELF");
	}

	// get server base
	static public function server() {
		return (($v=self::_server("HTTP_HOST"))?"http".(self::ishttps()?"s":"")."://".$v:"");
	}

	// get request path
	static public function request() {
		return (isset($_SERVER["REQUEST_URI"])?self::_server("REQUEST_URI"):self::_server("SCRIPT_NAME"));
	}

	// get path
	static public function path() {
		$p=self::request();
		if ($i=strpos($p, "?")) $p=substr($p, 0, $i);
		return $p;
	}

	// get query
	static public function query() {
		$p=self::request();
		$i=strpos($p, "?");
		return ($i!==false && $p?substr($p, $i+1):"");
	}

	// get "me"
	static public function me() {
		$url=self::path();
		return (substr($url, -1, 1) == "/"?"":basename($url));
	}

	// get relative URL+query
	static public function relative() {
		return self::me().(($q=self::query())?"?".$q:"");
	}

	// get url
	static public function url() {
		return self::server().self::request();
	}

	// get/set base
	static public function base($base=null) {
		global $page;
		if ($base !== null) $page["base"]=$base;
		if (isset($page["base"])) return $page["base"];
		$path=self::path();
		return (($server=self::server())?$server.(($i=strrpos($path, "/")) !== false?substr($path, 0, $i+1):"/"):"");
	}

	// link: create parametrized link
	static public function link($get=array(), $url=null) {
		if ($url === null) $url=self::me();
		else {
			$p=parse_url($url);
			$i=strpos($url, '?');
			if ($i !== false) $url=substr($url, 0, $i);
			$geturl=array();
			parse_str((isset($p['query'])?(string)$p['query']:''), $geturl);
			if ($geturl) $get=array_replace($geturl, $get);
		}
		// build query
		$p=($get?http_build_query($get, "", "&", PHP_QUERY_RFC3986):"");
		// supress alone equals
		$p=str_replace("=&", "&", $p);
		if (substr($p, -1, 1) === "=") $p=substr($p, 0, -1);
		// encode URL
		return $url.(strlen($p)?"?".$p:"");
	}

	// alter link: modify parametrized link from current request parameters
	static public function alink($get=array(), $url=null) {
		$get=array_replace($_GET, $get);
		return self::link($get, ($url === null?self::me():$url));
	}

	// redirect to URL and finish
	static public function redir($url=null) {
		header("Location: ".($url === null?self::base():$url));
		exit;
	}

	// force HTTPS navigation
	static public function forcehttps() {
		if (isset($_SERVER["HTTP_HOST"]) && !self::ishttps()) self::redir("https://".$_SERVER["HTTP_HOST"].self::request());
	}

	// efective remote address
	static function remoteaddr() {
		return (($v=self::_server("HTTP_X_FORWARDED_FOR"))?$v:self::_server("REMOTE_ADDR"));
	}

	// return current page charset or default charset
	static public function charset($charset=null) {
		if ($charset !== null) self::page("charset", $charset);
		$charset=self::page("charset");
		return ($charset?$charset:"UTF-8");
	}

	// return HTML entities in current page charset
	static public function entities($t) {
		return htmlentities((string)$t, ENT_QUOTES|ENT_SUBSTITUTE, self::charset());
	}

	// generic set cookie
	static public function setcookie(string $name, string $value, array $o=array()) {
		$c=[
			"\x00"=>"", "\x1F"=>"", "\x7F"=>"", "\n"=>"%0A", "\r"=>"%0D",
			" "=>"%20", ","=>"%2C", "/"=>"%2F", ";"=>"%3B", "="=>"%3D", "\\"=>"%5C"
		];
		$n=str_replace(array_keys($c), array_values($c), $name);
		$v=str_replace(array_keys($c), array_values($c), $value);
		if (!$o["path"]) $o["path"]="/";
		if (strnatcmp(phpversion(), '7.3.0') >= 0) {
			setrawcookie($n, $v, $o);
		} else {
			setrawcookie($n, $v, (is_numeric($o['expires'])?$o['expires']:0),
				(isset($o["path"])?(string)$o["path"]:"").(($v=$o['samesite'])?';samesite='.$v:''),
				(isset($o["domain"])?(string)$o["domain"]:""),
				(bool)$o["secure"],
				(bool)$o["httponly"]
			);
		}
	}

	// generic delete cookie
	static public function delcookie(string $name, array $o=array()) {
		self::setcookie($name, "", ["expires"=>1]+$o);
	}

	// set http max age (default: 10 minutes)
	static public function maxage($maxage=null) {
		if (!is_numeric($maxage) || $maxage <= 0) $maxage=600;
		header("Expires: ".gmdate("D, d M Y H:i:s", time()+$maxage)." GMT");
		header("Cache-Control: max-age=".$maxage);
		header("Pragma: cache");
		return true;
	}

	// isolated require
	static public function irequire($file, $data=array()) {
		extract($data);
		unset($data);
		return require($file);
	}

	// start and get + clean buffer
	static function ob($continue_ob=true) {
		$d=(@ob_get_contents());
		ob_end_clean();
		if ($continue_ob) ob_start();
		return $d;
	}

}

// dump error
if (!function_exists("perror")) {
	function perror($err="Unexpected error", $exit=1) {
		if (class_exists("xError") && ($error=xError::current())) {
			$error->err($err, $exit);
		} else {
			if ($GLOBALS["ajax"] && function_exists("ajax")) ajax(array("err"=>"ERROR: ".$err));
			else if (!x::_server("HTTP_HOST")) echo "ERROR: ".strip_tags($err)."\n";
			else {
				?><!doctype html>
				<html>
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=<?=x::charset()?>" />
					<style>
						._xerror { margin: 9px 0px; border: 2px solid #FD4; font-family: Open Sans, Segoe UI, Arial !important; font-size: 15px !important; box-shadow: 0px 3px 5px rgba(0,0,0,0.3); }
						._xerror_t { padding: 4px 12px; color: #822; background: #FD4; margin: 0px; font-size: inherit; }
						._xerror_m { padding: 9px 12px; color: #000; background-color: #FFFDF4; }
						._xerror_hr { background: #CCC; border: 0px; height: 1px; margin: 0px; }
						._xerror_foot { padding: 4px 12px; background: #F0F0F0; font-size: 12px !important; }
						._xerror_a { float: left; }
						._xerror_a a { color: #0AB; }
						._xerror_a a:hover { color: #089; }
						._xerror_ss { float: right; margin-left: 16px; color: #666; font-size: 13px; }
						._xerror_clr { clear: both; }
					</style>
				</head>
				<body>
					<div class='_xerror'>
						<h2 class='_xerror_t'>ERROR</h2>
						<div class='_xerror_m'><?=$err?></div>
						<hr class='_xerror_hr' />
						<div class='_xerror_foot'>
							<div class='_xerror_a'><a href='<?=x::url()?>'><?=x::url()?></a></div>
							<div class='_xerror_ss'><?=$_SERVER["SERVER_SOFTWARE"]?></div>
							<div class='_xerror_clr'></div>
						</div>
					</div>
				</body>
				</html><?php
			}
		}
		if ($exit) exit($exit);
	}
}

// PHP compatibility
if (intval(x::page("phpc")) < 8 && defined("PHP_VERSION_ID") && PHP_VERSION_ID >= 80000) error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// check basic setup
if ((bool)ini_get('register_globals')) perror("Security: register_globals must be disabled to continue.");

// forced variables
$me=x::self();
if (isset($_REQUEST["id"])) $id=$_REQUEST["id"];
if (!isset($data)) $data=array();

// legacy variables (deprecated)
$server=x::server();
$requesturi=x::path();
$serverurl=x::base();

// include all configuration files
$_f=(is_string(x::page("conf"))?x::page("conf"):"conf/");
if ($_f && file_exists($_f)) {
	$_d=dir($_f);
	while ($_e=$_d->read())
		if (substr($_e, -4, 4) == ".php")
			require_once($_d->path.$_e);
	$_d->close();
}

// change error reporting, if defined
if ($_e=x::page("error_reporting")) error_reporting($_e);

// session control
if (is_string(x::page("sessionname"))) {
	session_set_cookie_params(0, '/; samesite=Lax', '');
	if ($_x=x::page("sessionname")) session_name($_x);
	if (isset($_REQUEST["sessionid"]) && preg_match('/^[-,a-zA-Z0-9]{1,128}$/', (string)$_REQUEST["sessionid"])) session_id($_REQUEST["sessionid"]);
	session_start();
}

// user in session
$user=(isset($_SESSION["user"])?$_SESSION["user"]:null);

// legacy volatile variables
if (isset($_SESSION["ok"]))  { $ok =$_SESSION["ok"];  unset($_SESSION["ok"]); }
if (isset($_SESSION["err"])) { $err=$_SESSION["err"]; unset($_SESSION["err"]); }

// legacy PHP classes and additional libraries
if (isset($classes) && $_x=$classes) foreach ($_x as $_c) x::inc($_c);
if (isset($include) && $_x=$include) foreach ($_x as $_c) x::inc($_c);

// include all PHP classes
if ($_x=x::inc()) foreach ($_x as $_c) {
	$_f=(strpos($_c, "/") !== false?"":__DIR__."/").$_c;
	if (file_exists($_f.'.php')) x::isinc($_c, require_once($_f.'.php'));
}

// automatic instances
if (class_exists("Kernel")) {
	$kernel=(isset($db) && $db?new Kernel($db):new Kernel());
	if (class_exists("View")) $view=new View($kernel, (isset($base)?$base:null), (isset($skin)?$skin:null)); // deprecated
}
if (class_exists("Conf") && isset($db) && $db) $conf=new Conf(array("db"=>$db));

// dump variables for debug
if (!function_exists("debug")) {
	function debug(&$v, $level=0) {
		if (isset($GLOBALS["argv"])) var_dump($v);
		else {
			$old=$v;
			$v=$new=rand();
			$vname=false;
			foreach ($GLOBALS as $key=>$val)
				if ($val === $new) {
					$vname=$key;
					break;
				}
			$v=$old;
			if (!$level) echo "<div style='font-family:FiraSans,Arial;font-size:11px;line-height:12px;color:#333;background:#EEE;margin:1px;'><b>".$vname."</b> ";
			if (is_array($v)) {
				echo "{<br>";
				foreach ($v as $i=>$nv) {
					echo "<span style='color:#23A;padding-left:".(20*($level+1))."px;'>".$i."</span>".(is_array($nv)?" ":"=");
					debug($nv, $level+1);
				}
				echo "<span style='padding-left:".(20*$level)."px;'></span>}<br>";
			} else {
				if (
					(!is_array($v))
					&& (
						(!is_object($v) && settype($v, 'string') !== false )
						|| ( is_object($v) && method_exists($v, '__toString'))
					)
				) {
					echo "<span style='color:#820;'>".$v."</span><br>";
				} else {
					echo "<span style='color:#A0F;'>class <b>".get_class($v)."()</b></span><br>";
				}
			}
			if (!$level) echo "</div>";
		}
	}
}

// start and get + clean buffer (deprecated: use x::ob)
if (!function_exists("xob")) {
	function xob($continue_ob=true) {
		return x::ob($continue_ob);
	}
}

// module loading (deprecated: use x::module)
if (!function_exists("module")) {
	function module($m) {
		x::module($m);
	}
}

// pack CSS and JS files specified
if (isset($_GET["css"]) || isset($_GET["js"])) {

	// crypt autoloading
	$_pc=false;
	if ($page["crypt"]) {
		require_once(__DIR__."/crypt.php");
		$_pc=new $page["crypt"]["type"]($page["crypt"]);
		if (!$_pc->available()) $_pc->err();
	}

	// get includes
	$_i=array();
	$_x=false;
	if (isset($_GET["css"])) { $_x="css"; $_m="text/css"; }
	else if (isset($_GET["js"]))  { $_x="js";  $_m="text/javascript"; }
	if (!$_x) perror("/* BOOM: No ext! */"); // allowed extension
	if ($_pc) $_GET[$_x]=$_pc->decrypt($_GET[$_x]); // cypher
	if (strpos($_GET[$_x], "\0") !== false) die("/* BOOM: 0x00 Headshot! */"); // null character detected
	if ($_GET[$_x] && !$_i=explode("|", $_GET[$_x])) die("/* No includes */"); // no includes

	// caching
	if ($_c=x::page("cache_".$_x)) {
		require_once(__DIR__."/xcache.php");
		$xcache=new xCache();
		$xcache->modified($_c);
	}

	// caching via headers (in seconds)
	if ($_c=x::page("maxage")) x::maxage($_c);

	// send with compression, if available and not disabled
	if (!x::page("no_gzip") && extension_loaded('zlib') && ($_c=x::_server("HTTP_ACCEPT_ENCODING")) && in_array("gzip", explode(",", $_c))) ob_start("ob_gzhandler");

	// set mimetype and charset
	header("Content-type: ".$_m."; charset=".x::charset());

	// load included files
	if ($_i) foreach ($_i as $_c) {
		$_c=str_replace("\\", "/", $_c);
		if (strpos($_c, "..") !== false) die("Not allowed.");
		$_f=(substr($_c, 0, 1) == "/"?__DIR__:"");
		$_e=$_f.$_c.".".$_x;
		if (file_exists($_e)) {
			echo "/** ".$_c.".".$_x." **/\n";
			if (x::page("d".$_x)) { // dangerous method
				x::irequire($_e, array(
					"base"=>dirname($_c).(dirname($_c)?"/":""),
					"name"=>basename($_c).".".$_x,
				));
			} else {
				$_d=str_replace("%base%", dirname($_c).(dirname($_c)?"/":""), file_get_contents($_e));
				echo (strpos($_d, "<?") === false?$_d:"// disabled")."\n\n";
			}
		}
	}

	// finished
	exit;

}

// free temp vars
unset($_c);
unset($_d);
unset($_e);
unset($_f);
unset($_x);
