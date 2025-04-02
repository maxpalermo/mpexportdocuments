<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpExportDocuments\Helpers;

class CreateTemplate
{
    protected $module;

    /**
     * Summary of __construct
     *
     * @param string $moduleName Module name
     *
     * @throws \Exception
     */
    public function __construct($moduleName)
    {
        try {
            $this->module = \Module::getInstanceByName($moduleName);
        } catch (\Throwable $th) {
            $ln = '<br>';
            echo $th->getMessage() . $ln;
            echo $th->getTraceAsString() . $ln;
            exit;
        }
    }

    /**
     * Summary of getAdminTemplatePath
     *
     * @param string $name Template name
     *
     * @return string
     */
    public function getAdminTemplatePath($name)
    {
        return $this->module->getLocalPath() . 'views/templates/admin/' . $name;
    }

    /**
     * Summary of getAdminTemplatePath
     *
     * @param string $name Template name
     *
     * @return string
     */
    public function getFrontTemplatePath($name)
    {
        return $this->module->getLocalPath() . 'views/templates/front/' . $name;
    }

    /**
     * Summary of getAdminTemplatePath
     *
     * @param string $name Template name
     *
     * @return string
     */
    public function getHookTemplatePath($name)
    {
        return $this->module->getLocalPath() . 'views/templates/hook/' . $name;
    }

    /**
     * Summary of createTemplate
     *
     * @param string $name Template name
     * @param array $data Data to pass to template
     *
     * @return string
     */
    public function createTemplate($name, $data, $type = 'admin')
    {
        $allowedTypes = [
            'admin',
            'front',
            'hook',
        ];

        if (!in_array($type, $allowedTypes)) {
            return false;
        }

        $action = 'get' . \Tools::ucfirst($type) . 'TemplatePath';

        $tplPath = $this->$action($name);
        $tpl = \Context::getContext()->smarty->createTemplate($tplPath);
        $tpl->assign($data);
        $html = $tpl->fetch();

        return $html;
    }
}
