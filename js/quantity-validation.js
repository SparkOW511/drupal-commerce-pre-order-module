(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.commercePreorderBatchQuantityValidation = {
    attach: function (context, settings) {
      // Find quantity input fields using multiple selectors
      var quantityFields = context.querySelectorAll('input[name="quantity"], input[name*="quantity"], input[id*="quantity"]');
      
      // Also try alternative selectors
      var allInputs = context.querySelectorAll('input[type="number"]');
      var allQuantityInputs = context.querySelectorAll('input[id*="quantity"], input[class*="quantity"]');
      
      // Use the most specific selector for Commerce quantity fields
      if (quantityFields.length === 0) {
        // Try Commerce-specific selectors
        quantityFields = context.querySelectorAll('input[id*="edit-quantity"]');
      }
      
      if (quantityFields.length === 0) {
        return;
      }

      quantityFields.forEach(function(field, index) {
        var maxQuantity = parseInt(field.getAttribute('data-max-available'));
        
        if (!maxQuantity) {
          // Check if the max is set on a parent element
          var parent = field.closest('[data-max-available]');
          if (parent) {
            maxQuantity = parseInt(parent.getAttribute('data-max-available'));
          }
        }
        
        if (!maxQuantity) {
          return;
        }

        // Input event for real-time validation
        field.addEventListener('input', function() {
          var value = parseInt(this.value);
          
          if (value > maxQuantity) {
            this.setCustomValidity('Maximum available quantity is ' + maxQuantity + ' items across all batches.');
          } else {
            this.setCustomValidity('');
          }
        });

        // Change event for immediate feedback
        field.addEventListener('change', function() {
          var value = parseInt(this.value);
          
          if (value > maxQuantity) {
            alert('Maximum available quantity is ' + maxQuantity + ' items across all batches. Resetting to ' + maxQuantity);
            this.value = maxQuantity;
            this.setCustomValidity('');
          }
        });

        // Form submit validation
        var form = field.closest('form');
        if (form) {
          form.addEventListener('submit', function(e) {
            var value = parseInt(field.value);
            
            if (value > maxQuantity) {
              e.preventDefault();
              alert('Cannot submit form. Maximum available quantity is ' + maxQuantity + ' items across all batches.');
              field.focus();
              return false;
            }
          });
        }
      });
    }
  };

})(Drupal, drupalSettings); 