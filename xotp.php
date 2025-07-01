<?php

/*

	xOTP: One Time Password helper class

	Example usage:
		$xotp=new xOTP(["key"=>"Hello key!"]);
		//$xotp=new xOTP(["secret"=>"JBSWY3DPEBVWK6JB"]); // alternative
		echo ($xotp->totpValidate(["totp"=>"123456"])?"VALID for ".$xotp->valid." seconds":"INVALID");

*/
class xOTP {

	protected $defaults=[
		"version"=>"0.1",
		"algorithm"=>"sha1",
		"algorithms"=>[
			"sha1"  =>["size"=>20],
			"sha256"=>["size"=>32],
			"sha512"=>["size"=>64],
		],
		"base32"=>'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',
		"digits"=>6,
		"period"=>30,
		"valid"=>0,
		"window"=>1,
	];
	protected $error="";
	protected $o;

	// constructor/getter/setter/isset options
	function __construct(array $o=[]) {
		$this->set($this->defaults);
		$this->set($o);
	}
	function __get($k) { return $this->o[$k]; }
	function __set($k, $v) {
		if ($k == "algorithm") $v=strtolower($v);
		else if ($k == "totp") $v=$this->trim($v);
		else if ($k == "url") {
			$ui=parse_url($v);
			if ($ui["scheme"] == "otpauth" && $ui["host"] == "totp") {
				$qs=[];
				if ($ui["query"]) parse_str($ui["query"], $qs);
				if ($ui["path"]) $this->label=substr(rawurldecode($ui["path"]), 1);
				if (strlen($qs["issuer"])) $this->o["issuer"]=$qs["issuer"];
				if (strlen($qs["secret"])) $this->secret($qs["secret"]);
			}
		}
		$this->o[$k]=$v;
		if ($k == "key") $this->key($v);
	}
	function __isset($k) { return isset($this->o[$k]); }
	function __unset($k) { unset($this->o[$k]); }

	// get defaults/get/set multiple parameters
	function defaults() { return $this->defaults; }
	function get() { return $this->o; }
	function set(array $a) { foreach ($a as $k=>$v) $this->__set($k, $v); }

	// trim OTP/Secret
	function trim($s) {
		return strtoupper(str_replace([" ", "\t", "\r", "\n", "="], "", (string)$s));
	}

	// get/set key
	function key($key=null) {
		if ($key !== null) {
			$this->o["key"]=$key;
			$this->o["secret"]=$this->base32enc($key);
		}
		return $this->key;
	}

	// get/set secret
	function secret($secret=null) {
		if ($secret !== null) {
			$secret=$this->trim($secret);
			$key=$this->base32dec($secret);
			if ($key === false) return false;
			$this->o["key"]=$key;
			$this->o["secret"]=$secret;
		}
		return $this->secret;
	}

	// encode Base32 key
	function base32enc($key, $pad=false) {
		$len=strlen($key);
		$s='';
		$remainder=0;
		$remainder_size=0;
		for ($i=0; $i < $len; $i++) {
			$b=ord($key[$i]);
			$remainder=($remainder<<8)|$b;
			$remainder_size+=8;
			while ($remainder_size > 4) {
				$remainder_size-=5;
				$c=$remainder&(31<<$remainder_size);
				$c>>=$remainder_size;
				$s.=$this->base32[$c];
			}
		}
		if ($remainder_size > 0) {
			$remainder<<=(5-$remainder_size);
			$c=$remainder&31;
			$s.=$this->base32[$c];
		}
		if ($pad) {
			$pad_size=(8-ceil(($len%5) * 8/5))%8;
			$s.=str_repeat('=', $pad_size);
		}
		return $s;
	}

	// decode Base32 secret
	function base32dec($secret) {
		$secret=$this->trim($secret);
		$s='';
		foreach (str_split($secret) as $c) {
			if ($c === "=") break;
			$i=strpos($this->base32, $c);
			if ($i === false) return $this->error('Invalid base32 character in secret');
			$s.=str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
		}
		$r='';
		foreach (str_split($s, 8) as $b) {
			$r.=chr(bindec(str_pad($b, 8, '0', STR_PAD_RIGHT)));
		}
		return $r;
	}

	// generate TOTP
	function totpGenerate(array $o=[]) {
		$this->set($o);

		// checks
		$this->valid=$this->defaults["valid"];
		if (!strlen($this->secret)) return $this->error("no secret specified.");
		if ($this->digits < 1) return $this->error("digits must be more than 1.");
		if ($this->period < 1) return $this->error("period must be one or more seconds.");
		if (!($algorithm=$this->algorithms[$this->algorithm])) return $this->error("algorithm not supported.");

		// decode Base32 secret to binary
		$binsecret=$this->base32dec($this->secret);
		if ($binsecret === false) return false;

		// moment
		$time=(($v=$o["time"])?$v:time());

		// base time period
		$base=floor($time/$this->period);

		// save remaining seconds
		$this->valid=round($this->period-($time/$this->period-$base)*$this->period);

		// pack time bytes
		$base_s=pack('N*', 0).pack('N*', $base);

		// generate hash
		$hash=hash_hmac(strtolower($this->algorithm), $base_s, $binsecret, true);

		// determine dynamic position to truncate hash
		$offset=ord($hash[$algorithm["size"]-1]) & 0xf;

		// extract code from 4 bytes from hash and apply mask
		$code=(
			((ord($hash[$offset]) & 0x7f) << 24) |
			((ord($hash[$offset + 1]) & 0xff) << 16) |
			((ord($hash[$offset + 2]) & 0xff) << 8) |
			(ord($hash[$offset + 3]) & 0xff)
		) % pow(10, $this->digits);

		// padding with leading zeros, if needed
		return str_pad($code, $this->digits, '0', STR_PAD_LEFT);

	}

	// validate TOTP
	function totpValidate(array $o=[]) {
		$this->set($o);
		$time=(($v=$o["time"])?$v:time());
		$this->window=abs($this->window);
		for ($i=-$this->window; $i <= $this->window; $i++) {
			if (($totp=$this->totpGenerate(["time"=>$time+($i*$this->period)])) === false) return false;
			if ($this->totp === $totp) return true;
		}
		return false;
	}

	// generate TOTP URL
	function totpURL(array $o=[]) {
		$this->set($o);

		// validate requisites to generate TOTP
		if ($this->totpGenerate() === false) return false;

		// return generated link
		return \x::link([
			"issuer"=>$this->issuer,
			"secret"=>$this->secret,
			"image"=>$this->image,
			"algorithm"=>($this->algorithm == $this->defaults["algorithm"]?null:$this->algorithm),
			"digits"=>($this->digits == $this->defaults["digits"]?null:$this->digits),
			"period"=>($this->period == $this->defaults["period"]?null:$this->period),
		], "otpauth://totp/".urlencode($this->label));

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
