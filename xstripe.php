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
		"ajax"=>"xstripe.pay",
		"currency"=>"eur",
		"metadata"=>[],
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
			ajax(["err"=>$e->getMessage()]);
		// something else happened, completely unrelated to Stripe
			// display a very generic error to the user, and maybe send yourself an email
			return $this->error("Stripe ApiErrorException: ".$e->getMessage());
		} catch (Exception $e) {
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
