<?php


class nockscheckoutPaymentModuleFrontController extends ModuleFrontController {

	public function postProcess() {
		$issuer = Tools::getValue('nocks_ideal_issuer');
		$method = strtolower(Tools::getValue('method'));
		$metadata = [];

		if ($issuer) {
			$metadata['issuer'] = $issuer;
		}

		$this->module->execPayment($this->context->cart, [
			'method' => $method,
			'metadata' => $metadata,
		]);
	}
}


