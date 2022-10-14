<?php

/*

	xCaptcha: Generator class for Captchas v0.1.1
	Upgrade of cool-php-captcha de Jose Rodriguez adapted to base classes.
	License: GPLv3
	Author: Pablo Rodriguez Rey

	Example of instance:
		$xcaptcha=new xCaptcha(["noise"=>true]);

	Example of generator:
		$xcaptcha->output();

	Example of validator:
		if ($xcaptcha->verify("NUMBER")) {
			$xcaptcha->destroy();
			die("Bad security code.");
		}

*/
class xCaptcha {

	public $defaults=array(
		"debug"=>false,
		"blur"=>false,
		"line"=>false,
		"noise"=>true,
		"shadow"=>true,
		"wave"=>true,
	);
	public $required=array(
		"session"=>"xcaptcha",
		"scale"=>2,
		"lineWidth"=>2,
		"noiseSize"=>1000,
		"noiseWidth"=>20,
		"noiseHeight"=>20,
		"noiseAlpha"=>105,
		"shadowColor"=>array(99, 99, 99),
		"minWordLength"=>5,
		"maxWordLength"=>6,
		"backgroundColor"=>array(255, 255, 255),
		"colors"=>array(
			array(27, 78, 181), // blue
			array(27, 178, 181), // cyan
			array(22, 163, 35), // green
			array(122, 163, 35), // militar green
			array(214, 56, 0), // red
			array(214, 156, 0), // orange
		),
		"width"=>0,
		"height"=>64,
		"fontPath"=>"fonts/",
		"format"=>"jpg",
		"quality"=>85,
		"Yamplitude"=>8,
		"Yperiod"=>11,
		"Xamplitude"=>5,
		"Xperiod"=>11,
		"maxRotation"=>8,
	);
	public $fonts=array(
		'Antykwa' =>array('spacing'=>-3, 'minSize'=>27, 'maxSize'=>30, 'file'=>'AntykwaBold.ttf'),
		'Candice' =>array('spacing' =>-1.5,'minSize'=>28, 'maxSize'=>31, 'file'=>'Candice.ttf'),
		'DingDong'=>array('spacing'=>-2, 'minSize'=>24, 'maxSize'=>30, 'file'=>'Ding-DongDaddyO.ttf'),
		'Duality' =>array('spacing'=>-2, 'minSize'=>30, 'maxSize'=>38, 'file'=>'Duality.ttf'),
		'Heineken'=>array('spacing'=>-2, 'minSize'=>24, 'maxSize'=>34, 'file'=>'Heineken.ttf'),
		'Jura'    =>array('spacing'=>-2, 'minSize'=>28, 'maxSize'=>32, 'file'=>'Jura.ttf'),
		'StayPuft'=>array('spacing' =>-1.5,'minSize'=>28, 'maxSize'=>32, 'file'=>'StayPuft.ttf'),
		'Times'   =>array('spacing'=>-2, 'minSize'=>28, 'maxSize'=>34, 'file'=>'TimesNewRomanBold.ttf'),
		'VeraSans'=>array('spacing'=>-1, 'minSize'=>20, 'maxSize'=>28, 'file'=>'VeraSansBold.ttf'),
	);
	protected $im;
	protected $o;

	function __construct($o=array()) {

		// default options
		$o=array_merge($this->defaults, $o);

		// required options
		foreach ($this->required as $n=>$v)
			if (!$o[$n]) $o[$n]=$v;

		// check width
		if (!$o["width"]) $o["width"]=20+$o["maxWordLength"]*20;

		// other requisites
		if ($o["color"]) $o["colors"]=array($o["color"]);
		if ($o["fonts"]) $this->fonts=array_merge($this->fonts, $o["fonts"]);

		// ensure any font exists
		foreach ($this->fonts as $n=>$font)
			if ($font === false || !file_exists($o["fontPath"].$font["file"]))
				unset($this->fonts[$n]);
		if (!$this->fonts)
			$this->err("No fonts available!");

		// guardar opciones
		$this->o=$o;

	}

	// getter/setter/isset
	function __get($n) { return $this->o[$n]; }
	function __set($n, $v) { $this->o[$n]=$v; }
	function __isset($n) { return isset($this->o[$n]); }

	// critical error message
	function err($msg, $exit=1) {
		$_msg="xCaptcha Error: ".$msg;
		if (function_exists("perror")) perror($_msg);
		else echo $_msg;
		if ($exit) exit($exit);
	}
	
