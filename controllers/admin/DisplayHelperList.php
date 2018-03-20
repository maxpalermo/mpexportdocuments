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

class DisplayHelperList
{   
    public $fields_data;
    public $fields_list;
    
    public function display($params)
    {
        $pagination = (int)$params['pagination'];
        $current_page = (int)$params['current_page'];
        $controller_name = $params['controller_name'];
        
        $helper = new HelperListCore();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = array(); //array('edit', 'delete', 'view');
        $helper->identifier = 'document_id';
        $helper->show_toolbar = true;
        $helper->toolbar_btn = array(
            'export' => array(
                'href' => 'javascript:exportSelectedDocuments();',
                'desc' => $this->l('Export selected'),
            ),
            'toggle-on' => array(
                'href' => 'javascript:toggleAllBoxes(true);',
                'desc' => $this->l('Select all'),
            ),
            'toggle-off' => array(
                'href' => 'javascript:toggleAllBoxes(false);',
                'desc' => $this->l('Select none'),
            )
        );
        $helper->title = $this->l('Documents found');
        $helper->table = 'expdoc';
        $helper->no_link=true; // Row is not clickable
        $helper->token = Tools::getAdminTokenLite($controller_name);
        $helper->currentIndex = ContextCore::getContext()->link->getAdminLink($controller_name, false);
        $helper->listTotal = count($this->fields_data);
        return $helper->generateList($this->fields_data, $this->fields_list);
    }
    
    /**
     * Non-static method which uses AdminController::translate()
     *
     * @param string  $string Term or expression in english
     * @param string|null $class Name of the class
     * @param bool $addslashes If set to true, the return value will pass through addslashes(). Otherwise, stripslashes().
     * @param bool $htmlentities If set to true(default), the return value will pass through htmlentities($string, ENT_QUOTES, 'utf-8')
     * @return string The translation if available, or the english default text.
     */
    protected function l($string, $class = null, $addslashes = false, $htmlentities = true)
    {
        if ($class === null || $class == 'AdminTab') {
            $class = Tools::substr(get_class($this), 0, -10);
        } elseif (Tools::strtolower(Tools::substr($class, -10)) == 'controller') {
            /* classname has changed, from AdminXXX to AdminXXXController, so we remove 10 characters and we keep same keys */
            $class = Tools::substr($class, 0, -10);
        }
        return Translate::getAdminTranslation($string, $class, $addslashes, $htmlentities);
    }
}
