<?php

	// clase de configuración
	class Conf {

		protected $o;

		// constructor
		function __construct($o) {
			$this->o=$o;
			if (!$this->o["table"]) $this->o["table"]="configuracion";
			if (!$this->o["atomic"]) $this->o["atomic"]=false;
		}

		// devolver una opción
		function __get($n) {
			return $this->o[$n];
		}

		// establecer una opción
		function __set($n, $k) {
			$this->o[$n]=$k;
		}

		// tipos de valor soportado
		function tipos() {
			return Array(
				""=>"Deshabilitada",
				"L"=>"Enlace",
				"V"=>"Valor",
			);
		}

		// obtener los datos de una configuración
		function row($id) {
			if (!$this->o["db"]->query("SELECT id, tipo, value FROM ".$this->o["table"]." WHERE id='".$this->o["db"]->escape($id)."'")) $this->o["db"]->err();
			return $this->o["db"]->row();
		}

		// obtener los datos de varias configuraciones
		function rows($ids) {
			if (!is_array($ids)) return null;
			if (!$this->o["db"]->query("SELECT id, tipo, value FROM ".$this->o["table"]." WHERE id IN (".$this->o["db"]->sqlin($ids).")")) $this->o["db"]->err();
			$rows=Array();
			while ($row=$this->o["db"]->row())
				$rows[$row["id"]]=$row;
			return $rows;
		}

		// obtener una o varias configuraciones y cachearla
		function cache($ids=null) {
			static $cache=Array();
			if ($ids===null) {
				$cache=Array();
				return null;
			}
			if (is_array($ids)) {
				foreach ($ids as $n)
					if (isset($cache[$n]))
						$a[$n]=$cache[$n];
				if ($a)
					foreach ($a as $n=>$v)
						unset($ids[$n]);
				if ($_ids=$this->get($ids)) foreach ($_ids as $n=>$v)
					if ($v!==null)
						$a[$n]=$cache[$n]=$v;
				return $a;
			} else {
				if (!isset($cache[$ids])) {
					$v=$this->get($ids);
					if ($v!==null) $cache[$ids]=$v;
				}
				return $cache[$ids];
			}
		}

		// obtener una o varias configuraciones
		function get($ids) {
			if (is_array($ids)) {
				foreach ($this->rows($ids) as $row)
					$a[$row["id"]]=($row["tipo"]=="L"?$this->get($row["value"]):($row["tipo"]?$row["value"]:null));
				return $a;
			} else {
				if (!($row=$this->row($ids))) return null;
				return ($row["tipo"]=="L"?$this->get($row["value"]):($row["tipo"]?$row["value"]:null));
			}
		}

		// guardar una configuración
		function set($id, $value, $tipo="V") {
			if ($this->o["atomic"]) if (!$this->o["db"]->begin()) $this->o["db"]->err();
			if (!$this->o["db"]->query($this->o["db"]->sqlreplace($this->o["table"], Array("id"=>$id, "tipo"=>$tipo, "value"=>$value)))) $this->o["db"]->err();
			if ($this->o["atomic"]) if (!$this->o["db"]->commit()) $this->o["db"]->err();
			return $this->get($id);
		}

		// borra una configuración
		function del($id) {
			if ($this->o["atomic"]) if (!$this->o["db"]->begin()) $this->o["db"]->err();
			if (!$this->o["db"]->query($this->o["db"]->sqldelete($this->o["table"], Array("id"=>$id)))) $this->o["db"]->err();
			if ($this->o["atomic"]) if (!$this->o["db"]->commit()) $this->o["db"]->err();
			return true;
		}

	}
