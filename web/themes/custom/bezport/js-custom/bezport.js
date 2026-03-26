/**
 * @file
 * Custom behaviors for Bezport theme.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.bezport = {
    attach(context, settings) {
      const isLoggedIn = settings.bezport?.global?.isLoggedIn || false;

      // WOW init - ošetřeno pomocí once, aby se inicializovalo jen jednou na celém dokumentu
      once('wow-init', 'html', context).forEach(() => {
        new WOW().init();
      });

      // Main menu hamburger toggle
      once('menu-toggle', '#main-menu-title-bar', context).forEach((titleBar) => {
        jQuery(titleBar).on('toggled.zf.responsiveToggle', () => {
          const mainMenu = document.getElementById('main-menu');
          const hamburger = document.getElementById('main-menu-hamburger');
          
          if (mainMenu && hamburger) {
            const displayStyle = window.getComputedStyle(mainMenu).display;
            if (displayStyle === 'none') {
              hamburger.classList.remove('open');
            } else {
              hamburger.classList.add('open');
            }
          }
        });
      });

      // Sticky bar - Foundation event zachycen přes jQuery, DOM manipulace v čistém JS
      once('sticky-topbar', '#top-bar-sticky-container', context).forEach((container) => {
        jQuery(container)
          .on('sticky.zf.stuckto:top', () => {
            document.querySelectorAll('.block-system-branding-block').forEach((el) => {
              el.classList.add('element-sticky');
            });
          })
          .on('sticky.zf.unstuckfrom:top', () => {
            document.querySelectorAll('.block-system-branding-block').forEach((el) => {
              el.classList.remove('element-sticky');
            });
          });
      });

      // Aktivní li v menu - Čistý JS
      once('main-menu-active', '#main-menu', context).forEach((menu) => {
        const activeLinks = menu.querySelectorAll('a.is-active');
        activeLinks.forEach((link) => {
          const parentLi = link.closest('li');
          if (parentLi) {
            parentLi.classList.add('li-active');
          }
        });
      });

      // Focus na input v modálním hledání - Foundation event + čistý JS
      once('search-reveal-focus', '#search-reveal', context).forEach((reveal) => {
        jQuery(reveal).on('open.zf.reveal', () => {
          setTimeout(() => {
            const searchInput = document.querySelector('input[name="search_keys"]');
            if (searchInput) {
              searchInput.value = '';
              searchInput.focus();
            }
          }, 300);
        });
      });

      // Tooltip HTML fix - Přidáno do once() a přepsáno do čistého JS
      once('tooltip-html-fix', '.tooltip[role="tooltip"]', context).forEach((tooltip) => {
        let html = tooltip.innerHTML;
        html = html
          .replace(/\*¤/g, '<strong>')
          .replace(/¤\*/g, '</strong>')
          .replace(/~/g, '<br>');
        tooltip.innerHTML = html;
      });

    }
  };
})(Drupal, drupalSettings, once);