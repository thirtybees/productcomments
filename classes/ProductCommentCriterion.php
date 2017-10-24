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
use Db;
use DbQuery;
use ProductComments;
use Shop;
use Tools;
use Validate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class ProductCommentCriterion
 */
class ProductCommentCriterion extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'     => 'product_comment_criterion',
        'primary'   => 'id_product_comment_criterion',
        'multilang' => true,
        'fields'    => [
            'id_product_comment_criterion_type' => ['type' => self::TYPE_INT],
            'active'                            => ['type' => self::TYPE_BOOL],
            // Lang fields
            'name'                              => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
        ],
    ];
    /** @var int $id_product_comment_criterion_type */
    public $id_product_comment_criterion_type;
    /** @var string $name */
    public $name;
    /** @var bool $active */
    public $active = true;
    // @codingStandardsIgnoreEnd

    /**
     * Get criterion by Product
     *
     * @param int $idProduct
     * @param int $idLang
     *
     * @return array Criterion
     */
    public static function getByProduct($idProduct, $idLang)
    {
        if (!Validate::isUnsignedId($idProduct) ||
            !Validate::isUnsignedId($idLang)
        ) {
            die(Tools::displayError());
        }

        $cacheId = 'ProductCommentCriterion::getByProduct_'.(int) $idProduct.'-'.(int) $idLang;
        if (!Cache::isStored($cacheId)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('pcc.`'.bqSQL(static::$definition['primary']).'`, pccl.`name`')
                    ->from(bqSQL(static::$definition['table']), 'pcc')
                    ->leftJoin(
                        'product_comment_criterion_lang',
                        'pccl',
                        'pcc.`'.bqSQL(static::$definition['primary']).'` = pccl.`'.bqSQL(static::$definition['primary']).'` AND pccl.`id_lang` = '.(int) $idLang
                    )
                    ->leftJoin('product_comment_criterion_product', 'pccp', 'pcc.`'.bqSQL(static::$definition['primary']).'` = pccp.`'.bqSQL(static::$definition['primary']).'`')
                    ->leftJoin('product_comment_criterion_category', 'pccc', 'pcc.`'.bqSQL(static::$definition['primary']).'` = pccc.`'.bqSQL(static::$definition['primary']).'`')
                    ->leftJoin('product_shop', 'ps', 'ps.`id_category_default` = pccc.`id_category` AND ps.`id_product` = '.(int) $idProduct.' AND ps.`id_shop` = '.(int) Shop::getContextShopID())
                    ->where('pccp.`id_product` IS NOT NULL OR ps.`id_product` IS NOT NULL OR pcc.`id_product_comment_criterion_type` = 1')
                    ->where('pcc.`active` = 1')
                    ->groupBy('pcc.`id_product_comment_criterion`')
            );
            Cache::store($cacheId, $result);
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Get Criterions
     *
     * @param int      $idLang
     * @param int|bool $type
     * @param bool     $active
     *
     * @return array Criterions
     */
    public static function getCriterions($idLang, $type = false, $active = false)
    {
        if (!Validate::isUnsignedId($idLang)) {
            die(Tools::displayError());
        }

        $criterions = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('pcc.`'.bqSQL(static::$definition['primary']).'`, pcc.`id_product_comment_criterion_type`, pccl.`name`, pcc.`active`')
                ->from(bqSQL(static::$definition['table']), 'pcc')
                ->innerJoin('product_comment_criterion_lang', 'pccl', 'pcc.`'.bqSQL(static::$definition['primary']).'` = pccl.`'.bqSQL(static::$definition['primary']).'`')
                ->where('pccl.`id_lang` = '.(int) $idLang)
                ->where($active ? '`active` = 1' : '')
                ->where($type ? '`id_product_comment_criterion_type` = '.(int) $type : '')
                ->orderBy('pccl.`name` ASC')
        );

        $types = self::getTypes();
        foreach ($criterions as $key => $data) {
            $criterions[$key]['type_name'] = $types[$data['id_product_comment_criterion_type']];
        }

        return $criterions;
    }

    /**
     * @return array
     */
    public static function getTypes()
    {
        // Instance of module class for translations
        $module = new ProductComments();

        return [
            1 => $module->l('Valid for the entire catalog', 'ProductCommentCriterion'),
            2 => $module->l('Restricted to some categories', 'ProductCommentCriterion'),
            3 => $module->l('Restricted to some products', 'ProductCommentCriterion'),
        ];
    }

    /**
     * @return bool
     */
    public function delete()
    {
        if (!parent::delete()) {
            return false;
        }
        if ($this->id_product_comment_criterion_type == 2) {
            if (!$this->deleteCategories()) {
                return false;
            }
        } elseif ($this->id_product_comment_criterion_type == 3) {
            if (!$this->deleteProducts()) {
                return false;
            }
        }

        return $this->deleteGrades();
    }

    /**
     * @param bool $nullValues
     *
     * @return bool
     */
    public function update($nullValues = false)
    {
        $previousUpdate = new self((int) $this->id);
        if (!parent::update($nullValues)) {
            return false;
        }
        if ($previousUpdate->id_product_comment_criterion_type != $this->id_product_comment_criterion_type) {
            if ($previousUpdate->id_product_comment_criterion_type == 2) {
                return $this->deleteCategories((int) $previousUpdate->id);
            } elseif ($previousUpdate->id_product_comment_criterion_type == 3) {
                return $this->deleteProducts((int) $previousUpdate->id);
            }
        }

        return true;
    }

    /**
     * Link a Comment Criterion to a product
     *
     * @param int $idProduct
     *
     * @return bool succeed
     */
    public function addProduct($idProduct)
    {
        if (!Validate::isUnsignedId($idProduct)) {
            die(Tools::displayError());
        }

        return Db::getInstance()->insert(
            'product_comment_criterion_product',
            [
                bqSQL(static::$definition['primary']) => (int) $this->id,
                'id_product'                          => (int) $idProduct,
            ]
        );
    }

    /**
     * Link a Comment Criterion to a category
     *
     * @param int $idCategory
     *
     * @return bool succeed
     */
    public function addCategory($idCategory)
    {
        if (!Validate::isUnsignedId($idCategory)) {
            die(Tools::displayError());
        }

        return Db::getInstance()->insert(
            'product_comment_criterion_category',
            [
                bqSQL(static::$definition['primary']) => (int) $this->id,
                'id_category'                         => (int) $idCategory,
            ]
        );
    }

    /**
     * Add grade to a criterion
     *
     * @param int $idProductComment
     * @param int $grade
     *
     * @return bool succeed
     */
    public function addGrade($idProductComment, $grade)
    {
        if (!Validate::isUnsignedId($idProductComment)) {
            die(Tools::displayError());
        }
        if ($grade < 0) {
            $grade = 0;
        } elseif ($grade > 10) {
            $grade = 10;
        }

        return Db::getInstance()->insert(
            'product_comment_grade',
            [
                bqSQL(static::$definition['primary']) => (int) $this->id,
                'id_product_comment'                  => (int) $idProductComment,
                'grade'                               => (int) $grade,
            ]
        );
    }

    /**
     * @return array
     */
    public function getProducts()
    {
        $res = Db::getInstance()->executeS(
            (new DbQuery())
                ->select('pccp.`id_product`, pccp.`'.bqSQL(static::$definition['primary']).'`')
                ->from('product_comment_criterion_product', 'pccp')
                ->where('pccp.`'.bqSQL(static::$definition['primary']).'` = '.(int) $this->id)
        );
        $products = [];
        if ($res) {
            foreach ($res as $row) {
                $products[] = (int) $row['id_product'];
            }
        }

        return $products;
    }

    /**
     * @return array
     */
    public function getCategories()
    {
        $res = Db::getInstance()->executeS(
            (new DbQuery())
                ->select('pccc.`id_category`, pccc.`'.bqSQL(static::$definition['primary']).'`')
                ->from('product_comment_criterion_category', 'pccc')
                ->where('pccc.`'.bqSQL(static::$definition['primary']).'` = '.(int) $this->id)
        );
        $criterions = [];
        if ($res) {
            foreach ($res as $row) {
                $criterions[] = (int) $row['id_category'];
            }
        }

        return $criterions;
    }

    /**
     * @param null|int $idProductCommentCriterion
     *
     * @return bool
     */
    public function deleteCategories($idProductCommentCriterion = null)
    {
        if (!$idProductCommentCriterion) {
            $idProductCommentCriterion = (int) $this->id;
        }

        return Db::getInstance()->delete(
            'product_comment_criterion_category',
            '`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductCommentCriterion
        );
    }

    /**
     * @param null|int $idProductCommentCriterion
     *
     * @return bool
     */
    public function deleteProducts($idProductCommentCriterion = null)
    {
        if (!$idProductCommentCriterion) {
            $idProductCommentCriterion = (int) $this->id;
        }

        return Db::getInstance()->delete(
            'product_comment_criterion_product',
            '`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductCommentCriterion
        );
    }

    /**
     * @param null|int $idProductCommentCriterion
     *
     * @return bool
     */
    public function deleteGrades($idProductCommentCriterion = null)
    {
        if (!$idProductCommentCriterion) {
            $idProductCommentCriterion = (int) $this->id;
        }

        return Db::getInstance()->delete(
            'product_comment_grade',
            '`'.bqSQL(static::$definition['primary']).'` = '.(int) $idProductCommentCriterion
        );
    }
}
