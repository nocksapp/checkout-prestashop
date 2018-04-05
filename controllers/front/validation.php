<?php


class nockscheckoutValidationModuleFrontController extends ModuleFrontController
{
        /**
         * @see FrontController::postProcess()
         */
	public function postProcess() {
        file_put_contents(dirname(__FILE__)."/REQUEST", json_encode($_REQUEST));
        file_put_contents(dirname(__FILE__)."/POST", json_encode($_POST));
        file_put_contents(dirname(__FILE__)."/GET", json_encode($_GET));

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            return Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'nockscheckout') {
	            $authorized = true;
	            break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return Tools::redirect('index.php?controller=order&step=1');
        }

        return Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
}

