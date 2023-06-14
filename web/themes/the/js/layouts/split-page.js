const node_page_selector = "article.node--type-artist.node--view-mode-full";

((Drupal, once) => {
  Drupal.behaviors.nodePageArtist = {
    attach: function (context, settings) {
      once('nodePageArtist', node_page_selector, context).forEach((el) => {
          const subNav = document.getElementById('block-thecontentlinks');
          const fields = el.querySelectorAll('.node__content > .field');
          createPageLinks(fields, subNav)
      });
    }
  };
})(Drupal, once);

function createPageLinks(fields, dest) {
  fields.forEach((field, index) => {
    const field_index = stringToHash(`${node_page_selector}--field-${index}`)
    field.setAttribute('id', field_index);
    const label = field.querySelector('.field__label');
    if (label) {
      const labelAnchor = document.createElement('a');
      labelAnchor.setAttribute('href', `#${field_index}`);

      if (index === 0) {
        labelAnchor.classList.add('visually-hidden');
      }

      const parentFieldClass = Array.from(field.classList).find(className => className.startsWith('field--name-field-'));
      if (parentFieldClass) {
        labelAnchor.classList.add(parentFieldClass);
      }
      labelAnchor.appendChild(label.cloneNode(true));
      dest.appendChild(labelAnchor);
      // hide original.
      // label.classList.add('visually-hidden')
    }
  });
}

function stringToHash(string) {
  let hash = 0;
  if (string.length == 0) return hash;
  for (i = 0; i < string.length; i++) {
    const char = string.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash;
  }
  return hash;
}