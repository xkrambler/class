<?php

// WordPress DataBase compatibility layer with base
class dbWordPressDB extends dbbase {

	protected $wpdb;
	protected $results=array();
	protected $rowindex=array();

	// información del driver y versión de la clase
	function driver() { return "WPDB"; }
	function version() { return 0.1; }
	function protocol() { return "wordpressdb"; }

	// constructor
	function __construct($setup=Array()) {
		$this->setup($setup);
		$this->connect();
	}

	function connect() {
		global $wpdb;
		$this->idcon=true;
		$this->wpdb=$wpdb;
	}

	function close() {
		$this->idcon=false;
		$this->wpdb=false;
	}

	function ready() {
		return ($this->wpdb?true:false);
	}

	function info() {
		return $this->wpdb;
	}

	function query($sql, $querynum=null) {
		if (!$querynum) $querynum="%".(++$this->lastquerynum);
		$this->results[$querynum]=$this->wpdb->get_results($sql, ARRAY_A);
		$this->rowindex[$querynum]=0;
		return ($this->wpdb->last_error?false:true);
	}

	function freequery() {}

	function aquery($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->results[$querynum];
	}

	function row($querynum=null) {
		if (!$querynum) $querynum="%".$this->lastquerynum;
		return $this->results[$querynum][$this->rowindex[$querynum]++];
	}

	function field($querynum=null) {
		if ($row=$this->row($querynum))
			foreach ($row as $v)
				return $v;
		return false;
	}

	function escape($value) {
		return $this->wpdb->_escape($value);
	}

	function err($querynum=null, $doexit=true) {
		$this->wpdb->show_errors();
		$this->wpdb->print_error();
		if ($doexit) exit;
	}

}
