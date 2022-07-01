<?php

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * @param ProductComments $module
 * @return bool
 * @throws PrestaShopException
 */
function upgrade_module_4_1_0($module)
{
    return (
        $module->registerHook('displayBackOfficeHeader') &&
        $module->registerHook('actionGetNotificationType')
    );
}
