<?php

/*

	Stripe v3 Payment Processing Class v0.1

	Testing cards:
		4242 4242 4242 4242 - No authentication (default U.S. card)
		4000 0027 6000 3184 - Authentication required
		4000 0000 0000 9995 - Always fails with a decline code of insufficient_funds.

	Example usage:

		// instance Stripe Payment
		$xstripe=new xStripe([
			"pk"=>'pk_test_YOUR_PUBLIC_KEY',
			"sk"=>'sk_test_YOUR_SECRET_KEY',
		]);

		// set operation
		$xstripe->set([
			"amount"=>1.69, // decimal values allowed
			"metadata"=>[
				"item_id"=>1,
			],
			"onok"=>function($xstripe, $intent){
				// payment OK
			},
			"onko"=>function($xstripe, $error){
				// payment KO
			}
		]);

		$xstripe->ajax();
		$data=($data??[])+$xstripe->data();
		$js=($js??[])+$xstripe->js();

*/
class xStripe {

	protected $defaults=[
		"version"=>"0.1",
		"ajax"=>"xstripe.pay",
		"currency"=>"eur",
		"metadata"=>[],
		"base"=>"https://api.stripe.com/",
	];
	protected $error="";
	protected $o;

	// constructor/getter/setter/isset options
	function __construct($o) { $this->o=$o+$this->defaults; }
	function __get($k) { return $this->o[$k]; }
	function __set($k, $v) { $this->o[$k]=$v; }
	function __isset($k) { return isset($this->o[$k]); }

	// get defaults/get/set multiple parameters
	function defaults() { return $this->defaults; }
	function get() { return $this->o; }
	function set(array $a) { foreach ($a as $k=>$v) $this->o[$k]=$v; }

	// get account balance
	function balance() {
		return $this->request("v1/balance");
	}

	// create customer
	function customer(array $o) {
		return $this->request("v1/customers");
	}

	// create payment source
	function source(array $o) {
		return $this->request("v1/sources", $o);
	}

	// do request
	function request($url, $fields=null, $o=[]) {

		// CURL requisite
		if (!function_exists("curl_init")) return $this->error("xStripe: CURL module not installed.");

		// prepare POST
		if ($fields !== null) {
			$o[CURLOPT_POST]=1;
			$o[CURLOPT_POSTFIELDS]=(is_array($fields)?http_build_query($fields):$fields);
		}

		// CURL request
		$ch=curl_init();
		curl_setopt_array($ch, $o+[
			//CURLOPT_VERBOSE=>1, // CAUTION: only for debuging purposes
			CURLOPT_URL=>$this->base.$url,
			CURLOPT_FRESH_CONNECT=>1,
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_FORBID_REUSE=>1,
			//CURLOPT_HEADER=>1,
			//CURLINFO_HEADER_OUT=>1,
			CURLOPT_HTTPHEADER=>[
				"Authorization: Bearer ".$this->sk,
				"User-Agent: xStripe ".$this->version,
				//"Content-Type: application/json; charset=UTF-8",
				//"Expect:",
			],
		]);
		$this->response=curl_exec($ch);
		$this->info=curl_getinfo($ch);
		//file_put_contents("data/curldebug.txt", "\n[REQUEST]\n".json_encode($this->info, JSON_PRETTY_PRINT)."\n[POST]\n".$post."\n[RESPONSE]\n".$this->response."\n\n", FILE_APPEND);

		// separate headers
		/*if ($i=strpos($r, "\r\n\r\n")) {
			$h=explode("\r\n", substr($r, 0, $i));
			$r=substr($r, $i+4);
		} else {
			$h=[$r];
			$r="";
		}
		$f["headers"]=[];
		if ($h) for ($i=1; $i<count($h); $i++) {
			$l=$h[$i];
			$p=strpos($l, ": ");
			$f["headers"][strtolower(substr($l, 0, $p))]=substr($l, $p+2);
		}*/

		// close CURL connection
		curl_close($ch);

		// return response as array
		return json_decode($this->response, true);

	}

