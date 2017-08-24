(function ($, Drupal, window, document) {

  Drupal.behaviors.shp_site = {
    attach: function(context, settings) {
      var $context = $(context).find('form').once('shp-site');
      if ($context.length) {
        var $name_field = $context.find(':input[name="title[0][value]"]');
        var $short_name_field = $context.find(':input[name="field_shp_short_name[0][value]"]');
        // This event is triggered by drupal.form library.
        $name_field.on('formUpdated', function() { setShortName($short_name_field, this.value) });
      }
    }
  };

  var regex = /[^a-z0-9-]/g;

  /**
   * Update the short_name with the text from the title input.
   * @param $element
   * @param text
   */
  function setShortName($element, text) {
    // Before we set the text. Run our text replace over the top
    $element.val(replaceText(text));
  }

  /**
   * Replaces all spaces, underscores and converts to lower case.
   *
   * @param text
   * @returns {string}
   */
  function replaceText(text) {
    return (text ? text.toLowerCase().replace(regex, '-') : '');
  }

})(jQuery, Drupal, this, this.document);
