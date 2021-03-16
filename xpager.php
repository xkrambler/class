<?php

/*

	xpager 0.1b - Paginador simple.
	Ejemplo de uso:

	$pager=new xPager(array(
		"length"=>NUMERO_DE_ENTRADAS,
		"max"=>NUMERO_DE_RESULTADOS_POR_PAGINA,
		"show"=>NUMERO_DE_PAGINAS_POR_LISTADO,
		"class"=>array(
			"actual"=>CLASE_DE_PAGINA_ACTUAL,
			"normal"=>CLASE_DE_PAGINA_ENLACE,
			"disabled"=>CLASE_DE_PAGINA_DESHABILITADA,
			"input"=>CLASE_DE_INTRODUCCION_DE_PAGINA_MANUAL // opcional
		)
	));
	$db->query("SELECT * FROM tabla".$pager->limit);
	echo $pager->html();

*/
class xPager {

	public $page;
	public $num;
	public $max;
	public $pageshow;
	public $classes;
	public $start;
	public $end;
	public $single;
	public $limit;

	// constructor
	function __construct($o=array()) {
		$this->num=$o["length"];
		$this->page=intval($_GET["page"]);
		$this->max=intval($o["max"]);
		$this->pageshow=intval($o["show"]);
		$this->classes=$o["class"];
		$this->update();
	}

	// actualizar por si alguno de los parámetros accesibles públicamente ha cambiado
	function update() {
		$this->pagenum=ceil(intval($this->num)/$this->max);
		// comprobar rangos
		if (isset($this->num) && $this->page > $this->pagenum-1) $this->page=$this->pagenum-1; // no se limita si no se ha especificado número de elementos
		if ($this->page < 0) $this->page=0;
		$this->pagestart=$this->page-round($this->pageshow/2);
		if ($this->pagestart < 0) $this->pagestart=0;
		// consultables
		$this->start=$this->page*$this->max;
		$this->end=$this->start+$this->max;
		if ($this->end>$this->num) $this->end=$this->num;
		$this->limit=" LIMIT ".$this->start.",".$this->max." ";
		$this->single=($this->pagenum == 1?true:false);
	}

	// devolver número de página en HTML
	function drpage($drawpage, $label="", $disabled=false) {
		if ($drawpage==$this->page || $disabled) $t=array("span", $this->classes["actual"],false);
		else $t=array("a", $this->classes["normal"],true);
		$pl=x::base().x::alink(array("page"=>null));
		if ($this->classes["input"] && $drawpage==$this->page)
			return "<input class='".$this->classes["input"]."' type='text' value='".($drawpage+1)."'"
				." onKeyPress='javascript:if(event.keyCode==13)if(parseInt(this.value)!=NaN && parseInt(this.value)>0)location.href=\"".$pl.(strpos($pl, "?")?"&amp;":"?")."page=\"+(parseInt(this.value)-1)'"
				." onFocus='javascript:this.select()'"
				." onBlur='javascript:this.value=".($drawpage+1).";'"
				." title='Página actual'"
				." />";
		return "<".$t[0]
			." class='".($disabled?$this->classes["disabled"]:$t[1])."'"
			.($t[2]?" href='".x::alink(array("page"=>$drawpage))."'":"")
			.">"
				.($label?$label:($drawpage+1))
			."</".$t[0].">";
	}

	// devolver HTML completo del paginador
	function html() {

		// anterior
		$html=$this->drpage($this->page-1,"&lt;",($this->page?false:true));

		// primera página y puntos suspensivos
		if ($this->pagestart > 0) {
			$html.=$this->drpage(0);
			if ($this->pagestart > 1) $html.=" ... ";
		}

		// páginas intermedias
		if ($this->pagenum) {
			for ($i=$this->pagestart; $i < ($this->pagestart+$this->pageshow); $i++) {
				$html.=$this->drpage($i);
				if (($i+1) == $this->pagenum) { $i=$this->pagenum; break; }
			}
		}

		// puntos suspensivos y última página
		if ($i < $this->pagenum) {
			if (($i+1) != $this->pagenum) $html.=" ... ";
			$html.=$this->drpage($this->pagenum-1);
		}

		// siguiente
		$html.=$this->drpage($this->page+1, "&gt;", ((($this->page+1) == $this->pagenum) || ($this->page+1)>=$this->num));

		// HTML formado
		return $html;

	}

}
