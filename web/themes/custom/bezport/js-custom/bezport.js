/**
 * @file
 * Custom behaviors for Bezport theme.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.bezport = {
    attach: function (context, settings) {

      const isLoggedIn = settings.bezport?.global?.isLoggedIn || false;

      // WOW init
      new WOW().init();

      // Main menu hamburger toggle
      $(once('menu-toggle', '#main-menu-title-bar', context)).each(function () {
        $(this).on('toggled.zf.responsiveToggle', function () {
          if ($('#main-menu').css('display') === 'none') {
            $('#main-menu-hamburger').removeClass('open');
          } else {
            $('#main-menu-hamburger').addClass('open');
          }
        });
      });

      
      





    }
  };

})(jQuery, Drupal, drupalSettings, once);
