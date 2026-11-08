<?php

/*
	Cryptography helper class 0.3
	Abstraction to MCrypt/OpenSSL libraries.

	New tokens are encrypted with the configured algorithm (AES-256-CBC is
	recommended) and prefixed with $marker, so legacy tokens (Blowfish,
	AES-128...) can still be detected and decrypted.
*/
class Crypt {

	public $algorithm="";
	public $mode="cbc";
	public $filter="base64uf";
	public $iv;
	public $iv_size;
	public $key="";
	public $openssl;
	public $unpadding=true;
	public $legacy=["blowfish", "aes-128"];
	public $marker="~v2~";
	protected $iv_seed="";
	protected $cipher_methods=null;
	protected $error=false;
	protected static $blowfish_loaded=false;
	protected static $blowfish_autoload=false;

	// init
	function __construct($o=array()) {
		$this->available($o);
	}

	// setup & check for libraries availability
	function available($o=array()) {
		$this->openssl=(isset($o["openssl"])?$o["openssl"]:(function_exists("openssl_open")?OPENSSL_RAW_DATA:false));
		if (isset($o["filter"]))    $this->filter=$o["filter"];
		if (isset($o["mode"]))      $this->mode=$o["mode"];
		if (isset($o["key"]))       $this->key=$o["key"];
		if (isset($o["unpadding"])) $this->unpadding=$o["unpadding"];
		if (isset($o["iv"]))        $this->iv_seed=$o["iv"];
		if (isset($o["legacy"]))    $this->legacy=(is_array($o["legacy"])?$o["legacy"]:[$o["legacy"]]);
		if (isset($o["marker"]))    $this->marker=$o["marker"];
		$this->algorithm=(isset($o["algorithm"])?$o["algorithm"]:($this->algorithm?$this->algorithm:($this->openssl?"aes-128":"blowfish")));
		if ($this->openssl && !function_exists("openssl_open")) return $this->error("OpenSSL module not available.");
		else if (!$this->openssl && !function_exists("mcrypt_cbc")) return $this->error("MCrypt module not available.");
		// get IV size
		$this->iv_size=($this->openssl
			?openssl_cipher_iv_length($this->ciphersuite())
			:mcrypt_get_iv_size($this->algorithm, $this->mode)
		);
		if ($this->iv_size === false || $this->iv_size < 0)
			return $this->error("Cannot initiate cipher: ".$this->ciphersuite());
		// generate IV
		$this->iv=(isset($o["iv"])
			?str_pad("", $this->iv_size, substr($o["iv"], 0, $this->iv_size))
			:str_pad("", $this->iv_size, "\0")
		);
		// all ok
		return true;
	}

	// get real algorithm depending on ciphering library
	function realAlgorithm($algorithm=null) {
		if ($algorithm === null) $algorithm=$this->algorithm;
		if ($this->openssl) {
			switch ($algorithm) {
			case "blowfish": return "bf";
			}
		}
		return $algorithm;
	}

	// get OpenSSL cipher suite (algorithm-mode), accepting "aes-256-cbc" too
	function ciphersuite($algorithm=null) {
		$algorithm=$this->realAlgorithm($algorithm);
		if (preg_match('/-(?:cbc|ecb|cfb|ofb|ctr|gcm|ccm|xts|wrap)$/i', $algorithm))
			return strtolower($algorithm);
		return strtolower($algorithm."-".$this->mode);
	}

	// return supported algorithms
	function algorithms() {
		if (!$this->openssl) return mcrypt_list_algorithms();
		if ($this->cipher_methods === null) $this->cipher_methods=openssl_get_cipher_methods();
		return $this->cipher_methods;
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
		if (!strlen($this->realAlgorithm())) return $this->error("Cannot cipher: no algorithm.");
		if (!strlen($this->mode)) return $this->error("Cannot cipher: no mode.");
		if (!strlen($this->key)) return $this->error("Cannot cipher: no key.");
		$r=($this->openssl
			?openssl_encrypt($data, $this->ciphersuite(), $this->key, $this->openssl, $this->iv)
			:mcrypt_encrypt($this->realAlgorithm(), $this->key, $data, $this->mode, $this->iv)
		);
		return ($r === false?$this->error("Cannot cipher: ".($this->openssl?openssl_error_string():(($v=error_get_last()) && isset($v["message"])?$v["message"]:"Unknown"))):$r);
	}

