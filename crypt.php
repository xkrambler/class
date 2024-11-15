<?php

/*
	Cryptography helper class 0.2
	Allows abstraction to libraries MCrypt/OpenSSL.
*/
class Crypt {

	protected $algorithm;
	protected $mode;
	protected $filter;
	protected $iv;
	protected $iv_size;
	protected $key;
	protected $openssl;
	protected $unpadding;

	// init
	function __construct($o=array()) {
		$this->algorithm=(isset($o["algorithm"])?$o["algorithm"]:"blowfish");
		$this->filter   =(isset($o["filter"])?$o["filter"]:"base64uf");
		$this->key      =$o["key"];
		$this->mode     =(isset($o["mode"])?$o["mode"]:"cbc");
		$this->openssl  =(isset($o["openssl"])?$o["openssl"]:false);
		$this->unpadding=(isset($o["unpadding"])?$o["unpadding"]:true);
		if (!isset($o["openssl"]) && function_exists("openssl_open")) $this->openssl=OPENSSL_RAW_DATA;
		// enabled modules
		if ($this->openssl && !function_exists("openssl_open")) return $this->error("OpenSSL module not available.");
		else if (!$this->openssl && !function_exists("mcrypt_cbc")) return $this->error("MCrypt module not available.");
		// get IV size
		$this->iv_size=($this->openssl
			?openssl_cipher_iv_length($this->getAlgorithm()."-".$this->mode)
			:mcrypt_get_iv_size($this->algorithm, $this->mode)
		);
		// generate IV
		$this->iv=(isset($o["iv"])
			?str_pad("", $this->iv_size, $o["iv"])
			:str_pad("", $this->iv_size, "\0")
		);
	}

	// get real algorithm depending ciphering library
	function getAlgorithm() {
		if ($this->openssl) {
			switch ($this->algorithm) {
			case "blowfish": return "bf";
			}
		}
		return $this->algorithm;
	}

	// get/set key
	function key($key=null) {
		if ($key !== null) $this->key=$key;
		else return $key;
	}

	// get/set filter
	function filter($filter=null) {
		if ($filter !== null) $this->filter=$filter;
		else return $filter;
	}

	// encode
	function encrypt($data) {
		return ($this->openssl
			?$this->filterEncode($this->filter, openssl_encrypt($data, $this->getAlgorithm()."-".$this->mode, $this->key, $this->openssl, $this->iv))
			:$this->filterEncode($this->filter, mcrypt_encrypt($this->getAlgorithm(), $this->key, $data, $this->mode, $this->iv))
		);
	}

	// decode
	function decrypt($data) {
		return ($this->openssl
			?openssl_decrypt($this->filterDecode($this->filter, $data), $this->getAlgorithm()."-".$this->mode, $this->key, $this->openssl, $this->iv)
			:$this->unpadding(mcrypt_decrypt($this->getAlgorithm(), $this->key, $this->filterDecode($this->filter, $data), $this->mode, $this->iv))
		);
	}

	// remove padding
	function unpadding($data) {
		return ($this->unpadding?str_replace("\0","",$data):$data);
	}

	// encode filter
	function filterEncode($filter, $data) {
		if (!$filter) return $data;
		switch ($filter) {
		case "base64": return base64_encode($data);
		case "base64uf": return $this->base64ufEncode($data);
		}
	}

	// decode filter
	function filterDecode($filter, $data) {
		if (!$filter) return $data;
		switch ($filter) {
		case "base64": return base64_decode($data);
		case "base64uf": return $this->base64ufDecode($data);
		}
	}

	// encode base64-URL-Friendly
	function base64ufEncode($d) {
		$d=base64_encode($d);
		$d=str_replace("+", ".", $d);
		$d=str_replace("/", "_", $d);
		$d=str_replace("=", "-", $d);
		return $d;
	}

	// decode base64-URL-Friendly
	function base64ufDecode($d) {
		$d=str_replace(".", "+", $d);
		$d=str_replace("_", "/", $d);
		$d=str_replace("-", "=", $d);
		return base64_decode($d);
	}

	// get/set error
	function error($error=null) {
		if ($error === null) return $this->error;
		$this->error=$error;
		if ($onko=$this->onko) $r=$onko($this, $error);
		return false;
	}

	// throw error
	function err($exit=1) {
		$text=$this." ".($this->error?"ERROR: ".$this->error:"Undefined error");
		if ($GLOBALS["ajax"] && function_exists("ajax")) ajax(["err"=>$text]);
		if (function_exists("perror")) perror($text, $exit);
		echo $text."\n";
		if ($exit) exit($exit);
	}

	// string with basic info
	function __toString() {
		return "(".get_class($this).")";
	}

}
