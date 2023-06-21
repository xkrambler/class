<?php

class WS {

	public $data;
	protected $o;

	function __construct($o=[]) {
		$this->o=$o;
		$this->setup();
	}
	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }
	function __isset($n) { return isset($this->o[$n]); }

	// setup GET/POST/OPTIONS, allow all mods (including JSONP or AJAX)
	function setup() {

		// setup headers
		header('Access-Control-Allow-Origin: *');
		header("Allow: GET, POST, OPTIONS");
		if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") exit;

		// autoselect request type data
		if (is_string($_REQUEST["action"])) {
			$this->data=$_REQUEST;
		} else if ($this->action=$GLOBALS["ajax"]) {
			$this->data=$GLOBALS["adata"];
		} else if (isset($_REQUEST["data"])) {
			$this->data=json_decode($_REQUEST["data"], true);
		} else {
			$this->data=json_decode(file_get_contents('php://input'), true);
		}
		if (!isset($this->action)) $this->action=$this->data["action"];

		// ask for key
		if (!($key=$this->key()) && isset($_REQUEST["askkey"])) {
			header("Content-Type: text/html; charset=UTF-8");
			?><!doctype html>
			<html lang='en'>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			</head>
			<body>
				<form method='POST'>
					Key: <input name='key' type='text' size='40' /> <input type='submit' value='Enter' />
				</form>
			</body>
			</html><?php
			exit;
		}

		// common non authenticated actions
		switch ($this->action) {
		case "microtime": $this->out(["microtime"=>microtime(true)]);
		case "ping": $this->out(["pong"=>substr($this->data["ping"], 0, 256)]);
		case "status":
			$iskeyok=(
				(isset($this->key) && $key == $this->key)
				|| (is_array($this->keys) && $this->keys[$key])
			);
			$actions=($iskeyok && $this->keys[$key]?$this->keys[$key]:$iskeyok);
			$this->out([
				"iskeyok"=>$iskeyok,
				"actions"=>$actions,
				"ok"=>true,
			]);
		case "time": $this->out(["time"=>time()]);
		case "void": $this->out();
		default: if (!$this->action) $this->out(["err"=>"action not defined."]);
		}

	}

	// private: output CSV value
	private function outCSVvalue($v) {
		return str_replace(
			[";",'"',"\n"],
			[",",'""',"|"],
			$v
		);
	}

	// private: plain text output
	private function outPlain($d, $b=null) {
		if ($b !== null) $b=$b.".";
		foreach ($d as $n=>$v) {
			if (is_array($v)) $this->outPlain($v, $b.$n);
			else echo $b.$n."=".$v."\n";
		}
	}

	// return all HTTP headers, no maters if function exists
	function getAllHeaders() {
		if (function_exists('getallheaders')) {
			// direct method
			return getallheaders();
		} else {
			// probably PHP-FPM < 7.3
			$headers=[];
			foreach ($_SERVER as $k=>$v)
				if (substr($k, 0, 5) == 'HTTP_')
					$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))))]=$v;
			return $headers;
		}
	}

	// get current key
	function key() {

		// get key from data
		$key=$this->data["key"];

		// if no key in data, try get it from headers
		if (!isset($key)) {
			$headers=$this->getAllHeaders();
			foreach ($keyheaders=["Authorization", "X-API-KEY"] as $k)
				if (isset($headers[$k]))
					$key=$headers[$k];
		}

		// return key
		return $key;

	}

	// ensure authentication beyond this point
	function auth() {

		// get current key
		$key=$this->key();

		// key/keys authentication
		if (is_string($key)) {
			if (isset($this->key) && $key === $this->key) return;
			else if (is_array($this->keys)) {
				if ($this->keys[$key] === true) return;
				else if (is_array($this->keys[$key]) && in_array($this->action, $this->keys[$key])) return;
				else $this->out(["auth"=>"failed", "err"=>"Authorization key valid but failed for this action."]);
			}
			$this->out(["auth"=>"failed", "err"=>"Authorization key invalid."]);
		}

		// not beyond this point
		$this->out(["auth"=>"required", "err"=>"No authorization key was provided."]);
		exit; // fallback

	}

	// check parameters
	function check(array $param) {
		foreach ($param as $p=>$t) {
			if (!isset($this->data[$p])) $this->out(["err"=>"Required parameter (".$t."): ".implode(", ", array_keys($param)), "p"=>$p, "t"=>$t]);
			else {
				$nok=false;
				switch ("".$t) {
				case "json":
					$this->data[$p]=@json_decode($this->data[$p], true);
					break;
				case "number":
					if (is_numeric($this->data[$p])) $this->data[$p]=doubleval($this->data[$p]);
					else $nok=true;
					break;
				case "email":
					if (!filter_var($this->data[$p], FILTER_VALIDATE_EMAIL)) $nok=true;
					break;
				case "array":
					if (!is_array($this->data[$p])) $nok=true;
					break;
				case "date":
					if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $this->data[$p], $v) || !checkdate($v[2], $v[3], $v[1])) $nok=true;
					break;
				default: // se acepta todo
				}
				if ($nok) $this->out(["err"=>"Parameter ".$p." does not meet required type specification: ".$t,"p"=>$p]);
			}
		}
	}

	// output
	function out($d=null) {
		if ($d === null) exit; // empty
		if (is_string($_GET["jsonp"])) {
			// clean/protect data
			$data=$this->data;
			unset($data["jsonp"]);
			unset($data["key"]);
			// send JSONP
			header("Content-type: text/javascript");
			echo $_GET["jsonp"]."(".json_encode($data).",".json_encode($d).");\n";
			exit;
		} else {
			switch ($_REQUEST["out"]) {
			case "html":
				debug($d);
				exit;
			case "debug":
				header("Content-type: text/plain");
				var_dump($d);
				exit;
			case "plain":
				header("Content-type: text/plain");
				$this->outPlain($d);
				exit;
			case "csv":
				header("Content-type: text/plain");
				foreach ($d as $r) break;
				if (!is_array($r)) $d=[$d];
				$head=true;
				foreach ($d as $r) {
					if ($head) {
						$index=0;
						foreach ($r as $n=>$v)
							echo ($index++?";":"").$this->outCSVvalue($n);
						echo "\n";
						unset($head);
					}
					$index=0;
					foreach ($r as $n=>$v)
						echo ($index++?";":"").$this->outCSVvalue($v);
					echo "\n";
				}
				exit;
			case "xml":
				function array_to_xml($array, &$xml) {
					foreach ($array as $k=>$v) {
						if (is_array($v)) {
							if (is_int($k)) $k="item";
							$label=$xml->addChild($k);
							array_to_xml($v, $label);
						} else {
							if (is_array($array) && is_int($k)) $k="item";
							$xml->addChild($k, htmlspecialchars($v));
						}
					}
				}
				$xml=new SimpleXMLElement('<ws/>');
				array_to_xml($d, $xml);
				header("Content-type: text/xml; charset=UTF-8");
				echo $xml->asXML();
				exit;
			case "json":
			default:
				ajax($d);
			}
		}
	}

	// dump database error
	function dberr(dbbase $db) {
		$this->out(["err"=>$db->driver()." #".$db->errnum().": ".$db->error()]);
	}

	// dump error
	function err($error) {
		$this->out(["err"=>$error]);
	}

}
