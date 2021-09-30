<?php

/*

	xImage: class to image threatment for PHP using GD library
	(cc) 2006 Cucaracha Software

	authors:
		Javier Gil Motos (alias Cucaracha) cucaracha -at- inertinc -dot- org (http://cucaracha.inertinc.org)
		Pablo Rodríguez Rey (alias mr.xkr) mr -at- xkr -dot- es (http://mr.xkr.es/)

	Modified by mr.xkr in Feb/2008 respect to class "Imagen" initially developed by Cucaracha.
	6th version revised by mr.xkr Sep/2021.

	Usage:
		$xi=new xImage("file.jpg");
		$xi->scale(240, 160);
		$xi->save("jpg", "file2.jpg");
		$xi->output();

*/

class xImage {

	public $im=null;

	/**
	 * Constructor.
	 * There are 4 ways to create:
	 *  1) no parameters: no image
	 *  2) one parameter as a string: load filename
	 *  3) one parameter as an image handler: it will be copied
	 *  4) width and height parameters (in pixels) to create a blank image
	 *
	 * @param Mixed First parameter, as stated above
	 * @param Mixed Second parameter as stated above
	 */
	function __construct($first=null, $second=null) {
		if ($first) {
			if ($second) $this->im=imagecreatetruecolor($first, $second);
			else {
				if (is_object($first)) $this->copy($first);
				if (is_string($first)) $this->load($first);
			}
		}
	}

	// destructor
	function __destruct() {
		if ($this->im) {
			@imagedestroy($this->im);
			$this->im=null;
		}
	}

	// return version
	static function version() {
		return 0.4;
	}

	// return handler
	function getHandler() {
		return $this->im;
	}

	// get image width
	function width() {
		return ($this->im?imagesx($this->im):false);
	}

	// get image height
	function height() {
		return ($this->im?imagesy($this->im):false);
	}

	// get format by magic bytes
	static function getFormatByMagic($bytes) {
		if (substr($bytes,0,3)=="\xFF\xD8\xFF") return "jpg";
		else if (substr($bytes,0,3)=="GIF") return "gif";
		else if (substr($bytes,1,3)=="PNG") return "png";
		return false;
	}

	// get mimetype by format
	static function getMimeByFormat($format="jpg") {
		switch ($format) {
		case "jpg": return "image/jpeg";
		case "gif": return "image/gif";
		case "png": return "image/png";
		default: return false;
		}
	}

	// get mimetype by magic bytes
	static function getMimeByMagic($bytes) {
		return self::getMimeByFormat(self::getFormatByMagic($bytes));
	}

	// get file format
	static function getFileFormat($filename) {
		if ($f=(@fopen($filename, "r"))) {
			$bytes=fread($f, 4);
			fclose($f);
			return self::getFormatByMagic($bytes);
		}
		return false;
	}

	// get file mimetype
	static function getFileMime($filename) {
		if ($format=self::getFileFormat($filename)) return self::getMimeByFormat($format);
		return false;
	}

	// create image from a string
	function fromString($s) {
		if ($imt=(@imagecreatefromstring($s))) $this->im=$imt;
		return ($imt?true:false);
	}

	// return image as a string
	function toString($format="jpg", $quality=75){
		ob_start();
		$this->output($format, $quality, false);
		$s=ob_get_contents();
		ob_end_clean();
		return $s;
	}

	// load image from file
	function load($filename) {
		$this->filename=$filename;
		if ($format=$this->getFileFormat($filename)) {
			switch ($format) {
			case "jpg": $this->im=imagecreatefromjpeg($filename); return true;
			case "gif": $this->im=imagecreatefromgif($filename); return true;
			case "png": $this->im=imagecreatefrompng($filename); return true;
			}
		}
		return false;
	}

	// output mime header
	function outMime($format="jpg") {
		if ($mimetype=$this->getMimeByFormat($format)) {
			header("Content-type: ".$mimetype);
			return true;
		}
		return false;
	}

	// output image
	function output($format="jpg", $quality=90, $withmime=true) {
		if ($withmime) $this->OutMime($format);
		switch ($format) {
		case "jpg": imagejpeg($this->im, null, $quality); return true;
		case "gif": imagegif($this->im); return true;
		case "png": imagepng($this->im); return true;
		}
		return false;
	}

	// save in disk
	function save($format="jpg", $filename, $quality=90) {
		switch ($format) {
		case "jpg": imagejpeg($this->im, $filename, $quality); return true;
		case "gif": imagegif($this->im, $filename); return true;
		case "png": imagepng($this->im, $filename); return true;
		}
		return false;
	}

