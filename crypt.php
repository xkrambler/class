<?php

/*
	Cryptography helper class 0.2c
	Abstraction to MCrypt/OpenSSL libraries.
*/
class Crypt {

	public $algorithm="";
	public $mode="cbc";
	public $filter="base64uf";
	public $iv;
	public $iv_size;
	public $key;
	public $openssl;
	public $unpadding;
	protected $error=false;

	// init
	function __construct($o=array()) {
		$this->available($o);
	}

	// setup & check for libraries availability
	function available($o=array()) {
		$this->openssl  =(isset($o["openssl"])?$o["openssl"]:false);
		if (!isset($o["openssl"]) && function_exists("openssl_open")) $this->openssl=OPENSSL_RAW_DATA;
		$this->filter   =(isset($o["filter"])?$o["filter"]:"base64uf");
		$this->key      =(isset($o["key"])?$o["key"]:null);
		$this->mode     =(isset($o["mode"])?$o["mode"]:"cbc");
		$this->unpadding=(isset($o["unpadding"])?$o["unpadding"]:true);
		$this->algorithm=(isset($o["algorithm"])?$o["algorithm"]:($this->openssl?"aes-128":"blowfish"));
		$this->iv       =(isset($o["iv"])?$o["iv"]:null);
		if ($this->openssl && !function_exists("openssl_open")) return $this->error("OpenSSL module not available.");
		else if (!$this->openssl && !function_exists("mcrypt_cbc")) return $this->error("MCrypt module not available.");
		// get IV size
		$this->iv_size=($this->openssl
			?openssl_cipher_iv_length($this->realAlgorithm()."-".$this->mode)
			:mcrypt_get_iv_size($this->algorithm, $this->mode)
		);
		// generate IV
		$this->iv=(isset($o["iv"])
			?str_pad("", $this->iv_size, substr($o["iv"], 0, $this->iv_size))
			:str_pad("", $this->iv_size, "\0")
		);
		// all ok
		return true;
	}

	// get real algorithm depending on ciphering library
	function realAlgorithm() {
		if ($this->openssl) {
			switch ($this->algorithm) {
			case "blowfish": return "bf";
			}
		}
		return $this->algorithm;
	}

	// return supported algorithms
	function algorithms() {
		return ($this->openssl?openssl_get_cipher_methods():mcrypt_list_algorithms());
	}

	// get/set key
	function key($key=null) {
		if ($key !== null) $this->key=$key;
		return $key;
	}

	// get/set filter
	function filter($filter=null) {
		if ($filter !== null) $this->filter=$filter;
		return $filter;
	}

	// cipher
	function cipher($data) {
		$r=($this->openssl
			?openssl_encrypt($data, $this->realAlgorithm()."-".$this->mode, $this->key, $this->openssl, $this->iv)
			:mcrypt_encrypt($this->realAlgorithm(), $this->key, $data, $this->mode, $this->iv)
		);
		return ($r === false?$this->error("Cannot cipher: ".($this->openssl?openssl_error_string():(($v=error_get_last()) && isset($v["message"])?$v["message"]:"Unknown"))):$r);
	}

	// decipher
	function decipher($data) {
		$r=($this->openssl
			?openssl_decrypt($data, $this->realAlgorithm()."-".$this->mode, $this->key, $this->openssl, $this->iv)
			:$this->unpadding(mcrypt_decrypt($this->realAlgorithm(), $this->key, $data, $this->mode, $this->iv))
		);
		return ($r === false?$this->error("Cannot decipher: ".($this->openssl?openssl_error_string():(($v=error_get_last()) && isset($v["message"])?$v["message"]:"Unknown"))):$r);
	}

	// encrypt
	function encrypt($data) {
		return $this->filterEncode($this->filter, $this->cipher($data));
	}

	// decrypt
	function decrypt($data) {
		return $this->decipher($this->filterDecode($this->filter, $data));
	}

	// remove padding
	function unpadding($data) {
		return ($this->unpadding?str_replace("\0","",$data):$data);
	}

	// encode filter
	function filterEncode($filter, $data) {
		if (!$filter || !is_string($data)) return $data;
		switch ($filter) {
		case "base64": return base64_encode($data);
		case "base64uf": return $this->base64ufEncode($data);
		}
	}

	// decode filter
	function filterDecode($filter, $data) {
		if (!$filter || !is_string($data)) return $data;
		switch ($filter) {
		case "base64": return base64_decode($data);
		case "base64uf": return $this->base64ufDecode($data);
		}
	}

	// encode base64-URL-Friendly
	function base64ufEncode($d) {
		$d=base64_encode($d);
		$d=str_replace(["+", "/", "="], [".", "_", "-"], $d);
		return $d;
	}

	// decode base64-URL-Friendly
	function base64ufDecode($d) {
		$d=str_replace([".", "_", "-"], ["+", "/", "="], $d);
		return base64_decode($d);
	}

	// get/set error
	function error($error=null) {
		if ($error === null) return $this->error;
		$this->error=$error;
		if (isset($this->onok) && ($onko=$this->onko)) $r=$onko($this, $error);
		return false;
	}

	// throw error
	function err($exit=1) {
		$text=$this." ".($this->error?"ERROR: ".$this->error:"Undefined error");
		if (function_exists("perror")) perror($text, $exit);
		echo $text."\n";
		if ($exit) exit($exit);
	}

	// string with basic info
	function __toString() {
		return "(".get_class($this).")";
	}

}
