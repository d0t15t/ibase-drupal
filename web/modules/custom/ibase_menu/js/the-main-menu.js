((Drupal) => {
  Drupal.behaviors.theMainMenu = {
    attach: (context) => {
      once('theMainMenu', '.the-main-menu').forEach((e) => {

        const button = e.querySelector('button');

        const menu = e.querySelector('ul');

        const closedLabel = 'the-main-menu--menu--closed';

        button.addEventListener('click', () => {
          menu.classList.toggle(closedLabel);
        });

        document.addEventListener('click', (event) => {
          if (!e.contains(event.target)) {
            menu.classList.add(closedLabel);
          }
        });

      });
    }
  };
})(Drupal);
