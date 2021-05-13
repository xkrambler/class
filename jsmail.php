<?php

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
			var m={".$t."};
			var t='".str_repeat(" ",strlen($m))."';
			for (var i in m)
				for (var j in m[i])
					t=t.substring(0,m[i][j])+i+t.substring(m[i][j]+1);
			document.write(".($a?"\"<a href='mailto:\"+t+\"'".($aa?" ".$aa:"").">\"+t+\"</a>\"":"t").");
		</script>";
		return $b;
	}

	// crea un enlace a un correo cifrado
	function jsmaila($m,$t="",$aa="") {
		return (jsmail($m,true,($t?"target='".$t."'":"").($aa?" ".$aa:"")));
	}
