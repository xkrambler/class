<?php

// cifrar email en javascript y devolver como script devolviendo variable m
function jsmails($m) {
	$words=array();
	for ($i=0; $i<strlen($m); $i++) {
		$c=substr($m, $i, 1);
		$l=$words[$c];
		$words[$c][($words[$c]?count($words[$c]):0)]=$i;
	}
	$t="";
	foreach ($words as $l=>$p) {
		$t.=($t?",":"")."'".$l."':[";
		for ($i=0; $i<count($p); $i++)
			$t.=($i?",":"").$p[$i];
		$t.="]";
	}
	return "
		var w={".$t."};
		var m='".str_repeat(" ", strlen($m))."';
		for (var i in w)
			for (var j in w[i])
				m=m.substring(0, w[i][j])+i+m.substring(w[i][j]+1);
	";
}

// cifrar email en javascript y devolver como document.write
function jsmail($m,$a=false,$aa="") {
	$words=Array();
	for ($i=0;$i<strlen($m);$i++) {
		$c=substr($m,$i,1);
		$l=$words[$c];
		$words[$c][($words[$c]?count($words[$c]):0)]=$i;
	}
	$t="";
	foreach ($words as $l=>$p) {
		$t.=($t?",":"")."'".$l."':[";
		for ($i=0;$i<count($p);$i++)
			$t.=($i?",":"").$p[$i];
		$t.="]";
	}
	$b="<script>
		".jsmails($m)."
		document.write(".($a?"\"<a href='mailto:\"+m+\"'".($aa?" ".$aa:"").">\"+m+\"</a>\"":"m").");
	</script>";
	return $b;
}

// cifrar email en javascript y devolver como enlace document.write
function jsmaila($m,$t="",$aa="") {
	return (jsmail($m,true,($t?"target='".$t."'":"").($aa?" ".$aa:"")));
}
