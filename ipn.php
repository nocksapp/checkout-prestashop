<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__) . '/nockscheckout.php');

$nockscheckout = new nockscheckout();

$handle = fopen('php://input','r');
$jsonInput = fgets($handle);

fclose($handle);
error_log($jsonInput);

// Get the transaction
$transaction = $nockscheckout->getTransaction($jsonInput);

if ($transaction && in_array($transaction['status'], ['completed', 'cancelled'])) {
	$cartID = $transaction['metadata']['cartId'];
	$secureKey = $transaction['metadata']['secureKey'];
	$amount = $transaction['metadata']['amount'];
	$status = $transaction['status'] === 'completed' ? Configuration::get('PS_OS_PAYMENT') : Configuration::get('PS_OS_CANCELED');

	$orderID = Order::getIdByCartId($cartID);
	if (!$orderID) {
		// Order not found, create order
		$nockscheckout->validateOrder($cartID, $status, $amount, $nockscheckout->displayName, null, array(), null, false, $secureKey);
	} else {
		// Order update
		if (empty(Context::getContext()->link)) {
			Context::getContext()->link = new Link(); // workaround a prestashop bug so email is sent
		}

		$order = new Order($orderID);
		$orderHistory = new OrderHistory();
		$orderHistory->id_order = $orderID;
		$orderHistory->changeIdOrderState($status, $order, true);
		$orderHistory->addWithemail(true);
	}
} else {
	bplog('Transaction not found or still open');
}
