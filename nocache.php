<?php

	// evitar el uso de caché en el navegador
	Header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");   // Expira en fecha pasada
	Header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	Header("Pragma: no-cache"); 
