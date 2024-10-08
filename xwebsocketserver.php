<?php

/*

	Light Class to create WebSocket Servers based on RFC6455 and KISS principle v0.2
	(c)2017 by Pablo Rodríguez Rey (https://mr.xkr.es/)
	Distributed under the GPL version 3 license (http://www.gnu.org/licenses/gpl-3.0.html)

	Example usage:

		$ws=new \x\WebSocketServer([
			"port"=>52303, // TCP port (required)
			//"protocol"=>"example_proto",
			"onconnect"=>function($ws, $client, $ip){
				echo $ip.":".$client." connect\n";
			},
			"onready"=>function($ws, $client, $ip, $headers){
				echo $ip.":".$client." ready\n";
			},
			"onclose"=>function($ws, $client, $ip){
				echo $ip.":".$client." close\n";
			},
			"onsend"=>function($ws, $client, $ip, $raw, $sent){
				echo $ip.":".$client." send raw(".$sent.")=".$raw."\n";
			},
			"onrecv"=>function($ws, $client, $ip, $raw){
				echo $ip.":".$client." recv raw(".strlen($raw).")=".$raw."\n";
				if ($data == "TEST") $ws->send($client, "TESTED!");
			},
			"ondata"=>function($ws, $client, $ip, $data, $raw){
				if ($data["ping"]) $ws->data($client, ["pong"=>$data["ping"]]);
			},
			"onstatuscode"=>function($ws, $client, $ip, $statuscode, $statuscodetext){
				echo $ip.":".$client." statuscode: statuscode=".$statuscode.($statuscodetext?":".$statuscodetext:"")."\n";
			},
			"onopcode"=>function($ws, $client, $ip, $opcode, $raw){
				echo $ip.":".$client." opcode: opcode=".$opcode." raw(".strlen($raw).")=".$raw."\n";
			},
		]);

		// start server and create main loop
		if ($ws->start()) {
			echo "started\n";
			$ws->loop();
		}
		echo "An error has ocurred and websocket server did not started.\n";
		exit(1);

*/

namespace x;

class WebSocketServer {

	public $socket=false;
	public $clients=[];
	public $headers=[];
	public $statuscodes=[
		1000=>"Normal Closure",
		1001=>"Going Away",
		1002=>"Protocol error",
		1003=>"Unsupported Data",
		1004=>"-Reserved-",
		1005=>"No Status Rcvd",
		1006=>"Abnormal Closure",
		1007=>"Invalid frame payload data",
		1008=>"Policy Violation",
		1009=>"Message Too Big",
		1010=>"Mandatory Ext.",
		1011=>"Internal Server Error",
		1015=>"TLS handshake",
	];
	protected $o=[];
	protected $ncid=0;

	// constructor
	function __construct($o=[]) {
		$this->set($o);
		//register_shutdown_function([$this, '__destruct']);
	}

	// destructor
	function __destruct() {
		$this->stop();
	}

