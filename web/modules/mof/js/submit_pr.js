/**
 * @file
 * Handles GitHub Pull Request submission from model evaluation results.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.submitPullRequest = {
    attach: function (context, settings) {
      // Find all PR submission buttons
      const buttons = context.querySelectorAll('.js-submit-pr');

      buttons.forEach(function(button) {
        // Avoid attaching multiple times
        if (button.dataset.prAttached) {
          return;
        }
        button.dataset.prAttached = 'true';

        button.addEventListener('click', function(e) {
          e.preventDefault();

          const prUrl = button.dataset.prUrl;
          if (!prUrl) {
            alert('Error: PR submission URL not found.');
            return;
          }

          // Disable button and show loading state
          button.disabled = true;
          const originalText = button.textContent;
          button.textContent = 'Submitting...';

          // Create status message container if it doesn't exist
          let statusContainer = document.querySelector('.pr-status-message');
          if (!statusContainer) {
            statusContainer = document.createElement('div');
            statusContainer.className = 'pr-status-message messages';
            button.parentNode.insertBefore(statusContainer, button.nextSibling);
          }

          // Show progress message with spinner
          statusContainer.className = 'pr-status-message messages messages--status';
          statusContainer.innerHTML = '<h2 class="visually-hidden">Status message</h2>' +
            '<div class="messages__content">' +
            '<div class="pr-spinner"></div>' +
            '<strong>Submitting Pull Request...</strong><br>' +
            'This may take some time. Please wait...' +
            '</div>';

          // Make AJAX request to submit PR
          fetch(prUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
          })
          .then(response => response.json())
          .then(data => {
            // Check if re-authentication is required
            if (data.reauth_required && data.reauth_url) {
              // Show message and redirect
              statusContainer.className = 'pr-status-message messages messages--warning';
              statusContainer.innerHTML = '<h2 class="visually-hidden">Warning message</h2>' +
                '<div class="messages__content">' +
                '<strong>Re-authentication Required:</strong> ' + data.error +
                '<br>Redirecting to GitHub login...</div>';

              // Redirect after a short delay
              setTimeout(function() {
                window.location.href = data.reauth_url;
              }, 2000);
              return;
            }

            // Re-enable button
            button.disabled = false;
            button.textContent = originalText;

            if (data.success) {
              // Show success message
              statusContainer.className = 'pr-status-message messages messages--status';
              statusContainer.innerHTML = '<h2 class="visually-hidden">Status message</h2>' +
                '<div class="messages__content">' +
                '<strong>Success!</strong> ' + data.message;

              if (data.pr_url) {
                statusContainer.innerHTML += '<br><a href="' + data.pr_url + '" target="_blank" rel="noopener">View Pull Request #' + data.pr_number + '</a>';
              }

              statusContainer.innerHTML += '</div>';

              // Hide the button after successful submission
              button.style.display = 'none';
            } else {
              // Show error message
              statusContainer.className = 'pr-status-message messages messages--error';
              statusContainer.innerHTML = '<h2 class="visually-hidden">Error message</h2>' +
                '<div class="messages__content">' +
                '<strong>Error:</strong> ' + data.error;

              // If login is required, show login link
              if (data.login_url) {
                statusContainer.innerHTML += '<br><a href="' + data.login_url + '">Login with GitHub</a>';
              }

              statusContainer.innerHTML += '</div>';
            }

            // Scroll to status message
            statusContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          })
          .catch(error => {
            // Re-enable button
            button.disabled = false;
            button.textContent = originalText;

            // Show error message
            statusContainer.className = 'pr-status-message messages messages--error';
            statusContainer.innerHTML = '<h2 class="visually-hidden">Error message</h2>' +
              '<div class="messages__content">' +
              '<strong>Error:</strong> Failed to submit pull request. Please try again or submit manually.' +
              '<br>Note: You may need to log out and log back in.' +
              '</div>';

            console.error('PR submission error:', error);
          });
        });
      });
    }
  };

})(Drupal, drupalSettings);

// Made with Bob
