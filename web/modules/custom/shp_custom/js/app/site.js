(function ($, Drupal, window, document) {
  // @todo - how do attach behaviour to a field.
  // @todo - get the names for the field ?
  Drupal.behaviors.shp_short_name = {
    attach: function(context, settings) {
      var $context = $(context);
      var $name_field = $context.find(':input[name="title[0][value]"]');
      var $short_name_field = $context.find(':input[name="field_shp_short_name[0][value]"]');
      debugger;
      // This event is triggered by drupal.form library.
      $name_field.on('formUpdated', function (element) {
        alert('Changed');
      });
    }
  };
})(jQuery, Drupal, this, this.document);
