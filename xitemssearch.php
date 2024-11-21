<?php

define("XITEMSSEARCH_ASC",  0);
define("XITEMSSEARCH_DESC", 1);

class xItemsSearch {

	// helper for query
	static function query($o) {
		$r=self::data($o);
		if (is_array($r)) ajax(array_merge(array("ok"=>true), $r));
		return $r;
	}

	// make query and return as data
	static function data($o) {
		$adata=($o["adata"]?$o["adata"]:$GLOBALS["adata"]);
		$db=$o["db"];
		$db_protocol=$db->protocol();

		// parameters
		$page=intval(isset($o["page"])?$o["page"]:$adata["page"]); if (!$page || $page < 0) $page=0;
		$visible=intval(isset($o["visible"])?$o["visible"]:(isset($adata["visible"])?$adata["visible"]:100)); if ($visible < 0) $visible=100;

		// sorting
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

		// ensure query has SQL_CALC_FOUND_ROWS if no count query
		if (!isset($o["count"]) && !strpos($o["sql"], "SQL_CALC_FOUND_ROWS"))
			ajax(array("err"=>"Query requires SQL_CALC_FOUND_ROWS!"));

		// search
		if ($o["search"]) {
			$search_options=($o["search_options"]?$o["search_options"]:array());
			if ($o["translate"]) $search_options["translate"]=$o["translate"];
			if ($o["translate_field"]) $search_options["translate_field"]=$o["translate_field"];
			if ($o["translate_value"]) $search_options["translate_value"]=$o["translate_value"];
			require_once("xsearchsqlfromtext.php");
			$sqlsearch=new xSearchSQLfromText($db, $o["search"], $adata["search"], $search_options);
			$search=$sqlsearch->sql();
		}

		// filters by fields
		$sqlfilters="";
		if ($adata["filters"])
			foreach ($adata["filters"] as $field=>$value)
				$sqlfilters.=" AND ".$db->sqlfield($field)."='".$db->escape($value)."'";

		// make query
		$sqlquery=str_replace("%sqlsearch%", ($adata["search"]?$search:"1=1").$sqlfilters, $o["sql"]);

		// limit (sqlsrv)
		if (!$o["nolimit"] && $db_protocol == "sqlsrv") {
			switch ($o["querytype"]) {
			default: if ($visible) $sqlquery="SELECT TOP ".$visible." * FROM (".$sqlquery.") xis_table";
			}
		}

		// add order by
		$sqlquery.=($sqlsort?" ORDER BY ".$sqlsort:"");

		// limit (other)
		if (!$o["nolimit"]) switch ($db_protocol) {
			case "mysql": $sqlquery.=($visible?" LIMIT ".($page*$visible).",".$visible:""); break;
			case "oracle": $sqlquery="SELECT * FROM (SELECT ROWNUM AS xis_rownum, xis_table.* FROM (".$sqlquery.") xis_table) xis_table2 WHERE xis_table2.xis_rownum BETWEEN ".($page*$visible)." AND ".($page*$visible+$visible); break;
		}

		// return generated SQL, if asked
		if ($o["getsql"]) return $sqlquery;

		// start query
		if ($o["timeout"]) $db->atimeout=$o["timeout"];
		if (!$consulta=$db->query($sqlquery)) $db->err();
		$timedout=($db->atimedout === true);
		$rows=($timedout?array():$consulta->aquery());

		// get row count
		$max=false;
		if ($timedout) {
			$max=0;
		} else {
			if (isset($o["count"])) {
				$sqlcount=($o["count"] === true
					?"SELECT COUNT(*) FROM (".str_replace("%sqlsearch%", ($adata["search"]?$search:"1=1").$sqlfilters, $o["sql"]).") xis_tcount"
					:str_replace("%sqlsearch%", ($adata["search"]?$search:"1=1").$sqlfilters, $o["count"])
				);
				if ($sqlcount) {
					if (!$db->query($sqlcount)) $db->err();
					$max=intval($db->field());
				}
			} else if (!$o["count"]) {
				if (!$db->query("SELECT FOUND_ROWS()")) $db->err();
				$max=intval($db->field());
			}
		}

		// if any filter specified, apply
		if ($o["filter"]) $o["filter"]($rows);

		// return data
		return array_merge(($o["data"]?$o["data"]:array()), array(
			"timeout"=>$o["timeout"],
			"timedout"=>$timedout,
			"page"=>$page,
			"max"=>$max,
			"visible"=>$visible,
			"data"=>$rows,
		));

	}

}
