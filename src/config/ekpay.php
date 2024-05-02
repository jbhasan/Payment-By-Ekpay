<?php

// SSLCommerz configuration

$api_domain = env('EKPAY_API_DOMAIN');
return [
	'credentials' => [
		'merchant_id' => env("EKPAY_MERCHANT_ID"),
		'merchant_password' => env("EKPAY_MERCHANT_PASSWORD"),
		'whitelisted_ip' => env("EKPAY_WHITELISTED_IP", '1.1.1.1'),
	],
	'urls' => [
		'make_payment' => "/merchant-api",
		'transaction_status' => "/get-status",
	],
	'api_domain' => $api_domain,
	'application_success_url' => env('EKPAY_SUCCESS_URL'),
	'application_failed_url' => env('EKPAY_FAILED_URL'),
	'application_cancel_url' => env('EKPAY_CANCEL_URL'),
	'application_ipn_email' => env('EKPAY_IPN_EMAIL'),
];
