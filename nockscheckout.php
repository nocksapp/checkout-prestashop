<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;

function bplog($contents) {
    if (isset($contents)) {
        if (is_resource($contents)) {
	        return error_log(serialize($contents));
        }

        return error_log(var_dump($contents, true));
    }

    return false;
}

class nockscheckout extends PaymentModule {
    private $_html = '';

    public function __construct() {
        $this->name = 'nockscheckout';
        $this->version = '0.9';
        $this->author = 'Sebastiaan Pasma';
        $this->className = 'nockscheckout';
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->tab = 'payments_gateways';
        $this->display = 'view';
        $this->apiurl = 'https://api.nocks.com/api/v2/';
        $this->sslport = 443;
        $this->verifypeer = 1;
        $this->verifyhost = 2;
        $this->controllers = ['payment', 'validation'];

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Nocks Checkout');
        $this->description = $this->l('Accept Gulden payments in your webshop via the Nocks.com platform.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        $this->context->smarty->assign('base_dir', __PS_BASE_URI__);
    }

    public function install() {

        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('Sorry, this module requires the cURL PHP extension but it is not enabled on your server.  Please ask your web hosting provider for assistance.');

            return false;
        }

        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function uninstall() {
        Configuration::deleteByName('nockscheckout_APIKEY');
        Configuration::deleteByName('nockscheckout_MERCHANT_UUID');
        Configuration::deleteByName('nockscheckout_GULDEN');

        return parent::uninstall();
    }

    public function getContent() {
        $this->_html .= '<h2>' . $this->l('Nocks Checkout') . '</h2>';

        $this->_postProcess();
        $this->_setnockscheckoutSubscription();
        $this->_setConfigurationForm();

        return $this->_html;
    }


    public function hookPaymentOptions($params) {
        if (!$this->active) {
            return;
        }

        $payment_options = [
            $this->linkToGulden(),
        ];

        return $payment_options;
    }


    public function linkToGulden() {
        $nockscheckout_option = new PaymentOption();
        $nockscheckout_option->setCallToActionText($this->l('Gulden'))->setAction(Configuration::get('PS_FO_PROTOCOL') . __PS_BASE_URI__ . "modules/{$this->className}/payment.php");

        return $nockscheckout_option;
    }

    public function hookPayment($params) {
        global $smarty;

        $smarty->assign([
            'this_path'     => $this->_path,
            'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . "modules/{$this->className}/"
        ]);

        return $this->display(__FILE__, 'payment.tpl');
    }

    public function doApiCall($action, $postData = false) {
        $curl = curl_init($this->apiurl . $action);
        $length = 0;

        if (is_array($postData)) {
            $jsonData = json_encode($postData);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);

            $length = strlen($jsonData);
        }

        $bearerToken = Configuration::get('nockscheckout_APIKEY');
        $header = [
            'Content-Type: application/json',
            'Content-Length: ' . $length,
            'Authorization: Bearer ' . $bearerToken,
            'X-Nocks-Plugin-Version: Checkout-Presta-' . $this->version,
        ];

        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_PORT, $this->sslport);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->verifypeer); // verify certificate (1)
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->verifyhost); // check existence of CN and verify that it matches hostname (2)
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);


        $responseString = curl_exec($curl);

        if (!$responseString) {
            $response = curl_error($curl);

            die(Tools::displayError("Error: no data returned from API server!"));
        }

        $response = json_decode($responseString, true);

        curl_close($curl);

        if (isset($response['error'])) {
            bplog($response['error']);

            die(Tools::displayError("Error occurred! (" . $response['error']['type'] . " - " . $response['error']['message'] . ")"));
        }


        return $response;
    }

    public function execPayment($cart) {
        // Create invoice
        $currency = Currency::getCurrencyInstance((int)$cart->id_currency);
        $options = $_POST;
        $options['currency'] = $currency->iso_code;
        $total = $cart->getOrderTotal(true);

        $options['orderID'] = $cart->id;
        $options['price'] = $total;
        $options['fullNotifications'] = true;

        $amount = (string)number_format(round( $total, 2, PHP_ROUND_HALF_UP), 2);
        $post = [
            "merchant_profile"   => Configuration::get('nockscheckout_MERCHANT_UUID'),
            "source_currency"   => "NLG",
            "amount"            => [
                "amount"   => $amount,
                "currency" => strtoupper($options['currency'])
            ],
            "payment_method"    => [
                "method" => "gulden",
            ],
            "metadata"          => [
                'cartId'        => $cart->id,
                'secureKey'     => $this->context->customer->secure_key,
                'amount'        => $amount,
            ],
            "redirect_url"      => Context::getContext()->link->getModuleLink('nockscheckout', 'validation')."?id_cart=" . $cart->id,
            "callback_url"      => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8') . __PS_BASE_URI__ . 'modules/' . $this->className . '/ipn.php',
            "locale"            => "nl_NL"
        ];


        // Call Nocks
        $callResponse = $this->doApiCall('transaction', $post);

        if (isset($callResponse['data']['payments']["data"][0]['uuid'])) {
            // Redirect to nocks checkout screen
            header("Location: https://nocks.com/payment/url/".$callResponse['data']['payments']["data"][0]['uuid']);
            die();
        }

        // Error or invalid response
        die(Tools::displayError("Error occurred!"));
    }

	public function getTransaction($transactionID) {
		return $this->doApiCall( 'transaction/' . $transactionID )['data'];
	}

    public function hookPaymentReturn($params) {
        global $smarty;

        $order = ($params['objOrder']);
        $state = $order->current_state;

        $smarty->assign([
            'state'         => $state,
            'this_path'     => $this->_path,
            'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . "modules/{$this->className}/"
        ]);

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    private function _setnockscheckoutSubscription() {
        $this->_html .= '<div style="float: right; width: 440px; height: 150px; border: dashed 1px #666; padding: 8px; margin-left: 12px;">
                       <h2>' . $this->l('Nocks Merchant Account') . '</h2>
                       <div>
                       You\'ll need a Nocks Merchant account to use the Nocks payment service. You can signup for an account on the <a href="https://nocks.com">Nocks website</a>.
                       </div>
                       <div style="clear: both;"></div>
                       </div>
                       <img src="../modules/nockscheckout/nockscheckout.png" style="float:left; margin-right:15px; width: 200px" />
                       <b>' . $this->l('This module allows you to accept Gulden payments.') . '</b><br /><br />
                       ' . $this->l('You need to configure your Nocks merchant account before using this module.') . '
                       <div style="clear:both;">&nbsp;</div>';
    }

    private function _setConfigurationForm() {
        $this->_html .= '<form method="post" action="' . htmlentities($_SERVER['REQUEST_URI']) . '">
                       <script type="text/javascript">
                       var pos_select = ' . (($tab = (int)Tools::getValue('tabs')) ? $tab : '0') . ';
                       </script>';

        $this->_html .= '<script type="text/javascript" src="' . _PS_BASE_URL_ . _PS_JS_DIR_ . 'jquery/plugins/tabpane/jquery.tabpane.js"></script>
                         <link type="text/css" rel="stylesheet" href="' . _PS_BASE_URL_ . _PS_JS_DIR_ . 'jquery/plugins/tabpane/jquery.tabpane.css" />';

        $this->_html .= '<input type="hidden" name="tabs" id="tabs" value="0" />
                       <div class="tab-pane" id="tab-pane-1" style="width:100%;">
                       <div class="tab-page" id="step1">
                       <h4 class="tab">' . $this->l('Settings') . '</h2>
                       ' . $this->_getSettingsTabHtml() . '
                       </div>
                       </div>
                       <div class="clear"></div>
                       <script type="text/javascript">
                       function loadTab(id){}
                       setupAllTabs();
                       </script>
                       </form>';
    }

    private function _getSettingsTabHtml() {
        $apiToken = htmlentities(Tools::getValue('apikey', Configuration::get('nockscheckout_APIKEY')), ENT_COMPAT, 'UTF-8');
        $uuid = htmlentities(Tools::getValue('uuid', Configuration::get('nockscheckout_MERCHANT_UUID')), ENT_COMPAT, 'UTF-8');

        $merchantsOptions = "<option value=''>== No merchant account selected ==</option>";

        if ($apiToken) {
            $callResponse = ($this->doApiCall('merchant'));
            $merchants = $callResponse["data"];

            foreach ($merchants as $merchant) {
                $merchantName = $merchant['name'];
                foreach ($merchant['merchant_profiles']['data'] as $profile) {
                    $merchantsOptions .= '<option ' . ($uuid == $profile['uuid'] ? 'selected' : '') . ' value="' . htmlentities($profile['uuid'], ENT_COMPAT, 'UTF-8') . '">' . htmlentities(($merchantName == $profile['name'] ? $merchantName : $merchantName . ' (' . $profile['name'] . ')').' ('.$merchant['coc'].')', ENT_COMPAT, 'UTF-8') . '</option>';
                }
            }
        }

        $html = '<div class="nocks-checkout"><h2>' . $this->l('Settings') . '</h2>
               <div class="login">
               
               <h3 style="clear:both;">' . $this->l('Merchant access token') . '</h3>
               <textarea style="width:400px;" name="apikey_nockscheckout">' . $apiToken . '</textarea>
               <a href="https://www.nocks.com/account/api/personal-tokens">You can find or create your Merchant access token here</a><br/>
               Make sure you have the following scopes selected:</br>
               <ul>
               <li> merchant.read
               <li> transaction.create<br/>
               <li> transaction.read
               </ul>
               </div>
               <div style="">
               <label>
               Selected Merchant Account
               <select name="merchant_uuid_nockscheckout">' . $merchantsOptions . '</select>
               </label>
                </div>
               <div style="clear:both;margin-bottom:30px;overflow:hidden;">
               <h3 style="clear:both; margin-bottom: 3px;">' . $this->l('Enabled payment options') . '</h3>
               <div>
               <label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_gulden_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_GULDEN') == "1" ? 'checked' : '') . '> ' . $this->l('Gulden') . '</label>
               </div>
               </div>
               
               <p class="center"><input class="button" type="submit" name="submitnockscheckout" value="' . $this->l('Save settings') . '" /></p>
               
               </div>
               <style type="text/css">
               .nocks-checkout label, .nocks-checkout input[type=text], .nocks-checkout input[type=password], .nocks-checkout select {
               display: block;
               text-align: left !important;
                width: 100%;
               }
               .nocks-checkout, .nocks-checkout>div {
                clear:both;
                margin-bottom:30px;
                max-width: 300px;
               }
               </style>
               ';

        return $html;
    }

    private function _postProcess() {
        if (Tools::isSubmit('submitnockscheckout')) {
            $this->_errors = [];

            if (Tools::getValue('apikey_nockscheckout') == null)
                $this->_errors[] = $this->l('Please provide a merchant API Key');

            if (count($this->_errors) > 0) {
                $error_msg = '';

                foreach ($this->_errors AS $error) {
	                $error_msg .= $error . '<br />';
                }

                $this->_html = $this->displayError($error_msg);
            }
            else {
                Configuration::updateValue('nockscheckout_APIKEY', trim(Tools::getValue('apikey_nockscheckout')));
                Configuration::updateValue('nockscheckout_MERCHANT_UUID', trim(Tools::getValue('merchant_uuid_nockscheckout')));
                Configuration::updateValue('nockscheckout_GULDEN', trim(Tools::getValue('payment_gulden_nockscheckout')));

                $this->_html = $this->displayConfirmation($this->l('Settings updated'));
            }

        }

    }
}

?>
