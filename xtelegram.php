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
			if (isset($o[$f]) || isset($this->o[$f]))
				$a[$f]=(isset($o[$f])?$o[$f]:$this->o[$f]);
		return $a;
	}

	// multipart
	function multipartFormData(array $formdata) {
		$boundary=md5(microtime());
		$mime="Content-Type: multipart/form-data; boundary=".$boundary;
		$body="";
		if (is_array($formdata)) {
			foreach ($formdata as $fd) {
				$body.='--'.$boundary."\r\n"
					.'Content-Disposition: form-data;'
						.' name="'.$fd["name"].'"'
						.($fd['filename']?'; filename="'.basename($fd['filename']).'"':'')
						."\r\n"
					.($fd['mime']?'Content-Type: '.$fd['mime']."\r\n":'')
					//.'Content-Transfer-Encoding: base64'."\r\n"
					//.chunk_split(base64_encode($fd['data']))
					."\r\n".$fd['data']."\r\n"
				;
			}
			$body.='--'.$boundary.'--'."\r\n";
		}
		return [
			"mime"=>$mime,
			"body"=>$body,
		];
	}

	// get updates
	function getUpdates($o=[]) {
		return $this->request(($o["to"]?$o["to"]:$this->to)."/getUpdates", $this->selectOptions($o, [
			"offset",
			"limit",
			"timeout",
			"allowed_updates",
		]));
	}

	// send document
	function sendDocument($o=[]) {
		return $this->requestMultipart(($o["to"]?$o["to"]:$this->to)."/sendDocument", $this->selectOptions($o, [
			"chat_id",
			"document",
			"no_webpage",
		]));
	}

	// send message
	function sendMessage($o=[]) {
		return $this->request(($o["to"]?$o["to"]:$this->to)."/sendMessage", $this->selectOptions($o, [
			"chat_id",
			"text",
			"no_webpage",
		]));
	}

	// send message
	function sendPhoto($o=[]) {
		return $this->requestMultipart(($o["to"]?$o["to"]:$this->to)."/sendPhoto", $this->selectOptions($o, [
			"chat_id",
			"photo",
			"no_webpage",
		]));
	}

	// get file info - https://api.telegram.org/bot348307714:AAHMNRMq66c_KgQ9TEjyVaUhr614nyxvw4M/getFile?file_id=BQACAgQAAx0Ca-f34gACCOtnWBkYNoPceD_LdNCms_4Tmto2owACDBQAAk0VwFKjMg4tHd_QmjYE
	function getFile($o=[]) {
		return $this->request(($o["to"]?$o["to"]:$this->to)."/getFile", $this->selectOptions($o, [
			"file_id",
		]));
		// https://api.telegram.org/file/bot<token>/<file_path>, where <file_path>
		return $this->request("file/".($o["to"]?$o["to"]:$this->to)."/".$o["file_path"], $this->selectOptions($o, [
			"file_id",
		]));
	}

	// get file - https://api.telegram.org/file/bot<token>/<file_path>, where <file_path>
	function file($o=[]) {
		if (!($r=$this->getFile($o)) || !$r["ok"]) return false;
		$data=$this->request("file/".($o["to"]?$o["to"]:$this->to)."/".$r["result"]["file_path"]);
		return $r["result"]+["file_data"=>$data];
	}

	// request to Telegram API
	function request($url, $fields=null, array $o=[]) {
		if (!function_exists("curl_init")) return false;
		if (is_array($fields)) {
			$o+=[
				CURLOPT_POST=>is_array($fields),
				CURLOPT_POSTFIELDS=>http_build_query($fields),
			];
		}
		$r=$this->curlAPI($url, $o);
		$json=json_decode($r, true);
		return ($json?$json:$r);
	}

	// request to Telegram API
	function requestMultipart($url, $fields=null, array $o=[]) {
		if (is_array($fields)) {

			$formdata=[];
			foreach ($fields as $k=>$v) {
				if (is_array($v)) $formdata[]=$v+["name"=>$k];
				else $formdata[]=["name"=>$k, "data"=>$v];
			}
			$multipart=$this->multipartFormData($formdata);
			//debug($multipart);exit;

			$o+=[
				CURLOPT_POST=>true,
				CURLOPT_POSTFIELDS=>$multipart["body"],
				CURLOPT_HTTPHEADER=>[
					"Expect: 100-continue",
					$multipart["mime"],
				],
			];

		}
		$r=$this->curlAPI($url, $o);
		$json=json_decode($r, true);
		return ($json?$json:$r);
	}

	// request to CURL Telegram API
	function curlAPI($url, array $o=[]) {
		return $this->curl([
			"url"=>"https://api.telegram.org/".$url,
			//"url"=>"http://127.0.0.1:8080/".$url,
			"opt"=>$o,
		]);
	}

	// request to CURL
	function curl(array $o=[]) {
		if (!function_exists("curl_init")) return false;
		$ch=curl_init();
		$opt=[
			CURLOPT_URL=>$o["url"],
			CURLOPT_FRESH_CONNECT=>1,
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_FORBID_REUSE=>1,
			//CURLOPT_CONNECTTIMEOUT=>5,
			//CURLOPT_TIMEOUT=>5,
		];
		if (is_array($o["opt"]))
			foreach ($o["opt"] as $n=>$v)
				$opt[$n]=$v;
			//echo "<pre>";debug($opt);exit;
		curl_setopt_array($ch, $opt);
		$r=curl_exec($ch);
		$this->curl=curl_getinfo($ch);
		curl_close($ch);
		return $r;
	}

}

// instantiate all classes defined in the setup
if (isset($telegram_setup))
	foreach ($telegram_setup as $_n=>$_s)
		$$_n=new xTelegram($_s);
