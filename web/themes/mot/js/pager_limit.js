((Drupal, once) => {

  Drupal.behaviors.pageLimit = {
    attach(context) {
      once('setPerPage', '#per-page', context).forEach((el) => {
        const urlParams = new URLSearchParams(window.location.search);
        const perpage = urlParams.get('limit');
        el.value = (perpage) ? perpage : 50;
      });

      const el = document.getElementById('per-page');
      el.addEventListener('change', (evt) => {
        const url = new URL(window.location.href);
        url.searchParams.set('limit', evt.target.value);
        window.location.href = url.toString();
      });
    }
  };

})(Drupal, once);