	// last sessioned captcha value
	function get() {
		return $_SESSION[$this->session];
	}

	// get last image
	function imageGet() {
		return $this->im;
	}

	// verify sessioned captcha
	function verify($captcha) {
		$captcha=strtolower(trim($captcha));
		if (!$captcha) return false;
		if (!$_SESSION[$this->session]) return false;
		return ($captcha == strtolower($_SESSION[$this->session]));
	}

	// destroy sessioned captcha to prevent reuse
	function destroy() {
		unset($_SESSION[$this->session]);
	}

	// render
	function imageRender() {
		$start_time=microtime(true);

		// image allocation
		$this->imageAllocate();

		// captcha text insertion
		$_SESSION[$this->session]=$text=$this->GetCaptchaText();
		$this->WriteText($text);
		if ($this->noise) $this->imageNoise();
		if ($this->line) $this->writeLine($this->lineWidth);
		if ($this->wave) $this->imageWave();
		/*if ($this->blur && function_exists('imagefilter')) {
			//imagefilter($this->im, IMG_FILTER_SELECTIVE_BLUR);
			//imagefilter($this->im, IMG_FILTER_PIXELATE, 2);
			//imagefilter($this->im, IMG_FILTER_GAUSSIAN_BLUR);
			//imagefilter($this->im, IMG_FILTER_EDGEDETECT);
			imagefilter($this->im, IMG_FILTER_SMOOTH, 10);
		}*/
		$this->reduceImage();
		if ($this->debug) {
			imagestring($this->im, 1, 1, $this->height-8,
			"$text {$fontcfg['font']} ".round((microtime(true)-$start_time)*1000)."ms",
			$this->imForegroundColor
			);
		}
		return true;

	}

	// creates the image resources
	protected function imageAllocate() {

		// deallocate last image
		if (!empty($this->im)) imagedestroy($this->im);

		// create image
		$this->im=imagecreatetruecolor($this->width*$this->scale, $this->height*$this->scale);

		// background color
		$this->imBackgroundColor=imagecolorallocate($this->im,
			$this->backgroundColor[0],
			$this->backgroundColor[1],
			$this->backgroundColor[2]
		);
		imagefilledrectangle($this->im, 0, 0, $this->width*$this->scale, $this->height*$this->scale, $this->imBackgroundColor);

		// noise color
		$this->imNoiseColor=imagecolorallocatealpha($this->im,
			$this->backgroundColor[0],
			$this->backgroundColor[1],
			$this->backgroundColor[2],
			$this->noiseAlpha
		);
		imagefilledrectangle($this->im, 0, 0, $this->width*$this->scale, $this->height*$this->scale, $this->imBackgroundColor);

		// foreground color
		$color=$this->colors[mt_rand(0, sizeof($this->colors)-1)];
		$this->imForegroundColor=imagecolorallocate($this->im, $color[0], $color[1], $color[2]);

		// shadow color
		if ($this->shadowColor && is_array($this->shadowColor) && sizeof($this->shadowColor) >= 3) {
			$this->imShadowcolor=imagecolorallocate($this->im,
				$this->shadowColor[0],
				$this->shadowColor[1],
				$this->shadowColor[2]
			);
		}

	}

	// captcha text generation
	function getCaptchaText() {
		$text=$this->getDictionaryCaptchaText();
		if (!$text) $text=$this->getRandomCaptchaText();
		return $text;
	}

	// random captcha text generation
	function getRandomCaptchaText($length=null) {
		if (empty($length)) $length=rand($this->minWordLength, $this->maxWordLength);
		$words="abcdefghijlmnopqrstvwyz";
		$vocals="aeiou";
		$text="";
		$vocal=rand(0, 1);
		for ($i=0; $i<$length; $i++) {
			$text.=($vocal
				?substr($vocals, mt_rand(0, 4), 1)
				:substr($words, mt_rand(0, 22), 1)
			);
			$vocal=!$vocal;
		}
		return $text;
	}

	// random captcha dictionary word generation
	function getDictionaryCaptchaText() {
		if (!$this->dictFile) return false;
		if (!file_exists($this->dictFile)) return false;
		if (!($fp=fopen($this->dictFile, "r"))) return false;
		if (!($length=strlen(fgets($fp)))) return false;
		$line=rand(1, (filesize($this->dictFile)/$length)-2);
		if (fseek($fp, $length*$line) == -1) return false;
		$text=trim(fgets($fp));
		fclose($fp);
		// change ramdom volcals
		if ($this->dictRandomVocals) {
			$text=preg_split('//', $text, -1, PREG_SPLIT_NO_EMPTY);
			$vocals=array('a','e','i','o','u');
			foreach ($text as $i=>$c)
				if (mt_rand(0, 1) && in_array($c, $vocals))
					$text[$i]=$vocals[mt_rand(0, 4)];
			$text=implode('', $text);
		}
		return $text;
	}

