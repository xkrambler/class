<?php

// generic data class
class xData {
	protected $o=array();
	protected $defaults=array();
	function __construct(array $o=[]) { $this->o=array_merge($this->defaults, $o); }
	function __get(string $k) { return $this->o[$k]; }
	function __set(string $k, $v) { $this->o[$k]=$v; }
	function __isset(string $k) { return isset($this->o[$k]); }
	function get() { return $this->o; }
	function set(array $o) { foreach ($o as $k=>$v) $this->o[$k]=$v; }
}
