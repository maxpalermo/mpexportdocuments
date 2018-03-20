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
 *  @author    Massimiliano Palermo<info@mpsoft.it>
 *  @copyright 2018 Digital Solutions®
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of mpSOFT
 */

class ExportToXML
{
    protected $list_of_ids;
    protected $type;
    protected $xml;
    
    public function __construct($list_of_ids, $type) 
    {
        $this->list_of_ids = $list_of_ids;
        $this->type = $type;
    }
    
    /**
     * Export selected documents in XML format
     * @param type $params array of parameters
     */
    public function export()
    {
        /**
         * @var string type can assume :
         *  - Orders
         *  - Invoices
         *  - Returns
         *  - Slips
         *  - Deliveries
         */
        $this->content = $this->createContent($this->list_of_ids, $this->type);
        $this->xml = $this->exportXmlDocument(Tools::strtolower($this->type));
        return tools::jsonEncode(
            array(
                'result' => true,
                'content' => $this->xml->asXML(),
            )
        );
    }
    
    /**
     * Create array content of selected documents
     * @param array $list_of_ids array of selected documents id
     * @param string $type type document
     * @return mixed array of selected documents or false
     */
    public function createContent($list_of_ids, $type)
    {
        switch (Tools::strtolower($type)) {
            case 'invoices' :
                $content = $this->getInvoices($list_of_ids);
                break;
            case 'orders' :
                $content = $this->getOrders($list_of_ids);
                break;
            case 'returns' :
                $content = $this->getReturns($list_of_ids);
                break;
            case 'slips' :
                $content = $this->getSlips($list_of_ids);
                break;
            case 'deliveries' :
                $content = $this->getDeliveries($list_of_ids);
                break;
            default :
                return false;
        }
        
        return $content;
    }
    
    public function getInvoices($list_of_ids)
    {
        $output = array();
        foreach($list_of_ids as $id)
        {
            $document = new OrderInvoiceCore($id);
            $order = new OrderCore($document->id_order);
            
            $out = array('invoice' => array(
                'document_type' => ConfigurationCore::get('mpexpdoc_input_id_invoice'),
                'order_id' => $order->id,
                'order_date' => $order->date_add,
                'order_reference' => $order->reference,
                'invoice_id'=> $document->id,
                'invoice_number' => $document->number,
                'invoice_date' => $document->date_add,
                'discount_tax_excl' => $order->total_discounts_tax_excl,
                'products_tax_excl' => $order->total_products,
                'shipping_tax_excl' => $order->total_shipping_tax_excl,
                'wrapping_tax_excl' => $order->total_wrapping_tax_excl,
                'total_tax_excl' => $order->total_paid_tax_excl,
                'total_tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                'total_tax_incl' => $order->total_paid_tax_incl,
                'payment' => $this->getPayment($order->module),
                'customer' => $this->getCustomer($order->id_customer, $order->id_address_delivery, $order->id_address_invoice),
                'rows' => $this->getRows($document->id, 'invoices'),
                'fees' => $this->getFees($order->id),
                ),
            );
            
            $output[]= $out;
        }
        
        return $output;
    }
    
    public function getOrders($list_of_ids)
    {
        $output = array();
        foreach($list_of_ids as $id)
        {
            $order = new OrderCore($id);
            
            $out = array('order' => array(
                'document_type' => ConfigurationCore::get('mpexpdoc_input_id_order'),
                'order_id' => $order->id,
                'order_date' => $order->date_add,
                'order_reference' => $order->reference,
                'discount_tax_excl' => $order->total_discounts_tax_excl,
                'products_tax_excl' => $order->total_products,
                'shipping_tax_excl' => $order->total_shipping_tax_excl,
                'wrapping_tax_excl' => $order->total_wrapping_tax_excl,
                'total_tax_excl' => $order->total_paid_tax_excl,
                'total_tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                'total_tax_incl' => $order->total_paid_tax_incl,
                'payment' => $this->getPayment($order->module),
                'customer' => $this->getCustomer($order->id_customer, $order->id_address_delivery, $order->id_address_invoice),
                'rows' => $this->getRows($order->id, 'orders'),
                'fees' => $this->getFees($order->id),
                ),
            );
            
            $output[]= $out;
        }
        
        return $output;
    }
    
