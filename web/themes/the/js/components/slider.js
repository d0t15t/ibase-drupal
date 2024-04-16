import Glide, { Anchors, Controls } from '../../node_modules/@glidejs/glide/dist/glide.modular.esm.js'

((Drupal, once) => {
  Drupal.behaviors.glideSlider = {
    attach: function (context, settings) {
      once('glideSlider', '.glide__track ', context).forEach((el) => {
        if (el.querySelectorAll('ul.glide__slides li').length > 1) {
          // get the parent of .glide__track
          new Glide(el.parentNode).mount({ Anchors, Controls })
        }
      });
    }
  };
})(Drupal, once);
