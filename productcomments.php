<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use ProductCommentsModule\ProductComment;
use ProductCommentsModule\ProductCommentCriterion;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

/**
 * Class ProductComments
 */
class ProductComments extends Module
{
    // @codingStandardsIgnoreStart
    protected $moduleHtml = '';
    protected $moduleFilters = [];
    protected $postErrors = [];

    protected $productCommentsCriterionTypes = [];
    protected $baseUrl;
    public $secure_key;
    public $page_name;
    // @codingStandardsIgnoreEnd

    /**
     * ProductComments constructor.
     */
    public function __construct()
    {
        $this->name = 'productcomments';
        $this->tab = 'front_office_features';
        $this->version = '4.0.3';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        $this->setFilters();

        parent::__construct();

        $this->secure_key = Tools::encrypt($this->name);

        $this->displayName = $this->l('Product Comments');
        $this->description = $this->l('Allows users to post reviews and rate products on specific criteria.');
    }

    /**
     * Set filters
     */
    protected function setFilters()
    {
        $this->moduleFilters = [
            'page'                 => (string) Tools::getValue('submitFilter'.$this->name),
            'pagination'           => (string) Tools::getValue($this->name.'_pagination'),
            'filter_id'            => (string) Tools::getValue($this->name.'Filter_id_product_comment'),
            'filter_content'       => (string) Tools::getValue($this->name.'Filter_content'),
            'filter_customer_name' => (string) Tools::getValue($this->name.'Filter_customer_name'),
            'filter_grade'         => (string) Tools::getValue($this->name.'Filter_grade'),
            'filter_name'          => (string) Tools::getValue($this->name.'Filter_name'),
            'filter_date_add'      => (string) Tools::getValue($this->name.'Filter_date_add'),
        ];
    }

