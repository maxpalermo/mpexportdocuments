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

class AdminMpExportDocumentsController extends ModuleAdminController
{
    public $id_customer_prefix;
    private $date_start;
    private $date_end;
    public $link;
    public $id_lang;
    public $id_shop;
    
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'orders';
        $this->context = Context::getContext();
        $this->smarty = Context::getContext()->smarty;
        $this->debug = false;
        $this->id_lang = ContextCore::getContext()->language->id;
        $this->states = OrderStateCore::getOrderStates($this->id_lang);
        $this->name = 'AdminMpExportDocuments';
        
        parent::__construct();
        
        $this->link = Context::getContext()->link;
        $this->id_lang = (int) Context::getContext()->language->id;
        $this->id_shop = (int) Context::getContext()->shop->id;
    }
    
    public function initToolbar()
    {
        parent::initToolbar();
    }
    
    public function ajaxProcessGetTranslation()
    {
        $translate = Tools::getValue('translate');
        $title = Tools::getValue('title');
        
        $translations = array(
            'Export selected documents?' => $this->l('Export selected documents?'),
        );
        
        $titles = array(
            'Confirm' => $this->l('Confirm'),
        );
        
        foreach ($translations as $key=>$value) {
            if ($key == $translate) {
                $translate = $value;
                break;
            }
        }
        
        foreach ($titles as $key=>$value) {
            if ($key == $title) {
                $title = $value;
                break;
            }
        }
        
        return Tools::jsonEncode(
            array(
                'result' => true,
                'translation' => $translate,
                'title' => $title,
            )
        );
    }
    
    public function initContent()
    {
        if (Tools::isSubmit('ajax') && !empty(Tools::getValue('action'))) {
            $action = 'ajaxProcess' . Tools::getValue('action');
            print $this->$action();
            exit();
        }
        
        $this->helperlistContent = '';
        $this->messages = array();
        $this->date_start = '';
        $this->date_end = '';
        if (Tools::isSubmit('submitForm')) {
            $content = $this->getHelperListContent();
            $this->helperlistContent = $this->initHelperList($content);
        } elseif (Tools::isSubmit('submitBulkexportorders')) {
            $this->messages = $this->processBulkExport();
            exit();
        }
        $this->helperformContent = $this->initHelperForm();
        $this->content = implode('<br>', $this->messages) 
            . $this->helperformContent 
            . $this->helperlistContent 
            . $this->scriptContent();
        
        parent::initContent();
    }
    
    private function scriptContent()
    {
        Context::getContext()->controller->addJS($this->module->getUrl().'views/js/adminController.js');
        return '';
    }
    
    private function getHelperListContent()
    {
        $this->date_start = Tools::getValue('input_text_date_start');
        $this->date_end = Tools::getValue('input_text_date_end');
        $type = Tools::getValue('input_select_type_document');
        
        $isStartDate = ValidateCore::isDate($this->date_start);
        $isEndDate = ValidateCore::isDate($this->date_end);
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        if ($isStartDate || $isEndDate) {
            $this->date_start .= ' 00:00:00';
            $this->date_end .= ' 23:59:59';
            switch ($type) {
                case 'order':
                    $sql = $this->getDisplayOrderList();
                    break;
                case 'invoice':
                    $sql = $this->getDisplayInvoiceList();
                    break;
                case 'return':
                    $sql = $this->getDisplayReturnList();
                    break;
                case 'slip':
                    $sql = $this->getDisplaySlipList();
                    break;
                case 'delivery':
                    $sql = $this->getDisplayDeliveryList();
                    break;
                default:
                    $this->errors[] = $this->l('Please select a valid document type.');
                    return false;
            }
        } else {
            $this->errors[] = $this->l('Please select a valid date');
            return false;
        }
        
        $result = $db->executeS($sql);
        return $result;
    }
    
    private function addDateToSql(DbQueryCore $sql, $date_field)
    {
        if (Tools::strpos($date_field, '.')) {
            $split = explode('.',$date_field);
            $date_field = $split[0].'.`'.$split[1].'`';
        }
        
        if (!empty($this->date_start) && !empty($this->date_end)) {
            $sql->where("$date_field between '$this->date_start' and '$this->date_end'");
        } elseif (!empty($this->date_start) && empty($this->date_end)) {
            $sql->where("$date_field >= '$this->date_start'");
        } elseif (empty($this->date_start) && !empty($this->date_end)) {
            $sql->where("$date_field <= '$this->date_end'");
        }
        return $sql;
    }
    
    private function getDisplayOrderList()
    {
        $this->helperlistContent = array();
        $sql = new DbQueryCore();
        
        $fieldsArray = array(
            'o.id_order as document_id',
            'o.date_add as document_date',
            'o.reference as document_number',
            "CONCAT(c.firstname, ' ', c.lastname) as customer_name",
            'total_paid_real as document_total',
        );
        foreach ($fieldsArray as $field) {
            $sql->select($field);
        }
        $sql
            ->from('orders', '`o`')
            ->innerJoin('customer', '`c`', 'c.id_customer=o.id_customer')
            ->orderBy('o.date_add')
            ->orderBy('o.reference');
        
        return $this->addDateToSql($sql, 'o.date_add');
    }
    
    private function getOrderList()
    {
        $this->helperlistContent = array();
        $sql = new DbQueryCore();
        
        $sql->selectArray(
            array(
                'order_id',
                'date_add',
                'reference',
                'order_id as document_id',
                'reference as document_number',
                'date_add as document_date',
                'total_discounts_tax_excl',
                'total_products',
                'total_shipping_tax_excl',
                'total_wrapping_tax_excl',
                'total_paid_tax_incl-total_paid_tax_excl as total_taxes',
                'total_paid_tax_incl as total_document',
                'total_paid_real as total_paid',
            )
        )
            ->from('orders')
            ->orderBy('date_add')
            ->orderBy('reference');
        
        return $this->addDateToSql($sql, 'date_add');
    }
    
    private function getDisplayInvoiceList()
    {
        
    }
    
    private function getInvoiceList()
    {
        
    }
    
    private function getDisplayReturnList()
    {
        
    }
    
    private function getReturnList()
    {
        
    }
    
    private function getDisplaySlipList()
    {
        
    }
    
    private function getSlipList()
    {
        
    }
    
    private function getDisplayDeliveryList()
    {
        
    }
    
    private function getDeliverySlip()
    {
        
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
    
    protected function processBulkExport()
    {
        $orders = Tools::getValue('ordersBox');
        $this->exportInvoices($orders);
    }
    
    private function initFieldsList()
    {
        $list = array(
            'document_id' => array(
                'title' => $this->l('Id'),
                'text-align' => 'text-right',
                'type' => 'text',
                'width' => 'auto',
                'search' => false,
            ),
            'document_date' => array(
                'title' => $this->l('Date'),
                'align' => 'text-center',
                'width' => 'auto',
                'type' => 'date',
                'search' => false,
            ),
            'document_number' => array(
                'title' => $this->l('Number'),
                'align' => 'text-right',
                'type' => 'text',
                'width' => 'auto',
                'search' => false,
            ),
            'customer_name' => array(
                'title' => $this->l('Customer'),
                'align' => 'text-left',
                'width' => 'auto',
                'type' => 'text',
                'search' => false,
            ),
            'document_total' => array(
                'title' => $this->l('Total'),
                'align' => 'text-right',
                'width' => 'auto',
                'type' => 'price',
                'search' => false,
            ),
        );
        
        return $list;
    }
    
    public function initHelperList($rows)
    {
        $fields_list = $this->initFieldsList();
        $helper = new HelperListCore();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        // Actions to be displayed in the "Actions" column
        $helper->actions = array(); //array('edit', 'delete', 'view');
        $helper->identifier = 'document_id';
        $helper->show_toolbar = true;
        $helper->toolbar_btn = array(
            'export' => array(
                'href' => 'javascript:exportSelectedDocuments();',
                'desc' => $this->l('Export selected'),
            )
        );
        $helper->title = $this->l('Documents List');
        $helper->table = 'expdoc';
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
        $helper->listTotal = count($rows);
        return $helper->generateList($rows, $fields_list);
    }
    
    protected function initHelperForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Export configuration'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'required' => true,
                        'type' => 'date',
                        'name' => 'input_text_date_start',
                        'label' => $this->l('Start date'),
                        'desc' => $this->l('Please insert the start date'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-calendar"></i>',
                        'class' => 'datepicker'
                    ),
                    array(
                        'required' => true,
                        'type' => 'date',
                        'name' => 'input_text_date_end',
                        'label' => $this->l('End date'),
                        'desc' => $this->l('Please insert the end date'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-calendar"></i>',
                        'class' => 'datepicker',
                    ),
                    array(
                        'required' => true,
                        'type' => 'select',
                        'name' => 'input_select_type_document',
                        'label' => $this->l('Type document'),
                        'desc' => $this->l('Select the type document from the list above.'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-list-ul"></i>',
                        'class' => 'input fixed-width-sm',
                        'options' => array(
                            'query' => $this->getDocumentsType(),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Get'),
                    'icon' => 'process-icon-next'
                ),
            ),
        );
        
        $helper = new HelperFormCore();
        $helper->table = '';
        $helper->default_form_language = (int)$this->id_lang;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANGUAGE');
        $helper->submit_action = 'submitForm';
        $helper->currentIndex = $this->link->getAdminLink($this->name); 
        $helper->token = Tools::getAdminTokenLite($this->name);
        if (Tools::isSubmit('submitForm')) {
            $submit_values = Tools::getAllValues();
            $output = array();
            foreach($submit_values as $key=>$value) {
                if(is_array($value)) {
                    $output[$key.'[]'] = $value;
                } else {
                    $output[$key] = $value;
                }
            }
            $helper->tpl_vars = array(
                'fields_value' => $output,
                'languages' => $this->context->controller->getLanguages(),
            );
        } else {
            $helper->tpl_vars = array(
                'fields_value' => array(
                    'input_text_date_start' => '',
                    'input_text_date_end' => '',
                    'input_select_type_document' => 0,
                ),
                'languages' => $this->context->controller->getLanguages(),
            );
        }
        return $helper->generateForm(array($fields_form));
    }
    
    private function getDocumentsType()
    {
        return array(
            array(
                'id' => '0',
                'name' => $this->l('Please select a type document'),
            ),
            array(
                'id' => 'order',
                'name' => $this->l('Orders'),
            ),
            array(
                'id' => 'invoice',
                'name' => $this->l('Invoices'),
            ),
            array(
                'id' => 'return',
                'name' => $this->l('Returns'),
            ),
            array(
                'id' => 'slip',
                'name' => $this->l('Delivery slip'),
            ),
            array(
                'id' => 'delivery',
                'name' => $this->l('Delivery'),
            ),
        );
    }
}
