(function (Drupal) {

  function licenseChange(e) {
    let na = ['_na', '_not_included'];
    let cId = e.srcElement.dataset.componentId;
    let element = document.getElementById('component-' + cId);
    if (e.srcElement.value.length > 0) { 
      element.classList.add('open');
    }
    else {
      element.classList.remove('open');
    }
  }

  Drupal.behaviors.licenseChange = {
    attach: function (context, settings) {
      let select = document.getElementsByClassName('license-input');
      [...select].forEach(e => {
        e.addEventListener('keyup', licenseChange);
      });
    }
  };

})(Drupal);
