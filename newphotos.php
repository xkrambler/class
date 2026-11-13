<?php

	function newphotos_list($photos,$thumbnail_prefix="tn_",$thumbnail_sufix=".jpg",$sufix_replace_extension=true) {
		$l=Array();
		foreach ($photos as $p) {
			list($w,$h,$t,$a)=(@getimagesize($p));
			if ($w && $h) {
				$d=dirname((string)($p ?? ""));
				$t=$d.($d?"/":"").$thumbnail_prefix.basename((string)($p ?? ""));
				if ($thumbnail_sufix) {
					if ($sufix_replace_extension) {
						$i=stripos((string)($t ?? ""),".");
						if ($i) $t=substr((string)($t ?? ""),0,$i).$thumbnail_sufix;
					} else {
						$t.=$thumbnail_sufix;
					}
				}
				array_push($l,Array($p,$w,$h,$t));
			}
		}
		return $l;
	}
	
	function newphotos_dir($path="",$prefix="",$sufix="") {
		$l=Array();
		$d=dir($path?$path:".");
		while ($e=$d->read()) {
			if ($prefix && substr((string)($e ?? ""),0,strlen((string)($prefix ?? "")))!=$prefix) continue;
			if ($sufix && substr((string)($e ?? ""),-strlen((string)($sufix ?? "")),strlen((string)($sufix ?? "")))!=$sufix) continue;
			array_push($l,$path.$e);
		}
		$d->close();
		return $l;
	}
	
	// newphotos_list(newphotos_dir("","image_prefix_",".jpg"))
