<?php

	function newphotos_list($photos,$thumbnail_prefix="tn_",$thumbnail_sufix=".jpg",$sufix_replace_extension=true) {
		$l=Array();
		foreach ($photos as $p) {
			list($w,$h,$t,$a)=(@getimagesize($p));
			if ($w && $h) {
				$d=dirname($p);
				$t=$d.($d?"/":"").$thumbnail_prefix.basename($p);
				if ($thumbnail_sufix) {
					if ($sufix_replace_extension) {
						$i=stripos($t,".");
						if ($i) $t=substr($t,0,$i).$thumbnail_sufix;
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
			if ($prefix && substr($e,0,strlen($prefix))!=$prefix) continue;
			if ($sufix && substr($e,-strlen($sufix),strlen($sufix))!=$sufix) continue;
			array_push($l,$path.$e);
		}
		$d->close();
		return $l;
	}
	
	// newphotos_list(newphotos_dir("","image_prefix_",".jpg"))
