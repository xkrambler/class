<?php

	// Set to whatever size you want, or randomize for more security
	$captchaBase=($captchaBase?$captchaBase:"");
	$captchaTextSize=5;
	$fontFile=$captchaBase."fonts/VeraBd.ttf";

	// verificaciones previa ejecución
	if (!file_exists($fontFile)) die("Fichero de fuente no encontrada: ".$fontFile);

	do {
		// Generate a random string and encrypt it with md5
		$captchaKey = md5(microtime()*time());
		// Remove any hard to distinguish characters from our hash
		preg_replace('([1aeilou0])', "", $captchaKey);
	} while (strlen($captchaKey) < $captchaTextSize);

	// we need only 7 characters for this captcha
	$captchaKey = strtoupper(substr($captchaKey, 0, $captchaTextSize));

	// grab the base image from our pre-generated captcha image background
	$captchaImage = imagecreatefrompng($captchaBase."images/captcha.png");

	// Select a color for the text. Since our background is an aqua/greenish color, we choose a text color that will stand out, but not completely. A slightly darker green in our case.
	$textColor = imagecolorallocate($captchaImage, 97, 158, 92);

	// Select a color for the random lines we want to draw on top of the image, in this case, we are going to use another shade of green/blue
	$lineColor = imagecolorallocate($captchaImage, 65, 133, 63);

	// get the size parameters of our image
	$imageInfo = getimagesize($captchaBase."images/captcha.png");

	// decide how many lines you want to draw
	$linesToDraw=10;
	$circlesToDraw=10;

	// Add the lines randomly to the image
	for ($i=0; $i<$linesToDraw; $i++) {
		// generate random start spots and end spots
		$xStart = mt_rand(0, $imageInfo[0]);
		$xEnd = mt_rand(0, $imageInfo[1]);
		$lineColor=imagecolorallocate($captchaImage, 87+rand(0,20), 158+rand(0,40), 92+rand(0,20));
		imageline( $captchaImage, $xStart, 0, $xEnd, $imageInfo[1], $lineColor );
	}

	// Add the circles randomly to the image
	for ($i=0; $i<$circlesToDraw; $i++) {
		// generate random start spots and end spots
		$x = mt_rand(0, $imageInfo[0]);
		$y = mt_rand(0, $imageInfo[1]);
		$s1 = mt_rand(0, $imageInfo[0]);
		$s2 = mt_rand(0, $imageInfo[1]);
		$lineColor=imagecolorallocate($captchaImage, 87+rand(0,20), 158+rand(0,40), 92+rand(0,20));
		imageellipse( $captchaImage, $x, $y, $s1, $s2, $lineColor );
	}

	// Draw our randomly generated string to our captcha using the given true type font. In this case, I am using BitStream Vera Sans Bold, but you could modify it to any other font you wanted to use.
	for ($i=0;$i<strlen($captchaKey);$i++) {
		$textColor=imagecolorallocate($captchaImage, 67+rand(0,20), 138+rand(0,40), 92+rand(0,20));
		imagettftext($captchaImage, 18+rand(0,15), 0, 20+$i*25+rand(0,5), 30+rand(0,15), $textColor, $fontFile, substr($captchaKey,$i,1)) or die();
	}

	// Output the image to the browser, header settings prevent caching
	header("Content-type: image/png");
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Fri, 19 Jan 1994 05:00:00 GMT");
	header("Pragma: no-cache");
	imagepng($captchaImage);
