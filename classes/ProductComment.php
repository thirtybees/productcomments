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

namespace ProductCommentsModule;

use Cache;
use Configuration;
use Context;
use Db;
use DbQuery;
use Hook;
use Shop;
use Validate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class ProductComment
 */
class ProductComment extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'product_comment',
        'primary' => 'id_product_comment',
        'fields'  => [
            'id_product'    => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_customer'   => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_guest'      => ['type' => self::TYPE_INT],
            'customer_name' => ['type' => self::TYPE_STRING],
            'title'         => ['type' => self::TYPE_STRING],
            'content'       => ['type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 65535, 'required' => true],
            'grade'         => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'validate'      => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'deleted'       => ['type' => self::TYPE_BOOL],
            'date_add'      => ['type' => self::TYPE_DATE],
        ],
    ];
    public $id;
    /** @var integer Product's id */
    public $id_product;
    /** @var integer Customer's id */
    public $id_customer;
    /** @var integer Guest's id */
    public $id_guest;
    /** @var integer Customer name */
    public $customer_name;
    /** @var string Title */
    public $title;
    /** @var string Content */
    public $content;
    /** @var integer Grade */
    public $grade;
    /** @var boolean Validate */
    public $validate = 0;
    public $deleted = 0;
    /** @var string Object creation date */
    public $date_add;
    // @codingStandardsIgnoreEnd

    /**
     * Get comments by IdProduct
     *
     * @param int|     $idProduct
     * @param int|int  $p
     * @param int|null $n
     * @param int|null $idCustomer
     *
     * @return false|array Comments
     */
    public static function getByProduct($idProduct, $p = 1, $n = null, $idCustomer = null)
    {
        if (!\Validate::isUnsignedId($idProduct)) {
            return false;
        }
        $validate = \Configuration::get('PRODUCT_COMMENTS_MODERATE');
        $p = (int) $p;
        $n = (int) $n;
        if ($p <= 1) {
            $p = 1;
        }
        if ($n != null && $n <= 0) {
            $n = 5;
        }

        $cacheId = 'ProductComment::getByProduct_'.(int) $idProduct.'-'.(int) $p.'-'.(int) $n.'-'.(int) $idCustomer.'-'.(bool) $validate;
        if (!Cache::isStored($cacheId)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('pc.`id_product_comment`')
                    ->select('(SELECT count(*) FROM `'._DB_PREFIX_.'product_comment_usefulness` pcu WHERE pcu.`id_product_comment` = pc.`id_product_comment` AND pcu.`usefulness` = 1) AS `total_useful`')
                    ->select('(SELECT count(*) FROM `'._DB_PREFIX_.'product_comment_usefulness` pcu WHERE pcu.`id_product_comment` = pc.`id_product_comment`) AS `total_advice`')
                    ->select((int) $idCustomer ? '(SELECT count(*) FROM `'._DB_PREFIX_.'product_comment_usefulness` pcuc WHERE pcuc.`id_product_comment` = pc.`id_product_comment` AND pcuc.id_customer = '.(int) $idCustomer.') AS `customer_advice`' : '')
                    ->select((int) $idCustomer ? '(SELECT count(*) FROM `'._DB_PREFIX_.'product_comment_report` pcrc WHERE pcrc.`id_product_comment` = pc.`id_product_comment` AND pcrc.id_customer = '.(int) $idCustomer.') AS `customer_report`' : '')
                    ->select('pc.`customer_name` AS `customer_name`')
                    ->select('pc.`content`, pc.`grade`, pc.`date_add`, pc.`title`')
                    ->from(bqSQL(static::$definition['table']), 'pc')
                    ->where('pc.`id_product` = '.(int) $idProduct)
                    ->where($validate ? 'pc.`validate` = 1' : '')
                    ->orderBy('pc.`date_add` DESC')
                    ->limit((int) $n ?: 0, $n ? (int) (($p - 1) * $n) : 0)
            );
            Cache::store($cacheId, $result);
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Return customer's comment
     *
     * @param int      $idProduct
     * @param int      $idCustomer
     * @param bool     $getLast
     * @param int|bool $idGuest
     *
     * @return array Comments
     */
    public static function getByCustomer($idProduct, $idCustomer, $getLast = false, $idGuest = false)
    {
        $cacheId = 'ProductComment::getByCustomer_'.(int) $idProduct.'-'.(int) $idCustomer.'-'.(bool) $getLast.'-'.(int) $idGuest;
        if (!Cache::isStored($cacheId)) {
            $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('*')
                    ->from(bqSQL(static::$definition['table']), 'pc')
                    ->where('pc.`id_product` = '.(int) $idProduct)
                    ->where($idGuest ? 'pc.`id_guest` = '.(int) $idGuest : 'pc.`id_customer` = '.(int) $idCustomer)
                    ->orderBy('pc.`date_add` DESC')
                    ->limit($getLast ? 1 : 0)
            );

            if ($getLast && count($results)) {
                $results = array_shift($results);
            }

            Cache::store($cacheId, $results);
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * @param int $idProduct
     *
     * @return array|bool|null|object
     */
    public static function getRatings($idProduct)
    {
        $validate = Configuration::get('PRODUCT_COMMENTS_MODERATE');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new DbQuery())
                ->select('(SUM(pc.`grade`) / COUNT(pc.`grade`)) AS `avg`')
                ->select('MIN(pc.`grade`) AS `min`')
                ->select('MAX(pc.`grade`) AS `max`')
                ->from(bqSQL(static::$definition['table']), 'pc')
                ->where('pc.`id_product` = '.(int) $idProduct)
                ->where('pc.`deleted`= 0')
                ->where($validate ? 'pc.`validate` = 1' : '')
        );

    }

    /**
     * @param int $idProduct
     *
     * @return array|bool|null|object
     */
    public static function getAverageGrade($idProduct)
    {
        $validate = Configuration::get('PRODUCT_COMMENTS_MODERATE');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new DbQuery())
                ->select('(SUM(pc.`grade`) / COUNT(pc.`grade`)) AS `grade`')
                ->from(bqSQL(static::$definition['table']), 'pc')
                ->where('pc.`id_product` = '.(int) $idProduct)
                ->where('pc.`deleted` = 0')
                ->where($validate ? 'pc.`validate` = 1' : '')
        );
    }

    /**
     * @param int $idProduct
     * @param int $idLang
     *
     * @return array
     */
    public static function getAveragesByProduct($idProduct, $idLang)
    {
        /* Get all grades */
        $grades = static::getGradeByProduct((int) $idProduct, (int) $idLang);
        $total = static::getGradedCommentNumber((int) $idProduct);
        if (!count($grades) || (!$total)) {
            return [];
        }

        /* Addition grades for each criterion */
        $criterionsGradeTotal = [];
        $countGrades = count($grades);
        for ($i = 0; $i < $countGrades; ++$i) {
            if (array_key_exists($grades[$i]['id_product_comment_criterion'], $criterionsGradeTotal) === false) {
                $criterionsGradeTotal[$grades[$i]['id_product_comment_criterion']] = (int) ($grades[$i]['grade']);
            } else {
                $criterionsGradeTotal[$grades[$i]['id_product_comment_criterion']] += (int) ($grades[$i]['grade']);
            }
        }

        /* Finally compute the averages */
        $averages = [];
        foreach ($criterionsGradeTotal as $key => $criterionGradeTotal) {
            $averages[(int) ($key)] = (int) ($total) ? ((int) ($criterionGradeTotal) / (int) ($total)) : 0;
        }

        return $averages;
    }

    /**
     * Get Grade By product
     *
     * @param int $idProduct
     * @param int $idLang
     *
     * @return false|array Grades
     */
    public static function getGradeByProduct($idProduct, $idLang)
    {
        if (!Validate::isUnsignedId($idProduct) ||
            !Validate::isUnsignedId($idLang)
        ) {
            return false;
        }
        $validate = Configuration::get('PRODUCT_COMMENTS_MODERATE');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('pc.`'.bqSQL(static::$definition['primary']).'`, pcg.`grade`, pccl.`name`, pcc.`'.bqSQL(ProductCommentCriterion::$definition['primary']).'`')
                ->from(bqSQL(static::$definition['table']), 'pc')
                ->leftJoin('product_comment_grade', 'pcg', 'pcg.`'.bqSQL(static::$definition['primary']).'` = pc.`'.bqSQL(static::$definition['primary']).'`')
                ->leftJoin(
                    bqSQL(ProductCommentCriterion::$definition['table']),
                    'pcc',
                    'pcc.`'.bqSQL(ProductCommentCriterion::$definition['primary']).'` = pcg.`'.bqSQL(ProductCommentCriterion::$definition['primary']).'`'
                )
                ->leftJoin(
                    bqSQL(ProductCommentCriterion::$definition['table'].'_lang'),
                    'pccl',
                    'pccl.`'.bqSQL(ProductCommentCriterion::$definition['primary']).'` = pcg.`'.bqSQL(ProductCommentCriterion::$definition['primary']).'` AND pccl.`id_lang` = '.(int) $idLang
                )
                ->where('pc.`id_product` = '.(int) $idProduct)
                ->where($validate ? 'pc.`validate` = 1' : '')
        );
    }

    /**
     * Return number of comments and average grade by products
     *
     * @param int $idProduct
     *
     * @return false|int Info
     */
    public static function getGradedCommentNumber($idProduct)
    {
        if (!Validate::isUnsignedId($idProduct)) {
            return false;
        }
        $validate = (int) \Configuration::get('PRODUCT_COMMENTS_MODERATE');

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new DbQuery())
                ->select('COUNT(pc.`id_product`) AS `nbr`')
                ->from('product_comment', 'pc')
                ->where('`id_product` = '.(int) $idProduct)
                ->where($validate ? '`validate` = 1' : '')
                ->where('`grade` > 0')
        );

        return (int) ($result['nbr']);
    }

    /**
     * Return number of comments and average grade by products
     *
     * @param int $idProduct
     *
     * @return false|array Info
     */
    public static function getCommentNumber($idProduct)
    {
        if (!Validate::isUnsignedId($idProduct)) {
            return false;
        }
        $validate = (int) Configuration::get('PRODUCT_COMMENTS_MODERATE');
        $cacheId = 'ProductComment::getCommentNumber_'.(int) $idProduct.'-'.$validate;
        if (!Cache::isStored($cacheId)) {
            $result = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('COUNT(`'.bqSQL(static::$definition['primary']).'`) AS `nbr`')
                    ->from(bqSQL(static::$definition['table']), 'pc')
                    ->where('`id_product` = '.(int) $idProduct)
                    ->where($validate ? '`validate` = 1' : '')
            );
            Cache::store($cacheId, $result);
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Get comments by Validation
     *
     * @param int  $validate
     * @param bool $deleted
     *
     * @return array Comments
     */
    public static function getByValidate($validate = 0, $deleted = false)
    {
        return Db::getInstance()->executeS(
            (new DbQuery())
                ->select('pc.`'.bqSQL(static::$definition['primary']).'`, pc.`id_product`')
                ->select('IF(c.`id_customer`, CONCAT(c.`firstname`, \' \',  c.`lastname`), pc.`customer_name`) AS `customer_name`')
                ->select('pc.`title`, pc.`content`, pc.`grade`, pc.`date_add`, pl.`name`')
                ->from('product_comment', 'pc')
                ->leftJoin('customer', 'c', 'c.`id_customer` = pc.`id_customer`')
                ->leftJoin('product_lang', 'pl', 'pl.`id_product` = pc.`id_product` AND pl.`id_lang` = '.(int) \Context::getContext()->language->id.Shop::addSqlRestrictionOnLang('pl'))
                ->where('pc.`validate` = '.(int) $validate)
                ->where($deleted ? 'pc.`deleted` = 1' : '')
                ->orderBy('pc.`date_add` DESC')
        );
    }

    /**
     * Get all comments
     *
     * @return array Comments
     */
    public static function getAll()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('pc.`'.bqSQL(static::$definition['primary']).'`, pc.`id_product`')
                ->select('IF(c.id_customer, CONCAT(c.`firstname`, \' \',  c.`lastname`), pc.customer_name) AS `customer_name`')
                ->select('pc.`content`, pc.`grade`, pc.`date_add`, pl.`name`')
                ->leftJoin('customer', 'c', 'c.`id_customer` = pc.`id_customer`')
                ->leftJoin('product_lang', 'pl', 'pl.`id_product` = pc.`id_produt` AND pl.`id_lang` = '.(int) Context::getContext()->language->id.Shop::addSqlRestrictionOnLang('pl'))
                ->orderBy('pc.`date_add` DESC')
        );
    }

    /**
     * Report comment
     *
     * @param int $idProductComment
     * @param int $idCustomer
     *
     * @return bool
     */
    public static function reportComment($idProductComment, $idCustomer)
    {
        return Db::getInstance()->insert(
            'product_comment_report',
            [
                bqSQL(static::$definition['primary']) => (int) $idProductComment,
                'id_customer'                         => (int) $idCustomer,
            ]
        );
    }

    /**
     * Comment already report
     *
     * @param int $idProductComment
     * @param int $idCustomer
     *
     * @return bool
     */
    public static function isAlreadyReport($idProductComment, $idCustomer)
    {
        return (bool) Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COUNT(*)')
                ->from('product_comment_report')
                ->where('`id_customer` = '.(int) $idCustomer)
                ->where('`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductComment)
        );
    }

    /**
     * Set comment usefulness
     *
     * @param int $idProductComment
     * @param int $usefulness
     * @param int $idCustomer
     *
     * @return bool
     */
    public static function setCommentUsefulness($idProductComment, $usefulness, $idCustomer)
    {
        return Db::getInstance()->insert(
            'product_comment_usefulness',
            [
                bqSQL(static::$definition['primary']) => (int) $idProductComment,
                'usefulness' => (int) $usefulness,
                'id_customer' => (int) $idCustomer,
            ]
        );
    }

    /**
     * Usefulness already set
     *
     * @param int $idProductComment
     * @param int $idCustomer
     *
     * @return bool
     */
    public static function isAlreadyUsefulness($idProductComment, $idCustomer)
    {
        return (bool) Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COUNT(*)')
                ->From('product_comment_usefulness')
                ->where('`id_customer` = '.(int) $idCustomer)
                ->where('`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductComment)
        );
    }

    /**
     * Get reported comments
     *
     * @return array Comments
     */
    public static function getReportedComments()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('DISTINCT(pc.`'.bqSQL(static::$definition['primary']).'`), pc.`id_product`')
                ->select('IF(c.id_customer, CONCAT(c.`firstname`, \' \',  c.`lastname`), pc.customer_name) customer_name')
                ->select('pc.`content`, pc.`grade`, pc.`date_add`, pl.`name`, pc.`title`')
                ->from(bqSQL(static::$definition['table']), 'pc')
                ->leftJoin('customer', 'c', 'c.`id_customer` = pc.`id_customer`')
                ->leftJoin('product_lang', 'pl', 'pl.`id_product` = pc.`id_product` AND pl.`id_lang` = '.(int) Context::getContext()->language->id.' AND pl.`id_lang` = '.(int) Context::getContext()->language->id.\Shop::addSqlRestrictionOnLang('pl'))
                ->orderBy('pc.`date_add` DESC')
        );
    }

    /**
     * Validate a comment
     *
     * @param int $validate
     *
     * @return bool succeed
     */
    public function validate($validate = 1)
    {
        if (!Validate::isUnsignedId($this->id)) {
            return false;
        }

        $success = Db::getInstance()->update(
            bqSQL(static::$definition['table']),
            [
                'validate' => (int) $validate,
            ],
            '`'.bqSQL(static::$definition['primary']).'` = '.(int) $this->id
        );

        Hook::exec('actionObjectProductCommentValidateAfter', ['object' => $this]);

        return $success;
    }

    /**
     * Delete a comment, grade and report data
     *
     * @return boolean succeed
     */
    public function delete()
    {
        $success = parent::delete();
        $success &= ProductComment::deleteGrades($this->id);
        $success &= ProductComment::deleteReports($this->id);
        $success &= ProductComment::deleteUsefulness($this->id);

        return $success;
    }

    /**
     * Delete Grades
     *
     * @param int $idProductComment
     *
     * @return bool succeed
     */
    public static function deleteGrades($idProductComment)
    {
        if (!Validate::isUnsignedId($idProductComment)) {
            return false;
        }

        return Db::getInstance()->delete(
            'product_comment_grade',
            '`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductComment
        );
    }

    /**
     * Delete Reports
     *
     * @param int $idProductComment
     *
     * @return bool succeed
     */
    public static function deleteReports($idProductComment)
    {
        if (!Validate::isUnsignedId($idProductComment)) {
            return false;
        }

        return Db::getInstance()->delete(
            'product_comment_report',
            '`'.bQSQL(static::$definition['primary']).'` = '.(int) $idProductComment
        );
    }

    /**
     * Delete usefulness
     *
     * @param int $idProductComment
     *
     * @return bool succeed
     */
    public static function deleteUsefulness($idProductComment)
    {
        if (!Validate::isUnsignedId($idProductComment)) {
            return false;
        }

        return Db::getInstance()->delete(
            'product_comment_usefulness',
            '`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductComment
        );
    }
}
