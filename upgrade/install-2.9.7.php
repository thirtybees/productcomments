<?php

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * @param ProductComments $object
 *
 * @return bool
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 */
function upgrade_module_2_9_7($object)
{
    return ($object->unregisterHook('top'));
}
