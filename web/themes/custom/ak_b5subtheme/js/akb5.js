const navbarCollapse = document.querySelector('.navbar-collapse');
navbarCollapse.addEventListener('show.bs.collapse', function() {
  document.body.classList.remove('navbar-collapsed');
  document.body.classList.add('navbar-expanded');
});

navbarCollapse.addEventListener('hide.bs.collapse', function() {
  document.body.classList.remove('navbar-expanded');
  document.body.classList.add('navbar-collapsed');
});