    public function getReturns($list_of_ids)
    {
        $output = array();
        foreach($list_of_ids as $id)
        {
            $document = new OrderReturnCore($id);
            $order = new OrderCore($document->id_order);
            
            $out = array('return' => array(
                'document_type' => ConfigurationCore::get('mpexpdoc_input_id_return'),
                'order_id' => $order->id,
                'order_date' => $order->date_add,
                'order_reference' => $order->reference,
                'return_id'=> $document->id,
                'return_date' => $document->date_add,
                'return_question' => $document->question,
                'customer' => $this->getCustomer($order->id_customer, $order->id_address_delivery, $order->id_address_invoice),
                'rows' => $this->getRows($document->id, 'returns'),
                'fees' => array(),
                ),
            );
            
            $output[]= $out;
        }
        
        return $output;
    }
    
    public function getSlips($list_of_ids)
    {
        $output = array();
        foreach($list_of_ids as $id)
        {
            $document = new OrderSlipCore($id);
            $order = new OrderCore($document->id_order);
            
            $out = array('slip' => array(
                'document_type' => ConfigurationCore::get('mpexpdoc_input_id_slip'),
                'order_id' => $order->id,
                'order_date' => $order->date_add,
                'order_reference' => $order->reference,
                'slip_id'=> $document->id,
                'slip_number' => $document->id,
                'slip_date' => $document->date_add,
                'products_tax_excl' => $document->total_products_tax_excl,
                'shipping_tax_excl' => $document->total_shipping_tax_excl,
                'total_tax_excl' => $document->total_products_tax_excl + $document->total_shipping_tax_excl,
                'total_tax' => 
                    ($document->total_products_tax_incl + $document->total_shipping_tax_incl) -
                    ($document->total_products_tax_excl + $document->total_shipping_tax_excl),       
                'total_tax_incl' => $document->total_products_tax_incl + $document->total_shipping_tax_incl,
                'customer' => $this->getCustomer($order->id_customer, $order->id_address_delivery, $order->id_address_invoice),
                'rows' => $this->getRows($document->id, 'slips'),
                'fees' => $this->getFees($document->id_order),
                ),
            );
            
            $output[]= $out;
        }
        
        return $output;
    }
    
    public function getDeliveries($list_of_ids)
    {
        $output = array();
        foreach($list_of_ids as $id)
        {
            $document = new OrderInvoiceCore($id);
            $order = new OrderCore($document->id_order);
            
            $out = array('delivery' => array(
                'document_type' => ConfigurationCore::get('mpexpdoc_input_id_delivery'),
                'order_id' => $order->id,
                'order_date' => $order->date_add,
                'order_reference' => $order->reference,
                'delivery_id'=> $document->id,
                'delivery_number' => $document->delivery_number,
                'delivery_date' => $document->delivery_date,
                'products_tax_excl' => $document->total_products,
                'shipping_tax_excl' => $document->total_shipping_tax_excl,
                'total_tax_excl' => $document->total_products + $document->total_shipping_tax_excl,
                'total_tax' => 
                    ($document->total_products_wt + $document->total_shipping_tax_incl) -
                    ($document->total_products + $document->total_shipping_tax_excl),       
                'total_tax_incl' => $document->total_products_wt + $document->total_shipping_tax_incl,
                'customer' => $this->getCustomer($order->id_customer, $order->id_address_delivery, $order->id_address_invoice),
                'rows' => $this->getRows($document->id, 'slips'),
                'fees' => $this->getFees($document->id_order),
                ),
            );
            
            $output[]= $out;
        }
        
        return $output;
    }
    
    public function getRows($id, $type)
    {
        switch (Tools::strtolower($type)) {
            case 'orders' :
                return $this->getRowsFromOrder($id);
            case 'invoices' :
                return $this->getRowsFromInvoice($id);
            case 'returns' :
                return $this->getRowsFromReturn($id);
            case 'slips' :
                return $this->getRowsFromSlip($id);
            case 'deliveries' :
                return $this->getRowsFromDelivery($id);
            default: 
                return array();
        }
    }
    
