<?php
/**
 * This is shop system taken from Gesior, modified for MyAAC.
 *
 * @name      myaac-gesior-shop-system
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @website   github.com/slawkens/myaac-gesior-shop-system
 */

require_once '../common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';
require_once PLUGINS . 'gesior-shop-system/libs/shop-system.php';
require_once PLUGINS . 'gesior-shop-system/config.php';

if(!isset($config['paypal']) || !count($config['paypal']) || !count($config['paypal']['options']))
	die('PayPal disabled.');

$ip = $_SERVER['REMOTE_ADDR'];

require PLUGINS . 'gesior-shop-system/libs/paypal.php';
$ipn = new PaypalIPN();

// Use the sandbox endpoint during testing.
if(isset($config['paypal']['use_sandbox']) && $config['paypal']['use_sandbox']) {
	$ipn->useSandbox();
}

$verified = $ipn->verifyIPN();
if (!$verified) {
	log_append('paypal_scammer.log', $ip);
	die('Access denied.');
}

$paylist = $config['paypal']['options'];
$custom = stripslashes(trim($_REQUEST['custom']));
$payer_email = $_REQUEST['payer_email'];
$receiver_email = $_REQUEST['receiver_email'];
$business = $_REQUEST['business'];

$payment_status = $_REQUEST['payment_status'];
$payer_status = $_REQUEST['payer_status'];

$mc_gross = $_REQUEST['mc_gross'];
$mc_fee = $_REQUEST['mc_fee'];
$mc_currency = $_REQUEST['mc_currency'];
$txn_id = $_REQUEST['txn_id'];

$time = date('d.m.Y, H:i');

if($business !== $config['paypal']['email']) {
	paypal_log_append_die("PayPal is not correctly configured. Please edit the configuration file. Payment email is '$business', your email: {$config['paypal']['email']}. It needs to be the same.");
	return;
}

if(strtolower($payment_status) !== 'completed') {
	paypal_log_append_die("Payment status is '$payment_status'. Points will be added automatically after status is changed to 'completed'. Please wait.");
}

if(strtolower($mc_currency) !== strtolower($config['paypal']['currency_code'])) {
	paypal_log_append_die("PayPal is not correctly configured. Please edit the configuration file. Payment currency_code is '$mc_currency', your currency_code: '{$config['paypal']['currency_code']}'. It needs to be the same.");
}

if(!isset($paylist[$mc_gross])) {
	paypal_log_append_die("PayPal is not correctly configured. Please edit the configuration file. Info: option: '$mc_gross' does not exists.");
}

if($db->select(TABLE_PREFIX . 'paypal', ['txn_id' => $txn_id, 'payment_status' => 'Completed']) !== false) {
	paypal_log_append_die("Duplicated transaction $txn_id");
}

$db->insert(TABLE_PREFIX . 'paypal',
	[
		'txn_id' => $txn_id,
		'email' => $payer_email,
		'account_id' => (int)$custom,
		'price' => $mc_gross,
		'currency' => $mc_currency,
		'points' => $paylist[$mc_gross],
		'payer_status' => $payer_status,
		'payment_status' => $payment_status,
		'created' => date('Y-m-d H:i:s'),
	]);

$account = new OTS_Account();
$account->load($custom);
if($account->isLoaded()) {
	if(GesiorShop::changePoints($account, $paylist[$mc_gross])) {
		log_append('paypal.log', "$time;$custom;$payer_email;$mc_gross:$mc_currency;$mc_fee;$receiver_email;$payment_status;$ip;$business;$payer_status");
	}
}

header('HTTP/1.1 200 OK');

function paypal_log_append_die($str) {
	log_append('paypal-error.log', $str);
	http_response_code(510);
	die();
}
