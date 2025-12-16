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
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class HtmlHomepage extends Module
{
    public function __construct()
    {
        $this->name = 'htmlhomepage';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('HTML Homepage');
        $this->description = $this->l('Permette di visualizzare contenuto HTML personalizzato nella homepage');
        $this->confirmUninstall = $this->l('Sei sicuro di voler disinstallare questo modulo?');

        if (!Configuration::get('HTMLHOMEPAGE_LIVE_MODE')) {
            $this->warning = $this->l('Nessun nome fornito');
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->registerHook('displayHome') &&
            $this->registerHook('displayHeader') &&
            Configuration::updateValue('HTMLHOMEPAGE_LIVE_MODE', false) &&
            Configuration::updateValue('HTMLHOMEPAGE_HTML_CONTENT', '');
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('HTMLHOMEPAGE_LIVE_MODE') &&
            Configuration::deleteByName('HTMLHOMEPAGE_HTML_CONTENT');
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $htmlContent = Tools::getValue('HTMLHOMEPAGE_HTML_CONTENT', false, false);
            $liveMode = (bool)Tools::getValue('HTMLHOMEPAGE_LIVE_MODE');

            if ($htmlContent === false || $htmlContent === '') {
                $output .= $this->displayError($this->l('Contenuto HTML non valido'));
            } elseif (!$this->isValidHtmlWithIframe($htmlContent)) {
                $output .= $this->displayError($this->l('Contenuto HTML non valido - sono ammessi solo tag HTML sicuri e iframe'));
            } else {
                Configuration::updateValue('HTMLHOMEPAGE_HTML_CONTENT', $htmlContent, true);
                Configuration::updateValue('HTMLHOMEPAGE_LIVE_MODE', $liveMode);
                $output .= $this->displayConfirmation($this->l('Impostazioni aggiornate'));
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Valida il contenuto HTML permettendo iframe ma bloccando script pericolosi
     *
     * @param string $html Contenuto HTML da validare
     * @return bool True se il contenuto è valido
     */
    private function isValidHtmlWithIframe($html)
    {
        if (empty($html)) {
            return true;
        }

        // Blocca tag script, javascript:, vbscript:, e event handlers
        $dangerousPatterns = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/on\w+\s*=\s*["\'][^"\']*["\']/i',
            '/on\w+\s*=\s*[^\s>]+/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return false;
            }
        }

        return true;
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Impostazioni'),
            ],
            'input' => [
                [
                    'type' => 'switch',
                    'label' => $this->l('Abilita modulo'),
                    'name' => 'HTMLHOMEPAGE_LIVE_MODE',
                    'is_bool' => true,
                    'desc' => $this->l('Abilita o disabilita la visualizzazione del contenuto HTML'),
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Abilitato')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabilitato')
                        ]
                    ],
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Contenuto HTML'),
                    'name' => 'HTMLHOMEPAGE_HTML_CONTENT',
                    'lang' => false,
                    'autoload_rte' => false,
                    'rows' => 15,
                    'cols' => 100,
                    'desc' => $this->l('Inserisci il contenuto HTML che vuoi visualizzare nella homepage. Sono supportati iframe per embed di video, webcam, mappe, ecc.'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Salva'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Salva'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Indietro')
            ]
        ];

        $helper->fields_value['HTMLHOMEPAGE_LIVE_MODE'] = Configuration::get('HTMLHOMEPAGE_LIVE_MODE');
        $helper->fields_value['HTMLHOMEPAGE_HTML_CONTENT'] = Configuration::get('HTMLHOMEPAGE_HTML_CONTENT');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayHome($params)
    {
        if (!Configuration::get('HTMLHOMEPAGE_LIVE_MODE')) {
            return;
        }

        $htmlContent = Configuration::get('HTMLHOMEPAGE_HTML_CONTENT');

        if (empty($htmlContent)) {
            return;
        }

        $this->context->smarty->assign([
            'html_content' => $htmlContent,
        ]);

        return $this->display(__FILE__, 'htmlhomepage.tpl');
    }

    public function hookDisplayHeader($params)
    {
        $this->context->controller->addCSS($this->_path . 'views/css/htmlhomepage.css', 'all');
    }
}

