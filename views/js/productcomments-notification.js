function renderProductCommentsReviewNotification(notification, translations) {
  var html = '';
  html += "<a id='notification_product_comment"+notification.id+"' href='"+notification.link+"'>";
  html += "<p><small class='text-muted'>" + translations.product +"&nbsp;"+ notification.product + "</small></p>";
  html += "<div class='pc-ratings'>";
  for (var i = 1; i <= 5; i++) {
    html += "<div class='pc-star "+(notification.grade >= i ? 'pc-star-on' : '') + "'></div>";
  }
  html += "</div>";
  html += "<p><strong>" + notification.title + "</strong></p>";
  html += "<p>" + translations.from + "&nbsp;<strong>" + notification.customer + "</strong></p>";
  html += "<small class='text-muted'><i class='icon-time'></i>&nbsp;" + moment(notification.ts * 1000).fromNow() + "</small>";
  html += "</a>";
  return html;
}