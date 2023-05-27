((Drupal, once, $) => {
  Drupal.behaviors.yearsMenu = {
    attach: function (context, settings) {
      once('yearsMenu', 'nav.years-menu--nav', context).forEach((el) => {
        const button = el.querySelector('button');
        const list = el.querySelector('ul');
        button.addEventListener('click', function(e) {
          $(list).toggleClass('closed');
          $(button).toggleClass('button-state--clicked');
        })
      });
    }
  };
})(Drupal, once, jQuery);
