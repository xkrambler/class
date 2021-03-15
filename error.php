<?php

require_once(__DIR__."/init.php");

/*

	Class to threat error and access register.

	Syntax:
		$error=new xError([
			"access"=>"data/access.log", // set access log
			"errors"=>true, // set error log (true=same as access)
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

	protected $setup=array();
	protected $error=false;

	// constructor
	function __construct($setup=array()) {

		// setup
		$this->setup($setup);

		// access log
		if (($f=$this->access) && $_SERVER["HTTP_HOST"])
			file_put_contents($f, $this->accessEntry(), FILE_APPEND);

	}

	// getter/setter/isset
	function __get($n) { return $this->setup[$n]; }
	function __set($n, $v) { $this->setup[$n]=$v; }
	function __isset($n) { return isset($this->setup[$n]); }

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
					switch ($error["type"]) {
					case E_DEPRECATED:
					case E_WARNING:
						$this->err($error);
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
						//case E_WARNING:
						//case E_DEPRECATED:
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
		if ($access) $this->access=realpath($access);
		if ($errors) $this->errors($errors);
	}

	// set error log
	function errors($errors) {
		if ($f=$errors) {
			$this->errors=$errors;
			// use access log file
			if ($f === true) $f=$this->access;
			// resolve path now, to prevent future directory changes
			$this->errors_path=realpath($f);
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
			$this->db->event("error",function($o){
				$this->err(""
					."DB Error #".$o["num"]." ".$o["db"].":<br />\n"
					.$o["error"]
				);
			});

			/*
				// future table access-log
				if (!$db->query($db->sqlinsert("access_log", [
					"host"=>$_SERVER["HTTP_HOST"],
					"datetime"=>$db->now(),
					"ip"=>$_SERVER["REMOTE_ADDR"],
					"agent"=>substr($_SERVER["HTTP_USER_AGENT"], 0, 255),
					"url"=>substr(x::request(), 0, 4096),
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

	// get/set error
	function error($err=null) {
		if ($err === null) return $this->error;
		if (is_string($err)) $err=array("type"=>-1, "message"=>$err);
		$error_types=array(
			-1=>"App",
			E_ERROR=>"Fatal",
			E_PARSE=>"Parse",
			E_CORE_ERROR=>"Core",
			E_COMPILE_ERROR=>"Compile",
			E_USER_ERROR=>"User",
		);
		$err["type_text"]=$error_types[$err["type"]];
		$err["text"]=($err["type_text"]?$err["type_text"]." ":"")
			."Error: ".strip_tags($err["message"]).($err["file"]?" - file ".$err["file"]." line ".$err["line"]:"")
		;
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

	// dump error
	function err($err=null, $exit=1) {

		// prepare error message
		if ($err !== null) $this->error($err);
		$err=$this->error();

		// send e-mail
		if (($e=$this->mail) && method_exists("Kernel", "mailto")) {
			if ($this->burstCheck()) {
				if (!$e["subject"]) $e["subject"]="[xError".($_SERVER["HTTP_HOST"]?" ".$_SERVER["HTTP_HOST"]:"")." ".date("d/m/Y H:i:s")."]";
				$e["text"]=($e["text"]?$e["text"]:"").$err["text"]."\n"
					.($_SERVER["HTTP_HOST"]?"URL: ".x::base().x::alink()."\n":"")
					."Generated at: ".date("d/m/Y H:i:s")."\n"
					.(isset($this->burst_loop)?"Burst: ".$this->burst_loop."\n":"");
				$this->mailed=Kernel::mailto($e);
			}
		}

		// custom renderer (if display enabled or not set)
		if (!isset($this->display) || $this->display) {
			if ($this->html) {
				$this->html();
			} else {

				// AJAX
				if ($GLOBALS["ajax"] && function_exists("ajax")) {
					ajax(array("err"=>"ERROR: ".$err["text"], "code"=>$exit));
				// CLI
				} else if (!$_SERVER["HTTP_HOST"]) {
					echo "[".date("YmdHis")."] ERROR: ".$err["text"]."\n";
				// HTML
				} else {
					?><!doctype html>
					<html>
					<head>
						<meta http-equiv="Content-Type" content="text/html; charset=<?=x::charset()?>" />
						<style>
							._xerror { margin: 12px; border: 2px solid #FD4; font-family: Open Sans, Segoe UI, Arial !important; font-size: 15px !important; box-shadow: 0px 3px 5px rgba(0,0,0,0.3); }
							._xerror_t {}
							._xerror_t b { display: inline-block; padding: 6px 16px; color: #822; background: #FD4; margin: 0px; font-size: inherit; }
							._xerror_m { padding: 12px 16px; color: #000; background-color: #FFFDF4; }
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
							<div class='_xerror_t'><b><?=strtoupper($this->error["type_text"])?> ERROR:</b></div>
							<? if ($err["file"]) { ?>
								<div class='_xerror_f'>
									<b><?=$err["file"]?></b> line <b><?=$err["line"]?></b>
								</div>
							<? } ?>
							<div class='_xerror_m'>
								<?=$err["message"]?>
							</div>
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
		}

		// return with exit code
		if ($exit) exit($exit);

	}

}

// instanciar, si setup
if ($error_setup)
	foreach ($error_setup as $_n=>$_s)
		$$_n=new xError($_s);
