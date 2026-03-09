(function (Drupal, once) {
  Drupal.behaviors.rabpPreview = {
    attach(context) {
      const containers = once('rabp-preview', '.rabp-container', context);
      if (!containers.length) {
        return;
      }

      const updateSize = () => {
        const height = window.innerHeight || document.documentElement.clientHeight;
        const width = window.innerWidth || document.documentElement.clientWidth;
        containers.forEach((container) => {
          const sizeEl = container.querySelector('.size');
          if (sizeEl) {
            sizeEl.textContent = `${width}w x ${height}h`;
          }
        });
      };

      updateSize();

      if (!window.__rabpResizeBound) {
        window.__rabpResizeBound = true;
        window.addEventListener('resize', updateSize);
      }
    },
  };
})(Drupal, once);
