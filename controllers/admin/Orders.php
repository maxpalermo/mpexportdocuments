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

class MpExportDocumentsOrdersController extends MpCustomController
{
    protected $table_import = 'order_invoice';
    
    public function getFieldsList()
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
                'title' => $this->l('Reference'),
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
            'order_total' => array(
                'title' => $this->l('Total'),
                'align' => 'text-right',
                'width' => 'auto',
                'type' => 'price',
                'search' => false,
            ),
            'order_status' => array(
                'title' => $this->l('Status'),
                'align' => 'text-left',
                'width' => 'auto',
                'type' => 'text',
                'search' => false,
            ),
            
        );
        
        return $list;
    }
    
    public function getFieldsData($params)
    {
        $this->date_start = $params['date_start'];
        $this->date_end = $params['date_end'];
        $isStartDate = ValidateCore::isDate($this->date_start);
        $isEndDate = ValidateCore::isDate($this->date_end);
        
        if ($isStartDate) {
            $this->date_start = $this->date_start.' 00:00:00';
        }
        if ($isEndDate) {
            $this->date_end = $this->date_end.' 23:59:59';
        }
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        if ($isStartDate || $isEndDate) {
            $fieldsArray = array(
                'o.id_order as document_id',
                'o.date_add as document_date',
                'o.reference as document_number',
                'o.total_paid as order_total',
                'osl.name as order_status',
                "UPPER(CONCAT(c.firstname, ' ', c.lastname)) as customer_name",
            );
            foreach ($fieldsArray as $field) {
                $sql->select($field);
            }
            $sql
                ->from('orders', '`o`')
                ->innerJoin('customer', '`c`', 'c.id_customer=o.id_customer')
                ->innerJoin('order_state_lang', 'osl', 'osl.id_order_state=o.current_state')
                ->where('osl.id_lang='.(int) Context::getContext()->language->id)
                ->where('o.current_state != 6')
                ->orderBy('o.date_add')
                ->orderBy('o.reference');

            $sql = $this->addDateToSql($sql, 'o.date_add');
            
            $result = $db->executeS($sql);
            if ($result) {
                $total = 0;
                foreach ($result as $row) {
                    $total += $row['order_total'];
                }
                return array(
                    'total' => $total,
                    'result' => $result,
                );
            } else {
                return array(
                    'total' => 0,
                    'result' => array(),
                );
            }   
        } else {
            return array(
                    'total' => 0,
                    'result' => array(),
                );
        }
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
}
