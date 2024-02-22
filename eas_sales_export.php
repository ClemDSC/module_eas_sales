<?php
/**
 * 2007-2024 PrestaShop
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2024 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Eas_sales_export extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'eas_sales_export';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Klorel';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Easytis - Export des ventes');
        $this->description = $this->l('Module permettant l\'export de ventes, sur une période définie, au format .csv ');

        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('EAS_SALES_EXPORT_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('EAS_SALES_EXPORT_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitEas_sales_exportModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        return $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEas_sales_exportModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'date',
                        'label' => $this->l('Date de début'),
                        'name' => 'EAS_SALES_EXPORT_DATE_START',
                    ],
                    [
                        'type' => 'date',
                        'label' => $this->l('Date de fin'),
                        'name' => 'EAS_SALES_EXPORT_DATE_END',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Export'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            'EAS_SALES_EXPORT_DATE_START' => Configuration::get('EAS_SALES_EXPORT_DATE_START', true),
            'EAS_SALES_EXPORT_DATE_END' => Configuration::get('EAS_SALES_EXPORT_DATE_END', true),
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $this->exportCSVbyDate();
    }

    public function exportCSVbyDate()
    {
        $startDate = Configuration::get('EAS_SALES_EXPORT_DATE_START', true);
        $endDate = Configuration::get('EAS_SALES_EXPORT_DATE_END', true);

        /*        $sql = "SELECT
                            pl.`name` AS nom_produit,
                            p.`reference` AS reference_produit,
                            m.`name` AS manufacturer,
                            p.`mpn` AS deee,
                            SUM(eod.`product_quantity`) AS quantite_vendue,
                            p.`weight` AS poids_produit
                        FROM
                            " . _DB_PREFIX_ . "order_detail eod
                        LEFT JOIN " . _DB_PREFIX_ . "orders o ON o.`id_order` = eod.`id_order`
                        LEFT JOIN " . _DB_PREFIX_ . "product_lang pl ON eod.`product_id` = pl.`id_product`
                        LEFT JOIN " . _DB_PREFIX_ . "product p ON eod.`product_id` = p.`id_product`
                        LEFT JOIN " . _DB_PREFIX_ . "manufacturer m ON p.`id_manufacturer` = m.`id_manufacturer`
                        WHERE
                            o.`date_add` BETWEEN '" . pSQL($startDate) . "' AND '" . pSQL($endDate) . "'
                        GROUP BY
                            eod.`product_reference`;";*/

        $sql = "
                SELECT
                    p.`id_product`,
                    pl.`name` AS product_name,
                    p.`reference` AS product_reference,
                    m.`name` AS manufacturer_name,
                    p.`mpn` AS product_mpn,
                    SUM(od.`product_quantity`) AS total_quantity_sold,
                    p.`weight` AS product_weight
                FROM
                    `eas_orders` o
                INNER JOIN
                    `eas_order_detail` od ON o.`id_order` = od.`id_order`
                INNER JOIN
                    `eas_product` p ON od.`product_id` = p.`id_product`
                INNER JOIN
                    `eas_product_lang` pl ON p.`id_product` = pl.`id_product`
                INNER JOIN
                    `eas_manufacturer` m ON p.`id_manufacturer` = m.`id_manufacturer`
                WHERE
                    o.`date_add` BETWEEN '" . pSQL($startDate) . "' AND '" . pSQL($endDate) . "'
                AND pl.`id_lang` = 1
                AND o.`current_state` NOT IN (6, 7)
                GROUP BY
                    p.`id_product`
                ORDER BY
                    m.`name` ASC;
                ";

        $result = Db::getInstance()->executeS($sql);

        $filename = 'export_ventes_' . date('Y-m-d') . '.csv';

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $file = fopen('php://output', 'w');

        fputcsv($file, ['ID Produit', 'Désignation', 'Référence du produit', 'Fabricant', 'DEEE', 'Quantité vendue', 'Poids du produit'], ';');

        foreach ($result as $row) {
            fputcsv($file, $row, ';');
        }

        die('');
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }
}
