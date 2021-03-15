<?php

	/*
		Clase de acceso a cifrado 0.1
		Permite abstraer las funciones de cifrado hacia mcrypt/openssl.
	*/
	class Crypt {

		protected $openssl;
		protected $algorithm;
		protected $mode;
		protected $filter;
		protected $unpadding;
		protected $key;
		protected $iv;
		protected $iv_size;
		protected $opensslOptions;

		// inicialización
		public function __construct($o=Array()) {
			// configuraciones y opciones por defecto
			$this->openssl=(isset($o["openssl"])?$o["openssl"]:false);
			$this->algorithm=(isset($o["algorithm"])?$o["algorithm"]:"blowfish");
			$this->mode=(isset($o["mode"])?$o["mode"]:"cbc");
			$this->filter=(isset($o["filter"])?$o["filter"]:"base64uf");
			$this->unpadding=(isset($o["unpadding"])?$o["unpadding"]:true);
			$this->key=$o["key"];
			$this->openssl=(isset($o["openssl"])?$o["openssl"]:false);
			$this->opensslOptions=OPENSSL_RAW_DATA;
			// conmutar a OpenSSL si mcrypt no disponible y no forzado
			if (!isset($o["openssl"]) && function_exists("openssl_open")) $this->openssl=true;
			// verificar módulos requeridos
			if ($this->openssl && !function_exists("openssl_open")) die("OpenSSL module not enabled.");
			else if (!$this->openssl && !function_exists("mcrypt_cbc")) die("MCrypt module not enabled.");
			// obtener tamaño de IV
			$this->iv_size=($this->openssl
				?openssl_cipher_iv_length($this->getAlgorithm()."-".$this->mode)
				:mcrypt_get_iv_size($this->algorithm, $this->mode)
			);
			// generar IV
			$this->iv=(isset($o["iv"])
				?str_pad("", $this->iv_size, $o["iv"])
				:str_pad("", $this->iv_size, "\0")
			);
		}

		// obtener algoritmo real dependiendo del tipo de cifrador
		public function getAlgorithm() {
			if ($this->openssl) {
				switch ($this->algorithm) {
				case "blowfish": return "bf";
				}
			}
			return $this->algorithm;
		}

		// asignar/obtener clave
		public function key($key=null) {
			if ($key!==null) $this->key=$key;
			else return $key;
		}

		// asignar/obtener filtro
		public function filter($filter=null) {
			if ($filter!==null) $this->filter=$filter;
			else return $filter;
		}

		// encriptar
		public function encrypt($data) {
			return ($this->openssl
				?$this->filter_encode($this->filter, openssl_encrypt($data, $this->getAlgorithm()."-".$this->mode, $this->key, $this->opensslOptions, $this->iv))
				:$this->filter_encode($this->filter, mcrypt_encrypt($this->getAlgorithm(), $this->key, $data, $this->mode, $this->iv))
			);
		}

		// desencriptar
		public function decrypt($data) {
			return ($this->openssl
				?openssl_decrypt($this->filter_decode($this->filter, $data), $this->getAlgorithm()."-".$this->mode, $this->key, $this->opensslOptions, $this->iv)
				:$this->unpadding(mcrypt_decrypt($this->getAlgorithm(), $this->key, $this->filter_decode($this->filter, $data), $this->mode, $this->iv))
			);
		}

		// quitar padding
		public function unpadding($data) {
			return ($this->unpadding?str_replace("\0","",$data):$data);
		}

		// filtro de codificación
		public function filter_encode($filter, $data) {
			if (!$filter) return $data;
			switch ($filter) {
			case "base64": return base64_encode($data);
			case "base64uf": return $this->base64uf_encode($data);
			}
		}

		// filtro de decodificación
		public function filter_decode($filter, $data) {
			if (!$filter) return $data;
			switch ($filter) {
			case "base64": return base64_decode($data);
			case "base64uf": return $this->base64uf_decode($data);
			}
		}

		// codificar a base64-URL-Friendly
		public function base64uf_encode($d) {
			$d=base64_encode($d);
			$d=str_replace("+",".",$d);
			$d=str_replace("/","_",$d);
			$d=str_replace("=","-",$d);
			return $d;
		}

		// decodificar base64-URL-Friendly
		public function base64uf_decode($d) {
			$d=str_replace(".","+",$d);
			$d=str_replace("_","/",$d);
			$d=str_replace("-","=",$d);
			$d=base64_decode($d);
			return $d;
		}

	}