	// get/set/isset setup configuration
	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }
	function __isset($n) { return isset($this->o[$n]); }

	// initialize setup and parse values
	function set($o=[]) {
		if ($o["loopdelay"]??0  < 1) $o["loopdelay"] =10000; // 10ms
		if ($o["headersize"]??0 < 1) $o["headersize"]=1024*64; // 64KB
		if ($o["buffersize"]??0 < 1) $o["buffersize"]=1024*4; // 4KB
		if ($o["packetsize"]??0 < 1) $o["packetsize"]=4*1024*1024+1024; // 4MB+1KB
		$this->o=$o;
	}

	// create main loop (will never end)
	function loop() {
		while (true) {
			if (!$this->iterate()) usleep($this->loopdelay); // delay to prevent active waiting and waste CPU
		}
	}

	// start server
	function start() {
		$e=error_reporting();
		error_reporting(0);
		// create TCP/IP sream socket
		if (!$this->socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
			error_reporting($e);
			return $this->errorSocket();
		}
		// reuseable port
		if (!socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
			error_reporting($e);
			return $this->errorSocket($this->socket);
		}
		// bind socket to specified port
		if (!(@socket_bind($this->socket, 0, $this->port))) {
			error_reporting($e);
			return $this->errorSocket($this->socket);
		}
		// listen to port
		if (!socket_listen($this->socket)) {
			error_reporting($e);
			return $this->errorSocket($this->socket);
		}
		// create & add listening socket to the list
		$this->clients=[$this->socket];
		// on start event
		if ($event=$this->onstart) $event($this, $socket);
		// all OK
		return true;
	}

	// close listening socket
	function stop() {
		if ($this->socket) {
			socket_close($this->socket);
			$this->socket=false;
		}
	}

	// close client socket and remove from client list
	function close($client){
		if ($socket=$this->clients[$client]) {
			$ip="";
			@socket_getpeername($socket, $ip);
			socket_close($socket);
			if ($event=$this->onclose) $event($this, $client, $ip);
			unset($this->clients[$client]);
		}
	}

	// mask outgoing message
	function mask($data) {
		$b1=0x80 | (0x1 & 0x0f);
		$len=strlen($data);
		if ($len < 126) $header=pack('CC', $b1, $len);
		elseif ($len >= 126 && $len <= 65535) $header=pack('CCn', $b1, 126, $len);
		else $header=pack('CCJ', $b1, 127, $len); // if ($len > 65535)
		return $header.$data;
	}

	// unmask incoming framed message
	function unmask($packet) {

		// if packet does not contain opcode or length, not completed
		if (strlen($packet) < 2) return false;

		// decode using RFC
		$opcode=ord($packet[0]) & 0x0F;
		$len=ord($packet[1]) & 127;
		if ($len == 126) {
			$masks=substr($packet, 4, 4);
			$len=(ord($packet[2]) << 8)+ord($packet[3]);
			$payload=substr($packet, 8, $len);
			$packet=substr($packet, 8+$len);
		} elseif ($len == 127) {
			$masks=substr($packet, 10, 4);
			$len=
				(ord($packet[2]) << 56) + (ord($packet[3]) << 48) +
				(ord($packet[4]) << 40) + (ord($packet[5]) << 32) +
				(ord($packet[6]) << 24) + (ord($packet[7]) << 16) +
				(ord($packet[8]) <<  8) +  ord($packet[9])
			;
			$payload=substr($packet, 14, $len);
			$packet=substr($packet, 14+$len);
		} else {
			$masks=substr($packet, 2, 4);
			$payload=substr($packet, 6, $len);
			$packet=substr($packet, 6+$len);
		}

		// incomplete messages, do not unmask
		if (strlen($payload) < $len) return false;

		// unmask
		$data="";
		for ($i=0; $i < strlen($payload); ++$i)
			$data.=$payload[$i]^$masks[$i%4];

		// return unmasked opcode, data, length and return next packet
		return [
			"opcode"=>$opcode,
			"data"  =>$data,
			"len"   =>$len,
			"packet"=>$packet,
		];

	}

	// send data JSON-encoded to all connected clients
	function dataAll($data) {
		return $this->sendAll(json_encode($data));
	}

	// send data JSON-encoded to one client
	function data($client, $data) {
		return $this->send($client, json_encode($data));
	}

	// send message to all clients
	function sendAll($msg) {
		if ($this->clients)
			foreach ($this->clients as $client=>$socket)
				if ($client)
					$this->send($client, $msg);
		return true;
	}

	// send string to one client
	function send($client, $msg) {
		if ($socket=$this->clients[$client]) {
			$data=$this->mask($msg);
			$sent=socket_write($socket, $data, strlen($data));
			if ($event=$this->onsend) {
				@socket_getpeername($socket, $ip);
				$event($this, $client, $ip, $msg, $sent);
			}
			return $sent;
		}
		return null;
	}

	// handshake for new client
	function handshaking($socket, $header) {

		// reset headers
		$this->headers=[];
		$lines=preg_split("/\r\n/", $header);

		// obtain method, url and http protocol
		list($this->method, $this->url, $this->http)=explode(" ", $lines[0]);

		// parse headers
		foreach ($lines as $line) {
			$line=chop($line);
			if (preg_match('/\A(\S+): (.*)\z/', $line, $matches))
				$this->headers[$matches[1]]=$matches[2];
		}
		$sec_key=$this->headers['Sec-WebSocket-Key'];
		$sec_accept=base64_encode(pack('H*', sha1($sec_key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

		// handshaking header
		$upgrade=""
			."HTTP/1.1 101 Web Socket Protocol Handshake\r\n"
			."Upgrade: websocket\r\n"
			."Connection: Upgrade\r\n"
			.($this->host?"WebSocket-Origin: ".$this->host."\r\n":"")
			//."WebSocket-Location: ws://".$this->host.":".$this->port."/test/\r\n"
			.($this->protocol?"Sec-WebSocket-Protocol: ".$this->protocol."\r\n":"")
			."Sec-WebSocket-Accept: ".$sec_accept."\r\n"
			."\r\n"
		;
		socket_write($socket, $upgrade, strlen($upgrade));

	}

	// iterate
	function iterate() {

		// repeat until no changes found
		$iterations=0;
		do {

			// initial client list
			if (!($changed=$this->clients)) return;

			// returns the changed socket resources in $changed array
			$null=null;
			socket_select($changed, $null, $null, 0, 10);

			// check for new socket
			if (in_array($this->socket, $changed)) {

				// accept new socket and add to our client list
				$socket_new=socket_accept($this->socket);

				// add socket to our clients
				$client=++$this->ncid;
				$this->clients[$client]=$socket_new;

				// get remote IP address
				@socket_getpeername($socket_new, $ip);

				// make room for new socket
				$found_socket=array_search($this->socket, $changed);
				unset($changed[$found_socket]);

				// connection event
				if ($event=$this->onconnect) $event($this, $client, $ip);

				// read header
				$buf=@socket_read($socket_new, $this->headersize);
				if ($p=strpos($buf, "\r\n\r\n")) {
					// perform websocket handshake
					$this->handshaking($socket_new, substr($buf, 0, $p+4));
					$buf=substr($buf, $p+4);
					// connection event
					if ($event=$this->onready) $event($this, $client, $ip, $this->headers);
				} else {
					$this->close($client);
				}

			}

			// loop through all sockets with new activity
			if ($changed) foreach ($changed as $socket) {

				// get client index
				$client=array_search($socket, $this->clients);

				// get remote IP address
				if (@socket_getpeername($socket, $ip)) {

					// check for any incomming data
					$packet="";
					while (socket_recv($socket, $buf, $this->buffersize, 0) > 0) {

						// add to packet buffer
						$packet.=$buf;

						// check packet size limit
						if (strlen($packet) < $this->packetsize) {

							// unmask data, if false, packet is not complete, continue receiving
							while ($info=$this->unmask($packet)) {
								//echo $info["len"]."/".strlen($packet)."/".strlen($info["packet"]);

								// copy next buffer
								$packet=$info["packet"];

								// check opcodes
								if (
									   $info["opcode"] == 1 // text
									|| $info["opcode"] == 2 // binary
								) {

									// call recv/data events (RAW/JSON)
									if ($event=$this->onrecv) $event($this, $client, $ip, $info["data"]);
									if ($event=$this->ondata) $event($this, $client, $ip, json_decode($info["data"], true), $info["data"]);

								} else {

									// opcodes events
									if (strlen($info["data"]) == 2) {
										$statuscode=(ord($info["data"][0])*256+ord($info["data"][1]));
										if ($event=$this->onstatuscode) $event($this, $client, $ip, $info["opcode"], $statuscode, $this->statuscodes[$statuscode]);
									} else {
										if ($event=$this->onopcode) $event($this, $client, $ip, $info["opcode"], $info["data"]);
									}

									// unknown opcodes cause disconnection
									$this->close($client);

								}
							}

							// exit this loop
							if (!strlen($packet)) break 2;

						} else {

							// reset packet
							$packet="";

						}

					}

					// check read error sockets
					$buf=@socket_read($socket, $this->buffersize, PHP_NORMAL_READ);
					if ($buf === false) $this->close($client);

				} else {

					// disconnect if error on socket_getpeername
					$this->close($client);

				}

				// record iterations
				$iterations++;

			}

		// while we have any change, iterate again
		} while ($changed);

		// returns the number of active iterations
		return $iterations;

	}

	// set socket error
	function errorSocket($socket=null) {
		$n=socket_last_error($socket);
		return $this->error("Error #".$n.": ".socket_strerror($n));
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

	// return as string
	function __toString() {
		return __CLASS__;
	}

}
