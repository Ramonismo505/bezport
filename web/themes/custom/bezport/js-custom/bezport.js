/**
 * @file
 * Custom behaviors for Bezport theme.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.bezport = {
    attach: function (context, settings) {
      // Bezpečné načtení proměnné z PHP (fallback na false).
      const isLoggedIn = settings.bezport?.global?.isLoggedIn || false;

      // Zde je tvá logika závislá na přihlášení
      if (isLoggedIn) {
        //console.log("Jsem přihlášený uživatel!");
      } else {
        //console.log("Nejsem přihlášený.");
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
