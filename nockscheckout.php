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

require_once __DIR__ . '/Api.php';
require_once __DIR__ . '/Util.php';

class nockscheckout extends PaymentModule {
    private $_html = '';

    public function __construct() {
        $this->name = 'nockscheckout';
        $this->version = '1.3.0';
        $this->author = 'Sebastiaan Pasma';
        $this->className = 'nockscheckout';
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->tab = 'payments_gateways';
        $this->display = 'view';
        $this->sslport = 443;
        $this->verifypeer = 1;
        $this->verifyhost = 2;
        $this->controllers = ['payment', 'validation', 'accesstoken', 'merchants'];

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Nocks');
        $this->description = $this->l('Nocks payments');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        $this->context->smarty->assign('base_dir', __PS_BASE_URI__);
    }

    public function install() {

        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('Sorry, this module requires the cURL PHP extension but it is not enabled on your server.  Please ask your web hosting provider for assistance.');

            return false;
        }

        if (!parent::install() || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function uninstall() {
	    Configuration::deleteByName('nockscheckout_TESTMODE');
        Configuration::deleteByName('nockscheckout_APIKEY');
        Configuration::deleteByName('nockscheckout_MERCHANT_UUID');
        Configuration::deleteByName('nockscheckout_GULDEN');
	    Configuration::deleteByName('nockscheckout_IDEAL');
	    Configuration::deleteByName('nockscheckout_SEPA');
	    Configuration::deleteByName('nockscheckout_BALANCE');
	    Configuration::deleteByName('nockscheckout_BITCOIN');
	    Configuration::deleteByName('nockscheckout_LITECOIN');
	    Configuration::deleteByName('nockscheckout_ETHEREUM');
	    Configuration::deleteByName('nockscheckout_LOGO');

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

        $payment_options = [];
        $this->addPaymentMethod($payment_options, 'GULDEN', 'Gulden');
	    $this->addPaymentMethod($payment_options, 'IDEAL', 'iDEAL');
	    $this->addPaymentMethod($payment_options, 'SEPA', 'SEPA');
	    $this->addPaymentMethod($payment_options, 'BALANCE', 'Nocks Balance');
	    $this->addPaymentMethod($payment_options, 'BITCOIN', 'Bitcoin');
	    $this->addPaymentMethod($payment_options, 'LITECOIN', 'Litecoin');
	    $this->addPaymentMethod($payment_options, 'ETHEREUM', 'Ethereum');

        return $payment_options;
    }


    public function addPaymentMethod(&$arr, $id, $label) {
    	if (Configuration::get('nockscheckout_' . $id)) {
		    $nockscheckout_option = new PaymentOption();
		    $nockscheckout_option
			    ->setCallToActionText($this->l($label))
			    ->setInputs([
			    	'method' => [
			    		'name' => 'method',
					    'type' => 'hidden',
					    'value' => $id,
				    ]
			    ])
			    ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));

		    if (Configuration::get('nockscheckout_LOGO')) {
		    	$nockscheckout_option->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/' . strtolower($id) . '.png'));
		    }

		    if ($id === 'IDEAL') {
		    	$issuers = Nocks_NocksCheckout_Util::getIdealIssuers();
			    $this->smarty->assign(['issuers' => $issuers]);
			    $nockscheckout_option
				    ->setInputs(array_merge($nockscheckout_option->getInputs(), [
				    	'issuer' => [
				    		'name' => 'nocks_ideal_issuer',
						    'type' => 'hidden',
						    'value' => array_keys($issuers)[0],
					    ]
				    ]))
				    ->setAdditionalInformation($this->fetch('module:nockscheckout/views/templates/hook/issuers.tpl'));
		    }

		    $arr[] = $nockscheckout_option;
	    }
    }

    public function execPayment($cart, $paymentMethod) {
	    $sourceCurrency = null;

    	switch ($paymentMethod['method']) {
		    case 'ideal':
		    case 'sepa':
		        $sourceCurrency = 'EUR';
		    	break;
		    case 'gulden':
		    	$sourceCurrency = 'NLG';
		    	break;
		    case 'bitcoin':
			    $sourceCurrency = 'BTC';
			    break;
		    case 'litecoin':
			    $sourceCurrency = 'LTC';
			    break;
		    case 'ethereum':
			    $sourceCurrency = 'ETH';
	    }

	    // Create transaction
        $currency = Currency::getCurrencyInstance((int)$cart->id_currency);
        $total = $cart->getOrderTotal(true);

	    $amount = (string)number_format(round( $total, 2, PHP_ROUND_HALF_UP), 2);
        $post = [
            'merchant_profile'   => Configuration::get('nockscheckout_MERCHANT_UUID'),
            'amount'            => [
                'amount'   => $amount,
                'currency' => strtoupper($currency->iso_code)
            ],
            'payment_method'    => $paymentMethod,
            'metadata'          => [
                'cartId'        => $cart->id,
	            'nocks_plugin'  => 'prestashop:' . $this->version,
	            'prestashop_version' => _PS_VERSION_,
            ],
	        'redirect_url'      => Context::getContext()->link->getModuleLink('nockscheckout', 'validation')."?id_cart=" . $cart->id,
            'callback_url'      => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8') . __PS_BASE_URI__ . 'modules/' . $this->className . '/ipn.php',
	        'description'       => Configuration::get('PS_SHOP_NAME'),
        ];

        if ($sourceCurrency) {
        	$post['source_currency'] = $sourceCurrency;
        }

	    $accessToken = Configuration::get('nockscheckout_APIKEY');
	    $testmode = Configuration::get('nockscheckout_TESTMODE');

	    $nocks = new Nocks_NocksCheckout_Api($accessToken, $testmode === '1');
	    $response = $nocks->createTransaction($post);

	    if ($response && isset($response['data']['payments']["data"][0]['uuid'])) {
            // Redirect to nocks checkout screen
            header("Location: " . $response['data']['payments']["data"][0]['metadata']['url']);
            die();
        }

        // Error or invalid response
        die(Tools::displayError("Error occurred!"));
    }

	public function getTransaction($transactionID) {
		$accessToken = Configuration::get('nockscheckout_APIKEY');
		$testmode = Configuration::get('nockscheckout_TESTMODE');

		$nocks = new Nocks_NocksCheckout_Api($accessToken, $testmode === '1');

		return $nocks->getTransaction($transactionID);
	}

	public function processTransaction($transactionID) {
		// Get the transaction
		$transaction = $this->getTransaction($transactionID);

		if ($transaction) {
			$cartID = $transaction['metadata']['cartId'];
			$method = $transaction['payments']['data'][0]['payment_method']['data'];

			switch ($transaction['status']) {
				case 'completed':
					$status = Configuration::get('PS_OS_PAYMENT');
					break;
				case 'open':
					$status = $method['code'] === 'sepa' ? Configuration::get('PS_OS_BANKWIRE') : Configuration::get('PS_OS_CHEQUE');
					break;
				default:
					$status = Configuration::get('PS_OS_CANCELED');
			}

			// Order update
			if (empty(Context::getContext()->link)) {
				Context::getContext()->link = new Link(); // workaround a prestashop bug so email is sent
			}

			$orderID = Order::getIdByCartId($cartID);
			if (!$orderID) {
				// Create new order
				$this->validateOrder($cartID, $status, $transaction['amount']['amount'], $method['name'], null, array(), null, false, $this->context->customer->secure_key);
			} else {
				$order = new Order($orderID);
				$orderHistory = new OrderHistory();
				$orderHistory->id_order = $orderID;
				$orderHistory->changeIdOrderState($status, $order, true);
				$orderHistory->addWithemail(true);
			}
		}
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
                       <b>' . $this->l('This module allows you to accept payments via Nocks.') . '</b><br /><br />
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
	    $testMode = htmlentities(Tools::getValue('testmode', Configuration::get('nockscheckout_TESTMODE')), ENT_COMPAT, 'UTF-8');
        $apiToken = htmlentities(Tools::getValue('apikey', Configuration::get('nockscheckout_APIKEY')), ENT_COMPAT, 'UTF-8');
        $uuid = htmlentities(Tools::getValue('uuid', Configuration::get('nockscheckout_MERCHANT_UUID')), ENT_COMPAT, 'UTF-8');
        $logo = htmlentities(Tools::getValue('logo', Configuration::get('nockscheckout_LOGO')), ENT_COMPAT, 'UTF-8');

	    $nocks = new Nocks_NocksCheckout_Api($apiToken, $testMode === '1');
	    $options = Nocks_NocksCheckout_Util::getMerchantsOptions($nocks->getMerchants());

	    $merchantsOptions = "";
	    foreach ($options as $option) {
	        $merchantsOptions .= '<option ' . ($uuid == $option['value'] ? 'selected' : '') . ' value="' . $option['value'] . '">' . htmlentities($option['label'], ENT_COMPAT, 'UTF-8') . '</option>';
	    }

        $html = '<div class="nocks-checkout"><h2>' . $this->l('Settings') . '</h2>               
               <div class="form-control">
               	<label>
	                ' . $this->l('Testmode') . '
	                <select name="testmode_nockscheckout" id="payment_nockspaymentgateway_testmode">
	                    <option value="0" ' . ($testMode !== '1' ? 'selected' : '') . '>' . $this->l('No') . '</option>
	                    <option value="1" ' . ($testMode === '1' ? 'selected' : '') . '>' . $this->l('Yes') . '</option>
	                </select>
               	</label>
               </div>
               <br/>
               <div class="form-control">
               	<label>' . $this->l('Merchant access token') . '</label>
               	<textarea style="width: 50%; height: 100px;" name="apikey_nockscheckout" id="payment_nockspaymentgateway_access_token">' . $apiToken . '</textarea>  
               </div>
               <br/>
               <div class="form-control">
	               <label>
	                ' . $this->l('Selected Merchant Account') . '
	                <select name="merchant_uuid_nockscheckout" id="payment_nockspaymentgateway_merchant">' . $merchantsOptions . '</select>
	               </label>
               </div>
               <br/>
               <div class="form-control">
               	<label>' . $this->l('Enabled payment options') . '</label>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_gulden_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_GULDEN') == "1" ? 'checked' : '') . '> ' . $this->l('Gulden') . '</label>
               </div>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_ideal_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_IDEAL') == "1" ? 'checked' : '') . '> ' . $this->l('iDEAL') . '</label>
               </div>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_sepa_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_SEPA') == "1" ? 'checked' : '') . '> ' . $this->l('SEPA') . '</label>
               </div>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_balance_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_BALANCE') == "1" ? 'checked' : '') . '> ' . $this->l('Nocks Balance') . '</label>
               </div>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_bitcoin_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_BITCOIN') == "1" ? 'checked' : '') . '> ' . $this->l('Bitcoin') . '</label>
               </div>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_litecoin_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_LITECOIN') == "1" ? 'checked' : '') . '> ' . $this->l('Litecoin') . '</label>
               </div>
               <div>
               	<label style="width:auto; text-align: left; display: block; float: none; margin-bottom: 3px;"><input type="checkbox" name="payment_ethereum_nockscheckout" value="1" ' . (Configuration::get('nockscheckout_ETHEREUM') == "1" ? 'checked' : '') . '> ' . $this->l('Ethereum') . '</label>
               </div>
               <br/>
               <div class="form-control">
               	<label>
               		' . $this->l('Show logo\'s') . '
                	<select name="logo_nockscheckout" id="payment_nockspaymentgateway_logo">
                		<option value="0" ' . ($logo !== '1' ? 'selected' : '') . '>' . $this->l('No') . '</option>
                		<option value="1" ' . ($logo === '1' ? 'selected' : '') . '>' . $this->l('Yes') . '</option>
               		</select>
               	</label>
               </div>
               
               <p class="center"><input class="button" type="submit" name="submitnockscheckout" value="' . $this->l('Save settings') . '" /></p>
               
               </div>
               <style type="text/css">
               .nocks-checkout .form-control {
			    width: 100%;
			    display: inline-block;
			    margin-bottom: 8px;
               }
               .nocks-checkout label {
               display: block;
               text-align: left !important;
               width: 100%;
               }
              	.nocks-checkout input[type=text], .nocks-checkout select {
               display: block;
               }
               </style>
               ';

        return $html . $this->_js();
    }

    private function _postProcess() {
        if (Tools::isSubmit('submitnockscheckout')) {
            $this->_errors = [];

            $accessToken = Tools::getValue('apikey_nockscheckout');
            $testMode = Tools::getValue('testmode_nockscheckout') === '1' ? '1' : '0';

            if ($accessToken == null) {
	            $this->_errors[] = $this->l('Please provide a merchant access token');
            } else {
	            $nocks = new Nocks_NocksCheckout_Api($accessToken, $testMode === '1');
	            $scopes = $nocks->getTokenScopes();

	            if (!Nocks_NocksCheckout_Util::hasAllRequiredScopes($scopes)) {
		            $this->_errors[] = $this->l('Please provide a merchant access token with correct scopes');
                }
            }

            if (Tools::getValue('merchant_uuid_nockscheckout') == null)
	            $this->_errors[] = $this->l('Please provide a merchant');

            if (count($this->_errors) > 0) {
                $error_msg = '';

                foreach ($this->_errors AS $error) {
	                $error_msg .= $error . '<br />';
                }

                $this->_html = $this->displayError($error_msg);
            }
            else {
	            Configuration::updateValue('nockscheckout_TESTMODE', $testMode);
                Configuration::updateValue('nockscheckout_APIKEY', $accessToken);
                Configuration::updateValue('nockscheckout_MERCHANT_UUID', trim(Tools::getValue('merchant_uuid_nockscheckout')));
                Configuration::updateValue('nockscheckout_GULDEN', trim(Tools::getValue('payment_gulden_nockscheckout')));
	            Configuration::updateValue('nockscheckout_IDEAL', trim(Tools::getValue('payment_ideal_nockscheckout')));
	            Configuration::updateValue('nockscheckout_SEPA', trim(Tools::getValue('payment_sepa_nockscheckout')));
	            Configuration::updateValue('nockscheckout_BALANCE', trim(Tools::getValue('payment_balance_nockscheckout')));
	            Configuration::updateValue('nockscheckout_BITCOIN', trim(Tools::getValue('payment_bitcoin_nockscheckout')));
	            Configuration::updateValue('nockscheckout_LITECOIN', trim(Tools::getValue('payment_litecoin_nockscheckout')));
	            Configuration::updateValue('nockscheckout_ETHEREUM', trim(Tools::getValue('payment_ethereum_nockscheckout')));
	            Configuration::updateValue('nockscheckout_LOGO', trim(Tools::getValue('logo_nockscheckout')));

	            $this->_html = $this->displayConfirmation($this->l('Settings updated'));
            }
        }
    }

    private function _js() {
	    $merchantsUrl = Context::getContext()->link->getModuleLink('nockscheckout', 'merchants');
	    $loadingMerchantsText = $this->l('Loading merchants');
	    $noMerchantsFoundText = $this->l('No merchants found');

	    $js = '
            <script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
            <script type="text/javascript">
                var merchantsUrl = "' . $merchantsUrl . '";
                var loadingMerchantsText = "' . $loadingMerchantsText . '";
                var noMerchantsFoundText = "' . $noMerchantsFoundText . '";
        
                var $j = jQuery.noConflict();
            
                $j(document).ready(function() {            
                    var merchantsTimeout = null;
                    var $merchantSelect = $j(\'#payment_nockspaymentgateway_merchant\');
                    $merchantSelect.before(\'<p id="payment_nockspaymentgateway_merchants_message" style="display: none; color: red"></p>\');
            
                    if ($merchantSelect.find(\'option\').length === 0) {
                        $merchantSelect.hide();
                        $j(\'#payment_nockspaymentgateway_merchants_message\')
                            .html(noMerchantsFoundText)
                            .show();
                    }
            
                    function getMerchants() {
                        $merchantSelect.hide();
                        $j(\'#payment_nockspaymentgateway_merchants_message\')
                            .html(loadingMerchantsText)
                            .show();
            
                        clearTimeout(merchantsTimeout);
                        merchantsTimeout = setTimeout(function() {
                            var testmode = $j(\'#payment_nockspaymentgateway_testmode\').val();
                            var accessToken = $j(\'#payment_nockspaymentgateway_access_token\').val();
            
                            $j.ajax({
                                method: \'POST\',
                                url: merchantsUrl,
                                data: {
                                    accessToken: accessToken,
                                    testMode: testmode
                                }
                            }).done(function(data) {
                                if (data.merchants.length > 0) {
                                    $merchantSelect.find(\'option\').remove().end();
            
                                    for (var i = 0; i < data.merchants.length; i++) {
                                        var merchant = data.merchants[i];
                                        $merchantSelect.append(\'<option value="\' + merchant.value + \'">\' + merchant.label + \'</option>\');
                                    }
            
                                    $merchantSelect.show();
                                    $j(\'#payment_nockspaymentgateway_merchants_message\').hide();
                                } else {
                                    $merchantSelect.hide();
                                    $j(\'#payment_nockspaymentgateway_merchants_message\')
                                        .html(noMerchantsFoundText)
                                        .show();
                                }
                            });
                        }, 200);
                    }
            
                    $j(\'#payment_nockspaymentgateway_testmode\').on(\'change\', function() {
                        getMerchants();
                    });
            
                    var lastAccessToken = null;
                    $j(\'#payment_nockspaymentgateway_access_token\').on(\'change keyup\', function() {
                        var val = $j(this).val();
                
                        if (lastAccessToken !== val) {
                            getMerchants();
                            lastAccessToken = val;
                        }
                    });
                });
            </script>
        ';

	    return $js;
    }
}

?>