    /**
     * @return bool
     */
    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $keep
     *
     * @return bool
     */
    public function uninstall($keep = true)
    {
        if (!parent::uninstall() || ($keep && !$this->deleteTables()) ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MODERATE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_ALLOW_GUESTS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MINIMAL_TIME') ||
            !$this->unregisterHook('extraProductComparison') ||
            !$this->unregisterHook('displayRightColumnProduct') ||
            !$this->unregisterHook('productTabContent') ||
            !$this->unregisterHook('header') ||
            !$this->unregisterHook('productTab') ||
            !$this->unregisterHook('top') ||
            !$this->unregisterHook('displayProductListReviews')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function deleteTables()
    {
        return Db::getInstance()->execute(
            '
			DROP TABLE IF EXISTS
			`'._DB_PREFIX_.'product_comment`,
			`'._DB_PREFIX_.'product_comment_criterion`,
			`'._DB_PREFIX_.'product_comment_criterion_product`,
			`'._DB_PREFIX_.'product_comment_criterion_lang`,
			`'._DB_PREFIX_.'product_comment_criterion_category`,
			`'._DB_PREFIX_.'product_comment_grade`,
			`'._DB_PREFIX_.'product_comment_usefulness`,
			`'._DB_PREFIX_.'product_comment_report`'
        );
    }

    /**
     * @param bool $keep
     *
     * @return bool
     */
    public function install($keep = true)
    {
        if ($keep) {
            if (!file_exists(__DIR__.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'install.sql')) {
                return false;
            } elseif (!$sql = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'install.sql')) {
                return false;
            }
            $sql = str_replace(['PREFIX_', 'ENGINE_TYPE', 'DB_NAME'], [_DB_PREFIX_, _MYSQL_ENGINE_, _DB_NAME_], $sql);
            $sql = preg_split("/;\s*[\r\n]+/", trim($sql));

            foreach ($sql as $query) {
                if (!Db::getInstance()->execute(trim($query))) {
                    return false;
                }
            }
        }

        if (parent::install() == false ||
            !$this->registerHook('productTab') ||
            !$this->registerHook('extraProductComparison') ||
            !$this->registerHook('productTabContent') ||
            !$this->registerHook('header') ||
            !$this->registerHook('displayRightColumnProduct') ||
            !$this->registerHook('displayProductListReviews') ||
            !Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', 30) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', 0) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', 1)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $this->moduleHtml = '';
        if (Tools::isSubmit('updateproductcommentscriterion')) {
            $this->moduleHtml .= $this->renderCriterionForm((int) Tools::getValue('id_product_comment_criterion'));
        } else {
            $this->postProcess();
            $this->moduleHtml .= $this->renderConfigForm();
            $this->moduleHtml .= $this->renderModerateLists();
            $this->moduleHtml .= $this->renderCriterionList();
            $this->moduleHtml .= $this->renderCommentsList();
        }

        $this->setBaseUrl();
        $this->productCommentsCriterionTypes = ProductCommentCriterion::getTypes();

        $this->context->controller->addJs($this->_path.'views/js/moderate.js');

        return $this->moduleHtml;
    }

    /**
     * Render criterion form
     *
     * @param int $idCriterion
     *
     * @return string
     */
    public function renderCriterionForm($idCriterion = 0)
    {
        $types = ProductCommentCriterion::getTypes();
        $query = [];
        foreach ($types as $key => $value) {
            $query[] = [
                'id'    => $key,
                'label' => $value,
            ];
        }

        $criterion = new ProductCommentCriterion((int) $idCriterion);
        $selectedCategories = $criterion->getCategories();

        $productTableValues = Product::getSimpleProducts($this->context->language->id);
        $selectedProducts = $criterion->getProducts();
        foreach ($productTableValues as $key => $product) {
            if (false !== array_search($product['id_product'], $selectedProducts)) {
                $productTableValues[$key]['selected'] = 1;
            }
        }


        $fieldCategoryTree = [
            'type'   => 'categories',
            'label'  => $this->l('Criterion will be restricted to the following categories'),
            'name'   => 'categoryBox',
            'desc'   => $this->l('Mark the boxes of categories to which this criterion applies.'),
            'tree'   => [
                'use_search'          => false,
                'id'                  => 'categoryBox',
                'use_checkbox'        => true,
                'selected_categories' => $selectedCategories,
            ],
            //retro compat 1.5 for category tree
            'values' => [
                'trads'               => [
                    'Root'         => Category::getTopCategory(),
                    'selected'     => $this->l('Selected'),
                    'Collapse All' => $this->l('Collapse All'),
                    'Expand All'   => $this->l('Expand All'),
                    'Check All'    => $this->l('Check All'),
                    'Uncheck All'  => $this->l('Uncheck All'),
                ],
                'selected_cat'        => $selectedCategories,
                'input_name'          => 'categoryBox[]',
                'use_radio'           => false,
                'use_search'          => false,
                'disabled_categories' => [],
                'top_category'        => Category::getTopCategory(),
                'use_context'         => true,
            ],
        ];

        $fieldsForm1 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Add new criterion'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type' => 'hidden',
                        'name' => 'id_product_comment_criterion',
                    ],
                    [
                        'type'  => 'text',
                        'lang'  => true,
                        'label' => $this->l('Criterion name'),
                        'name'  => 'name',
                    ],
                    [
                        'type'    => 'select',
                        'name'    => 'id_product_comment_criterion_type',
                        'label'   => $this->l('Application scope of the criterion'),
                        'options' => [
                            'query' => $query,
                            'id'    => 'id',
                            'name'  => 'label',
                        ],
                    ],
                    $fieldCategoryTree,
                    [
                        'type'   => 'products',
                        'label'  => $this->l('The criterion will be restricted to the following products'),
                        'name'   => 'ids_product',
                        'values' => $productTableValues,
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Active'),
                        'name'    => 'active',
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitEditCriterion',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->name;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEditCriterion';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getCriterionFieldsValues($idCriterion),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm1]);
    }

    /**
     * @param int|null $idRoot
     * @param int      $idCriterion
     *
     * @return string
     */
    public function initCategoriesAssociation($idRoot = null, $idCriterion = 0)
    {
        if (is_null($idRoot)) {
            $idRoot = Configuration::get('PS_ROOT_CATEGORY');
        }
        $idShop = (int) Tools::getValue('id_shop');
        $shop = new Shop($idShop);
        if ($idCriterion == 0) {
            $selectedCat = [];
        } else {
            $pdcObject = new ProductCommentCriterion($idCriterion);
            $selectedCat = $pdcObject->getCategories();
        }

        if (Shop::getContext() == Shop::CONTEXT_SHOP && Tools::isSubmit('id_shop')) {
            $rootCategory = new Category($shop->id_category);
        } else {
            $rootCategory = new Category($idRoot);
        }
        $rootCategory = ['id_category' => $rootCategory->id, 'name' => $rootCategory->name[$this->context->language->id]];

        $helper = new Helper();

        return $helper->renderCategoryTree($rootCategory, $selectedCat, 'categoryBox', false, true);
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getCriterionFieldsValues($id = 0)
    {
        $criterion = new ProductCommentCriterion($id);

        return [
            'name'                              => $criterion->name,
            'id_product_comment_criterion_type' => $criterion->id_product_comment_criterion_type,
            'active'                            => $criterion->active,
            'id_product_comment_criterion'      => $criterion->id,
        ];
    }

    /**
     * Post process
     */
    protected function postProcess()
    {
        $this->setFilters();

        if (Tools::isSubmit('submitModerate')) {
            Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', (int) Tools::getValue('PRODUCT_COMMENTS_MODERATE'));
            Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', (int) Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS'));
            Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', (int) Tools::getValue('PRODUCT_COMMENTS_MINIMAL_TIME'));
            $this->moduleHtml .= '<div class="conf confirm alert alert-success">'.$this->l('Settings updated').'</div>';
        } elseif (Tools::isSubmit('productcomments')) {
            $idProductComment = (int) Tools::getValue('id_product_comment');
            $comment = new ProductComment($idProductComment);
            $comment->validate();
            ProductComment::deleteReports($idProductComment);
        } elseif (Tools::isSubmit('deleteproductcomments')) {
            $idProductComment = (int) Tools::getValue('id_product_comment');
            $comment = new ProductComment($idProductComment);
            $comment->delete();
        } elseif (Tools::isSubmit('submitEditCriterion')) {
            $criterion = new ProductCommentCriterion((int) Tools::getValue('id_product_comment_criterion'));
            $criterion->id_product_comment_criterion_type = Tools::getValue('id_product_comment_criterion_type');
            $criterion->active = Tools::getValue('active');

            $languages = Language::getLanguages();
            $name = [];
            foreach ($languages as $key => $value) {
                $name[$value['id_lang']] = Tools::getValue('name_'.$value['id_lang']);
            }
            $criterion->name = $name;

            $criterion->save();

            // Clear before reinserting data
            $criterion->deleteCategories();
            $criterion->deleteProducts();
            if ($criterion->id_product_comment_criterion_type == 2) {
                if ($categories = Tools::getValue('categoryBox')) {
                    if (count($categories)) {
                        foreach ($categories as $idCategory) {
                            $criterion->addCategory((int) $idCategory);
                        }
                    }
                }
            } elseif ($criterion->id_product_comment_criterion_type == 3) {
                if ($products = Tools::getValue('ids_product')) {
                    if (count($products)) {
                        foreach ($products as $product) {
                            $criterion->addProduct((int) $product);
                        }
                    }
                }
            }
            if ($criterion->save()) {
                Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminModules').'&configure='.$this->name.'&conf=4');
            } else {
                $this->moduleHtml .= '<div class="conf confirm alert alert-danger">'.$this->l('The criterion could not be saved').'</div>';
            }
        } elseif (Tools::isSubmit('deleteproductcommentscriterion')) {
            $productCommentCriterion = new ProductCommentCriterion((int) Tools::getValue('id_product_comment_criterion'));
            if ($productCommentCriterion->id) {
                if ($productCommentCriterion->delete()) {
                    $this->moduleHtml .= '<div class="conf confirm alert alert-success">'.$this->l('Criterion deleted').'</div>';
                }
            }
        } elseif (Tools::isSubmit('statusproductcommentscriterion')) {
            $criterion = new ProductCommentCriterion((int) Tools::getValue('id_product_comment_criterion'));
            if ($criterion->id) {
                $criterion->active = (int) (!$criterion->active);
                $criterion->save();
            }
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules').'&configure='.$this->name.'&tab_module='.$this->tab.'&conf=4&module_name='.$this->name);
        } elseif ($idProductComment = (int) Tools::getValue('approveComment')) {
            $comment = new ProductComment($idProductComment);
            $comment->validate();
        } elseif ($idProductComment = (int) Tools::getValue('noabuseComment')) {
            ProductComment::deleteReports($idProductComment);
        }

        $this->_clearcache('productcomments_reviews.tpl');
    }

    /**
     * @return string
     */
    public function renderConfigForm()
    {
        $firstFieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('All reviews must be validated by an employee'),
                        'name'    => 'PRODUCT_COMMENTS_MODERATE',
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Allow guest reviews'),
                        'name'    => 'PRODUCT_COMMENTS_ALLOW_GUESTS',
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Minimum time between 2 reviews from the same user'),
                        'name'   => 'PRODUCT_COMMENTS_MINIMAL_TIME',
                        'class'  => 'fixed-width-xs',
                        'suffix' => 'seconds',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitModerate',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->name;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducCommentsConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$firstFieldsForm]);
    }

    /**
     * @return array
     */
    public function getConfigFieldsValues()
    {
        return [
            'PRODUCT_COMMENTS_MODERATE'     => Tools::getValue('PRODUCT_COMMENTS_MODERATE', Configuration::get('PRODUCT_COMMENTS_MODERATE')),
            'PRODUCT_COMMENTS_ALLOW_GUESTS' => Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS', Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')),
            'PRODUCT_COMMENTS_MINIMAL_TIME' => Tools::getValue('PRODUCT_COMMENTS_MINIMAL_TIME', Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME')),
        ];
    }

    /**
     * @return null|string
     */
    public function renderModerateLists()
    {
        $return = null;

        if (Configuration::get('PRODUCT_COMMENTS_MODERATE')) {
            $comments = ProductComment::getByValidate(0, false);

            $fieldsList = $this->getStandardFieldList();

            if (version_compare(_PS_VERSION_, '1.6', '<')) {
                $return .= '<h1>'.$this->l('Reviews waiting for approval').'</h1>';
                $actions = ['enable', 'delete'];
            } else {
                $actions = ['approve', 'delete'];
            }

            $helper = new HelperList();
            $helper->shopLinkType = '';
            $helper->simple_header = true;
            $helper->actions = $actions;
            $helper->show_toolbar = false;
            $helper->module = $this;
            $helper->listTotal = count($comments);
            $helper->identifier = 'id_product_comment';
            $helper->title = $this->l('Reviews waiting for approval');
            $helper->table = $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
            //$helper->tpl_vars = array('priority' => array($this->l('High'), $this->l('Medium'), $this->l('Low')));

            $return .= $helper->generateList($comments, $fieldsList);
        }

        $comments = ProductComment::getReportedComments();

        $fieldsList = $this->getStandardFieldList();

        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $return .= '<h1>'.$this->l('Reported Reviews').'</h1>';
            $actions = ['enable', 'delete'];
        } else {
            $actions = ['delete', 'noabuse'];
        }

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->actions = $actions;
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->listTotal = count($comments);
        $helper->identifier = 'id_product_comment';
        $helper->title = $this->l('Reported Reviews');
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        //$helper->tpl_vars = array('priority' => array($this->l('High'), $this->l('Medium'), $this->l('Low')));

        $return .= $helper->generateList($comments, $fieldsList);

        return $return;
    }

    /**
     * @return array
     */
    public function getStandardFieldList()
    {
        return [
            'id_product_comment' => [
                'title' => $this->l('ID'),
                'type'  => 'text',
            ],
            'title'              => [
                'title' => $this->l('Review title'),
                'type'  => 'text',
            ],
            'content'            => [
                'title' => $this->l('Review'),
                'type'  => 'text',
            ],
            'grade'              => [
                'title'  => $this->l('Rating'),
                'type'   => 'text',
                'suffix' => '/5',
            ],
            'customer_name'      => [
                'title' => $this->l('Author'),
                'type'  => 'text',
            ],
            'name'               => [
                'title' => $this->l('Product'),
                'type'  => 'text',
            ],
            'date_add'           => [
                'title' => $this->l('Time of publication'),
                'type'  => 'date',
            ],
        ];
    }

    /**
     * @return string
     */
    public function renderCriterionList()
    {
        $criterions = ProductCommentCriterion::getCriterions($this->context->language->id, false, false);

        $fieldsList = [
            'id_product_comment_criterion' => [
                'title' => $this->l('ID'),
                'type'  => 'text',
            ],
            'name'                         => [
                'title' => $this->l('Name'),
                'type'  => 'text',
            ],
            'type_name'                    => [
                'title' => $this->l('Type'),
                'type'  => 'text',
            ],
            'active'                       => [
                'title'  => $this->l('Status'),
                'active' => 'status',
                'type'   => 'bool',
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;
        $helper->toolbar_btn['new'] = [
            'href' => $this->context->link->getAdminLink('AdminModules').'&configure='.$this->name.'&module_name='.$this->name.'&updateproductcommentscriterion',
            'desc' => $this->l('Add New Criterion', null),
        ];
        $helper->module = $this;
        $helper->identifier = 'id_product_comment_criterion';
        $helper->title = $this->l('Review Criteria');
        $helper->table = $this->name.'criterion';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        //$helper->tpl_vars = array('priority' => array($this->l('High'), $this->l('Medium'), $this->l('Low')));

        return $helper->generateList($criterions, $fieldsList);
    }

    /**
     * @return string
     */
    public function renderCommentsList()
    {
        $comments = ProductComment::getByValidate(1, false);
        $moderate = Configuration::get('PRODUCT_COMMENTS_MODERATE');
        if (empty($moderate)) {
            $comments = array_merge($comments, ProductComment::getByValidate(0, false));
        }

        $fieldsList = $this->getStandardFieldList();

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->actions = ['delete'];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->listTotal = count($comments);
        $helper->identifier = 'id_product_comment';
        $helper->title = $this->l('Approved Reviews');
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        //$helper->tpl_vars = array('priority' => array($this->l('High'), $this->l('Medium'), $this->l('Low')));

        return $helper->generateList($comments, $fieldsList);
    }

    /**
     * Set module base url
     */
    protected function setBaseUrl()
    {
        $this->baseUrl = 'index.php?';
        foreach ($_GET as $k => $value) {
            if (!in_array($k, ['deleteCriterion', 'editCriterion'])) {
                $this->baseUrl .= $k.'='.$value.'&';
            }
        }
        $this->baseUrl = rtrim($this->baseUrl, '&');
    }

    /**
     * @param string   $token
     * @param int      $id
     * @param int|null $name
     *
     * @return string
     */
    public function displayApproveLink($token, $id, $name = null)
    {
        $this->smarty->assign(
            [
                'href'   => $this->context->link->getAdminLink('AdminModules').'&configure='.$this->name.'&module_name='.$this->name.'&approveComment='.$id,
                'action' => $this->l('Approve'),
            ]
        );

        return $this->display(__FILE__, 'views/templates/admin/list_action_approve.tpl');
    }

    /**
     * @param mixed $token
     * @param mixed $id
     * @param mixed $name
     *
     * @return string
     */
    public function displayNoabuseLink($token, $id, $name = null)
    {
        $this->smarty->assign(
            [
                'href'   => $this->context->link->getAdminLink('AdminModules').'&configure='.$this->name.'&module_name='.$this->name.'&noabuseComment='.$id,
                'action' => $this->l('Not abusive'),
            ]
        );

        return $this->display(__FILE__, 'views/templates/admin/list_action_noabuse.tpl');
    }

    /**
     * @return string
     */
    public function hookProductTab()
    {
        $average = ProductComment::getAverageGrade((int) Tools::getValue('id_product'));

        $this->context->smarty->assign(
            [
                'allow_guests' => (int) Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS'),
                'comments'     => ProductComment::getByProduct((int) (Tools::getValue('id_product'))),
                'criterions'   => ProductCommentCriterion::getByProduct((int) (Tools::getValue('id_product')), $this->context->language->id),
                'averageTotal' => round($average['grade']),
                'nbComments'   => (int) (ProductComment::getCommentNumber((int) (Tools::getValue('id_product')))),
            ]
        );

        return $this->display(__FILE__, 'tab.tpl');
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayProductListReviews($params)
    {
        $idProduct = (int) $params['product']['id_product'];
        if (!$this->isCached('productcomments_reviews.tpl', $this->getCacheId($idProduct))) {
            $average = ProductComment::getAverageGrade($idProduct);
            $this->smarty->assign(
                [
                    'product'      => $params['product'],
                    'averageTotal' => round($average['grade']),
                    'ratings'      => ProductComment::getRatings($idProduct),
                    'nbComments'   => (int) ProductComment::getCommentNumber($idProduct),
                ]
            );
        }

        return $this->display(__FILE__, 'productcomments_reviews.tpl', $this->getCacheId($idProduct));
    }

    /**
     * @param int|null $idProduct
     *
     * @return string
     */
    public function getCacheId($idProduct = null)
    {
        return parent::getCacheId().'|'.(int) $idProduct.'|'.(int) $this->context->language->id.'|'.(int) $this->context->shop->id;
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayLeftColumnProduct($params)
    {
        return $this->hookDisplayRightColumnProduct($params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayRightColumnProduct($params)
    {
        $idGuest = (!$id_customer = (int) $this->context->cookie->id_customer) ? (int) $this->context->cookie->id_guest : false;
        $customerComment = ProductComment::getByCustomer((int) (Tools::getValue('id_product')), (int) $this->context->cookie->id_customer, true, (int) $idGuest);

        $average = ProductComment::getAverageGrade((int) Tools::getValue('id_product'));
        /** @var ProductController $controller */
        $controller = $this->context->controller;
        $product = $controller->getProduct();
        $image = Product::getCover((int) Tools::getValue('id_product'));
        $coverImage = $this->context->link->getImageLink($product->link_rewrite, $image['id_image'], 'medium_default');

        $this->context->smarty->assign(
            [
                'id_product_comment_form'    => (int) Tools::getValue('id_product'),
                'product'                    => $product,
                'secure_key'                 => $this->secure_key,
                'logged'                     => $this->context->customer->isLogged(true),
                'allow_guests'               => (int) Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS'),
                'productcomment_cover'       => (int) Tools::getValue('id_product').'-'.(int) $image['id_image'], // retro compat
                'productcomment_cover_image' => $coverImage,
                'mediumSize'                 => Image::getSize(ImageType::getFormatedName('medium')),
                'criterions'                 => ProductCommentCriterion::getByProduct((int) Tools::getValue('id_product'), $this->context->language->id),
                'action_url'                 => '',
                'averageTotal'               => round($average['grade']),
                'ratings'                    => ProductComment::getRatings((int) Tools::getValue('id_product')),
                'too_early'                  => ($customerComment && ((int) strtotime($customerComment['date_add']) + (int) Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME')) > time()),
                'nbComments'                 => (int) (ProductComment::getCommentNumber((int) Tools::getValue('id_product'))),
            ]
        );

        return $this->display(__FILE__, 'productcomments-extra.tpl');
    }

    /**
     * @return string
     */
    public function hookProductTabContent()
    {
        $this->context->controller->addJS($this->_path.'views/js/jquery.rating.pack.js');
        $this->context->controller->addJS($this->_path.'views/js/jquery.textareaCounter.plugin.js');
        $this->context->controller->addJS($this->_path.'views/js/productcomments.js');

        $idGuest = (!$idCustomer = (int) $this->context->cookie->id_customer) ? (int) $this->context->cookie->id_guest : false;
        $customerComment = ProductComment::getByCustomer((int) (Tools::getValue('id_product')), (int) $this->context->cookie->id_customer, true, (int) $idGuest);

        $averages = ProductComment::getAveragesByProduct((int) Tools::getValue('id_product'), $this->context->language->id);
        $averageTotal = 0;
        foreach ($averages as $average) {
            $averageTotal += (float) ($average);
        }
        $averageTotal = count($averages) ? ($averageTotal / count($averages)) : 0;

        /** @var ProductController $controller */
        $controller = $this->context->controller;
        $product = $controller->getProduct();
        $image = Product::getCover((int) Tools::getValue('id_product'));
        $coverImage = $this->context->link->getImageLink($product->link_rewrite, $image['id_image'], 'medium_default');

        $this->context->smarty->assign(
            [
                'logged'                                  => $this->context->customer->isLogged(true),
                'action_url'                              => '',
                'product'                                 => $product,
                'comments'                                => ProductComment::getByProduct((int) Tools::getValue('id_product'), 1, null, $this->context->cookie->id_customer),
                'criterions'                              => ProductCommentCriterion::getByProduct((int) Tools::getValue('id_product'), $this->context->language->id),
                'averages'                                => $averages,
                'product_comment_path'                    => $this->_path,
                'averageTotal'                            => $averageTotal,
                'allow_guests'                            => (int) Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS'),
                'too_early'                               => ($customerComment && ((int) strtotime($customerComment['date_add']) + (int) Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME')) > time()),
                'delay'                                   => Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME'),
                'id_product_comment_form'                 => (int) Tools::getValue('id_product'),
                'secure_key'                              => $this->secure_key,
                'productcomment_cover'                    => (int) Tools::getValue('id_product').'-'.(int) $image['id_image'],
                'productcomment_cover_image'              => $coverImage,
                'mediumSize'                              => Image::getSize(ImageType::getFormatedName('medium')),
                'nbComments'                              => (int) ProductComment::getCommentNumber((int) Tools::getValue('id_product')),
                'productcomments_controller_url'          => $this->context->link->getModuleLink('productcomments', 'default', [], true),
                'productcomments_url_rewriting_activated' => Configuration::get('PS_REWRITING_SETTINGS', 0),
                'moderation_active'                       => (int) Configuration::get('PRODUCT_COMMENTS_MODERATE'),
            ]
        );

        $this->context->controller->pagination((int) ProductComment::getCommentNumber((int) Tools::getValue('id_product')));

        Media::addJsDef([
            'productcomments_controller_url' => htmlspecialchars($this->context->link->getModuleLink('productcomments', 'default', [], true), ENT_QUOTES, 'UTF-8'),
            'confirm_report_message' => htmlspecialchars($this->l('Are you sure that you want to report this comment?'), ENT_QUOTES, 'UTF-8'),
            'secure_key' => htmlspecialchars($this->secure_key, ENT_QUOTES, 'UTF-8'),
            'productcomments_url_rewrite' => (bool) Configuration::get('PS_REWRITING_SETTINGS', 0),
            'productcomment_added' => htmlspecialchars($this->l('Your comment has been added!'), ENT_QUOTES, 'UTF-8'),
            'productcomment_added_moderation' => htmlspecialchars($this->l('Your comment has been submitted and will be available once approved by a moderator.'), ENT_QUOTES, 'UTF-8'),
            'productcomment_title' => htmlspecialchars($this->l('New comment'), ENT_QUOTES, 'UTF-8'),
            'productcomment_ok' => htmlspecialchars($this->l('OK'), ENT_QUOTES, 'UTF-8'),
            'moderation_active' => (int) Configuration::get('PRODUCT_COMMENTS_MODERATE'),
        ]);

        return $this->display(__FILE__, 'productcomments.tpl');
    }

    /**
     *
     */
    public function hookHeader()
    {
        if (file_exists(_PS_THEME_DIR_."css/modules/{$this->name}/{$this->name}.css")) {
            $this->context->controller->addCSS(_PS_THEME_DIR_."css/modules/{$this->name}/{$this->name}.css", 'all');
        } else {
            $this->context->controller->addCSS($this->_path.'views/css/productcomments.css', 'all');
        }

        $this->page_name = Dispatcher::getInstance()->getController();
        if (in_array($this->page_name, ['product', 'productscomparison'])) {
            $this->context->controller->addJS($this->_path.'views/js/jquery.rating.pack.js');
            if (in_array($this->page_name, ['productscomparison'])) {
                $this->context->controller->addjqueryPlugin('cluetip');
                $this->context->controller->addJS($this->_path.'views/js/products-comparison.js');
            }
        }
    }

    /**
     * @param array $params
     *
     * @return bool|string
     */
    public function hookExtraProductComparison($params)
    {
        $listGrades = [];
        $listProductGrades = [];
        $listProductAverage = [];
        $listProductComment = [];

        foreach ($params['list_ids_product'] as $idProduct) {
            $idProduct = (int) $idProduct;
            $grades = ProductComment::getAveragesByProduct($idProduct, $this->context->language->id);
            $criterions = ProductCommentCriterion::getByProduct($idProduct, $this->context->language->id);
            $gradeTotal = 0;
            if (count($grades) > 0) {
                foreach ($criterions as $criterion) {
                    if (isset($grades[$criterion['id_product_comment_criterion']])) {
                        $listProductGrades[$criterion['id_product_comment_criterion']][$idProduct] = $grades[$criterion['id_product_comment_criterion']];
                        $gradeTotal += (float) ($grades[$criterion['id_product_comment_criterion']]);
                    } else {
                        $listProductGrades[$criterion['id_product_comment_criterion']][$idProduct] = 0;
                    }

                    if (!array_key_exists($criterion['id_product_comment_criterion'], $listGrades)) {
                        $listGrades[$criterion['id_product_comment_criterion']] = $criterion['name'];
                    }
                }

                $listProductAverage[$idProduct] = $gradeTotal / count($criterions);
                $listProductComment[$idProduct] = ProductComment::getByProduct($idProduct, 0, 3);
            }
        }

        if (count($listGrades) < 1) {
            return false;
        }

        $this->context->smarty->assign(
            [
                'grades'               => $listGrades,
                'product_grades'       => $listProductGrades,
                'list_ids_product'     => $params['list_ids_product'],
                'list_product_average' => $listProductAverage,
                'product_comments'     => $listProductComment,
            ]
        );

        return $this->display(__FILE__, 'products-comparison.tpl');
    }

    /**
     * Check delete comment
     */
    protected function checkDeleteComment()
    {
        $action = Tools::getValue('delete_action');
        if (empty($action) === false) {
            $productComments = Tools::getValue('delete_id_product_comment');

            if (count($productComments)) {
                if ($action == 'delete') {
                    foreach ($productComments as $idProductComment) {
                        if (!$idProductComment) {
                            continue;
                        }
                        $comment = new ProductComment((int) $idProductComment);
                        $comment->delete();
                        ProductComment::deleteGrades((int) $idProductComment);
                    }
                }
            }
        }
    }
}
