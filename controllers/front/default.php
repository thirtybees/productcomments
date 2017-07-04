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

/**
 * Class ProductCommentsDefaultModuleFrontController
 */
class ProductCommentsDefaultModuleFrontController extends ModuleFrontController
{
    /** @var ProductComments $module */
    public $module;

    /**
     * ProductCommentsDefaultModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->context = Context::getContext();
    }

    /**
     * Init controller content
     */
    public function initContent()
    {
        parent::initContent();

        if (Tools::isSubmit('action')) {
            switch (Tools::getValue('action')) {
                case 'add_comment':
                    $this->ajaxProcessAddComment();
                    break;
                case 'report_abuse':
                    $this->ajaxProcessReportAbuse();
                    break;
                case 'comment_is_usefull':
                    $this->ajaxProcessCommentIsUsefull();
                    break;
            }
        }
    }

    /**
     * Add comment
     */
    protected function ajaxProcessAddComment()
    {
        $moduleInstance = $this->module;

        $idGuest = 0;
        $idCustomer = $this->context->customer->id;
        if (!$idCustomer) {
            $idGuest = $this->context->cookie->id_guest;
        }

        $errors = [];
        // Validation
        if (!Validate::isInt(Tools::getValue('id_product'))) {
            $errors[] = $moduleInstance->l('Product ID is incorrect', 'default');
        }
        if (!Tools::getValue('title') || !Validate::isGenericName(Tools::getValue('title'))) {
            $errors[] = $moduleInstance->l('Title is incorrect', 'default');
        }
        if (!Tools::getValue('content') || !Validate::isMessage(Tools::getValue('content'))) {
            $errors[] = $moduleInstance->l('Comment is incorrect', 'default');
        }
        if (!$idCustomer && (!Tools::isSubmit('customer_name') || !Tools::getValue('customer_name') || !Validate::isGenericName(Tools::getValue('customer_name')))) {
            $errors[] = $moduleInstance->l('Customer name is incorrect', 'default');
        }
        if (!$this->context->customer->id && !Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')) {
            $errors[] = $moduleInstance->l('You must be connected in order to send a comment', 'default');
        }
        if (!count(Tools::getValue('criterion'))) {
            $errors[] = $moduleInstance->l('You must give a rating', 'default');
        }

        $product = new Product(Tools::getValue('id_product'));
        if (!$product->id) {
            $errors[] = $moduleInstance->l('Product not found', 'default');
        }

        if (!count($errors)) {
            $customerComment = ProductComment::getByCustomer(Tools::getValue('id_product'), $idCustomer, true, $idGuest);
            if (!$customerComment || ($customerComment && (strtotime($customerComment['date_add']) + (int) Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME')) < time())) {
                $comment = new ProductComment();
                $comment->content = strip_tags(Tools::getValue('content'));
                $comment->id_product = (int) Tools::getValue('id_product');
                $comment->id_customer = (int) $idCustomer;
                $comment->id_guest = $idGuest;
                $comment->customer_name = Tools::getValue('customer_name');
                if (!$comment->customer_name) {
                    $comment->customer_name = pSQL($this->context->customer->firstname.' '.$this->context->customer->lastname);
                }
                $comment->title = Tools::getValue('title');
                $comment->grade = 0;
                $comment->validate = 0;
                $comment->save();

                $gradeSum = 0;
                foreach (Tools::getValue('criterion') as $idProductCommentCriterion => $grade) {
                    $gradeSum += $grade;
                    $productCommentCriterion = new ProductCommentCriterion($idProductCommentCriterion);
                    if ($productCommentCriterion->id) {
                        $productCommentCriterion->addGrade($comment->id, $grade);
                    }
                }

                if (count(Tools::getValue('criterion')) >= 1) {
                    $comment->grade = $gradeSum / count(Tools::getValue('criterion'));
                    // Update Grade average of comment
                    $comment->save();
                }
                $result = true;
                Tools::clearCache(Context::getContext()->smarty, $this->getTemplatePath('productcomments-reviews.tpl'));
            } else {
                $result = false;
                $errors[] = $moduleInstance->l('Please wait before posting another comment', 'default').' '.Configuration::get('PRODUCT_COMMENTS_MINIMAL_TIME').' '.$moduleInstance->l('seconds before posting a new comment', 'default');
            }
        } else {
            $result = false;
        }

        die(
        json_encode(
            [
                'result' => $result,
                'errors' => $errors,
            ]
        )
        );
    }

    protected function ajaxProcessReportAbuse()
    {
        if (!Tools::isSubmit('id_product_comment')) {
            die('0');
        }

        if (ProductComment::isAlreadyReport(Tools::getValue('id_product_comment'), $this->context->cookie->id_customer)) {
            die('0');
        }

        if (ProductComment::reportComment((int) Tools::getValue('id_product_comment'), $this->context->cookie->id_customer)) {
            die('1');
        }

        die('0');
    }

    protected function ajaxProcessCommentIsUsefull()
    {
        if (!Tools::isSubmit('id_product_comment') || !Tools::isSubmit('value')) {
            die('0');
        }

        if (ProductComment::isAlreadyUsefulness(Tools::getValue('id_product_comment'), $this->context->cookie->id_customer)) {
            die('0');
        }

        if (ProductComment::setCommentUsefulness((int) Tools::getValue('id_product_comment'), (bool) Tools::getValue('value'), $this->context->cookie->id_customer)) {
            die('1');
        }

        die('0');
    }
}
