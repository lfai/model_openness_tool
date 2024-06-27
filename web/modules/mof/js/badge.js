((Drupal) => {

  async function copyText(element) {
    try {
      const txtNode = element.previousSibling;
      if (txtNode.nodeType === Node.TEXT_NODE) {
        const txt = txtNode.textContent.trim();
        await navigator.clipboard.writeText(txt);
      }
    }
    catch (err) {
      console.error('Failed to copy markdown', err);
      alert(err);
    }
  }

  Drupal.behaviors.copyPasteBadge = {
    attach(context, settings) {
      [...context.getElementsByClassName('btn-copy')].forEach(
        btn => btn.addEventListener('click', () => copyText(btn))
      );
    }
  };

})(Drupal);