	// paste an image in another
	function paste($i2, $x=0, $y=0, $forzar=false) {
		$im2=$i2->getHandler();
		if ($forzar) imagecopyresized($this->im,$im2,$x,$y,0,0,imagesx($this->im),imagesy($this->im),imagesx($im2),imagesy($im2));
		else imagecopy($this->im,$im2,$x,$y,0,0,imagesx($im2),imagesy($im2));
		return true;
	}

	// copy image
	function copy($i2) {
		$im2=$i2->getHandler();
		$this->im=imagecreatetruecolor(imagesx($im2),imagesy($im2));
		imagecopy($this->im,$im2,0,0,0,0,imagesx($im2),imagesy($im2));
		return true;
	}

	// add color to image
	function color($c=false) {
		if ($c && count($c)==3) return imagecolorallocate($this->im, $c[0], $c[1], $c[2]);
		return false;
	}

	// fill image with a color
	function fill($color=array(0,0,0)) {
		$c=$this->color($color);
		imagefilledrectangle($this->im,0,0, imagesx($this->im), imagesy($this->im), ($c?$c:0));
		return true;
	}

	// resize image
	function resize($width, $height, $resample=true) {
		// create container
		$imt=imagecreatetruecolor($width,$height);
		// create new image resized
			if ($resample) imagecopyresampled($imt,$this->im,0,0,0,0,$width,$height,imagesx($this->im),imagesy($this->im));
		else imagecopyresized($imt,$this->im,0,0,0,0,$width,$height,imagesx($this->im),imagesy($this->im));
		// destroy old and return new
		imagedestroy($this->im);
		$this->im=$imt;
		// all ok
		return true;
	}

	// scale image
	function scale($maxwidth, $maxheight, $border=false, $resample=true, $background=array(255,255,255)) {
		// cambiar tamaño manteniendo proporciones
		$p=imagesx($this->im)/imagesy($this->im);
		$nw=$maxwidth;
		$nh=$maxwidth/$p;
		if ($nh>$maxheight) {
			$nh=$maxheight;
			$nw=round($maxheight*$p);
			if (abs($nw-$maxwidth)<=4) $nw=$maxwidth;
			$x=($border?($maxwidth-$nw)/2:0);
			$y=0;
		} else {
			if (abs($nh-$maxheight)<=4) $nh=$maxheight;
			$x=0;
			$y=($border?($maxheight-$nh)/2:0);
		}
		$nh=round($nh);
		// create container
		if (!$border) $imt=imagecreatetruecolor($nw,$nh);
		else $imt=imagecreatetruecolor($maxwidth,$maxheight);
		// if border, paint with color ($border must be an array(R,G,B))
		$color=(is_array($border) && count($border) == 3
			?imagecolorallocate($imt,$border[0],$border[1],$border[2])
			:imagecolorallocate($imt,$background[0],$background[1],$background[2])
		);
		imagefilledrectangle($imt,0,0,imagesx($imt),imagesy($imt),$color);
		// create new image resized
		if ($resample) imagecopyresampled($imt,$this->im,$x,$y,0,0,$nw,$nh,imagesx($this->im),imagesy($this->im));
		else imagecopyresized($imt,$this->im,$x,$y,0,0,$nw,$nh,imagesx($this->im),imagesy($this->im));
		// destroy old and return new
		imagedestroy($this->im);
		$this->im=$imt;
		// todo ok
		return true;
	}

	// flip image
	function flip($f) {
		imageflip($this->im, $f);
		return true;
	}

	// rotate image
	function rotate($a, $c=false) {
		$c=$this->color($c);
		$this->im=imagerotate($this->im, $a, ($c?$c:0));
		return true;
	}

	// get exif data
	function exif($filename=null) {
		if (!$filename) $filename=$this->filename;
		if (!$filename) return false;
		if (!function_exists("exif_read_data")) return false;
		return @exif_read_data($filename);
	}

	// get orientation
	function getOrientation($filename=null) {
		$exif=$this->exif($filename);
		if ($exif && $exif['Orientation']) return $exif['Orientation'];
		return false;
	}

	// fix orientation
	function fixOrientation($filename=null) {
		if ($orientation=$this->getOrientation($filename)) switch ($orientation) {
		case 1: return true; // nothing
		case 2: $this->flip(IMG_FLIP_HORIZONTAL); return true; // horizontal flip
		case 3: $this->rotate(180); return true; // 180 rotate left
		case 4: $this->flip(IMG_FLIP_VERTICAL); return true; // vertical flip
		case 5: $this->flip(IMG_FLIP_VERTICAL); $this->rotate(-90); return true; // vertical flip + 90 rotate right
		case 6: $this->rotate(-90); return true; // 90 rotate right
		case 7: $this->flip(IMG_FLIP_HORIZONTAL); $this->rotate(-90); return true; // horizontal flip + 90 rotate right
		case 8: $this->rotate(90); return true; // 90 rotate left
		}
		return false;
	}

}
