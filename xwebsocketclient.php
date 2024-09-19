<?php

/*

	Light Class to instance WebSocket Clients based on RFC6455 and KISS principle v0.2
	(c)2024 by Pablo Rodríguez Rey (https://mr.xkr.es/)
	Thanks to Simon Rigét (paragi) for the original PHP WebSocket Client code (https://github.com/paragi/PHP-websocket-client).
	Distributed under the GPL version 3 license (http://www.gnu.org/licenses/gpl-3.0.html)

	Example usage:

		$wsc=new \x\WebSocketClient([
			"url"=>"http://10.0.0.1:80/ws/", // WebSocket URL
			//"headers"=>["X-Header-Test: OK"], // array of headers
			//"timeout"=>3, // default 3 second
			//"persistant"=>false, // persistant connection, default false
			//"context"=>stream_context_get_default(), // context
			//"key"=>base64_encode(openssl_random_pseudo_bytes(16)), // custom key
		]);
		if (!$wsc->connect()) $wsc->err(); // connect
		//if (!$wsc->send('{"hello":"world"}') $wsc->err(); // send RAW data
		if (!$wsc->data(["hello"=>"world"])) $wsc->err(); // send JSON data
		//print_r($wsc->recv()); // wait for RAW data
		print_r($wsc->data()); // wait for data and decode JSON
		$wsc->close(); // disconnect

*/

namespace x;

class WebSocketClient {

	public $con;
	protected $o=[];

