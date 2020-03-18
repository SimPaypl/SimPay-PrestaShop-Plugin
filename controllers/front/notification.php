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

class SimPayNotificationModuleFrontController extends ModuleFrontController
{
    
    private $simPay;
    
    public function postProcess()
    {
    
        if ($this->module->active == false) {
            exit();
        }
        
        $simPay = new SimPayDB();
        
        $simPay->setApiKey(Configuration::get('SIMPAY_API_KEY'));
        
        if (!$simPay->checkIp($simPay->getRemoteAddr())) {
            $simPay->okTransaction();
            exit();
        }
        
        if ($simPay->parse($_POST)) {
            if ($simPay->isError()) {
                $simPay->okTransaction();
                exit();
            }
            
            if (!$simPay->isTransactionPaid()) {
                $simPay->okTransaction();
                exit();
            }
        } else {
            error_log($simPay->getErrorText());
            $simPay->okTransaction();
            exit();
        }

        $history = new OrderHistory();
        $history->id_order = $simPay->getControl();
        
        $order = new Order($simPay->getControl());
        if ($simPay->getValueGross() < $order->total_products_wt) {
            $simPay->okTransaction();
            exit();
        }
        
        $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $simPay->getControl());
        $history->addWithemail(true);
        
        $simPay->okTransaction();
        exit();
    }
}
