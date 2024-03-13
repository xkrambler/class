<?php

require_once(__DIR__."/init.php");

/*

	xError
	Class for handling errors and access registering.

	Syntax:
		$error=new xError([
			"access"=>"data/access.log", // set access log
			"errors"=>true, // set error log (true=same as access)
			"generic"=>false, // show a generic error message
			"render"=>function($err){ print_r($err); }, // custom renderer
			"display"=>true, // display errors
			"args"=>true, // visible arguments if true, not only types (false), or disabled (null)
			"mail"=>[ // set mailing on errors
				"from"=>"from@email.com",
				"to"=>"to@email.com",
			],
		]);
		$error->db($db); // set database error event capturing
		$error->err(); // display last error
		$error->err("error"); // display custom error message
		$error->error("error"); // set custom error message
		$error->err(); // display last error message

*/
class xError {

	const ERR_APP=-1;
	const ERR_DB =-2;

	static protected $current=false;
	protected $setup=[];
	protected $error=false;
	protected $T=[
		"en"=>[
			"generic"=>"An error that has been recorded has occurred. Contact your application administrator for more details.",
		],
		"es"=>[
			"generic"=>"Ha ocurrido un error que ha sido registrado. Contacte al administrador de la aplicación para más detalles.",
		],
	];
	protected $warnings=[E_DEPRECATED, E_WARNING];
	protected $criticals=[E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
	protected $error_types=[
		self::ERR_APP  =>"APP",
		self::ERR_DB   =>"DB",
		E_ERROR        =>"Fatal",
		E_PARSE        =>"Parse",
		E_CORE_ERROR   =>"Core",
		E_COMPILE_ERROR=>"Compile",
		E_USER_ERROR   =>"User",
		E_DEPRECATED   =>"Deprecated",
		E_WARNING      =>"Warning",
	];

	// constructor
	function __construct(array $setup=[]) {

		// setup
		$this->setup($setup);

		// access log
		if (($f=$this->access) && $_SERVER["HTTP_HOST"])
			file_put_contents($f, $this->accessEntry(), FILE_APPEND);

		// disable critical errors
		$e=error_reporting();
		foreach ($this->criticals as $c) $e&=~$c;
		error_reporting($e);

		// set current perror instance
		if (!self::$current) self::$current=$this;

	}

	// getter/setter/isset
	function __get($n) { return (isset($this->setup[$n])?$this->setup[$n]:null); }
	function __set($n, $v) { $this->setup[$n]=$v; }
	function __call($n, $a) { $f=$this->setup[$n]; if (is_callable($f)) call_user_func_array($f, $a); }
	function __isset($n) { return isset($this->setup[$n]); }

	// get/set current instance
	static function current($current=null) {
		if ($current !== null) self::$current=$current;
		return self::$current;
	}

	// setup
	function setup(array $setup=[]) {

		// define setup
		$this->setup=$setup;

		// burst time
		if (!$this->mail_bursttime) $this->mail_bursttime=60;

		// setup logs
		$this->access($this->access);
		$this->errors($this->errors);

		// by default, capture is activated
		if (!isset($this->capture) || $this->capture) {

			// hide errors
			$this->visible(false);

			// capture non-critical errors
			set_error_handler(function($type, $message, $file, $line, $context=[]){
				if (in_array($type, $this->warnings)) {
					$this->error=[
						"type"=>$type,
						"message"=>$message,
						"file"=>$file,
						"line"=>$line,
						"trace"=>$this->trace(),
						//"context"=>$context,
					];
					if (error_reporting()) $this->err($this->error, false);
				}
			});

			// capture critical errors
			register_shutdown_function(function(){
				if (error_reporting() && ($e=error_get_last())) {
					if (in_array($e["type"], $this->criticals)) {
						// split message and trace
						$a=explode("\n", $e["message"]);
						$e["message"]=$a[0];
						unset($a[0]);
						$e["trace"]=[];
						foreach ($a as $l)
							if (!in_array($l, ["Stack trace:", "  thrown"]))
								$e["trace"][]=["message"=>$l];
						$this->error=$e;
						$this->err($e);
					}
				}
			});

		}

		// set database
		if (isset($setup["db"])) $this->db($setup["db"]);

		// return setup
		return $this->setup;

	}

	// set access (and errors) log
	function access($access, $errors=false) {
		// resolve path now, to prevent future directory changes
		if ($access) $this->access=realpath(dirname($access))."/".basename($access);
		if ($errors) $this->errors($errors);
	}

	// set error log
	function errors($errors) {
		if ($f=$errors) {
			$this->errors=$errors;
			// use access log file
			if ($f === true) $f=$this->access;
			// resolve path now, to prevent future directory changes
			$this->errors_path=realpath(dirname($f))."/".basename($f);
		}
	}

	// access entry
	function accessEntry() {
		return date("YmdHis")
			." ".($_SERVER["HTTP_HOST"]?$_SERVER["HTTP_HOST"]:"-")
			." ".($_SERVER["REMOTE_ADDR"]?$_SERVER["REMOTE_ADDR"]:"-")
			." ".(is_string($_SESSION["user"])?$_SESSION["user"]:"-")
			." ".\x::request()."\n"
			.($_POST?" POST ".substr(serialize($_POST), 0, 64*1024)."]\n":"")
		;
	}

	// convert first trace to file/line
	function traceFileLine(array $err) {
		$err["trace"]=$this->trace(["start"=>1]);
		if ($t=$err["trace"][0]) {
			$err["file"]=$t["file"];
			$err["line"]=$t["line"];
			$err["trace"]=$this->trace(["start"=>2]);
		}
		return $err;
	}

	// get/set database event errors
	function db($db=null) {
		if ($db !== null) {
			$this->db=$db;

			// set db error event
			$this->db->event("error", function($o){
				$this->err($this->traceFileLine([
					"type"=>self::ERR_DB,
					"message"=>""
						."<span>#".$o["num"]." ".$o["db"].":</span><br />"
						." <b>".$o["error"]."</b>",
					"sql"=>$o["lastquery"],
				]), $o["doexit"]);
			});

			/*
				// future table access-log
				if (!$db->query($db->sqlinsert("access_log", [
					"host"=>$_SERVER["HTTP_HOST"],
					"datetime"=>$db->now(),
					"ip"=>$_SERVER["REMOTE_ADDR"],
					"agent"=>substr($_SERVER["HTTP_USER_AGENT"], 0, 255),
					"url"=>substr(\x::request(), 0, 4096),
					"post"=>($_POST?json_encode($_POST):null),
				]))) $db->err();
			*/

		}

		// return current database
		return $this->db;

	}

	// PHP error visibility
	function visible($visible) {
		ini_set('display_errors', $visible);
		ini_set('display_startup_errors', $visible);
	}

	// return argument as string representation
	function args($a) {
		$s="";
		if (isset($this->args) && $a)
			foreach ($a as $i=>$v)
				$s.=($i?", ":"").($this->args === true?var_export($v, true):gettype($v));
		return $s;
	}

	// get stack trace
	function trace(array $o=[]) {
		if (!$o["start"]) $o["start"]=0;
		$p=0;
		$a=[];
		$exception=new \Exception();
		if ($trace=$exception->getTrace())
			foreach ($trace as $i=>$t)
				if (!($t["class"] == "xError" && in_array($t["function"], ["trace", "error"])))
					if ($p++ >= $o["start"])
						$a[]=array_merge($t, [
							"message"=>"#".count($a)." ".$t["file"]."(".$t["line"].")"
								.($t["class"] != "xError"?": ".$t["class"].$t["type"].$t["function"].($t["args"]?"(".$this->args($t["args"]).")":""):"")
							,
						]);
		return $a;
	}

	// get/set error
	function error($err=null) {
		if ($err === null) return $this->error;
		if (is_string($err)) $err=$this->traceFileLine([
			"type"=>self::ERR_APP,
			"message"=>$err,
		]);
		if (!isset($err["title"])) $err["title"]=(($et=$this->error_types[$err["type"]])?$et:"Error[".$err["type"]."]");
		if (!isset($err["text"])) $err["text"]=strip_tags($err["message"]).($err["file"]?""
			.(strpos($err["message"], "\n")?"\n ":"")
			." - ".$err["file"]." line ".$err["line"]:"");
		if (!isset($err["trace"])) $err["trace"]=$this->trace();
		if ($err["trace"])
			foreach ($err["trace"] as $t)
				$err["text"].="\n  ".$t["message"];
		$this->error=$err;
		// error log
		if ($f=$this->errors_path) {
			$text=date("YmdHis ").str_replace("\n", "\n\t", $this->error["text"]);
			file_put_contents($f, ($this->access?"":$this->accessEntry()).$text."\n", FILE_APPEND);
		}
		// always returns false!
		return false;
	}

	// burst check
	function burstCheck() {
		if (!$this->burst_check) {
			$this->burst_check=true;
			$this->burst_loop=0;
			if (session_status() == PHP_SESSION_ACTIVE) {
				if ($_SESSION["xError"]["text"] !== $this->error["text"]) unset($_SESSION["xError"]["burst_timer"]);
				if (
					$_SESSION["xError"]["burst_timer"] &&
					(microtime(true) - $_SESSION["xError"]["burst_timer"]) < $this->mail_bursttime
				) {
					$_SESSION["xError"]["burst_loop"]=intval($_SESSION["xError"]["burst_loop"])+1;
					$this->burst_check=false;
					return $this->burst_check;
				}
				$this->burst_loop=intval($_SESSION["xError"]["burst_loop"]);
				$_SESSION["xError"]["burst_loop"]=0;
				$_SESSION["xError"]["burst_timer"]=microtime(true);
				$_SESSION["xError"]["text"]=$this->error["text"];
			}
		}
		return $this->burst_check;
	}

	// e-mail subject
	function emailSubject($err) {
		return "[xError ".date("d/m/Y H:i:s")."] ".$err["title"].($_SERVER["HTTP_HOST"]?" ".$_SERVER["HTTP_HOST"]:"");
	}

	// e-mail text
	function emailText($err) {
		$e=$this->mail;
		return ($e["text"]?$e["text"]:"")
			.$err["title"].": ".strip_tags($err["message"])."\n"
			.($err["file"]?"File: ".$err["file"].($err["line"]?" at line ".$err["line"]:"")."\n":"")
			.($_SERVER["HTTP_HOST"]?"URL: ".\x::base().\x::alink()."\n":"")
			.(($v=$_SERVER["REMOTE_ADDR"])?"Remote_Addr: ".$v."\n":"")
			."Time: ".date("d/m/Y H:i:s")."\n"
			.(isset($this->burst_loop)?"Burst: ".$this->burst_loop."\n":"")
			.($err["sql"]?"SQL:\n".$err["sql"]."\n":"")
		;
	}

	// e-mail data
	function emailData($err) {
		return [
			"subject"=>$this->emailSubject($err),
			"text"=>$this->emailText($err),
		];
	}

	// returns text by code in current language
	function _T($t) {
		$lang=\x::page("lang");
		if (isset($this->T[$lang][$t])) return $this->T[$lang][$t];
		return $this->T["en"][$t];
	}

	// dump error
	function dump($err) {

		// use generic error message
		if ($this->generic) $err=[
			"type"=>$err["type"],
			"title"=>($err["title"]?$err["title"]:"ERROR"),
			"message"=>($this->generic === true?$this->_T("generic"):$this->generic),
		];

		// si no tengo versión de texto, preparar ahora
		if (!$err["text"]) $err["text"]=strip_tags(nl2br($err["message"]));

		// AJAX
		if ($GLOBALS["ajax"] && function_exists("ajax")) {
			ajax(["err"=>$err["title"].": ".$err["text"], "code"=>$exit]);
		// CLI
		} else if (!isset($_SERVER["HTTP_HOST"])) {
			error_log("[".date("YmdHis")."] ".$err["title"].": ".$err["text"]);
		// HTML
		} else {
			?><!doctype html>
			<html lang="<?=\x::page("lang")?>">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=<?=\x::charset()?>" />
				<style>
					._xerror { background: #FFF; margin: 12px; border: 1px solid #FD4; font-family: Open Sans, Arial, Sans !important; font-size: 15px !important; box-shadow: 0 3px 5px rgba(0,0,0,0.3); tab-size: 2 !important; -moz-tab-size: 2 !important; }
					._xerror_h b { display: inline-block; padding: 4px 12px; color: #822; background: #FD4; margin: 0; font-size: inherit; }
					._xerror_c { border-color: #F88; }
					._xerror_c ._xerror_h b { background: #F88; }
					._xerror_t { margin: 12px; color: #666; }
					._xerror_f { display: inline-block; margin: 0 1px; color: #444; }
					._xerror_f b { padding: 2px 6px; background: #DDD; white-space: nowrap; }
					._xerror_m { margin: 12px; color: #000; background-color: #FFFDF4; }
					._xerror_m span { color: #055; font-weight: normal; }
					._xerror_sql { margin: 12px; color: #05A; font-size: 13px; }
				</style>
			</head>
			<body>
				<div class='_xerror <?=(in_array($err["type"], $this->criticals)?"_xerror_c":"")?>'>
					<span class='_xerror_h'><b><?=$err["title"]?></b></span>
					<?php if ($err["file"]) { ?>
						<span class='_xerror_f'>
							<b><?=$err["file"]?></b> line <b><?=$err["line"]?></b>
						</span>
					<?php } ?>
					<div class='_xerror_m'>
						<b><?=$err["message"]?></b>
					</div>
					<?php if ($trace=$err["trace"]) { ?>
						<div class='_xerror_t'>
							<?php if ($trace=$err["trace"]) foreach ($trace as $t) { ?>
								<?=$t["message"]?><br />
							<?php } ?>
						</div>
					<?php } ?>
					<?php if ($err["sql"]) { ?>
						<pre class='_xerror_sql'><?=$err["sql"]?></pre>
					<?php } ?>
				</div>
			</body>
			</html><?php
		}

	}

	// dump error
	function err($err=null, $exit=1) {

		// prepare error message
		if ($err !== null) $this->error($err);
		$err=$this->error();

		// send e-mail
		if (($e=$this->mail) && method_exists("Kernel", "mailto"))
			if ($this->burstCheck())
				$this->mailed=\Kernel::mailto($this->emailData($err)+$e);

		// custom renderer (if display enabled or not set)
		if (!isset($this->display) || $this->display) {

			// custom renderer (if display enabled or not set)
			if (is_callable($this->render)) {
				$this->render($this, $err);
			} else {
				$this->dump($err);
			}

		} // display

		// return with exit code
		if ($exit) exit($exit);

	}

	// convert to string
	function __toString() {
		return "(".get_class($this).")";
	}

}

// instance, if setup defined
if ($error_setup)
	foreach ($error_setup as $_n=>$_s)
		$$_n=new xError($_s);
