<?php

	class xSearchSQLfromText {
		
		protected $db;
		protected $fields;
		protected $fields_oem;
		protected $search;
		protected $options;
		
		function __construct($db, $fields, $search="", $options=Array()) {
			if (!$db) return false;
			if (!$fields) return false;
			$this->db=$db;
			$this->fields($fields);
			$this->search($search);
			$this->options=$options;
			if (!$this->options["fieldsep"]) $this->options["fieldsep"]="";
		}
		
		function __get($k) {
			switch ($k) {
			case "db": return $this->db;
			case "fields": return $this->fields;
			case "search": return $this->search;
			case "sql": return $this->sql();
			case "options": return $this->options;
			}
		}
		
		function __set($k, $v) {
			switch ($k) {
			case "fields": $this->fields($v); break;
			case "search": $this->search($v); break;
			case "options": $this->options($v); break;
			}
		}
		
		function options($options=null) {
			if ($options!==null) $this->options=$options;
			return $this->options;
		}
		
		function fieldsep($newsep=null) {
			if ($newsep===null) return $this->options["fieldsep"];
			$this->options["fieldsep"]=$newsep;
		}
		
		function fields($fields=null) {
			if ($fields!==null) {
				$i=0;
				foreach ($fields as $j=>$v) if ($v) {
					if (!$i) $is_hash=$j;
					$searchfields[$i++]=($is_hash?$j:$v);
				}
				$this->fields=$searchfields;
				$this->fields_oem=$fields;
			}
			return $this->fields_oem;
		}
		
		function search($search=null) {
			if ($search!==null) $this->search=$search;
			return $this->search;
		}
		
		function fieldsql($f) {
			$r=false;
			if ($this->options["translate_field"]) {
				$s=$this->options["translate_field"]($f);
				if ($s===true) $r=true;
				else return $this->options["fieldsep"].$s.$this->options["fieldsep"];
			}
			if ($this->options["translate"] || $r) {
				switch ($this->db->protocol()) {
				case "oracle": return "LOWER(TRANSLATE(".$this->options["fieldsep"].$f.$this->options["fieldsep"].",'ÁÉÍÓÚÀÈÌÒÙÄËÏÖÜÂÊÎÔÛáéíóúàèìòùäëïöüâêîôû','aeiouaeiouaeiouaeiouaeiouaeiouaeiouaeiou'))";
				}
			}
			return $this->options["fieldsep"].$f.$this->options["fieldsep"];
		}
		
		function valuesql($f, $s) {
			$s=$this->db->escape($s);
			$r=false;
			if ($this->options["translate_value"]) {
				$s=$this->options["translate_value"]($f, $s);
				if ($s===true) $r=true;
				else return $this->options["fieldsep"].$s.$this->options["fieldsep"];
			}
			if ($this->options["translate"]===true || $r) {
				$s=(function_exists("strtolower_utf8")
					?strtolower_utf8($s)
					:strtolower($s)
				);
				$s=str_replace(
					array("_","á","é","í","ó","ú","à","è","ì","ò","ù","ä","ë","ï","ö","ü","â","ê","î","ô","û"),
					array("\\_","a","e","i","o","u","a","e","i","o","u","a","e","i","o","u","a","e","i","o","u"),
				$s);
			}
			return $s;
		}

		function sql() {
			$sql="";
			$busqueda=explode(" ", $this->search);
			foreach ($busqueda as $i=>$w) {
				$sql.=($sql?" AND ":"")."(";
				foreach ($this->fields as $i=>$f)
					$sql.=($i?" OR ":"").$this->fieldsql($f)." LIKE '".$this->valuesql($f, (substr_count($w,"%")?$w:"%".$w."%"))."'";
				$sql.=")";
			}
			return $sql;
		}

		function texto($htmlEnhancer="b") {
			$texto="";
			$busqueda=explode(" ",$this->search);
			foreach ($busqueda as $i=>$w) {
				$s=strpos($w,"=");
				if ($s) {
					$f=substr($w,0,$s);
					$w=substr($w,$s+1);
					$texto.=($texto?", ":"")." con la columna ".($this->fields_oem[$f]["th"]?$this->fields_oem[$f]["th"]:$f);
					$texto.=(substr($w,0,1)=="%"
							?(substr($w,-1,1)=="%"?" que contenga ":" que termine por ")
							:(substr($w,-1,1)=="%"?" que empiece por ":" igual a ")
						);						
					$w=(substr($w,0,1)=="%"?substr($w,1):$w);
					$w=(substr($w,-1,1)=="%"?substr($w,0,strlen($w)-1):$w);
					$texto.="<".$htmlEnhancer.">".$w."</".$htmlEnhancer.">";
				} else {
					$s=strpos($w,"!");
					if ($s) {
						$f=substr($w,0,$s);
						$w=substr($w,$s+1);
						$texto.=($texto?", ":"")." con la columna ".($this->fields_oem[$f]["th"]?$this->fields_oem[$f]["th"]:$f);
						$texto.=(substr($w,0,1)=="%"
								?(substr($w,-1,1)=="%"?" que no contenga ":" que no termine por ")
								:(substr($w,-1,1)=="%"?" que no empiece por ":" diferente a ")
							);						
						$w=(substr($w,0,1)=="%"?substr($w,1):$w);
						$w=(substr($w,-1,1)=="%"?substr($w,0,strlen($w)-1):$w);
						$texto.="<".$htmlEnhancer.">".$w."</".$htmlEnhancer.">";
					} else {
						$texto.=($texto?", con ":"")." <".$htmlEnhancer.">".$w."</".$htmlEnhancer."> en todos los campos";
					}
				}
			}
			return $texto;
		}

	}