	// horizontal line insertion
	protected function writeLine($lineWidth) {
		$x1=$this->width*$this->scale*.15;
		$x2=$this->textFinalX;
		$y1=rand($this->height*$this->scale*.40, $this->height*$this->scale*.65);
		$y2=rand($this->height*$this->scale*.40, $this->height*$this->scale*.65);
		$width=$lineWidth/2*$this->scale;
		for ($i=$width*-1; $i <= $width; $i++)
			imageline($this->im, $x1, $y1+$i, $x2, $y2+$i, $this->imForegroundColor);
	}

	// captcha text render
	protected function writeText($text) {
		// font file
		$fontcfg=$this->fonts[array_rand($this->fonts)];
		$fontfile=$this->fontPath.$fontcfg['file'];
		// increase font-size for shortest words: 9% for each glyp missing
		$lettersMissing=$this->maxWordLength-strlen($text);
		$fontSizefactor=1+($lettersMissing*0.09);
		// text generation (char by char)
		$x     =15*$this->scale;
		$y     =10+round(($this->height*27/40)*$this->scale);
		$length=strlen($text);
		for ($i=0; $i<$length; $i++) {
			$degree  =rand($this->maxRotation*-1, $this->maxRotation);
			$fontsize=rand($fontcfg['minSize'], $fontcfg['maxSize'])*$this->scale*$fontSizefactor;
			$letter  =substr($text, $i, 1);
			if ($this->imShadowcolor) {
				$coords=imagettftext($this->im, $fontsize, $degree,
				$x+$this->scale, $y+$this->scale,
				$this->imShadowcolor, $fontfile, $letter);
			}
			$coords=imagettftext($this->im, $fontsize, $degree, $x, $y, $this->imForegroundColor, $fontfile, $letter);
			$x+=($coords[2]-$x) + ($fontcfg['spacing']*$this->scale);
		}
		$this->textFinalX=$x;
	}

	// noise filter
	function imageNoise() {
		for ($i=0; $i<$this->noiseSize; $i++)
			imagefilledellipse($this->im,
				rand(0, $this->width*$this->scale),
				rand(0, $this->height*$this->scale),
				rand(1, $this->noiseWidth),
				rand(1, $this->noiseHeight),
				$this->imNoiseColor
			);
	}

	// wave filter
	protected function imageWave() {

		// X-axis wave generation
		$k=rand(0, 100);
		$xp=$this->scale*$this->Xperiod*rand(1,3);
		for ($i=0; $i < ($this->width*$this->scale); $i++)
			imagecopy($this->im, $this->im, $i-1, sin($k+$i/$xp) * ($this->scale*$this->Xamplitude), $i, 0, 1, $this->height*$this->scale);

		// Y-axis wave generation
		$k=rand(0, 100);
		$yp=$this->scale*$this->Yperiod*rand(1,2);
		for ($i=0; $i < ($this->height*$this->scale); $i++)
			imagecopy($this->im, $this->im, sin($k+$i/$yp) * ($this->scale*$this->Yamplitude), $i-1, 0, $i, $this->width*$this->scale, 1);

	}

	// reduce the image to the final size
	protected function reduceImage() {
		$imResampled=imagecreatetruecolor($this->width, $this->height);
		imagecopyresampled($imResampled, $this->im, 0, 0, 0, 0, $this->width, $this->height, $this->width*$this->scale, $this->height*$this->scale);
		imagedestroy($this->im);
		$this->im=$imResampled;
	}

	// output to image
	function output($format=null, $quality=null) {
		if (!$this->im)
			if (!$this->imageRender())
				return false;
		if (!$format) $format=$this->format;
		if (!$quality) $quality=$this->quality;
		if ($this->mime) header("Content-Type: ".$this->mime);
		switch ($format) {
		case "png":
			if (!isset($this->mime)) header("Content-Type: image/png");
			imagepng($this->im);
			return true;
		case "jpg":
			if (!isset($this->mime)) header("Content-Type: image/jpeg");
			imagejpeg($this->im, null, $quality);
			return true;
		}
		return false;
	}

}
