<?php

// Persistent Session implementation
class PSession extends \xData {

	protected $defaults=[
		"table"=>"sessions", // database table name
		"expiration"=>(60*60*24*30), // expiration time (30 day)
		"revalidate"=>60*15, // revalidation time (15 min)
		"samesite"=>"Lax", // None, Lax, Secure
		"secure"=>true, // conditional Secure cookie if over HTTPS
	];

	// extended cookie expiration
	function expiration() {
		$is_secure=\x::ishttps();
		$expiration=[
			"expires"=>time()+$this->expiration,
			"samesite"=>($this->secure && $is_secure?"Secure":$this->samesite),
			"secure"=>$is_secure,
		];
		\x::setcookie(session_name(), session_id(), $expiration);
		return $expiration;
	}

	// session path (if defined)
	function path() {
		$path=($this->path?$this->path.(substr($this->path, -1, 1) != "/"?"/":""):false);
		if ($path && !is_dir($path)) mkdir($path, 0775, true);
		return $path;
	}

	// session file (if path defined)
	function file(string $sid) {
		return (($path=$this->path())?$path.$sid:false);
	}

	// expired session files cleanup
	function filesCleanup() {
		$err=0;
		if ($p=$this->path()) {
			$d=dir($p);
			while ($e=$d->read()) {
				if (is_file($p.$e) && ($m=filemtime($p.$e)) && (time()-$m) > $this->expiration) {
					if (!@unlink($p.$e)) $err++;
				}
			}
			$d->close();
		}
		return ($err?false:true);
	}

	// save session data in file (if defined)
	function fileSave($sdata) {
		if ($f=$this->file($sdata["sid"])) {
			if (!@file_put_contents($f, json_encode($sdata))) return false;
		}
		return true;
	}

	// create sessions database
	function dbCreate() {
		if (!$this->db->atomic("
			CREATE TABLE ".$this->db->sqltable($this->table)." (
				`sid` VARCHAR(40) NOT NULL,
				`updated` DATETIME NOT NULL,
				`expires` DATETIME NOT NULL,
				`ip` VARCHAR(64) NULL DEFAULT NULL,
				`useragent` VARCHAR(255) NULL DEFAULT NULL,
				`userdata` VARCHAR(4096) NULL DEFAULT NULL,
				PRIMARY KEY (`sid`) USING BTREE,
				INDEX `expires` (`expires`) USING BTREE
			)
			COMMENT='Persistent Sessions'
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB
		")) $this->db->err();
		return true;
	}

	// persistent session login
	function login($userdata=null) {

		// set cookie expiration
		$session=$this->expiration();

		// session data
		$sdata=[
			"sid"      =>session_id(),
			"updated"  =>date("Y-m-d H:i:s"),
			"expires"  =>date("Y-m-d H:i:s", $session["expires"]),
			"ip"       =>$_SERVER["REMOTE_ADDR"],
			"useragent"=>$_SERVER["HTTP_USER_AGENT"],
			"userdata" =>$userdata,
		];

		// delete expired sessions and save current session
		if ($this->db) {
			do {
				$retry=false;
				if (!$this->db->atomic([
					$this->db->sqldelete($this->table, "expires<NOW()"),
					$this->db->sqlreplace($this->table, $sdata),
				])) {
					if ($this->db->errnum() == 1146) {
						if ($this->dbCreate()) $retry=true;
					} else {
						$this->db->err();
					}
				}
			} while ($retry);
		}
		$this->filesCleanup();
		if (!$this->fileSave($sdata)) return false;

		// all ok
		return true;

	}

	// persistent session logout
	function logout() {
		if (!($sid=session_id())) return false;
		if ($this->db) {
			if (!$this->db->atomic($this->db->sqldelete($this->table, ["sid"=>$sid]))) $this->db->err();
		}
		if ($f=$this->file($sid)) {
			if (file_exists($f)) if (!@unlink($f)) return false;
		}
		return true;
	}

	// persistent session load
	function load() {
		if ($sid=$_COOKIE[session_name()]) {
			if ($this->db) {
				if (!$this->db->query("SELECT * FROM ".$this->db->sqltable($this->table)." WHERE sid='".$this->db->escape($sid)."' AND expires>NOW()")) $this->db->err();
				$sdata=$this->db->row();
				$this->db->freequery();
				return $sdata;
			}
			if ($f=$this->file($sid)) {
				if (file_exists($f)) {
					$sdata=@json_decode(file_get_contents($f), true);
					return $sdata;
				}
			}
		}
		return false;
	}

	// revalidate cookie
	function revalidate() {
		return (!$_SESSION["psession"] || (time()-$_SESSION["psession"] > $this->revalidate)?$this->check():false);
	}

	// persistent session check
	function check() {

		// try load session data
		if ($sdata=$this->load()) {

			// change session id
			if (session_id() != $sdata["sid"]) {
				$session=$_SESSION; // save session
				session_destroy(); // close session
				session_id($sdata["sid"]); // set session id
				session_start(); // start session
				$_SESSION=$session; // restore session
			}
			$_SESSION["psession"]=time();

			// set cookie expiration
			$session=$this->expiration();

			// update new expiration time
			$values=[
				"updated"=>date("Y-m-d H:i:s"),
				"expires"=>date("Y-m-d H:i:s", $session["expires"]),
			];
			$sdata=$values+$sdata;
			if ($this->db) {
				if (!$this->db->atomic($this->db->sqlupdate($this->table, $values, ["sid"=>session_id()]))) $this->db->err();
			}
			if (!$this->fileSave($sdata)) return false;

		}

		// return session data (if exists)
		return $sdata;

	}

}
