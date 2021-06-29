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
	const ERR_DB=-2;

	static protected $default=false;
	protected $setup=array();
	protected $error=false;
	protected $T=array(
		"en"=>array(
			"generic"=>"An error that has been recorded has occurred. Contact your application administrator for more details.",
		),
		"es"=>array(
			"generic"=>"Ha ocurrido un error que ha sido registrado. Contacte al administrador de la aplicación para más detalles.",
		),
	);

	// constructor
	function __construct($setup=array()) {

		// setup
		$this->setup($setup);

		// access log
		if (($f=$this->access) && $_SERVER["HTTP_HOST"])
			file_put_contents($f, $this->accessEntry(), FILE_APPEND);

		// disable critical errors
		error_reporting(
			error_reporting()
			& ~E_ERROR
			& ~E_PARSE
			& ~E_CORE_ERROR
			& ~E_COMPILE_ERROR
			& ~E_USER_ERROR
		);

		// set default perror instance
		if (!self::$default) self::$default=$this;

	}

	// getter/setter/isset
	function __get($n) { return $this->setup[$n]; }
	function __set($n, $v) { $this->setup[$n]=$v; }
	function __call($n, $a) { $f=$this->setup[$n]; if (is_callable($f)) call_user_func_array($f, $a); }
	function __isset($n) { return isset($this->setup[$n]); }

	// get/set default instance
	static function default($default=null) {
		if ($default !== null) self::$default=$default;
		return self::$default;
	}

	// setup
	function setup($setup=null) {

		// define setup
		if (is_array($setup)) {
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
				set_error_handler(function($type, $message, $file, $line, $context){
					if (!error_reporting()) return false; // prevent @-operator reporting
					$error=array(
						"type"=>$type,
						"message"=>$message,
						"file"=>$file,
						"type"=>$type,
						"line"=>$line,
						//"context"=>$context,
					);
					//print_r($error);
					switch ($error["type"]) {
					case E_DEPRECATED:
					case E_WARNING:
						$this->err($error, false);
					}
				});

				// capture critical errors
				register_shutdown_function(function(){
					if ($error=error_get_last()) {
						switch ($error["type"]) {
						case E_ERROR:
						case E_PARSE:
						case E_CORE_ERROR:
						case E_COMPILE_ERROR:
						case E_USER_ERROR:
							$this->err($error);
						}
					}
				});

			}
		}

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
			." ".x::request()."\n"
			.($_POST?" POST ".substr(serialize($_POST), 0, 64*1024)."]\n":"")
		;
	}

	// get/set database event errors
	function db($db=null) {
		if ($db !== null) {
			$this->db=$db;

			// set db error event
			$this->db->event("error", function($o){
				$this->err(array(
					"type"=>self::ERR_DB,
					"message"=>""
						." #".$o["num"]." ".$o["db"].":<br />\n"
						."<b>".$o["error"]."</b><br />\n",
					"sql"=>$o["lastquery"],
				), $o["doexit"]);
			});

			/*
				// future table access-log
				if (!$db->query($db->sqlinsert("access_log", array(
					"host"=>$_SERVER["HTTP_HOST"],
					"datetime"=>$db->now(),
					"ip"=>$_SERVER["REMOTE_ADDR"],
					"agent"=>substr($_SERVER["HTTP_USER_AGENT"], 0, 255),
					"url"=>substr(x::request(), 0, 4096),
					"post"=>($_POST?json_encode($_POST):null),
				)))) $db->err();
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

	// get/set error
	function error($err=null) {
		if ($err === null) return $this->error;
		if (is_string($err)) $err=array("type"=>-1, "message"=>$err);
		$error_types=array(
			self::ERR_APP=>"APP",
			self::ERR_DB=>"DB",
			E_ERROR=>"Fatal",
			E_PARSE=>"Parse",
			E_CORE_ERROR=>"Core",
			E_COMPILE_ERROR=>"Compile",
			E_USER_ERROR=>"User",
			E_DEPRECATED=>"Deprecated",
			E_WARNING=>"Warning",
		);
		$err["title"]=(($et=$error_types[$err["type"]])?$et:"Error[".$err["type"]."]");
		$err["text"]=strip_tags($err["message"]).($err["file"]?" - file ".$err["file"]." line ".$err["line"]:"");
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
			.($_SERVER["HTTP_HOST"]?"URL: ".x::base().x::alink()."\n":"")
			.(($v=$_SERVER["REMOTE_ADDR"])?"Remote_Addr: ".$v."\n":"")
			."Time: ".date("d/m/Y H:i:s")."\n"
			.(isset($this->burst_loop)?"Burst: ".$this->burst_loop."\n":"")
			.($err["sql"]?"SQL:\n".$err["sql"]."\n":"")
		;
	}

	// e-mail data
	function emailData($err) {
		return array(
			"subject"=>$this->emailSubject($err),
			"text"=>$this->emailText($err),
		);
	}

	// returns text by code in current language
	function _T($t) {
		$lang=x::page("lang");
		if (isset($this->T[$lang][$t])) return $this->T[$lang][$t];
		return $this->T["en"][$t];
	}

	// dump error
	function dump($err) {

		// use generic error message
		if ($this->generic) {
			$err=array(
				"title"=>($err["title"]?$err["title"]:"ERROR"),
				"message"=>($this->generic === true?$this->_T("generic"):$this->generic),
			);
		}

		// si no tengo versión de texto, preparar ahora
		if (!$err["text"]) $err["text"]=strip_tags(nl2br($err["message"]));

		// AJAX
		if ($GLOBALS["ajax"] && function_exists("ajax")) {
			ajax(array("err"=>$err["title"].": ".$err["text"], "code"=>$exit));
		// CLI
		} else if (!$_SERVER["HTTP_HOST"]) {
			error_log("[".date("YmdHis")."] ".$err["title"].": ".$err["text"]);
		// HTML
		} else {
			?><!doctype html>
			<html lang="<?=x::page("lang")?>">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=<?=x::charset()?>" />
				<style>
					._xerror { margin: 12px; border: 2px solid #FD4; font-family: Open Sans, Segoe UI, Arial !important; font-size: 15px !important; box-shadow: 0px 3px 5px rgba(0,0,0,0.3); }
					._xerror_t {}
					._xerror_t b { display: inline-block; padding: 6px 16px; color: #822; background: #FD4; margin: 0px; font-size: inherit; }
					._xerror_m { padding: 12px 16px; color: #000; background-color: #FFFDF4; }
					._xerror_sql { padding: 12px 16px; color: #05A; font-size: 13px; }
					._xerror_f { padding: 12px 16px 0px 12px; color: #444; }
					._xerror_f b { padding: 2px 6px; background: #DDD; white-space: nowrap; }
					._xerror_hr { background: #FD4; border: 0px; height: 2px; margin: 0px; }
					._xerror_foot { padding: 6px 12px; background: #FFFDF4; font-size: 12px !important; }
					._xerror_a { float: left; }
					._xerror_a a { color: #0AB; }
					._xerror_a a:hover { color: #089; }
					._xerror_ss { float: right; margin-left: 16px; color: #666; font-size: 13px; }
					._xerror_clr { clear: both; }
				</style>
			</head>
			<body>
				<div class='_xerror'>
					<div class='_xerror_t'><b><?=$err["title"]?></b></div>
					<?php if ($err["file"]) { ?>
						<div class='_xerror_f'>
							<b><?=$err["file"]?></b> line <b><?=$err["line"]?></b>
						</div>
					<?php } ?>
					<div class='_xerror_m'>
						<?=$err["message"]?>
					</div>
					<?php if ($err["sql"]) { ?>
						<pre class='_xerror_sql'><?=$err["sql"]?></pre>
					<?php } ?>
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

	// dump error
	function err($err=null, $exit=1) {

		// prepare error message
		if ($err !== null) $this->error($err);
		$err=$this->error();

		// send e-mail
		if (($e=$this->mail) && method_exists("Kernel", "mailto"))
			if ($this->burstCheck())
				$this->mailed=Kernel::mailto($this->emailData($err)+$e);

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

// instanciar, si setup
if ($error_setup)
	foreach ($error_setup as $_n=>$_s)
		$$_n=new xError($_s);
