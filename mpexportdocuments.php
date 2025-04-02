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
if (!defined('_PS_VERSION_')) {
    exit;
}

class MpExportDocuments extends Module
{
    protected $id_lang;

    public function __construct()
    {
        $this->name = 'mpexportdocuments';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->trans('MP Export Documents', [], 'Modules.Mpexportdocuments.Admin');
        $this->description = $this->trans('Export documents in XML format.', [], 'Modules.Mpexportdocuments.Admin');
        $this->id_lang = (int) $this->context->language->id;
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('displayAdminEndContent');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function renderWidget($hookName, array $configuration)
    {
        switch ($hookName) {
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
            default:
                return '';
        }

        return '';
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $vars = [];
        switch ($hookName) {
            case 'displayAdminOrderMain':
            case 'displayAdminOrderSide':
            case 'displayAdminOrderTop':
            case 'displayBackOfficeFooter':
                break;
            default:
                return [];
        }

        return $vars;
    }

    public function hookActionAdminControllerSetMedia()
    {
        $controller = Tools::strtolower(Tools::getValue('controller'));
        $id_order = (int) Tools::getValue('id_order');

        if ($controller == 'adminorders' && !$id_order) {
            $this->context->controller->addJS([
                $this->_path . 'views/js/summaryPanel/summaryPanel.js',
                $this->_path . 'views/js/notePanel/notePanel.js',
                $this->_path . 'views/js/notePanel/notePanelAttachment.js',
                $this->_path . 'views/js/notePanel/bindNoteAttachment.js',
                $this->_path . 'views/js/notePanel/bindNoteFlags.js',
                $this->_path . 'views/js/notePanel/bindSearchBar.js',
            ]);
        }
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        if ($searchQueryBuilder) {
            $skip_os = $this->getForbiddenOs();
            // copio in un'altra variabile
            $query = clone $searchQueryBuilder;

            // 1. Modifica la SELECT originale per ottenere la somma
            $query->select('SUM(o.total_paid_tax_incl) as total_order');
            $query->andWhere('o.current_state NOT IN (' . implode(',', $skip_os) . ')');

            // 2. Esegui la query SENZA limiti per ottenere il totale complessivo
            $limit = $query->getMaxResults();
            $query->setMaxResults(null);
            $totalResult = $query->execute()->fetchAssociative();
            $totalOrdersNoLimit = $totalResult['total_order'] ?? 0;
            // 3. Esegui la query con i limiti originali
            $query->select('o.total_paid_tax_incl as total_order');
            $query->setMaxResults($limit);
            $totalResult = $query->execute()->fetchAllAssociative();
            $totalOrdersLimit = 0;
            if ($totalResult) {
                foreach ($totalResult as $result) {
                    $totalOrdersLimit += (float) $result['total_order'];
                }
            }

            // 4. Puoi salvare il totale in qualche variabile globale o usarlo come necessario
            $this->context->smarty->assign('global_order_total_no_limit', $totalOrdersNoLimit);
            $this->context->smarty->assign('global_order_total_limit', $totalOrdersLimit);

            // Inserisco i cookie dei totali per lòeggerli successivamente
            $cookie = $this->context->cookie;
            $cookie->__set('MPTOTALORDERS_NOLIMIT', $totalOrdersNoLimit);
            $cookie->__set('MPTOTALORDERS_LIMIT', $totalOrdersLimit);
            $cookie->write();
        }
    }

    public function hookDisplayAdminEndContent($params)
    {
        $controller = Tools::strtolower(Tools::getValue('controller'));
        if ($controller != 'adminorders') {
            return;
        }
        $cookie = $this->context->cookie;
        $totalNolimit = (float) $cookie->__get('MPTOTALORDERS_NOLIMIT');
        $totalLimit = (float) $cookie->__get('MPTOTALORDERS_LIMIT');

        $formattedPriceLimit = $this->context->getCurrentLocale()->formatPrice(
            $totalLimit,
            $this->context->currency->iso_code // EUR, USD, ecc.
        );
        $formattedPriceNolimit = $this->context->getCurrentLocale()->formatPrice(
            $totalNolimit,
            $this->context->currency->iso_code // EUR, USD, ecc.
        );

        $tpl = $this->getLocalPath() . 'views/templates/hook/totalOrdersBadge.tpl';
        $template = $this->context->smarty->createTemplate($tpl, $this->context->smarty);
        $template->assign([
            'totalSearch' => $formattedPriceNolimit,
            'totalPage' => $formattedPriceLimit,
        ]);

        return $template->fetch();
    }

    private function getTotalSearch()
    {
        return 123456;
    }

    private function getTotalPage()
    {
        return 23564;
    }

    public function getContent()
    {
        $message = '';
        $message_type = 'success';
        if (Tools::isSubmit('submitSaveConfig')) {
            $this->setForbiddenOs(Tools::getValue('forbidden_os'));
            $message = $this->displayConfirmation($this->l('Configuration updated'));
        }
        $tpl = $this->getLocalPath() . 'views/templates/admin/configuration.tpl';
        $template = $this->context->smarty->createTemplate($tpl, $this->context->smarty);
        $params = [
            'forbidden_os' => $this->getForbiddenOs(),
            'orderStates' => OrderState::getOrderStates($this->id_lang),
            'message' => $message,
            'message_type' => $message_type,
        ];
        $template->assign($params);

        return $template->fetch();
    }

    public function getForbiddenOs()
    {
        $values = Configuration::get(static::FORBIDDEN_OS);

        try {
            return json_decode($values, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function setForbiddenOs($list)
    {
        if (!is_array($list)) {
            $list = [$list];
        }

        return Configuration::updateValue(static::FORBIDDEN_OS, json_encode($list));
    }
}