	// constructor/get/set/isset
	function __construct(array $o=[]) { $this->o=$o; }
	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }
	function __isset($n) { return isset($this->o[$n]); }

	// return as string
	function __toString() { return __CLASS__; }

	// connect, returns connection if successfull, or false if any error
	function connect() {

		// parse URL
		if (!strlen($this->url)) return $this->error("No websocket URL but required (url).");
		$url=parse_url($this->url);

		// generate a key (to convince server that the update is not random)
		// the key is for the server to prove it is websocket aware. (we know it is)
		if (!$this->key) $this->key=base64_encode(openssl_random_pseudo_bytes(16));

		// header
		$header="GET ".$url["path"]." HTTP/1.1\r\n"
			."Host: ".$url["host"]."\r\n"
			."pragma: no-cache\r\n"
			."User-Agent: x/websocketclient\r\n"
			."Upgrade: WebSocket\r\n"
			."Connection: Upgrade\r\n"
			."Sec-WebSocket-Key: ".$this->key."\r\n"
			."Sec-WebSocket-Version: 13\r\n"
		;

		// add extra headers
		if (!empty($headers))
			foreach ($headers as $h)
				$header.=$h."\r\n";

		// add end of header marker
		$header.="\r\n";

		// prepare data and parameters
		$url["host"]=(($v=$url["host"])?$v:"127.0.0.1");
		if ($url["port"] < 1) $url["port"]=($url["ssl"]?443:80);
		if ($this->timeout??0 < 1) $this->timeout=10;
		if ($this->maxheader??0 < 1) $this->maxheader=4096;
		$ssl=in_array($url["scheme"], ["https", "ssl"]);
		$address=($ssl?'ssl://':'').$url["host"].':'.$url["port"];
		$flags=STREAM_CLIENT_CONNECT|($this->persistant?STREAM_CLIENT_PERSISTENT:0);
		$ctx=(isset($this->context)?$this->context:stream_context_create());
		$errno=0;
		$errstr="";

		// connect socket client
		$con=@stream_socket_client($address, $errno, $errstr, $this->timeout, $flags, $ctx);
		if ($con === false) return $this->error("Unable to connect to websocket server #".$errno.": ".$errstr);

		// set timeout
		stream_set_timeout($con, $this->timeout);

		// websocket upgrade
		if (!$this->persistant || ftell($con) === 0) {

			// request upgrade to websocket
			$rc=fwrite($con, $header);
			if (!$rc) return $this->error("Unable to send upgrade header to websocket server #".$errno.": ".$errstr);

			// read response into an assotiative array of headers, fails if upgrade failes
			$response_header=fread($con, $this->maxheader);

			// status code 101 indicates that the WebSocket handshake has completed
			if (stripos($response_header, ' 101 ') === false || stripos($response_header, 'Sec-WebSocket-Accept: ') === false)
				return $this->error("Server did not accept to upgrade connection to websocket: ".$response_header);

			// the key we send is returned, concatenate with "258EAFA5-E914-47DA-95CA-
			// C5AB0DC85B11" and then base64-encoded. one can verify if one feels the need...

		}

		// return connection handler
		$this->con=$con;
		return $con;

	}

	// close connection
	function close() {
		if ($this->con) {
			fclose($this->con);
			unset($this->con);
		}
	}

	// send raw data
	function send(string $data, bool $final=true, bool $binary=true) {
		if (!$this->con) return $this->error("No connection established");

		// assemble header: FINal 0x80 | Mode (0x02 binary, 0x01 text)
		$header=chr(($final?0x80:0x00)|($binary?0x02:0x01));

		// mask 0x80 | payload length (0-125)
		if (strlen($data) < 126) $header.=chr(0x80|strlen($data));
		elseif (strlen($data) < 0xFFFF) $header.=chr(0x80|126).pack("n", strlen($data));
		else $header.=chr(0x80|127).pack("N", 0).pack("N", strlen($data));

		// add mask
		$mask=pack("N", rand(1, 0x7FFFFFFF));
		$header.=$mask;

		// mask application data
		for ($i=0; $i < strlen($data); $i++) $data[$i]=chr(ord($data[$i]) ^ ord($mask[$i % 4]));

		// write
		$n=fwrite($this->con, $header.$data);
		if ($n === false) return $this->error('Unable to write to websocket');

		// return written bytes
		return $n;

	}

	// read len size with error control
	private function fread(int $len) {
		if (!$this->con) return $this->error("No connection established");
		$s=fread($this->con, $len);
		return $s;
	}

	// receive raw message
	function recv() {
		$data="";
		do {

			// read header
			if (!($header=$this->fread(2))) return $this->error("Reading header from websocket failed.");

			// decode
			$opcode=ord($header[0])&0x0F;
			$final=ord($header[0])&0x80;
			$masked=ord($header[1])&0x80;
			$payload_len=ord($header[1])&0x7F;

			// get payload length extensions
			$ext_len=0;
			if ($payload_len >= 0x7E) {
				$ext_len=2;
				if ($payload_len == 0x7F) $ext_len=8;
				if (!($header=$this->fread($ext_len))) return $this->error("Reading payload length from websocket failed.");;
				// set extented payload length
				$payload_len=0;
				for ($i=0; $i < $ext_len; $i++) $payload_len+=ord($header[$i])<<($ext_len-$i-1)*8;
			}

			// get mask key
			if ($masked) {
				if (!($mask=$this->fread(4))) return $this->error("Reading mask from websocket failed.");;
			}

			// get payload
			$frame_data='';
			while ($payload_len > 0) {
				if (!($frame=$this->fread($payload_len))) return $this->error("Reading payload from websocket failed.");;
				$payload_len-=strlen($frame);
				$frame_data.=$frame;
			}

			// handle ping requests (sort of) send pong and continue to read
			if ($opcode == 9) {
				// assamble header: FINal 0x80 | Opcode 0x0A + Mask on 0x80 with zero payload
				fwrite($this->con, chr(0x8A).chr(0x80).pack("N", rand(1, 0x7FFFFFFF)));
				continue;
			// close
			} elseif ($opcode == 8) {
				$this->close();
			// 0=continuation frame, 1=text frame, 2=binary frame
			} elseif ($opcode < 3) {
					// unmask data
					$data_len=strlen($frame_data);
					if ($masked) {
						for ($i=0; $i < $data_len; $i++)
							$data.=$frame_data[$i]^$mask[$i%4];
					} else {
						$data.=$frame_data;
					}
			} else {
				continue;
			}

		} while (!$final);
		return $data;
	}

	// read/send JSON data
	function data($data=null) {
		return ($data === null
			?json_decode($this->recv(), true)
			:$this->send(json_encode($data))
		);
	}

	// get/set last error
	function error($error=null) {
		if ($error !== null) {
			$this->error=$error;
			return false;
		}
		return $this->error;
	}

	// dump error
	function err($doexit=1) {
		perror($this->error, $doexit);
	}

}
