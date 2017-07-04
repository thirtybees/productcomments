{*
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
*}

{extends file="helpers/form/form.tpl"}

{block name="input"}
  {if $input.type == 'products'}
    <table id="{$input.name}">
      <tr>
        <th></th>
        <th>ID</th>
        <th width="80%">{l s='Product Name'}</th>
      </tr>
      {foreach $input.values as $value}
        <tr>
          <td>
            <input type="checkbox" name="{$input.name}[]" value="{$value.id_product}"
                    {if isset($value.selected) && $value.selected == 1} checked {/if} />
          </td>
          <td>{$value.id_product}</td>
          <td width="80%">{$value.name}</td>
        </tr>
      {/foreach}
    </table>
  {elseif $input.type == 'switch' && $smarty.const._PS_VERSION_|@addcslashes:'\'' < '1.6'}
    {foreach $input.values as $value}
      <input type="radio" name="{$input.name}" id="{$value.id}" value="{$value.value|escape:'html':'UTF-8'}"
             {if $fields_value[$input.name] == $value.value}checked="checked"{/if}
              {if isset($input.disabled) && $input.disabled}disabled="disabled"{/if} />
      <label class="t" for="{$value.id}">
        {if isset($input.is_bool) && $input.is_bool == true}
          {if $value.value == 1}
            <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/admin/enabled.gif" alt="{$value.label|escape:'htmlall':'UTF-8'}" title="{$value.label|escape:'htmlall':'UTF-8'}"/>
          {else}
            <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/admin/disabled.gif" alt="{$value.label|escape:'htmlall':'UTF-8'}" title="{$value.label|escape:'htmlall':'UTF-8'}"/>
          {/if}
        {else}
          {$value.label|escape:'htmlall':'UTF-8'}
        {/if}
      </label>
      {if isset($input.br) && $input.br}<br/>{/if}
      {if isset($value.p) && $value.p}<p>{$value.p}</p>{/if}
    {/foreach}
  {else}
    {$smarty.block.parent}
  {/if}

{/block}
