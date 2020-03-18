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

class SimPayValidationModuleFrontController extends ModuleFrontController
{
    
    public function postProcess()
    {
        
        if ($this->module->active == false) {
            exit();
        }
        
        $cart = $this->context->cart;
        
        $payMethod = Tools::getValue('payMethod');
        if (!$payMethod) {
            $this->_errors[] = $this->module->l('Prosimy wybrać metodę płatności!', 'validation');
        }
        
        if (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
            exit();
        }
        
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'simpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('Prosimy wybrać metodę płatności!', 'validation'));
        }
        
        $this->context->smarty->assign([
            'params' => $_REQUEST,
        ]);
        
        $amount = number_format(round((float)$cart->getOrderTotal(true, Cart::BOTH), 2), 2, '.', '');
        $currency = $this->context->currency;
        
        $this->module->validateOrder($cart->id, '10', $amount, $this->module->displayName, null, null, (int)$currency->id, false, $customer->secure_key);
        
        $returnUrl = Context::getContext()->shop->getBaseURL(true) . 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder.'&key='.$customer->secure_key;
        
        $simpayTransaction = new SimPayDBTransaction();
        $simpayTransaction->setDebugMode(false);
        $simpayTransaction->setServiceID(Configuration::get('SIMPAY_SERVICE_ID'));
        $simpayTransaction->setApiKey(Configuration::get('SIMPAY_API_KEY'));
        $simpayTransaction->setControl($this->module->currentOrder);
        $simpayTransaction->setCompleteLink($returnUrl);
        $simpayTransaction->setFailureLink($returnUrl);
        if (Configuration::get('SIMPAY_PAYMENT_TYPE') == "amount") {
            $simpayTransaction->setAmount($amount);
        } elseif (Configuration::get('SIMPAY_PAYMENT_TYPE') == "amount_gross") {
            $simpayTransaction->setAmountGross($amount);
        } else {
            $simpayTransaction->setAmountRequired($amount);
        }
        
        $simpayTransaction->generateTransaction();
        if ($simpayTransaction->getResults()->status == "success") {
            Tools::redirect($simpayTransaction->getResults()->link);
        } else {
            $this->module->l('Nie udało się przejść do płatności!', 'validation');
        }
    }
}
