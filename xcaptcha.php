<?php

	/*
		xCaptcha: Clase generadora y para control de Captchas v0.1
		Modificación de cool-php-captcha de Jose Rodriguez.
		Licencia: GPLv3
		Autor: Pablo Rodriguez Rey
		
		Sintaxis:
			$xcaptcha=new xCaptcha(Array("noise"=>true));
			$xcaptcha->output();
			$xcaptcha->get();
		
	*/
	class xCaptcha {

		// font configuration: (font: TTF file, spacing: relative pixel space between character,
		// minSize: min font size, maxSize: max font size)
		protected $fonts=array(
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
		protected $Yperiod=12;
		protected $Yamplitude=8;
		protected $Xperiod=11;
		protected $Xamplitude=5;
		protected $maxRotation=8;
		protected $scale=2;
		protected $blur=false;
		protected $debug=false;
		protected $im;
		protected $o;
		public $width=170;
		public $height=64;

		function __construct($o=array()) {
			// verificar opciones
			
			if (!$o["session"]) $o["session"]="xcaptcha";
			if (!$o["line"]) $o["line"]=false;
			if (!$o["scale"]) $o["scale"]=2;
			if (!$o["noise"]) $o["noise"]=false;
			if (!isset($o["shadow"])) $o["shadow"]=true;
			if (!isset($o["wave"])) $o["wave"]=true;
			if (!$o["lineWidth"]) $o["lineWidth"]=2;
			if (!$o["noiseSize"]) $o["noiseSize"]=1000;
			if (!$o["noiseWidth"]) $o["noiseWidth"]=20;
			if (!$o["noiseHeight"]) $o["noiseHeight"]=20;
			if (!$o["noiseAlpha"]) $o["noiseAlpha"]=105;
			if (!$o["shadowColor"]) $o["shadowColor"]=array(127,127,127);
			if (!$o["minWordLength"]) $o["minWordLength"]=5;
			if (!$o["maxWordLength"]) $o["maxWordLength"]=6;
			if (!$o["backgroundColor"]) $o["backgroundColor"]=array(255, 255, 255);
			if (!$o["colors"]) $o["colors"]=array(
				array(27,78,181), // blue
				array(27,178,181), // cyan
				array(22,163,35), // green
				array(122,163,35), // militar green
				array(214,56,0),  // red
				array(214,156,0),  // orange
			);
			if ($o["color"]) $o["colors"]=array($o["color"]);
			if ($o["width"]) $this->width=$o["width"];
			if ($o["height"]) $this->height=$o["height"];
			if ($o["fonts"]) $this->fonts=array_merge($this->fonts, $o["fonts"]);
			if (!$o["fontPath"]) $o["fontPath"]="fonts/";
			// verificar que existan los ficheros de tipos de letra
			foreach ($this->fonts as $n=>$font)
				if ($font===false || !file_exists($o["fontPath"].$font["file"]))
					unset($this->fonts[$n]);
			if (!$this->fonts)
				$this->perror("No fonts available!");
			// guardar opciones
			$this->o=$o;
		}
		
		// mostrar un mensaje de error crítico
		function perror($msg) {
			$_msg="xCaptcha Error: ".$msg;
			if (function_exists("perror")) perror($_msg);
			else die($_msg);
		}
		
		// obtener último captcha de sesión
		function get() {
			return $_SESSION[$this->o["session"]];
		}

		// obtener imagen GD
		function imageGet() {
			return $this->im;
		}

		// verificar captcha de sesión
		function verify($captcha) {
			$captcha=strtolower(trim($captcha));
			if (!$captcha) return false;
			if (!$_SESSION[$this->o["session"]]) return false;
			return ($captcha == strtolower($_SESSION[$this->o["session"]]));
		}

		// destruir captcha de sesión para que no pueda ser reutilizado
		function destroy() {
			unset($_SESSION[$this->o["session"]]);
		}

		// renderizar
		function imageRender() {
			$start_time=microtime(true);
			// image allocation
			$this->imageAllocate();
			// captcha text insertion
			$_SESSION[$this->o["session"]]=$text=$this->GetCaptchaText();
			$this->WriteText($text);
			if ($this->o["noise"]) $this->imageNoise();
			if ($this->o["line"]) $this->writeLine($this->o["lineWidth"]);
			if ($this->o["wave"]) $this->imageWave();
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
			$this->im=imagecreatetruecolor($this->width*$this->o["scale"], $this->height*$this->o["scale"]);
			// background color
			$this->imBackgroundColor=imagecolorallocate($this->im,
				$this->o["backgroundColor"][0],
				$this->o["backgroundColor"][1],
				$this->o["backgroundColor"][2]
			);
			imagefilledrectangle($this->im, 0, 0, $this->width*$this->o["scale"], $this->height*$this->o["scale"], $this->imBackgroundColor);
			// noise color
			$this->imNoiseColor=imagecolorallocatealpha($this->im,
				$this->o["backgroundColor"][0],
				$this->o["backgroundColor"][1],
				$this->o["backgroundColor"][2],
				$this->o["noiseAlpha"]
			);
			imagefilledrectangle($this->im, 0, 0, $this->width*$this->o["scale"], $this->height*$this->o["scale"], $this->imBackgroundColor);
			// foreground color
			$color=$this->o["colors"][mt_rand(0, sizeof($this->o["colors"])-1)];
			$this->imForegroundColor=imagecolorallocate($this->im, $color[0], $color[1], $color[2]);
			// shadow color
			if ($this->o["shadowColor"] && is_array($this->o["shadowColor"]) && sizeof($this->o["shadowColor"]) >= 3) {
				$this->imShadowcolor=imagecolorallocate($this->im,
					$this->o["shadowColor"][0],
					$this->o["shadowColor"][1],
					$this->o["shadowColor"][2]
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
			if (empty($length)) $length=rand($this->o["minWordLength"], $this->o["maxWordLength"]);
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
			if (!$this->o["dictFile"]) return false;
			if (!file_exists($this->o["dictFile"])) return false;
			if (!($fp=fopen($this->o["dictFile"], "r"))) return false;
			if (!($length=strlen(fgets($fp)))) return false;
			$line=rand(1, (filesize($this->o["dictFile"])/$length)-2);
			if (fseek($fp, $length*$line) == -1) return false;
			$text=trim(fgets($fp));
			fclose($fp);
			// change ramdom volcals
			if ($this->o["dictRandomVocals"]) {
				$text=preg_split('//', $text, -1, PREG_SPLIT_NO_EMPTY);
				$vocals=array('a','e','i','o','u');
				foreach ($text as $i=>$c)
					if (mt_rand(0, 1) && in_array($c, $vocals))
						$text[$i] = $vocals[mt_rand(0, 4)];
				$text=implode('', $text);
			}
			return $text;
		}

		// horizontal line insertion
		protected function writeLine($lineWidth) {
			$x1 = $this->width*$this->o["scale"]*.15;
			$x2 = $this->textFinalX;
			$y1 = rand($this->height*$this->o["scale"]*.40, $this->height*$this->o["scale"]*.65);
			$y2 = rand($this->height*$this->o["scale"]*.40, $this->height*$this->o["scale"]*.65);
			$width = $lineWidth/2*$this->o["scale"];
			for ($i = $width*-1; $i <= $width; $i++)
				imageline($this->im, $x1, $y1+$i, $x2, $y2+$i, $this->imForegroundColor);
		}

		// captcha text render
		protected function writeText($text) {
			// font file
			$fontcfg=$this->fonts[array_rand($this->fonts)];
			$fontfile = $this->o["fontPath"].$fontcfg['file'];
			// increase font-size for shortest words: 9% for each glyp missing
			$lettersMissing = $this->o["maxWordLength"]-strlen($text);
			$fontSizefactor = 1+($lettersMissing*0.09);
			// text generation (char by char)
			$x      = 15*$this->o["scale"];
			$y      = 10+round(($this->height*27/40)*$this->o["scale"]);
			$length = strlen($text);
			for ($i=0; $i<$length; $i++) {
				$degree   = rand($this->maxRotation*-1, $this->maxRotation);
				$fontsize = rand($fontcfg['minSize'], $fontcfg['maxSize'])*$this->o["scale"]*$fontSizefactor;
				$letter   = substr($text, $i, 1);
				if ($this->imShadowcolor) {
					$coords = imagettftext($this->im, $fontsize, $degree,
					$x+$this->o["scale"], $y+$this->o["scale"],
					$this->imShadowcolor, $fontfile, $letter);
				}
				$coords = imagettftext($this->im, $fontsize, $degree, $x, $y, $this->imForegroundColor, $fontfile, $letter);
				$x += ($coords[2]-$x) + ($fontcfg['spacing']*$this->o["scale"]);
			}
			$this->textFinalX=$x;
		}

		// noise filter
		function imageNoise() {
			for ($i=0; $i<$this->o["noiseSize"]; $i++)
				imagefilledellipse($this->im, rand(0, $this->width*$this->o["scale"]), rand(0, $this->height*$this->o["scale"]),
			rand(1,$this->o["noiseWidth"]),rand(1,$this->o["noiseHeight"]),
			$this->imNoiseColor);
		}

		// wave filter
		protected function imageWave() {
			// X-axis wave generation
			$k=rand(0, 100);
			$xp=$this->o["scale"]*$this->Xperiod*rand(1,3);
			for ($i=0; $i < ($this->width*$this->o["scale"]); $i++)
				imagecopy($this->im, $this->im, $i-1, sin($k+$i/$xp) * ($this->o["scale"]*$this->Xamplitude), $i, 0, 1, $this->height*$this->o["scale"]);
			// Y-axis wave generation
			$k=rand(0, 100);
			$yp=$this->o["scale"]*$this->Yperiod*rand(1,2);
			for ($i=0; $i < ($this->height*$this->o["scale"]); $i++)
				imagecopy($this->im, $this->im, sin($k+$i/$yp) * ($this->o["scale"]*$this->Yamplitude), $i-1, 0, $i, $this->width*$this->o["scale"], 1);
		}

		// reduce the image to the final size
		protected function reduceImage() {
			$imResampled=imagecreatetruecolor($this->width, $this->height);
			imagecopyresampled($imResampled, $this->im, 0, 0, 0, 0, $this->width, $this->height, $this->width*$this->o["scale"], $this->height*$this->o["scale"]);
			imagedestroy($this->im);
			$this->im=$imResampled;
		}

		// output to image
		function output($format="jpg", $quality=85) {
			if (!$this->im)
				if (!$this->imageRender())
					return false;
			if ($this->o["mime"]) header("Content-Type: ".$this->o["mime"]);
			switch ($format) {
			case "png":
				if (!isset($this->o["mime"])) header("Content-Type: image/png");
				imagepng($this->im);
				return true;
			case "jpg":
				if (!isset($this->o["mime"])) header("Content-Type: image/jpeg");
				imagejpeg($this->im, null, $quality);
				return true;
			}
			return false;
		}
		
	}
