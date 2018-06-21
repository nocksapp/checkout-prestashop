<?php

require_once __DIR__ . '/../../Api.php';
require_once __DIR__ . '/../../Util.php';

class nockscheckoutMerchantsModuleFrontController extends ModuleFrontController {

	public function initContent() {
		parent::initContent();

		$testMode = Tools::getValue('testMode');

		$nocks = new Nocks_NocksCheckout_Api(Tools::getValue('accessToken'), $testMode === '1');
		$merchants = $nocks->getMerchants();

		header('Content-Type: application/json');
		die(json_encode(['merchants' => Nocks_NocksCheckout_Util::getMerchantsOptions($merchants)]));
	}
}