<?php

define("XITEMSSEARCH_ASC",  0);
define("XITEMSSEARCH_DESC", 1);

class xItemsSearch {

	// helper para el ajax de consulta
	static function query($o) {
		$r=self::data($o);
		if (is_array($r)) ajax(array_merge(array("ok"=>true), $r));
		return $r;
	}

	// realizar consulta consulta y devolver como datos
	static function data($o) {
		$adata=($o["adata"]?$o["adata"]:$GLOBALS["adata"]);
		$db=$o["db"];
		$db_protocol=$db->protocol();

		// inicialización
		$page=intval(isset($o["page"])?$o["page"]:$adata["page"]); if (!$page || $page<0) $page=0;
		$visible=intval(isset($o["visible"])?$o["visible"]:$adata["visible"]); if ($visible<1 || $visible>1000) $visible=0;

		// ordenación
		$sqlsort="";
		if ($sort=($o["sort"]?$o["sort"]:$adata["sort"])) foreach ($sort as $f=>$t) {
			if ($ordersf=$o["orders"][$f]) {
				$ordersf=explode(",", $ordersf);
				if ($ordersf)
					foreach ($ordersf as $orderf)
						$sqlsort.=($sqlsort?",":"").$orderf.($t?" DESC":" ASC");
			} else {
				$sqlsort.=($sqlsort?",":"").$db->sqlfield($f).($t?" DESC":" ASC");
			}
		}

		// verificar que la consulta lleve SQL_CALC_FOUND_ROWS si no tiene consulta de conteo
		if (!isset($o["count"]) && !strpos($o["sql"], "SQL_CALC_FOUND_ROWS"))
			ajax(array("err"=>"La consulta precisa SQL_CALC_FOUND_ROWS para ser contada!"));

		// búsqueda
		if ($o["search"]) {
			$search_options=($o["search_options"]?$o["search_options"]:array());
			if ($o["translate"]) $search_options["translate"]=$o["translate"];
			if ($o["translate_field"]) $search_options["translate_field"]=$o["translate_field"];
			if ($o["translate_value"]) $search_options["translate_value"]=$o["translate_value"];
			require_once("xsearchsqlfromtext.php");
			$sqlsearch=new xSearchSQLfromText($db, $o["search"], $adata["search"], $search_options);
			$search=$sqlsearch->sql();
		}

		// filtros por campos y límites
		$sqlfilters="";
		$sqllimit="";
		if ($adata["filters"])
			foreach ($adata["filters"] as $field=>$value)
				$sqlfilters.=" AND ".$db->sqlfield($field)."='".$db->escape($value)."'";

		// consulta
		$sqlquery=str_replace("%sqlsearch%", ($adata["search"]?$search:"1=1").$sqlfilters.$sqllimit, $o["sql"]);

		// limitar (sqlsrv)
		if (!$o["nolimit"] && $db_protocol=="sqlsrv") {
			switch ($o["querytype"]) {
			//case "2000": break;
			default: $sqlquery="SELECT TOP ".$visible." * FROM (".$sqlquery.") xis_table";
			}
		}

		// añadir ordenación ahora
		$sqlquery.=($sqlsort?" ORDER BY ".$sqlsort:"");

		// limitar (mysql)
		if (!$o["nolimit"]) switch ($db_protocol) {
			case "mysql": $sqlquery.=($visible?" LIMIT ".($page*$visible).",".$visible:""); break;
			case "oracle": $sqlquery="SELECT * FROM (SELECT ROWNUM AS xis_rownum, xis_table.* FROM (".$sqlquery.") xis_table) xis_table2 WHERE xis_table2.xis_rownum BETWEEN ".($page*$visible)." AND ".($page*$visible+$visible); break;
		}

		// devolver consulta SQL generada, si solicitada
		if ($o["getsql"]) return $sqlquery;

		// realizar consulta
		if ($o["timeout"]) $db->atimeout=$o["timeout"];
		if (!$consulta=$db->query($sqlquery)) $db->err();
		$timedout=($db->atimedout === true);
		$datos=($timedout?[]:$consulta->aquery());

		// obtener conteo de filas
		$max=false;
		if ($timedout) {
			$max=0;
		} else {
			if (isset($o["count"])) {
				// el conteo se realizará por consulta SQL autogenerada o específica
				$sqlcount=($o["count"]===true
					?"SELECT COUNT(*) FROM (".str_replace("%sqlsearch%", ($adata["search"]?$search:"1=1").$sqlfilters, $o["sql"]).") xis_tcount"
					:str_replace("%sqlsearch%", ($adata["search"]?$search:"1=1").$sqlfilters, $o["count"])
				);
				if ($sqlcount) {
					if (!$db->query($sqlcount)) $db->err();
					$max=intval($db->field());
				}
			} else if (!$o["count"]) {
				// por defecto, se contea con SQL_CALC_FOUND_ROWS
				if (!$db->query("SELECT FOUND_ROWS()")) $db->err();
				$max=intval($db->field());
			}
		}

		// aplicar filtro, si especificado
		if ($o["filter"]) $o["filter"]($datos);

		// devolver datos
		return array(
			"timeout"=>$o["timeout"],
			"timedout"=>$timedout,
			"page"=>$page,
			"max"=>$max,
			"visible"=>$visible,
			"data"=>$datos,
			"extra"=>$extra,
		);

	}

}
