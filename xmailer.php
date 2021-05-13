<?php

// funciones AJAX xmailer
function axmailer() {
	global $ajax, $adata;
	if ($ajax == "mailer:send") {
		if (!strlen($adata["from"])) ajax(Array("field"=>"from", "err"=>"Especifique un correo electrónico para el emisor."));
		if (!strlen($adata["to"])) ajax(Array("field"=>"to", "err"=>"Especifique un correo electrónico para el destinatario."));
		if (Kernel::mailto(Array(
			"from"=>$adata["from"],
			"to"=>$adata["to"],
			"reply"=>$adata["reply"],
			"cc"=>$adata["cc"],
			"bcc"=>$adata["bcc"],
			"subject"=>$adata["subject"],
			"text"=>$adata["text"],
			"html"=>$adata["html"],
		))) ajax(Array("ok"=>true));
		ajax(Array("err"=>"Ha ocurrido un error al intentar enviar el correo. Vuelva a intentarlo más tarde por si se trata de un problema transitorio."));
	}
}
