<?php


class nockscheckoutValidationModuleFrontController extends ModuleFrontController
{
	public function postProcess() {
        $cart = $this->context->cart;
        $isCancelled = Tools::getValue('status') === 'cancelled';
        if ($isCancelled || $cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'nockscheckout')
			{
				$authorized = true;
				break;
			}
		if (!$authorized) {
			die($this->module->l('This payment method is not available.', 'validation'));
		}

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			Tools::redirect('index.php?controller=order&step=1');
		}

		$nockscheckout = new nockscheckout();
		$nockscheckout->processTransaction(Tools::getValue('uuid'));

		Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
}

