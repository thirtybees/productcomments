<?php

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * @param ProductComments $object
 *
 * @return bool
 * @throws PrestaShopException
 */
function upgrade_module_2_4($object)
{
    return ($object->registerHook('displayProductListReviews') && $object->registerHook('top'));
}
