<?php

namespace x;

// process execution helper
class Process {

	public $data; // custom data
	public $proc=null;
	public $out=null;
	public $err=null;
	public $status=null;
	public $rpipes=[];
	public $exit=null;
	public $pipes=[
		0=>['pipe', 'r'],
		1=>['pipe', 'w'],
		2=>['pipe', 'w'],
	];
	protected $o=[
		"buffer"=>64*1024,
	];

	// constructor and runner
	function __construct(array $o=[]) {
		$this->set($o);
		if (is_array(($v=$o["pipes"]))) $this->pipes=$v;
		if ($v=$o["data"]) $this->data=$v;
		if ($v=$o["run"]) $this->run($v);
		if ($v=$o["exec"]) $this->exec($v);
	}

	// destructor trys to join process
	function __destruct() {
		$this->join();
	}

	// get/set/isset
	function __get(string $k) { return (isset($this->o[$k])?$this->o[$k]:null); }
	function __set(string $k, $v) { $this->o[$k]=$v; }
	function __isset(string $k) { return isset($this->o[$k]); }

	// get/set options
	function get() { return $this->o; }
	function set(array $o=null) { if (is_array($o)) foreach ($o as $k=>$v) $this->o[$k]=$v; return $this->o; }

	// parse command/arguments
	function parse($o) {
		if (is_array($o)) return $o;
		if (!is_string($o)) return [];
		$a=[];
		$s='';
		$in_s=false;
		$in_d=false;
		$escape=false;
		for ($i=0; $i < strlen($o); $i++) {
			$c=$o[$i];
			if ($escape) {
				$s.=$c;
				$escape=false;
			} elseif ($c === '\\') {
				$escape=true;
			} elseif ($c === '"' && !$in_s) {
				$in_d=!$in_d;
			} elseif ($c === "'" && !$in_d) {
				$in_s=!$in_s;
			} elseif (ctype_space($c) && !$in_s && !$in_d) {
				if ($s !== '') {
					$a[]=$s;
					$s='';
				}
			} else {
				$s.=$c;
			}
		}
		if ($s !== '') $a[]=$s;
		return $a;
	}

	// start process
	function run($o=[]) {
		if (is_string($o)) $o=["cmd"=>$o];
		$this->set($o);
		$cmd=$this->parse($this->cmd);
		$arg=$this->parse($this->arg); if (is_array($arg) && $arg) foreach ($arg as $p) $cmd[]=$p;
		$this->out="";
		$this->err="";
		$this->time=microtime(true);
		$this->status=null;
		$this->exit=null;
		$this->proc=proc_open(
			$cmd,
			$this->pipes,
			$this->rpipes,
			($this->cwd?$this->cwd:null),
			($this->env?$this->env:null),
			($this->options?$this->options:null)
		);
		if (is_resource($this->proc)) {
			$this->status();
			stream_set_blocking($this->rpipes[1], 0);
			stream_set_blocking($this->rpipes[2], 0);
			if (is_string($this->in)) {
				fwrite($this->rpipes[0], $this->in);
			} else if (is_callable($this->in)) {
				$callback=$this->in;
				$callback($this, $this->rpipes[0]);
			}
			fflush($this->rpipes[0]);
			fclose($this->rpipes[0]);
		}
		return $this->proc;
	}

	// start process and wait for termination
	function exec($o=[]) {
		return (($v=$this->run($o))?$this->join():$v);
	}

	// current process status
	function status() {
		if ($this->proc) {
			$this->status=proc_get_status($this->proc);
			if ($this->exit !== null) $this->status["exitcode"]=$this->exit;
		}
		return $this->status;
	}

	// current PID of the running process
	function pid() {
		return (isset($this->status) && isset($this->status["pid"])?$this->status["pid"]:null);
	}

	// check if process is running
	function running() {
		if (!$this->proc) return false;
		//return ($this->proc && ($status=$this->status())?$status["running"]:false); // alternative
		$this->read();
		if (!feof($this->rpipes[1]) && !feof($this->rpipes[2])) return true; // out&err eof
		$this->exit(true);
		return false;
	}

	// process pipes and check if process is running
	function process() {
		$this->read();
		return $this->running();
	}

	// read output and error pipes
	function read() {
		return [$this->out(), $this->err()];
	}

	// read output pipe
	function out($flush=false) {
		if (!is_resource($this->rpipes[1])) return false;
		do {
			$b=fread($this->rpipes[1], $this->buffer);
			if (is_string($b) && strlen($b)) {
				$this->out.=$b;
				if (is_callable($this->output)) { $callback=$this->output; $callback($this, $b); }
			}
		} while ($flush && !feof($this->rpipes[1]));
		return $b;
	}

	// read error pipe
	function err($flush=false) {
		if (!is_resource($this->rpipes[2])) return false;
		$b=($flush?stream_get_contents($this->rpipes[2]):fread($this->rpipes[2], $this->buffer));
		if (is_string($b) && strlen($b)) {
			$this->err.=$b;
			if (is_callable($this->error)) { $callback=$this->error; $callback($this, $b); }
		}
		return $b;
	}

	// terminate process
	function term() {
		return $this->kill(15); // SIGTERM
	}

	// kill process (+signal)
	function kill($signal=9) {
		return proc_terminate($this->proc, $signal);
	}

	// return exit code (last or updated)
	function exit($update=null) {
		if ($update) {
			$this->status();
			$this->exit=$this->status["exitcode"];
			$this->status["time"]=microtime(true)-$this->time;
		}
		return $this->exit;
	}

	// join process
	function join() {
		if (!$this->proc) return false;

		// flush pipes and close
		stream_set_blocking($this->rpipes[1], 1); $this->out(true); @fclose($this->rpipes[1]);
		stream_set_blocking($this->rpipes[2], 1); $this->err(true); @fclose($this->rpipes[2]);

		// close process
		proc_close($this->proc);

		// destroy process
		$this->proc=null;

		// return exit code
		return $this->exit(true);

	}

	// debug string
	function __toString() {
		return trim("Process(".$this->cmd.") "
			.($this->proc?"OK":($this->status?"END":"ERROR"))
			.($this->status
				?""
					." pid=".$this->status["pid"]
					.($this->status["running"]?" running":" stopped")
					.($this->status["exitcode"] >= 0?" exitcode=".$this->status["exitcode"]:"")
					.($this->status["signaled"]?" signaled":"")
					.(($v=$this->status["termsig"])?" termsig=".$v:"")
					.(($v=$this->status["stopsig"])?" stopsig=".$v:"")
					.($this->status["cached"]?" cached":"")
				:""
			)
		);
	}

}
