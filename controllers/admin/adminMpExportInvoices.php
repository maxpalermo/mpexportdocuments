<?php
/**
 * 2017 mpSOFT
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
 *  @author    mpSOFT by Massimiliano Palermo<info@mpsoft.it>
 *  @copyright 2017 mpSOFT by Massimiliano Palermo
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of mpSOFT
 */

class AdminMpExportInvoicesController extends ModuleAdminController
{
    public $id_customer_prefix;
    
    public function __construct()
    {
        $this->bootstrap = true;
        $this->bootstrap = true;
        $this->table = 'orders';
        $this->context = Context::getContext();
        $this->smarty = Context::getContext()->smarty;
        $this->debug = false;
        $this->id_lang = ContextCore::getContext()->language->id;
        $this->states = OrderStateCore::getOrderStates($this->id_lang);
        $this->name = 'AdminMpExportInvoices';
        
        parent::__construct();
    }
    
    public function initToolbar()
    {
        parent::initToolbar();
    }

    public function initContent()
    {
        $list = '';
        $message = '';
        
        if (Tools::isSubmit('submitForm')) {
            $orders = $this->getOrders();
            $list = $this->renderHelperList($orders);
            ContextCore::getContext()->cookie->__set('input_id_customer_prefix', Tools::getValue('input_id_customer_prefix', '---'));
        } elseif (Tools::isSubmit('submitBulkexportorders')) {
            $this->id_customer_prefix = ContextCore::getContext()->cookie->__get('input_id_customer_prefix');
            $message = $this->processBulkExport();
        }
        
        $form = $this->generateForm();
        
        $this->context->controller->addJqueryUI('ui.datepicker');
        
        $this->context->smarty->assign(
            array(
                'token' => Tools::getAdminTokenLite($this->name),
                'controller' => $this->name,
            )
        );
        $this->content = $message . $form . $list;
        
        parent::initContent();
    }
    
    private function getOrders()
    {
        $startDate = Tools::getValue('input_start_date');
        $endDate = Tools::getValue('input_end_date');
        
        $isStartDate = ValidateCore::isDate($startDate);
        $isEndDate = ValidateCore::isDate($endDate);
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        if ($isStartDate || $isEndDate) {
            $sql->select('o.*')
                ->select('oi.date_add as invoice_date')
                ->select('oi.number as invoice_document')
                ->select('CONCAT(c.firstname,\' \',c.lastname) as customer')
                ->from('orders', 'o')
                ->innerJoin('order_invoice', 'oi', 'oi.id_order=o.id_order')
                ->innerJoin('customer', 'c', 'c.id_customer=o.id_customer')
                ->where('o.invoice_number > 0')
                ->orderBy('oi.number, oi.date_add');
        } else {
            $this->errors[] = $this->l('Not a valid date');
            return false;
        }
        
        if ($isStartDate && $isEndDate) {
            if ($startDate==$endDate) {
                $sql->where("oi.date_add like '$startDate%' ");
            } else {
                $date = new DateTime($endDate);
                $date->modify('+1 day');
                $endDate = $date->format('Y-m-d');
                $sql->where("oi.date_add between '$startDate' and '$endDate'");
            }
        } elseif ($isStartDate && !$isEndDate) {
            $sql->where("oi.date_add >= '$startDate' ");
        } elseif (!$isStartDate && $isEndDate) {
            $sql->where("oi.date_add <= '$endDate' ");
        }
        
        //PrestaShopLoggerCore::addLog('query: ' . $sql->__toString());
        
        $result = $db->executeS($sql);
        return $result;
    }
    
    private function exportInvoices($orders)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><invoices></invoices>');
        foreach ($orders as $id_order) {
            $order = new OrderCore($id_order);
            $xml = $this->addInvoice($xml, $order);
        }
        
        $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR .".."
            .DIRECTORY_SEPARATOR . ".."
            .DIRECTORY_SEPARATOR . "export"
            .DIRECTORY_SEPARATOR . "invoices(" . date('Ymd-his') . ").xml";
        
