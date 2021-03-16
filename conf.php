<?php

// clase de gestión de la configuración
class Conf {

	protected $o;

	// constructor
	function __construct($o) {
		$this->o=$o;
		if (!$this->atomic) $this->atomic=false;
		if (!$this->table) $this->table="configuracion";
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
		if (!($r=$this->db->query("SELECT * FROM ".$this->table." WHERE id='".$this->db->escape($id)."'"))) $this->db->err();
		$row=$r->row();
		$r->freequery();
		return $row;
	}

	// obtener los datos de varias configuraciones
	function rows($ids) {
		if (!is_array($ids)) return null;
		if (!($r=$this->db->query("SELECT * FROM ".$this->table." WHERE id IN (".$this->db->sqlin($ids).")"))) $this->db->err();
		return $this->db->asocquery("id");
	}

	// obtener una o varias configuraciones y cachearla
	function cache($ids=null) {
		static $cache=array();
		if ($ids === null) {
			$cache=array();
			return null;
		}
		if (is_array($ids)) {
			foreach ($ids as $n)
				if (isset($cache[$n]))
					$a[$n]=$cache[$n];
			if ($a)
				foreach ($a as $n=>$v)
					unset($ids[$n]);
			if ($_ids=$this->get($ids))
				foreach ($_ids as $n=>$v)
					if ($v!==null)
						$a[$n]=$cache[$n]=$v;
			return $a;
		} else {
			if (!isset($cache[$ids])) {
				$v=$this->get($ids);
				if ($v!==null)
					$cache[$ids]=$v;
			}
			return $cache[$ids];
		}
	}

	// obtener una o varias configuraciones
	function get($ids) {
		if (is_array($ids)) {
			foreach ($this->rows($ids) as $row)
				$a[$row["id"]]=($row["tipo"] == "L"?$this->get($row["value"]):($row["tipo"]?$row["value"]:null));
			return $a;
		} else {
			if (!($row=$this->row($ids))) return null;
			return ($row["tipo"] == "L"?$this->get($row["value"]):($row["tipo"]?$row["value"]:null));
		}
	}

	// obtener varias configuraciones via like (p.e. valor.%)
	function like($like) {
		if (!$r=$this->db->query("SELECT id, tipo, value FROM ".$this->table." WHERE id LIKE '".$this->db->escape($like)."'")) $this->db->err();
		$a=array();
		while ($row=$r->row())
			$a[$row["id"]]=($row["tipo"] == "L"?$this->get($row["value"]):($row["tipo"]?$row["value"]:null));
		$r->freequery();
		return $a;
	}

	// guardar una configuración
	function set($id, $value, $tipo="V") {
		if ($this->atomic) if (!$this->db->begin()) $this->db->err();
		$row=$this->row($id);
		if (!$this->db->query($this->db->sqlreplace($this->table, array("id"=>$id, "tipo"=>$tipo, "value"=>$value)+($row?$row:array())))) $this->db->err();
		if ($this->atomic) if (!$this->db->commit()) $this->db->err();
		return $this->get($id);
	}

	// borra una configuración
	function del($id) {
		if ($this->atomic) if (!$this->db->begin()) $this->db->err();
		if (!$this->db->query($this->db->sqldelete($this->table, array("id"=>$id)))) $this->db->err();
		if ($this->atomic) if (!$this->db->commit()) $this->db->err();
		return true;
	}

}
