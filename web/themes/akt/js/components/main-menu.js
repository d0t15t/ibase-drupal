const moveMenu = () => {
  const menu = document.querySelector('nav#block-mainmenu');
  const header = document.querySelector('header .region-header');
  const sideBar = document.querySelector('aside .region-sidebar-first');
  if (window.innerWidth > 768) {
    // console.log('sidebar')
    sideBar.prepend(menu);
  } else {
    // console.log('header')
    header.appendChild(menu);
  }
};

document.addEventListener('DOMContentLoaded', moveMenu);

((Drupal, once) => {
  Drupal.behaviors.mainMenu = {
    attach: function (context, settings) {
      once('mainMenu', 'nav#block-mainmenu', context).forEach((el) => {
        window.addEventListener('resize', Drupal.debounce(moveMenu, 100, false));
      });
    }
  };
})(Drupal, once);
