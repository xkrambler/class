<?php

/*

	AJAX One Click Multiple File Uploader Helper

	Example:
		new xUploader(array(
			//"temp"=>"data/temp/",
			//"store"=>"data/files/", // store in this folder
			//"store"=>function($uploader, $upload, $o){ return "data/temp/".$upload["name"]; }, // generate
			"oncomplete"=>function($uploader, $upload, $o){ // at the end of a completed upload
				//echo "[oncomplete]\n"; print_r($o);
				$uploader->store($upload, "data/files/upload_".$upload["name"], 0664);
			},
			"onupload"=>function($uploader, $uploads, $o){ // at the end of all uploads
				//echo "Receibed\n"; print_r($o);
			},
		));

*/
class xUploader {

	protected $o;

	// get PHP value in bytes
	static function getValueInBytes($v) {
		$v=trim($v);
		$last=strtolower(substr($v, -1, 1));
		if (!is_numeric($last)) $v=(int)substr($v, 0, -1);
		switch ($last) {
		case 'g': $v*=1024;
		case 'm': $v*=1024;
		case 'k': $v*=1024;
		}
		return (int)$v;
	}

	// get recommended chunk size
	static function getChunkSize() {
		$limit=20*1024*1024; // limit
		$size=max(
			self::getValueInBytes(ini_get("upload_max_filesize")),
			self::getValueInBytes(ini_get("post_max_size"))
		);
		if ($size > $limit) $size=$limit;
		$size*=0.7; // reserve 30%
		return intval($size);
	}

	// constructor and upload control
	function __construct($o) {
		$this->o=$o;
		if ($_FILES) {
			$uploaded=array();
			foreach ($_FILES as $field=>$files) {
				// convert to multiple array if single file is loaded
				if (!is_array($files["name"])) {
					$_files=array();
					foreach ($files as $k=>$v)
						$_files[$k][0]=$v;
					$files=$_files;
					unset($_files);
				}
				// iterate files
				foreach ($files["type"] as $index=>$type_info) {
					$name=basename($files["name"][$index]);
					if (!$name || strpos($name, "..")!==false || strpos($name, "/")!==false || strpos($name, "\\")!==false) continue;
					$tmp=$files["tmp_name"][$index];
					$append=false;
					$ended=false;
					$p=strrpos($type_info, "//");
					$chunked=($p!==false);
					$u=array(
						"chunked"=>$chunked,
						"name"=>$name,
						"type"=>($chunked?substr($type_info, 0, $p):$type_info),
						"ext"=>pathinfo($name, PATHINFO_EXTENSION),
					);
					if ($chunked) {
						list($action, $num, $count, $total)=explode(".", substr($type_info, $p+2));
						switch ($action) {
						case "resume": $append=true; break; // append
						case "end": $append=true; $ended=true; break; // append, end
						case "complete": $ended=true; // overwrite, end (no break)
						case "start": // overwrite and initialize session
							if ($num==1) if (!$this->clear()) $this->ajax(array("cantclear"=>true, "err"=>"xuploader: Cannot clear session."));
							if (!$this->uploadStart($u, $o)) $this->ajax(array("cantstart"=>true, "err"=>"xuploader: Cannot start upload."));
							break;
						default:
							$this->ajax(array("cantaction"=>true, "err"=>"xuploader: No action set."));
						}
						$u+=array(
							"action"=>$action,
							"num"=>$num,
							"count"=>$count,
							"total"=>$total,
							"ended"=>$ended,
						);
						if (!$upload=$this->uploadGet($u)) $this->ajax(array("nostart"=>true, "err"=>"xuploader: Upload not started."));
						file_put_contents($upload["tmp"], file_get_contents($tmp), ($append?FILE_APPEND:0));
						$u["size"]=filesize($upload["tmp"]);
						$upload=$this->uploadSet($u);
					} else {
						$u+=array(
							"size"=>filesize($tmp),
						);
						$upload=array_merge($u, array("tmp"=>$tmp));
						$ended=true;
					}
					if ($o["onprogress"]) $o["onprogress"]($this, $upload, $o);
					if ($ended) {
						if ($o["oncomplete"]) $o["oncomplete"]($this, $upload, $o);
						if ($chunked) {
							$this->uploadEnd($u);
							if (($num==$count) && $o["onupload"]) $o["onupload"]($this, array("uploads"=>$this->uploads()), $o);
							if (!$o["store"] && file_exists($upload["tmp"])) unlink($upload["tmp"]);
						} else {
							if ($o["store"]) $this->store($upload);
						}
					}
					if ($chunked) $this->ajax($u+array("ok"=>true));
					$uploaded[$index]=$upload;
				}
			}
			if (!$chunked && $o["onupload"]) $o["onupload"]($this, array("uploads"=>$uploaded), $o);
			foreach ($uploaded as $i=>$v) unset($uploaded[$i]["tmp"]);
			$this->ajax(array("files"=>$uploaded, "ok"=>true));
		}
		$this->ajax(array("nofiles"=>true, "err"=>"xuploader: No file data."));
	}

	// getter/setter
	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }

	// store file (default: file 0660 rw-rw---- directory 0770 rwxrwx---)
	function store($upload, $dst=null, $permissions=0664, $dpermissions=0775) {
		if (!$dst) $dst=(is_callable($this->o["store"])?$this->o["store"]($this, $upload, $this->o):$this->o["store"].$upload["name"]);
		if (strpos($dst, "/")!==false && !file_exists(dirname($dst))) {
			mkdir(dirname($dst), $dpermissions, true);
			chmod(dirname($dst), $dpermissions);
		}
		if ($upload["chunked"]) rename($upload["tmp"], $dst);
		else move_uploaded_file($upload["tmp"], $dst);
		chmod($dst, $permissions);
		return $dst;
	}

	// ensure session
	function session() {
		if (!strlen(session_id())) session_start();
	}

	// clear session
	function clear() {
		$this->session();
		$_SESSION["xuploader"]=array();
		return (strlen(session_id())?true:false);
	}

	// get upload information
	function uploadGet($upload) {
		$this->session();
		return $_SESSION["xuploader"]["uploads"][$upload["name"]];
	}

	// set upload information
	function uploadSet($upload) {
		$this->session();
		if (!$_SESSION["xuploader"]["uploads"][$upload["name"]]) return false;
		$_SESSION["xuploader"]["uploads"][$upload["name"]]=array_merge(
			$_SESSION["xuploader"]["uploads"][$upload["name"]],
			$upload
		);
		return $this->uploadGet($upload);
	}

	// start upload
	function uploadStart($upload, $o) {
		$this->session();
		$_SESSION["xuploader"]["uploads"][$upload["name"]]=array(
			"tmp"=>($o["store"]
				?(is_callable($o["store"])?$o["store"]($this, $upload, $o):$o["store"].$upload["name"])
				:tempnam(($o["temp"]?$o["temp"]:sys_get_temp_dir()), "xupload_")
			),
		);
		$this->uploadSet($upload);
		return true;
	}

	// finish upload
	function uploadEnd($upload) {
		$this->session();
		if (!$_SESSION["xuploader"]["uploads"][$upload["name"]]) return false;
		$_SESSION["xuploader"]["uploads"][$upload["name"]]["ended"]=true;
		return true;
	}

	// return all uploads
	function uploads() {
		$this->session();
		return $_SESSION["xuploader"]["uploads"];
	}

	// send AJAX request
	function ajax($o) {
		header("Content-type: application/json");
		echo json_encode($o);
		exit;
	}

}
