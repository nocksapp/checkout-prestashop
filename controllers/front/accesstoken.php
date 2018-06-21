<?php

require_once __DIR__ . '/../../Api.php';
require_once __DIR__ . '/../../Util.php';

class nockscheckoutAccesstokenModuleFrontController extends ModuleFrontController {

	public function initContent() {
		parent::initContent();

		$testMode = Tools::getValue('testMode');

		$nocks = new Nocks_NocksCheckout_Api(Tools::getValue('accessToken'), $testMode === '1');
		$scopes = $nocks->getTokenScopes();

		header('Content-Type: application/json');
		die(json_encode(['valid' => Nocks_NocksCheckout_Util::hasAllRequiredScopes($scopes)]));
	}
}