        $this->context->smarty->assign('xml_invoices', $xml->asXML());
        $xml->asXML($filename);
        chmod($filename, 0777);
        
        header('Content-disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-type: "text/xml"; charset="utf8"');
        readfile($filename);
        ignore_user_abort(true);
        if (connection_aborted()) {
            unlink($filename);
        }
        exit();
    }
    
    /**
     * ADD AN INVOICE ELEMENT TO XML
     * @param SimpleXMLElement $xml
     * @param OrderCore $order
     * @return SimpleXMLElement $xml
     */
    private function addInvoice($xml, $order)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_invoice')
                ->from('order_invoice')
                ->where('id_order = ' . (int)$order->id);
        $id_order_invoice = (int)$db->getValue($sql);
        $order_invoice = new OrderInvoiceCore($id_order_invoice);
        
        $this->id_customer_prefix = strval(ConfigurationCore::get('MPEXPORTINVOICES_CUSTOMER_PREFIX'));
        
        $payment_module_id = (int)ModuleCore::getModuleIdByName($order->module);
        $payment_module_name = ConfigurationCore::get('MPEXPORTINVOICES_MODULE' . $payment_module_id);
        $document_type = ConfigurationCore::get('MPEXPORTINVOICES_ID_DOCUMENT');
        
        $invoice = $xml->addChild('invoice');
        $invoice->addChild('document_type', $document_type);
        $invoice->addChild('order_id', $order->id);
        $invoice->addChild('order_date', $order->date_add);
        $invoice->addChild('order_reference', $order->reference);
        $invoice->addChild('invoice_id', $order_invoice->id);
        $invoice->addChild('invoice_number', $order_invoice->number);
        $invoice->addChild('invoice_date', $order_invoice->date_add);
        $invoice->addChild('discounts_tax_excl', $order->total_discounts_tax_excl);
        $invoice->addChild('products_tax_excl', $order->total_products);
        $invoice->addChild('shipping_tax_excl', $order->total_shipping_tax_excl);
        $invoice->addChild('wrapping_tax_excl', $order->total_wrapping_tax_excl);
        $invoice->addChild('total_tax_excl', $order->total_paid_tax_excl);
        $invoice->addChild('total_tax', $order->total_paid_tax_incl - $order->total_paid_tax_excl);
        $invoice->addChild('total_tax_incl', $order->total_paid_tax_incl);
        $invoice->addChild('payment', Tools::strtoupper($payment_module_name));
        
        //Add customer
        $objCustomer = new CustomerCore($order->id_customer);
        $objGender = new GenderCore($objCustomer->id_gender);
        $objAddrDelivery = new AddressCore($order->id_address_delivery);
        $objAddrInvoice = new AddressCore($order->id_address_invoice);
        
        $customer = $invoice->addChild('customer');
        $customer->addChild('id', $this->id_customer_prefix . $objCustomer->id);
        $customer->addChild('gender', $objGender->name[ContextCore::getContext()->language->id]);
        $customer->addChild('firstname', $objCustomer->firstname);
        $customer->addChild('lastname', $objCustomer->lastname);
        $customer->addChild('email', $objCustomer->email);
        $customer->addChild('birthday', $objCustomer->birthday);
        
        $addr_delivery = $customer->addChild('address_delivery');
        $this->addAddress($addr_delivery, $objAddrDelivery);
        $addr_invoice = $customer->addChild('address_invoice');
        $this->addAddress($addr_invoice, $objAddrInvoice);
        
        $orderList = OrderDetailCore::getList($order->id);
        $products = $invoice->addChild('rows');
        
        /**
         * @var OrderDetailCore $product
         */
        foreach ($orderList as $product) {
            //print "<pre>" . print_r($product,1) . "<pre>";
            
            //Get tax rate
            $db = Db::getInstance();
            $sql = new DbQueryCore();
            
            $sql    ->select('t.rate')
                    ->from('tax', 't')
                    ->innerJoin('tax_rule', 'tr', 'tr.id_tax=t.id_tax')
                    ->where('tr.id_tax_rules_group = ' . $product['id_tax_rules_group']);
            $tax_rate = $db->getValue($sql);
            
            //Add row
            $row_product = $products->addChild('row');
            $row_product->addChild('ean13', $product['product_ean13']);
            $row_product->addChild('reference', $product['product_reference']);
            $row_product->addChild('product_price_tax_excl', $product['product_price']);
            $row_product->addChild('discount_percent', $this->getDiscount($product['product_price'], $product['unit_price_tax_excl']));
            $row_product->addChild('price_tax_excl', $product['unit_price_tax_excl']);
            $row_product->addChild('qty', $product['product_quantity']);
            $row_product->addChild('total_tax_excl', $product['total_price_tax_excl']);
            $row_product->addChild('tax_rate', $tax_rate);
            $row_product->addChild('total_tax_incl', $product['total_price_tax_incl']);
            
        }
        
        if ($this->tableExists(_DB_PREFIX_ . 'mp_advpayment_fee')) {
            $feeTableName = 'mp_advpayment_fee';
            $result = $this->getFee($order->id, $feeTableName);
        }
        
        if ($result === false && $this->tableExists(_DB_PREFIX_ . 'mp_advpayment')) {
            $feeTableName = 'mp_advpayment';
            $result = $this->getFee($order->id, $feeTableName);
        }
        
        if ($result!==false) {
            $fee = $invoice->addChild('fees');
            $fee->addChild('fee_tax_excl', $result['fee_tax_excl']);
            $fee->addChild('fee_tax_rate', $result['fee_tax_rate']);
            $fee->addChild('fee_tax_incl', $result['fee_tax_incl']);
        }
        
        return $xml;
    }
    
    private function getDiscount($price_full, $price_reducted)
    {
        return sprintf('%.4f', (($price_full-$price_reducted)/$price_full) * 100);
    }
    
    /**
     * 
     * @param SimpleXMLElement $node
     * @param AddressCore $address
     */
    private function addAddress($node, $address)
    {
        $objState = new StateCore($address->id_state);
        $objCountry = new CountryCore($objState->id_country);
        
        $node->addChild('company', htmlspecialchars($address->company));
        $node->addChild('firstname', htmlspecialchars($address->firstname));
        $node->addChild('lastname', htmlspecialchars($address->lastname));
        $node->addChild('address1', htmlspecialchars($address->address1));
        $node->addChild('address2', htmlspecialchars($address->address2));
        $node->addChild('postcode', htmlspecialchars($address->postcode));
        $node->addChild('city', htmlspecialchars($address->city));
        $node->addChild('state', htmlspecialchars($objState->iso_code));
        $node->addChild('country', htmlspecialchars($objCountry->iso_code));
        $node->addChild('phone', htmlspecialchars($address->phone));
        $node->addChild('phone_mobile', htmlspecialchars($address->phone_mobile));
        $node->addChild('vat_number', htmlspecialchars($address->vat_number));
        $node->addChild('dni', htmlspecialchars(Tools::strtoupper($address->dni)));
        
        return $node;
    }
    
    private function tableExists($tablename)
    {
        try {
            Db::getInstance()->getValue("select 1 from `$tablename`");
            return true;
        } catch (Exception $exc) {
            PrestaShopLoggerCore::addLog('Table ' . $tablename . ' not exists.' . $exc->getMessage());
            return false;
        }
    }
    
    private function getFee($id_order, $tablename)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql->select('fee_tax_excl')
            ->select('fee_tax_rate')
            ->select('fee_tax_incl')
            ->from($tablename)
            ->where('id_order = ' . $id_order);
        
        $result = $db->getRow($sql);
        
        //PrestaShopLoggerCore::addLog('Get fee from ' . $tablename . ': ' . print_r($result, 1));
        
        if (empty($result)) {
            return false;
        }
        
        return $result;
    }
    
    private function object_to_array($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = $this->object_to_array($value);
            }
            return $result;
        }
        return $data;
    }
    
    public function renderHelperList($rows)
    {
        $helper = new HelperListCore();
     
        $helper->shopLinkType = '';

        $helper->simple_header = true;

        // Actions to be displayed in the "Actions" column
        $helper->actions = array(); //array('edit', 'delete', 'view');
        
        $helper->identifier = 'id_order';
        $helper->show_toolbar = true;
        $helper->title = $this->l('Orders List');
        $helper->table = $this->table;
        $helper->bulk_actions = array(
            'export' => array(
                'text' => $this->l('Export selected'),
                'confirm' => $this->l('Continue with this operation?'),
                'icon' => 'icon fa-list'
            )
        );
        $helper->no_link=true; // Row is not clickable
        $helper->token = Tools::getAdminTokenLite($this->name);
        $helper->currentIndex = ContextCore::getContext()->link->getAdminLink($this->name, false);
                
        $this->field_list = $this->generateFieldList();
        return $helper->generateList($rows, $this->field_list);
    }
    
    protected function processBulkExport()
    {
        $orders = Tools::getValue('ordersBox');
        $this->exportInvoices($orders);
    }
    
    private function generateForm()
    {
        $this->fields_form = array(
                array('form' =>
                    array(
                        'legend' => array(       
                        'title' => $this->l('Export Invoices'),       
                        'icon' => 'icon icon-cogs',
                    ),   
                    'input' => array(       
                        array(           
                            'type' => 'date',
                            'label' => $this->l('Start date'),
                            'name' => 'input_start_date',
                            'required' => true,
                            'desc' => $this->l('Set initial date for invoice search'),
                            'suffix' => '<i class=\'icon fa-calendar\'</i>',
                        ),
                        array(           
                            'type' => 'date',
                            'label' => $this->l('End date'),
                            'name' => 'input_end_date',
                            'required' => true,
                            'desc' => $this->l('Set final date for invoice search'),
                            'suffix' => '<i class=\'icon fa-calendar\'</i>',
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('GO'),       
                        'class' => 'btn btn-default pull-right'   
                        )
                    )
                )
            );
        
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        
        $helper = new HelperFormCore();
        $helper->module = $this;
        $helper->name_controller = $this->module->name;
        $helper->token = Tools::getAdminTokenLite($this->name);
        $helper->currentIndex = $this->context->link->getAdminLink($this->name, false);
        $helper->languages = Language::getLanguages();
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->module->displayName;
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = false;
        $helper->submit_action = 'submitForm';
        $helper->fields_value['input_start_date'] = Tools::getValue('input_start_date', '');
        $helper->fields_value['input_end_date'] = Tools::getValue('input_end_date', '');
        $helper->tpl_vars = array(
            'fields_value' => $helper->fields_value,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm($this->fields_form);
    }
    
    private function generateFieldList()
    {
        $list = array(
            'id_order' => array(
                'title' => $this->l('id Order'),
                'text-align' => 'text-right',
                'type' => 'text',
                'width' => 'auto',
            ),
            'id_customer' => array(
                'title' => $this->l('id Customer'),
                'align' => 'text-right',
                'type' => 'text',
                'width' => 'auto',
            ),
            'customer' => array(
                'title' => $this->l('Customer'),
                'align' => 'text-left',
                'width' => 'auto',
                'type' => 'text'
            ),
            'invoice_document' => array(
                'title' => $this->l('Invoice number'),
                'align' => 'text-right',
                'type' => 'text',
                'width' => 'auto',
            ),
            'invoice_date' => array(
                'title' => $this->l('Invoice Date'),
                'align' => 'text-center',
                'width' => 'auto',
                'type' => 'date',
            ),
            'total_paid' => array(
                'title' => $this->l('Total invoice'),
                'align' => 'text-right',
                'width' => 'auto',
                'type' => 'price',
            ),
        );
        
        return $list;
    }
}