	// process payment
	function process(array $o=[]) {
		if (!$this->sk) return $this->err("Secret Key (sk) parameter not specified.");
		if (!$this->amount) return $this->err("No ammount specified.");
		if (!$this->currency) return $this->err("No currency specified.");

		// set secret key
		\Stripe\Stripe::setApiKey($this->sk);

		// create payment intent
		try {
			$intent=false;
			if ($o["payment_method_id"]) {
				$intent=\Stripe\PaymentIntent::create([
					'payment_method'     =>$o["payment_method_id"],
					'amount'             =>round($this->amount*100),
					'currency'           =>$this->currency,
					'metadata'           =>$this->metadata,
					'confirmation_method'=>'manual',
					'confirm'            =>true,
				]);
			} else if ($o["payment_intent_id"]) {
				$intent=\Stripe\PaymentIntent::retrieve($o["payment_intent_id"]);
				$intent->confirm();
			}
		/*
		} catch(\Stripe\Exception\CardException $e) {
			// since it's a decline, \Stripe\Exception\CardException will be caught
			echo 'Status is:' . $e->getHttpStatus() . '\n';
			echo 'Type is:' . $e->getError()->type . '\n';
			echo 'Code is:' . $e->getError()->code . '\n';
			echo 'Param is:' . $e->getError()->param . '\n';
			echo 'Message is:' . $e->getError()->message . '\n';
		} catch (\Stripe\Exception\RateLimitException $e) {
			// too many requests made to the API too quickly
		} catch (\Stripe\Exception\InvalidRequestException $e) {
			// invalid parameters were supplied to Stripe's API
		} catch (\Stripe\Exception\AuthenticationException $e) {
			// authentication with Stripe's API failed (maybe you changed API keys recently)
		} catch (\Stripe\Exception\ApiConnectionException $e) {
			// network communication with Stripe failed
		*/
		} catch (\Stripe\Exception\ApiErrorException $e) {
			// display a very generic error to the user, and maybe send yourself an email
			//ajax(["err"=>$e->getMessage()]);
			return $this->error("Stripe ApiErrorException: ".$e->getMessage());
		} catch (Exception $e) {
			// something else happened, completely unrelated to Stripe
			return $this->error("Exception: ".$e->getMessage());
		}

		// check intent
		if ($intent) {
			// if required action
			if (in_array($intent->status, ['requires_action', 'requires_source_action']) && $intent->next_action->type == 'use_stripe_sdk') {
				return [
					"client_secret"=>$intent->client_secret,
					"ok"=>true,
				];
			// if confirmation succeeded
			} else if ($intent->status == 'succeeded') {
				if ($onok=$this->onok) $r=$onok($this, $intent);
				return (is_array($r)?$r:[])+["ok"=>true];
			}
		}

		// payment failed
		return $this->error(($intent && $intent->status?$intent->status:"Unknown"));

	}

	// process AJAX action
	function ajax() {
		global $ajax, $adata;
		if ($ajax == $this->ajax) {
			if (!($r=$this->process($adata))) $this->err();
			ajax($r);
		}
	}

	// return client data
	function data() {
		return [
			"stripe_pk"=>$this->pk,
		];
	}

	// return required JS
	function js() {
		return [
			"https://js.stripe.com/v3/"=>true,
		];
	}

	// get/set error
	function error($error=null) {
		if ($error === null) return $this->error;
		$this->error=$error;
		if ($onko=$this->onko) $r=$onko($this, $error);
		return false;
	}

	// throw error
	function err($exit=1) {
		$message=$this." ".($this->error?"ERROR: ".$this->error:"Undefined error");
		if ($GLOBALS["ajax"] && function_exists("ajax")) ajax(["err"=>$message]);
		if (function_exists("perror")) perror($text, $exit);
		echo $text."\n";
		if ($exit) exit($exit);
	}

	// string with basic info
	function __toString() {
		return "(".get_class($this).")";
	}

}