    public function getRowsFromOrder($id)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
            ->from('order_detail')
            ->where('id_order='.(int)$id)
            ->orderBy('id_order_detail');
        $result = $db->executeS($sql);
        if ($result) {
            $rows = array();
            foreach ($result as $row) {
                $rows[] = array(
                    'ean13' => $row['product_ean13'],
                    'reference' => $row['product_reference'],
                    'product_price_tax_excl' => $row['product_price'],
                    'discount_percent' => $this->getDiscount($row['product_price'], $row['unit_price_tax_excl']),
                    'reduction_amount' => 0,
                    'price_tax_excl' => $row['unit_price_tax_excl'],
                    'qty' => $row['product_quantity'],
                    'total_tax_excl' => $row['total_price_tax_excl'],
                    'tax_rate' => $this->getTaxRate($row['product_id']),
                    'total_tax_incl' => $row['total_price_tax_incl'],
                );
            }
            return $rows;
        } else {
            return array();
        }
    }
    
    public function getRowsFromInvoice($id)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order')
            ->from('order_invoice')
            ->where('id_order_invoice='.(int)$id);
        return $this->getRowsFromOrder($db->getValue($sql));
    }
    
    public function getRowsFromReturn($id)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_detail')
            ->select('product_quantity')
            ->from('order_return_detail')
            ->where('id_order_return='.(int)$id);
        $result = $db->executeS($sql);
        $output = array();
        if ($result) {
            foreach ($result as $row) {
                $order_detail = $this->getOrderDetail($row['id_order_detail'], $row['product_quantity']);
                $output[] = $order_detail;
            }
        }
        return $output;
    }
    
    public function getRowsFromSlip($id)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_detail')
            ->select('product_quantity')
            ->from('order_slip_detail')
            ->where('id_order_slip='.(int)$id);
        $result = $db->executeS($sql);
        $output = array();
        if ($result) {
            foreach ($result as $row) {
                $order_detail = $this->getOrderDetail($row['id_order_detail'], $row['product_quantity']);
                $output[] = $order_detail;
            }
        }
        return $output;
    }
    
    public function getRowsFromDelivery($id)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order')
            ->from('order_invoice')
            ->where('id_order_invoice='.(int)$id);
        return $this->getRowsFromOrder($db->getValue($sql));
    }
    
    public function getOrderDetail($id_order_detail, $quantity)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
            ->from('order_detail')
            ->where('id_order_detail='.(int)$id_order_detail);
        $result = $db->executeS($sql);
        if ($result) {
            $rows = array();
            foreach ($result as $row) {
                $rows[] = array(
                    'ean13' => $row['product_ean13'],
                    'reference' => $row['product_reference'],
                    'product_price_tax_excl' => $row['product_price'],
                    'discount_percent' => $this->getDiscount($row['product_price'], $row['unit_price_tax_excl']),
                    'reduction_amount' => 0,
                    'price_tax_excl' => $row['unit_price_tax_excl'],
                    'qty' => $quantity,
                    'total_tax_excl' => $row['total_price_tax_excl'],
                    'tax_rate' => $this->getTaxRate($row['product_id']),
                    'total_tax_incl' => $row['total_price_tax_incl'],
                );
            }
            return $rows;
        } else {
            return array();
        } 
    }
    
    public function getFees($id)
    {
        if ($this->tableExists(_DB_PREFIX_ . 'mp_advpayment_fee')) {
            $feeTableName = 'mp_advpayment_fee';
            $result = $this->getOrderFee($id, $feeTableName);
        }
        
        if ($result === false && $this->tableExists(_DB_PREFIX_ . 'mp_advpayment')) {
            $feeTableName = 'mp_advpayment';
            $result = $this->getOrderFee($id, $feeTableName);
        }
        
        return array();
    }
    
    public function getPayment($module)
    {
        return ConfigurationCore::get('mpexpdoc_'.$module);
    }
    
    public function getCustomer($id_customer, $id_address_delivery, $id_address_invoice)
    {
        $customer = new CustomerCore($id_customer);
        $content = array(
            'id' => ConfigurationCore::get('mpexpdoc_customer_prefix').$customer->id,
            'gender' => $customer->id_gender,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
            'birthday' => $customer->birthday,
            'address_delivery' => $this->getAddress($id_address_delivery),
            'address_invoice' => $this->getAddress($id_address_invoice),
        );
        
        return $content;
    }
    
    public function getAddress($id_address)
    {
        $address = new AddressCore($id_address);
        $content = array(
            'company' => $address->company,
            'firstname' => $address->firstname,
            'lastname' => $address->lastname,
            'address1' => $address->address1,
            'address2' => $address->address2,
            'postcode' => $address->postcode,
            'city' => $address->city,
            'state' => $this->getAddressState($address->id_state),
            'country' =>$this->getAddressCountry($address->id_country),
            'phone' => $address->phone,
            'phone_mobile' => $address->phone_mobile,
            'vat_number'=> Tools::strtoupper($address->vat_number),
            'dni' => Tools::strtoupper($address->dni),
        );
        return $content;
    }
    
    public function getAddressState($id_state)
    {
        $state = new StateCore($id_state);
        if ($state) {
            return Tools::strtoupper($state->iso_code);
        } else {
            return '--';
        }
    }
    
    public function getAddressCountry($id_country)
    {   
        $country = new CountryCore($id_country);
        if ($country) {
            return Tools::strtoupper($country->name[Context::getContext()->language->id]);
        }  else {
            return '--';
        }
    }
    
    public function exportXmlDocument($type)
    {
        $header = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><'.$type.'></'.$type.'>');
        foreach ($this->content as $document) {
            foreach ($document as $key=>$content) {
                $doc = $header->addChild($key);
                foreach ($content as $key_detail=>$detail) {
                    if ($key_detail == 'customer') {
                        $xmlCustomer = $doc->addChild('customer');
                        foreach ($detail as $key_customer=>$cust_detail) {
                            if ($key_customer == 'address_delivery') {
                                $xmlAddressInvoice = $xmlCustomer->addChild('address_delivery');
                                foreach ($cust_detail as $key_addr => $cont_addr) {
                                    $xmlAddressInvoice->addChild($key_addr, $cont_addr);
                                }
                            } elseif ($key_customer == 'address_invoice') {
                                $xmlAddressInvoice = $xmlCustomer->addChild('address_delivery');
                                foreach ($cust_detail as $key_addr => $cont_addr) {
                                    $xmlAddressInvoice->addChild($key_addr, $cont_addr);
                                }
                            } else {
                                $xmlCustomer->addChild($key_customer, $cust_detail);
                            }
                        }
                    } elseif ($key_detail == 'rows') {
                        $xmlRows = $doc->addChild('rows');
                        foreach ($detail as $row_details) {
                            $xmlRow = $xmlRows->addChild('row');
                            foreach ($row_details as $key_row => $row_detail) {
                                $xmlRow->addChild($key_row, $row_detail);
                            }
                        }
                    } elseif ($key_detail == 'fees') {
                        $xmlFees = $doc->addChild('fees');
                        foreach ($detail as $key_row => $row_fees) {
                            $xmlFees->addChild($key_row, $row_fees);
                        }
                    } else {
                        $doc->addChild($key_detail, $detail);
                    }
                }
            }
        }
        return $header;
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
    
    private function getOrderFee($id_order, $tablename)
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
    
    public function getTaxRate($id_product)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();

        $sql    ->select('t.rate')
                ->from('tax', 't')
                ->innerJoin('tax_rule', 'tr', 'tr.id_tax=t.id_tax')
                ->innerJoin('product', '`p`', 'p.id_tax_rules_group=tr.id_tax_rules_group')
                ->where('p.id_product='.(int)$id_product);
        return $db->getValue($sql);
    }
    
    public function getDiscount($price_full, $price_reducted)
    {
        return sprintf('%.4f', (($price_full-$price_reducted)/$price_full) * 100);
    }
}
