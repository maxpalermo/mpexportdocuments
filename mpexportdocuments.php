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
 *  @author    Massimiliano Palermo <info@mpsoft.it>
 *  @copyright 2018 Digital Solutions®
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of mpSOFT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MpExportDocuments extends Module
{
    protected $id_lang;
    protected $id_shop;
    protected $adminClassName = 'AdminMpExportDocuments';
    protected $link;
    
    public function __construct()
    {
        $this->name = 'mpexportdocuments';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Digital Solutions®';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MP Export Documents');
        $this->description = $this->l('Export Documents in XML format');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->id_lang = (int)Context::getContext()->language->id;
        $this->link = Context::getContext()->link;
        $this->id_shop = (int)Context::getContext()->shop->id;
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
                || !$this->installTab('MpModules', $this->adminClassName, $this->l('MP Documents export'))
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
     * Install Main Menu
     * @return int Main menu id
     */
    public function installMainMenu()
    {
        $id_mp_menu = (int) TabCore::getIdFromClassName('MpModules');
        if ($id_mp_menu == 0) {
            $tab = new TabCore();
            $tab->active = 1;
            $tab->class_name = 'MpModules';
            $tab->id_parent = 0;
            $tab->module = null;
            $tab->name = array();
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $this->l('MP Modules');
            }
            $id_mp_menu = $tab->add();
            if ($id_mp_menu) {
                PrestaShopLoggerCore::addLog('id main menu: '.(int)$id_mp_menu);
                return (int)$tab->id;
            } else {
                PrestaShopLoggerCore::addLog('id main menu error');
                return false;
            }
        }
    }
    
    /**
     * Get id of main menu
     * @return int Main menu id
     */
    public function getMainMenuId()
    {
        $id_menu = (int)Tab::getIdFromClassName('MpModules');
        return $id_menu;
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
        $id_parent = (int)Tab::getIdFromClassName($parent);
        PrestaShopLoggerCore::addLog('Install main menu: id=' . (int)$id_parent);
        if (!$id_parent) {
            $id_parent = $this->installMainMenu();
            if (!$id_parent) {
                $this->_errors[] = $this->l('Unable to install main module menu tab.');
                return false;
            }
            PrestaShopLoggerCore::addLog('Created main menu: id=' . (int)$id_parent);
        }
        $tab->id_parent = (int)$id_parent;
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
            $result = $tab->delete();
            if (!$result) {
                $this->_errors[] = $this->l('Unable to remove module menu tab.');
            }
            return $result;
        }
    }
    
    public function hookDisplayBackOfficeHeader()
	{
		$ctrl = $this->context->controller;
		if ($ctrl instanceof AdminController && method_exists($ctrl, 'addCss')) {
            $ctrl->addCss($this->_path . 'views/css/icon-menu.css');
        }
        if ($ctrl instanceof AdminController && method_exists($ctrl, 'addJS')) {
            $ctrl->addJs($this->_path . 'views/js/getContent.js');
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
        $this->displayMessage = '';
        if (Tools::isSubmit('ajax') && !empty(Tools::getValue('action', ''))) {
            $action = 'ajaxProcess' . Tools::getValue('action', '');
            $this->$action();
            exit();
        }
        if (Tools::isSubmit('submitForm')) {
            $values = Tools::getAllValues();
            ConfigurationCore::updateValue('mpexpdoc_input_customer_prefix', $values['input_text_customer_prefix']);
            ConfigurationCore::updateValue('mpexpdoc_input_id_order', $values['input_text_id_order']);
            ConfigurationCore::updateValue('mpexpdoc_input_id_invoice', $values['input_text_id_invoice']);
            ConfigurationCore::updateValue('mpexpdoc_input_id_return', $values['input_text_id_return']);
            ConfigurationCore::updateValue('mpexpdoc_input_id_slip', $values['input_text_id_slip']);
            ConfigurationCore::updateValue('mpexpdoc_input_id_delivery', $values['input_text_id_delivery']);
            $this->displayMessage = $this->displayConfirmation($this->l('Configuration saved.'));
        }
        return $this->displayMessage . $this->initForm();
    }
    
    protected function initForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Export configuration'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_customer_prefix',
                        'label' => $this->l('Customer prefix'),
                        'desc' => $this->l('Please insert the customer prefix'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-sm',
                    ),
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_id_order',
                        'label' => $this->l('Document type: Order'),
                        'desc' => $this->l('Please insert Document type for orders'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-sm text-right',
                    ),
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_id_invoice',
                        'label' => $this->l('Document type: Invoice'),
                        'desc' => $this->l('Please insert Document type for invoices'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-sm text-right',
                    ),
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_id_return',
                        'label' => $this->l('Document type: Return'),
                        'desc' => $this->l('Please insert Document type for returns'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-sm text-right',
                    ),
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_id_slip',
                        'label' => $this->l('Document type: Slip'),
                        'desc' => $this->l('Please insert Document type for slips'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-sm text-right',
                    ),
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_id_delivery',
                        'label' => $this->l('Document type: Delivery'),
                        'desc' => $this->l('Please insert Document type for deliveries'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-sm text-right',
                    ),
                    array(
                        'required' => true,
                        'type' => 'select',
                        'name' => 'input_select_payment_modules',
                        'label' => $this->l('Payment modules'),
                        'desc' => $this->l('Select the payment module.'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-list-ul"></i>',
                        'class' => 'input fixed-width-sm',
                        'options' => array(
                            'query' => $this->getPaymentModuleList(),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'required' => false,
                        'type' => 'text',
                        'name' => 'input_text_payment_display',
                        'label' => $this->l('Display export name'),
                        'desc' => $this->l('Please insert export name for the selected payment module.'),
                        'prefix' => '<i class="icon-chevron-right"></i>',
                        'suffix' => '<i class="icon-pencil"></i>',
                        'class' => 'input fixed-width-xxl',
                    ),
                ),
                'buttons' => array(
                    'display_name' => array(
                        'title' => $this->l('Save display payment name'),
                        'href' => 'javascript:void(0);',
                        'class' => 'pull-right',
                        'icon' => 'process-icon-new',
                        'name' => 'input_btn_save_payment_name',
                        'id' => 'input_btn_save_payment_name',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'icon' => 'process-icon-save'
                ),
            ),
        );
        
        $helper = new HelperFormCore();
        $helper->table = '';
        $helper->default_form_language = (int)$this->id_lang;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANGUAGE');
        $helper->submit_action = 'submitForm';
        $helper->currentIndex = $this->link->getAdminLink('AdminModules', false) 
            . '&configure=' . $this->name
            . '&tab_module=administration' 
            . '&module_name=mpexportdocuments';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        if (Tools::isSubmit('submitForm')) {
            $submit_values = Tools::getAllValues();
            $output = array();
            foreach($submit_values as $key=>$value) {
                if(is_array($value)) {
                    $output[$key.'[]'] = $value;
                } else {
                    $output[$key] = $value;
                }
                $output['input_select_payment_modules'] = 0;
                $output['input_text_payment_display'] = '';
            }
            $helper->tpl_vars = array(
                'fields_value' => $output,
                'languages' => $this->context->controller->getLanguages(),
            );
        } else {
            $helper->tpl_vars = array(
                'fields_value' => array(
                    'input_text_customer_prefix' => ConfigurationCore::get('mpexpdoc_input_customer_prefix'),
                    'input_text_id_order' => ConfigurationCore::get('mpexpdoc_input_id_order'),
                    'input_text_id_invoice' => ConfigurationCore::get('mpexpdoc_input_id_invoice'),
                    'input_text_id_return' => ConfigurationCore::get('mpexpdoc_input_id_return'),
                    'input_text_id_slip' => ConfigurationCore::get('mpexpdoc_input_id_slip'),
                    'input_text_id_delivery' => ConfigurationCore::get('mpexpdoc_input_id_delivery'),
                    'input_select_payment_modules' => 0,
                    'input_text_payment_display' => '',
                ),
                'languages' => $this->context->controller->getLanguages(),
            );
        }
        return $helper->generateForm(array($fields_form));
    }
    
    public function ajaxProcessUpdatePaymentDisplayName()
    {
        $payment_module = Tools::getValue('payment_module', ''); 
        $display_name = Tools::getValue('display_name', '');
        if (!$payment_module) {
            print Tools::jsonEncode(
                array(
                    'result' => false,
                    'error_msg' => $this->l('Please select a valid payment method.'),
                )
            );
            exit();
        }
        if (!$display_name) {
            print Tools::jsonEncode(
                array(
                    'result' => false,
                    'error_msg' => $this->l('Please insert a valid display name for this payment method.'),
                    'title' => $this->l('ERROR'),
                )
            );
            exit();
        }
        ConfigurationCore::updateValue('mpexpdoc_' . $payment_module, $display_name);
        print Tools::jsonEncode(
            array(
                'result' => true,
                'display_msg' => $this->l('Payment method updated.'),
                'title' => $this->l('Operation done'),
            )
        );
        exit();
    }
    
    public function ajaxProcessGetPaymentDisplayName()
    {
        $payment_module = Tools::getValue('payment_module', ''); 
        $display_name = ConfigurationCore::get('mpexpdoc_' . $payment_module);
        if (empty($display_name)) {
            $display_name = '---';
        }
        print Tools::jsonEncode(
            array(
                'result' => true,
                'return_value' => $display_name,
            )
        );
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
    
    public function getUrl()
    {
        return $this->_path;
    }
    
    public function getPath()
    {
        return $this->local_path;
    }
}
