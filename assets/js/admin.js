jQuery(document).ready(function($) {
  // You can add interactive functionality here
  console.log('Chainsaw Plugin admin JS loaded');
  
  // Example: Show/hide fields based on checkbox
  $('#chainsaw_field_checkbox').change(function() {
    if ($(this).is(':checked')) {
      $('#chainsaw_field_text').closest('tr').show();
    } else {
      $('#chainsaw_field_text').closest('tr').hide();
    }
  }).trigger('change');
});