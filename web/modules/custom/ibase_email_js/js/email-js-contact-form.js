(function (Drupal) {
  Drupal.behaviors.emailJsContactForm = {
    attach: function (context, settings) {
      // Use querySelectorAll to find the form within the context.
      var forms = context.querySelectorAll('.contact-form');
      forms.forEach(function (form) {
        // Check if the form already has the 'emailJsContactForm' class.
        if (!form.classList.contains('emailJsContactForm')) {
          // Add the class to ensure the behavior is only applied once.
          form.classList.add('emailJsContactForm');

          // Add your custom JavaScript functionality here.
          form.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent the default form submission.
            console.log('Contact form submitted!');

            // Example: Extract values from form fields.
            var email = form.querySelector('input[name="your_email_field"]').value;
            var message = form.querySelector('textarea[name="your_message_field"]').value;

            // Perform some action with the form data.
            // ...

            // Optionally, you could submit the form via AJAX or show a custom confirmation message.
            alert('Email: ' + email + '\nMessage: ' + message);
          });
        }
      });
    }
  };
})(Drupal);

