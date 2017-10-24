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
(function () {
  $(document).ready(function () {
    $('input.star').rating();
    $('.auto-submit-star').rating();
    $('a.cluetip').cluetip({
      local: true,
      cursor: 'pointer',
      cluetipClass: 'comparison_comments',
      dropShadow: false,
      dropShadowSteps: 0,
      showTitle: false,
      tracking: true,
      sticky: false,
      mouseOutClose: true,
      width: 450,
      fx: {
        open: 'fadeIn',
        openSpeed: 'fast'
      }
    }).css('opacity', 0.8);
  });
}());

function closeCommentForm() {
  $('#sendComment').slideUp('fast');
  $('input#addCommentButton').fadeIn('slow');
}
