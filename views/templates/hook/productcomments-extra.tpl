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
<script type="text/javascript">
  $(function () {
    $('a[href=#idTab5]').click(function () {
      $('*[id^="idTab"]').addClass('block_hidden_only_for_screen');
      $('div#idTab5').removeClass('block_hidden_only_for_screen');

      $('ul#more_info_tabs a[href^="#idTab"]').removeClass('selected');
      $('a[href="#idTab5"]').addClass('selected');
    });
  });
</script>
{if (!$content_only && (($nbComments == 0 && $too_early == false && ($logged || $allow_guests)) || ($nbComments != 0)))}
  <div id="product_comments_block_extra">
    {if $nbComments != 0}
      <div class="comments_note">
        <span>{l s='Average grade' mod='productcomments'}&nbsp</span>
        <div class="star_content clearfix">
          {section name="i" start=0 loop=5 step=1}
            {if $averageTotal le $smarty.section.i.index}
              <div class="star"></div>
            {else}
              <div class="star star_on"></div>
            {/if}
          {/section}
        </div>
      </div>
    {/if}

    <div class="comments_advices">
      {if $nbComments != 0}
        <a href="#idTab5">{l s='Read user reviews' mod='productcomments'} ({$nbComments|intval})</a>
        <br/>
      {/if}
      {if ($too_early == false && ($logged || $allow_guests))}
        <a class="open-comment-form" href="#new_comment_form">{l s='Write your review' mod='productcomments'}</a>
      {/if}
    </div>
  </div>
{/if}
<!--  /Module ProductComments -->