	// decipher
	function decipher($data) {
		if (!strlen($this->realAlgorithm())) return $this->error("Cannot cipher: no algorithm.");
		if (!strlen($this->mode)) return $this->error("Cannot cipher: no mode.");
		if (!strlen($this->key)) return $this->error("Cannot cipher: no key.");
		$r=($this->openssl
			?openssl_decrypt($data, $this->ciphersuite(), $this->key, $this->openssl, $this->iv)
			:$this->unpadding(mcrypt_decrypt($this->realAlgorithm(), $this->key, $data, $this->mode, $this->iv))
		);
		return ($r === false?$this->error("Cannot decipher: ".($this->openssl?openssl_error_string():(($v=error_get_last()) && isset($v["message"])?$v["message"]:"Unknown"))):$r);
	}

	// encrypt (new token format)
	function encrypt($data) {
		$r=$this->cipher($data);
		if ($r === false) return false;
		return $this->marker.$this->filterEncode($this->filter, $r);
	}

	// decrypt (new and legacy token formats)
	function decrypt($data, $validator=null) {
		if (!is_string($data) || !strlen($data)) return false;
		$m=(string)$this->marker;
		if ($m !== "" && strncmp($data, $m, strlen($m)) === 0) {
			$r=$this->decipher($this->filterDecode($this->filter, substr($data, strlen($m))));
			return ($r === false || ($validator && !$validator($r)) ? false : $r);
		}
		$raw=$this->filterDecode($this->filter, $data);
		if (!is_string($raw) || !strlen($raw)) return false;
		foreach ((array)$this->legacy as $algorithm) {
			if (!strlen($algorithm)) continue;
			$r=$this->legacyDecipher($algorithm, $raw);
			if ($r === false) continue;
			if ($validator === null || $validator($r)) return $r;
		}
		return false;
	}

	// decrypt JSON (tries legacy algorithms until valid JSON is found)
	function decryptJSON($data) {
		return $this->decrypt($data, function($s) {
			return is_array(json_decode($s, true));
		});
	}

	// decipher data using a legacy algorithm
	function legacyDecipher($algorithm, $data) {
		if (in_array($algorithm, ["blowfish", "bf", "bf-cbc"], true)) {
			$iv=$this->legacyIv("blowfish");
			if ($this->openssl && in_array("bf-".$this->mode, $this->algorithms(), true)) {
				$r=@openssl_decrypt($data, "bf-".$this->mode, $this->key, $this->openssl, $iv);
				if ($r !== false) return $r;
			}
			if (!$this->loadBlowfish()) return false;
			try {
				$bf=new \phpseclib3\Crypt\Blowfish($this->mode);
				$bf->setKey($this->key);
				$bf->setIV($iv);
				$r=$bf->decrypt($data);
				return ($r === false?false:$r);
			} catch (\Throwable $e) {
				return false;
			}
		}
		if (!$this->openssl) return false;
		$cipher=$this->ciphersuite($algorithm);
		if (!in_array($cipher, $this->algorithms(), true)) return false;
		$r=@openssl_decrypt($data, $cipher, $this->key, $this->openssl, $this->legacyIv($algorithm));
		return ($r === false?false:$r);
	}

	// get IV for a legacy algorithm
	function legacyIv($algorithm) {
		$size=($this->openssl
			?openssl_cipher_iv_length($this->ciphersuite($algorithm))
			:($algorithm === "blowfish" ? 8 : 16)
		);
		if ($size === false || $size < 0) $size=($algorithm === "blowfish" ? 8 : 16);
		return str_pad("", $size, substr((string)$this->iv_seed, 0, $size));
	}

	// load phpseclib's pure-PHP Blowfish (for OpenSSL 3 without bf-cbc)
	function loadBlowfish() {
		if (self::$blowfish_loaded || class_exists("phpseclib3\\Crypt\\Blowfish")) return self::$blowfish_loaded=true;
		$bases=[
			__DIR__."/../vendor/phpseclib/phpseclib/phpseclib/",
			__DIR__."/../fac/vendor/phpseclib/phpseclib/phpseclib/",
		];
		if (!self::$blowfish_autoload) {
			self::$blowfish_autoload=true;
			spl_autoload_register(function($class) use ($bases) {
				$prefix="phpseclib3\\";
				if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
				$file=str_replace("\\", "/", substr($class, strlen($prefix))).".php";
				foreach ($bases as $base) {
					if (is_file($base.$file)) {
						require_once($base.$file);
						return;
					}
				}
			});
		}
		if (class_exists("phpseclib3\\Crypt\\Blowfish")) return self::$blowfish_loaded=true;
		foreach ([__DIR__."/../vendor/autoload.php", __DIR__."/../fac/vendor/autoload.php"] as $autoload) {
			if (is_file($autoload)) {
				require_once($autoload);
				if (class_exists("phpseclib3\\Crypt\\Blowfish")) return self::$blowfish_loaded=true;
			}
		}
		return self::$blowfish_loaded=false;
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
