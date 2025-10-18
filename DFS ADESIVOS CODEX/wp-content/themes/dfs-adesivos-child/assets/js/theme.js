(function(){
  const toggle = document.querySelector('[data-dfs-toggle="menu"]');
  const nav = document.querySelector('.dfs-header__nav');

  if (toggle && nav) {
    toggle.addEventListener('click', function(){
      nav.classList.toggle('is-open');
      toggle.classList.toggle('is-open');
    });
  }
})();
