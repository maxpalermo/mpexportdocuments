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
 *  @author    mpSOFT <info@mpsoft.it>
 *  @copyright 2017 mpSOFT Massimiliano Palermo
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of mpSOFT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MpExportInvoices extends Module
{
    protected $_lang;
    protected $adminClassName = 'AdminMpExportInvoices';
    
    public function __construct()
    {
        $this->name = 'mpexportinvoices';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Digital Solutions®';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MP Export Invoices');
        $this->description = $this->l('Export Invoices in XML format');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->_lang = ContextCore::getContext()->language->id;
        
        if (!defined('MP_EXPORTINVOICE_FOLDER')) {
            define('MP_EXPORTINVOICE_FOLDER', _PS_MODULE_DIR_ . 'mpexportinvoices/');
        }
        if (!defined('MP_EXPORTINVOICE_CSS')) {
            define('MP_EXPORTINVOICE_CSS', MP_EXPORTINVOICE_FOLDER . 'views/css/');
        }
        if (!defined('MP_EXPORTINVOICE_JS')) {
            define('MP_EXPORTINVOICE_JS', MP_EXPORTINVOICE_FOLDER . 'views/js/');
        }
        if (!defined('MP_EXPORTINVOICE_IMG')) {
            define('MP_EXPORTINVOICE_IMG', '../modules/mpexportinvoices/views/img/');
        }
    }
    
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!parent::install() 
                || !$this->registerHook('displayBackOfficeHeader')
                || !$this->registerHook('displayAdminOrderContentOrder')
                || !$this->registerHook('displayHeader')
                || !$this->installTab('AdminOrders', $this->adminClassName, $this->l('MP Invoices export'))
        ) {
            return false;
        }
        return true;
    }
      
    public function uninstall()
    {
        if (!parent::uninstall() || !$this->uninstallTab($this->adminClassName)) {
            return false;
        }
        return true;
    }
    
    /**
     * 
     * @param string $parent Parent tab name
     * @param type $class_name Class name of the module
     * @param type $name Display name of the module
     * @param type $active If true, Tab menu will be shown
     * @return boolean True if successfull, False otherwise
     */
    public function installTab($parent, $class_name, $name, $active = 1)
    {
        // Create new admin tab
        $tab = new Tab();
        
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->name      = array();
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        
        $tab->class_name = $class_name;
        $tab->module     = $this->name;
        $tab->active     = $active;
        
        if (!$tab->add()) {
            $this->_errors[] = $this->l('Error during Tab install.');
            return false;
        }
        return true;
    }
    
    /**
     * 
     * @param string pe $class_name Class name of the module
     * @return boolean True if successfull, False otherwise
     */
    public function uninstallTab($class_name)
    {
        $id_tab = (int)Tab::getIdFromClassName($class_name);
        if ($id_tab) {
            $tab = new Tab((int)$id_tab);
            return $tab->delete();
        }
    }
    
    public function hookDisplayAdminOrderContentOrder($params)
    {
        $this->context->smarty->assign(
                'mpexport_link',
                $this->context->link->getAdminLink('AdminMpExportInvoices')
                . '&id_order='. $params['order']->id
        );
        return $this->display(__FILE__, 'toolBar.tpl');
    }
    
    public function getContent()
    {
        $smarty = ContextCore::getContext()->smarty;
        $smarty->assign(
            array(
                'modules' => $this->getPaymentModuleList(),
                'page_link' => ContextCore::getContext()->link->getAdminLink('AdminModules'),
                'token' => Tools::getAdminTokenLite('AdminModules'),
                'id_customer_prefix' => ConfigurationCore::get('MPEXPORTINVOICES_CUSTOMER_PREFIX'),
                'id_document' => ConfigurationCore::get('MPEXPORTINVOICES_ID_DOCUMENT'),
            )
        );
        $template = dirname(__FILE__) . '/views/templates/admin/getcontent.tpl';
        
        $fetch = $smarty->fetch($template);
        
        return $fetch;
    }
    
    public function ajaxProcessSaveCustomerPrefix()
    {
        $prefix = Tools::getValue('id_customer_prefix', '');
        $id_document = Tools::getValue('id_document', '');
        ConfigurationCore::updateValue('MPEXPORTINVOICES_CUSTOMER_PREFIX', $prefix);
        ConfigurationCore::updateValue('MPEXPORTINVOICES_ID_DOCUMENT', $id_document);
        print $this->l('Parameters saved successfully.');
        exit();
    }
    
    public function ajaxProcessRefreshPaymentModuleList()
    {
        $id_module = (int)Tools::getValue('id_payment_module', '0');
        $module_name = ConfigurationCore::get('MPEXPORTINVOICES_MODULE'.$id_module);
        print $module_name;
        exit();
    }
    
    public function ajaxProcessSaveModuleName()
    {
        $id_module = (int)Tools::getValue('id_module', '0');
        $module_name = Tools::getValue('module_name', '');
        ConfigurationCore::updateValue('MPEXPORTINVOICES_MODULE'.$id_module, $module_name);
        print $this->l('Payment module name updated.');
        exit();
    }
    
    public function getPaymentModuleList()
    {
        $payments = array();

        $modules_list = Module::getPaymentModules();
        array_push($payments, array(
            'id' => 0,
            'name' => $this->l('Choose a payment module'),
        ));
        foreach($modules_list as $module)
            {		
                array_push($payments, array(
                    'id' => $module['id_module'],
                    'name' => $module['name'],
                ));
            }

        return $payments;
    }
}
