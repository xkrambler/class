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
	function expiration($expires=null) {
		if ($expires === null) $expires=time()+$this->expiration;
		$expiration=[
			"expires"=>$expires,
			"path"=>"/",
			"domain"=>"",
			"samesite"=>$this->samesite,
			"secure"=>($this->secure?\x::ishttps():false),
			"httponly"=>true,
		];
		\x::setcookie(session_name(), session_id(), $expiration);
		return $expiration;
	}

	// session path (if defined)
	function path() {
		$path=($this->path?$this->path.(substr((string)($this->path ?? ""), -1, 1) != "/"?"/":""):false);
		if ($path && !is_dir($path)) mkdir($path, 0775, true);
		return $path;
	}

	// session file (if path defined)
	function file(string $sid) {
		if (!preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $sid)) return false;
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
			$tmp=$f.".tmp.".getmypid();
			if (@file_put_contents($tmp, json_encode($sdata), LOCK_EX) === false) return false;
			if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
		}
		return true;
	}

	// create sessions database
	function dbCreate() {
		if (!$this->db->atomic("
			CREATE TABLE ".$this->db->sqltable($this->table)." (
				`sid` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`updated` DATETIME NOT NULL,
				`expires` DATETIME NOT NULL,
				`ip` VARCHAR(45) NULL DEFAULT NULL,
				`useragent` VARCHAR(255) NULL DEFAULT NULL,
				`userdata` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`sid`) USING BTREE,
				INDEX `expires` (`expires`) USING BTREE
			)
			COMMENT='Persistent Sessions'
			COLLATE='utf8mb4_general_ci'
			ENGINE=InnoDB
		")) $this->db->err();
		return true;
	}

	// delete persistent session rows (tolerates a not yet created table)
	protected function dbDelete($where) {
		if (!$this->db) return true;
		if (!$this->db->query($this->db->sqldelete($this->table, $where)) && $this->db->errnum() != 1146) $this->db->err();
		return true;
	}

	// persistent session login
	function login($userdata=null) {

		// a session must be active
		if (session_status() !== PHP_SESSION_ACTIVE) return false;
		if (!($old_sid=session_id())) return false;

		// rotate session id to prevent session fixation
		if (!session_regenerate_id(true)) return false;

		// session data
		$expires=time()+$this->expiration;
		$sdata=[
			"sid"      =>session_id(),
			"updated"  =>date("Y-m-d H:i:s"),
			"expires"  =>date("Y-m-d H:i:s", $expires),
			"ip"       =>substr((string)($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45),
			"useragent"=>substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255),
			"userdata" =>($userdata === null?0:intval($userdata)),
		];

		// delete old/expired sessions and save current session
		if ($this->db) {
			do {
				$retry=false;
				if (!$this->db->atomic([
					$this->db->sqldelete($this->table, ["sid"=>$old_sid]),
					$this->db->sqldelete($this->table, "expires<NOW()"),
					$this->db->sqlreplace($this->table, $sdata),
				])) {
					if ($this->db->errnum() == 1146) {
						$this->dbCreate();
						$retry=true;
					} else {
						$this->db->err();
					}
				}
			} while ($retry);
		}
		$this->filesCleanup();
		$this->fileSave($sdata); // best effort (optional file storage)

		// all ok: mark as persistent and extend cookie
		$_SESSION["persistent"]=true;
		$this->expiration($expires);
		return true;

	}

	// persistent session logout
	function logout() {
		$sid=session_id();
		if ($sid) $this->dbDelete(["sid"=>$sid]);
		if ($sid && ($f=$this->file($sid)) && file_exists($f)) @unlink($f);

		// remove cookie from client
		\x::delcookie(session_name(), [
			"path"=>"/",
			"domain"=>"",
			"samesite"=>$this->samesite,
			"secure"=>($this->secure?\x::ishttps():false),
			"httponly"=>true,
		]);

		// destroy local session data
		$_SESSION=[];
		if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
		return true;
	}

	// forget persistent session but keep current authenticated session
	function forget() {
		$sid=session_id();
		if (!$sid) return false;
		$this->dbDelete(["sid"=>$sid]);
		if (($f=$this->file($sid)) && file_exists($f)) @unlink($f);

		// rotate current session id
		if (session_status() === PHP_SESSION_ACTIVE) {
			if (!session_regenerate_id(true)) return false;
		}

		// keep a normal (non persistent) session cookie
		$_SESSION["persistent"]=false;
		return true;
	}

	// revoke persistent sessions for an user (or current one)
	// rotates current session id so old token is no longer valid
	function revoke($userdata=null, bool $rotate=true) {
		if (session_status() !== PHP_SESSION_ACTIVE) return false;
		$sid=session_id();
		$uid=intval($_SESSION["user"]["id"] ?? 0);
		$is_current=($userdata === null || ($uid && intval($userdata) === $uid));

		// delete persistent records (all devices or current one)
		if ($sid) {
			$where=($userdata !== null?["userdata"=>intval($userdata)]:["sid"=>$sid]);
			$this->dbDelete($where);
		}

		// rotate current session id
		if ($rotate && $is_current && $sid) {
			$old_sid=$sid;
			if (!session_regenerate_id(true)) return false;
			if (($f=$this->file($old_sid)) && file_exists($f)) @unlink($f);

			// if current device was persistent, recreate under new session id
			if (!empty($_SESSION["persistent"])) {
				$expires=time()+$this->expiration;
				$sdata=[
					"sid"      =>session_id(),
					"updated"  =>date("Y-m-d H:i:s"),
					"expires"  =>date("Y-m-d H:i:s", $expires),
					"ip"       =>substr((string)($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45),
					"useragent"=>substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255),
					"userdata" =>($uid?$uid:intval($userdata)),
				];
				if ($this->db) {
					if (!$this->db->atomic($this->db->sqlreplace($this->table, $sdata))) $this->db->err();
				}
				$this->fileSave($sdata);
				$this->expiration($expires);
			}
		}
		return true;
	}

	// persistent session load
	function load() {
		$sid=($_COOKIE[session_name()] ?? "");
		if (!is_string($sid) || !preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $sid)) return false;
		if ($this->db) {
			if (!$this->db->query("SELECT * FROM ".$this->db->sqltable($this->table)." WHERE sid='".$this->db->escape($sid)."' AND expires>NOW()")) {
				if ($this->db->errnum() == 1146) return false;
				$this->db->err();
			}
			$sdata=$this->db->row();
			$this->db->freequery();
			return $sdata;
		}
		if ($f=$this->file($sid)) {
			if (file_exists($f)) {
				$sdata=@json_decode(file_get_contents($f), true);
				if (!is_array($sdata)) return false;
				if (($sdata["expires"] ?? "") < date("Y-m-d H:i:s")) {
					@unlink($f);
					return false;
				}
				return $sdata;
			}
		}
		return false;
	}

	// revalidate cookie
	function revalidate() {
		$last=intval($_SESSION["psession"] ?? 0);
		if ($last && (time()-$last) < $this->revalidate) return false;
		return $this->check();
	}

	// persistent session check
	function check() {

		// an active session is required
		if (session_status() !== PHP_SESSION_ACTIVE) return false;

		// try load session data
		if (!($sdata=$this->load())) return false;

		// "use_strict_mode" may have rotated the id if the old session
		// storage was removed (gc). Re-key the row to the current id
		// instead of forcing the old (now invalid) session id.
		$old_sid=$sdata["sid"];
		$sid=session_id();
		$expires=time()+$this->expiration;

		if ($sid !== $old_sid) {
			$sdata["sid"]=$sid;
			$sdata["updated"]=date("Y-m-d H:i:s");
			$sdata["expires"]=date("Y-m-d H:i:s", $expires);
			if ($this->db) {
				if (!$this->db->atomic([
					$this->db->sqldelete($this->table, ["sid"=>$old_sid]),
					$this->db->sqlreplace($this->table, $sdata),
				])) $this->db->err();
			}
			if (($f=$this->file($old_sid)) && file_exists($f)) @unlink($f);
		}

		$_SESSION["psession"]=time();
		$_SESSION["persistent"]=true;

		// update new expiration time
		$values=[
			"updated"=>date("Y-m-d H:i:s"),
			"expires"=>date("Y-m-d H:i:s", $expires),
		];
		$sdata=$values+$sdata;
		if ($this->db) {
			if (!$this->db->atomic($this->db->sqlupdate($this->table, $values, ["sid"=>session_id()]))) $this->db->err();
		}
		$this->fileSave($sdata); // best effort (optional file storage)

		// set cookie expiration (after persistence)
		$this->expiration($expires);

		// return session data
		return $sdata;

	}

}
