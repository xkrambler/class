<?php

class xTelegram {

	protected $o;

	function __construct($o) {
		$this->o=$o;
	}

	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }
	function __isset($n) { return isset($this->o[$n]); }

	// select options
	private function selectOptions($o, $fields) {
		$a=[];
		foreach ($fields as $f)
			$a[$f]=(isset($o[$f])?$o[$f]:$this->o[$f]);
		return $a;
	}

	// get updates
	function getUpdates($o=[]) {
		return $this->request(($o["to"]?$o["to"]:$this->to)."/getUpdates", $o);
	}

	// send message
	function sendMessage($o=[]) {
		return $this->request(($o["to"]?$o["to"]:$this->to)."/sendMessage", $this->selectOptions($o, [
			"chat_id",
			"text",
			"no_webpage",
		]));
	}

	// request to Telegram API
	function request($url, $fields=null) {
		if (!function_exists("curl_init")) return false;
		$ch=curl_init();
		$o=array(
			CURLOPT_URL=>"https://api.telegram.org/".$url,
			CURLOPT_FRESH_CONNECT=>1,
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_FORBID_REUSE=>1,
		);
		if (is_array($fields)) {
			$o+=[
				CURLOPT_POST=>is_array($fields),
				CURLOPT_POSTFIELDS=>http_build_query($fields),
			];
		}
		if ($this->curlopts)
			foreach ($this->curlopts as $n=>$v)
				$o[$n]=$v;
		curl_setopt_array($ch, $o);
		$r=curl_exec($ch);
		curl_close($ch);
		$json=json_decode($r, true);
		return ($json?$json:$r);
	}

}

// instantiate all classes defined in the setup
if (@$telegram_setup)
	foreach ($telegram_setup as $_n=>$_s)
		$$_n=new xTelegram($_s);
