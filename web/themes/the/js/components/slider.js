import Glide, { Anchors, Controls } from '../../node_modules/@glidejs/glide/dist/glide.modular.esm.js'
// import Glide from '@glidejs/glide';



((Drupal, once) => {
  Drupal.behaviors.glideSlider = {
    attach: function (context, settings) {
      once('glideSlider', '.glide.slider', context).forEach((el) => {
        if (el.querySelectorAll('ul.glide__slides li').length > 1) {
          new Glide('.glide.slider').mount({ Anchors, Controls })
        }
      });
    }
  };
})(Drupal, once);
