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
<tr class="comparison_header">
  <td>
    {l s='Comments' mod='productcomments'}
  </td>
  {section loop=$list_ids_product|count step=1 start=0 name=td}
    <td></td>
  {/section}
</tr>

{foreach from=$grades item=grade key=grade_id}
  <tr>
    {cycle values='comparison_feature_odd,comparison_feature_even' assign='classname'}
    <td class="{$classname|escape:'htmlall':'UTF-8'}">
      {$grade|intval}
    </td>

    {foreach from=$list_ids_product item=id_product}
      {assign var='tab_grade' value=$product_grades[$grade_id]}
      <td width="{$width}%" class="{$classname|escape:'htmlall':'UTF-8'} comparison_infos ajax_block_product" align="center">
        {if isset($tab_grade[$id_product]) && $tab_grade[$id_product]}
          {section loop=6 step=1 start=1 name=average}
            <input class="auto-submit-star" disabled="disabled" type="radio" name="{$grade_id|intval}_{$id_product|intval}_{$smarty.section.average.index|intval}" {if isset($tab_grade[$id_product]) && $tab_grade[$id_product]|round neq 0 && $smarty.section.average.index eq $tab_grade[$id_product]|round}checked="checked"{/if} />
          {/section}
        {else}
          -
        {/if}
      </td>
    {/foreach}
  </tr>
{/foreach}

{cycle values='comparison_feature_odd,comparison_feature_even' assign='classname'}
<tr>
  <td class="{$classname|escape:'htmlall':'UTF-8'} comparison_infos">{l s='Average' mod='productcomments'}</td>
  {foreach from=$list_ids_product item=id_product}
    <td width="{$width|intval}%" class="{$classname|escape:'htmlall':'UTF-8'} comparison_infos" align="center">
      {if isset($list_product_average[$id_product]) && $list_product_average[$id_product]}
        {section loop=6 step=1 start=1 name=average}
          <input class="auto-submit-star" disabled="disabled" type="radio" name="average_{$id_product}" {if $list_product_average[$id_product]|round neq 0 and $smarty.section.average.index eq $list_product_average[$id_product]|round}checked="checked"{/if} />
        {/section}
      {else}
        -
      {/if}
    </td>
  {/foreach}
</tr>

<tr>
  <td class="{$classname|escape:'htmlall':'UTF-8'} comparison_infos">&nbsp;</td>
  {foreach from=$list_ids_product item=id_product}
    <td width="{$width}%" class="{$classname} comparison_infos" align="center">
      {if isset($product_comments[$id_product]) && $product_comments[$id_product]}
        <a href="#" rel="#comments_{$id_product|intval}" class="cluetip">{l s='view comments' mod='productcomments'}</a>
        <div style="display:none" id="comments_{$id_product|intval}">
          {foreach from=$product_comments[$id_product] item=comment}
            <div class="comment">
              <div class="customer_name">
                {dateFormat date=$comment.date_add|escape:'html':'UTF-8' full=0}
                {$comment.customer_name|escape:'html':'UTF-8'}.
              </div>
              {$comment.content|escape:'html':'UTF-8'|nl2br}
            </div>
            <br/>
          {/foreach}
        </div>
      {else}
        -
      {/if}
    </td>
  {/foreach}
</tr>
