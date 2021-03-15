<?php

	// iniciar sesión
	if (@$sessionname) session_name($sessionname);
	if ($_REQUEST["sessionid"]) session_id($_REQUEST["sessionid"]);
	session_start();
	$nocache=$_SESSION["nocache"]=(intval($_SESSION["nocache"])+1);
