<?php
/**
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit();
}

require_once(_PS_MODULE_DIR_ . '/simpay/inc/SimPay.db.class.php');

class SimPay extends PaymentModule {
	
	private $simpayTransaction;
	
	public function __construct() {
		
		$this->name = 'simpay';
		$this->displayName = 'SimPay';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'Payments Soultion sp. z o.o.';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_]; 
		$this->bootstrap = true;
	 
		parent::__construct();
	 
		$this->displayName = $this->l('SimPay');
		$this->description = $this->l('Płatności DirectCarrierBilling SMS+ dla twojego sklepu');
		
	}
	
	public function install() {
		
		/*if (extension_loaded('curl') == false) {
			$this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
			return false;
		}*/

		if (
		!parent::install() ||
		!$this->registerHook('paymentOptions') ||
		!$this->registerHook('paymentReturn') ||
		!$this->registerHook('displayOrderDetail') ||
		!Configuration::updateValue('SIMPAY_SERVICE_ID', '') ||
		!Configuration::updateValue('SIMPAY_API_KEY', '') ||
		!Configuration::updateValue('SIMPAY_PAYMENT_TYPE', '')
		) {
			return false;
		}
		
		return true;
		
	}
	
    public function hookPaymentOptions($params) {
		
        if (!$this->active) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption()
        ];

        return $payment_options;
    }
	
    public function getExternalPaymentOption() {
        $externalOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText($this->l('SimPay DirectCarrierBilling (SMS+)'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setAdditionalInformation($this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/front/payment.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));

        return $externalOption;
    }
	
    public function getContent() {
		$output = null;
    
        if (Tools::isSubmit('submit'.$this->name)) {
			$simpay_service_id = Tools::getValue('SIMPAY_SERVICE_ID');
			$simpay_api_key = Tools::getValue('SIMPAY_API_KEY');
			$simpay_payment_type = Tools::getValue('SIMPAY_PAYMENT_TYPE');
			if (!empty($simpay_service_id) && !empty($simpay_api_key) && !empty($simpay_payment_type)) {
				Configuration::updateValue('SIMPAY_SERVICE_ID', $simpay_service_id);
				Configuration::updateValue('SIMPAY_API_KEY', $simpay_api_key);
				Configuration::updateValue('SIMPAY_PAYMENT_TYPE', $simpay_payment_type);
				$output .= $this->displayConfirmation($this->l('Ustawienia zaktualizowane.'));
			} else {
				$output .= $this->displayError($this->l('Wypełnij poprawnie wszystkie pola.'));
			}
		}
		return $output . $this->displayForm();
	}
	
    public function displayForm() {
		
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		
		$fields_form = [];
		
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Ustawienia'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('ID Usługi'),
                    'name' => 'SIMPAY_SERVICE_ID',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'name' => 'SIMPAY_API_KEY',
                    'size' => 20,
                    'required' => true
                ),
				array(
					'type' => 'select',
					'lang' => true,
					'label' => $this->l('Typ kwoty'),
					'name' => 'SIMPAY_PAYMENT_TYPE',
					'options' => array(
						'query' => array(
							array(
								'value' => 'amount_gross',
								'name' => 'Kwota brutto'
							),
							array(
								'value' => 'amount',
								'name' => 'Kwota netto'
							),
							array(
								'value' => 'amount_required',
								'name' => 'Kwota wymagana, zmieniana przy wyborze operatora'
							)
						),
						'id' => 'value',
						'name' => 'name'
					)
				),
            ),
            'submit' => array(
                'title' => $this->l('Zapisz'),
                'class' => 'btn btn-default pull-right'
            )
        );
    
        $helper = new HelperForm();
    
        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
    
        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
    
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name.
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Wróć do listy')
            )
        );
    
        // Load current value
        $helper->fields_value['SIMPAY_SERVICE_ID'] = Configuration::get('SIMPAY_SERVICE_ID');
        $helper->fields_value['SIMPAY_API_KEY'] = Configuration::get('SIMPAY_API_KEY');
        $helper->fields_value['SIMPAY_PAYMENT_TYPE'] = Configuration::get('SIMPAY_PAYMENT_TYPE');
        
        return $helper->generateForm($fields_form);
    }
	
	/*public function payment() {
		
		$simpayTransaction = new SimPayDBTransaction();
		$simpayTransaction->setDebugMode(FALSE);
		$simpayTransaction->setServiceID(91);
		$simpayTransaction->setApiKey('ZFb23GCnTDAc46Nq');
		$simpayTransaction->setControl('dd');
		$simpayTransaction->setCompleteLink('https://simpay.pl');
		$simpayTransaction->setFailureLink('https://simpay.pl');
		/*if ($cfg['simpay']['amountType'] == "amount") {
			$simpayTransaction->setAmount($cfg['simpay']['amount']);
		} elseif ($cfg['simpay']['amountType'] == "amount_gross") {
			$simpayTransaction->setAmountGross($cfg['simpay']['amount']);
		} else {
			$simpayTransaction->setAmountRequired($cfg['simpay']['amount']);
		}
		$simpayTransaction->setAmount(20.00);
		$simpayTransaction->generateTransaction();
		if ($simpayTransaction->getResults()->status == "success") {
			return [
				'link' => $simpayTransaction->getResults()->link
			];
		} else {
			return false;
		}
	}*/
	
}