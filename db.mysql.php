<?php if (!class_exists("dbbase")) die();

/*
	Clase PHP de Acceso a MySQL v0.5, desarrollado por Pablo Rodríguez Rey
	http://mr.xkr.inertinc.org ~ mr.xkr-at-inertinc.org
	Bajo licencia de uso libre GPL (http://www.gnu.org/copyleft/gpl.html)

	La extensión mysql_* fue eliminada en PHP 7.0. Esta clase se conserva
	como alias compatible del driver MySQLi para configuraciones antiguas
	que sigan usando el tipo "mysql".
*/

require_once(dirname(__FILE__)."/db.mysqli.php");

class dbMySQL extends dbMySQLi {

	// información del driver y versión de la clase
	function driver() { return "MySQL"; }
	function version() { return 0.5; }
	function protocol() { return "mysql"; }

}
