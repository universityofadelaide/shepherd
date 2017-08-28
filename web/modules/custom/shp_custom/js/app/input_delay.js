(function ($, Drupal, window, document) {

  // Interval between firing inputdelay event.
  var interval = 600;

  Drupal.behaviors.shp_input_delay_event = {
    attach: function(context, settings) {
      var $context = $(context).find('form').once();
      if ($context.length) {
        var $text_inputs = $context.find(':input[type="text"]');
        $text_inputs.each(function(key, el) {
          input_delay($(el));
        })
      }
    }
  };

  function input_delay(element) {
    var timer;
    element.on('keyup', function (event) {
      clearTimeout(timer);
      if ($(this).val()) {
        // Input has data.
        var $id = $(this);
        timer = setTimeout(function () {
          $id.triggerHander('inputdelay');
        }, interval);
      }
    });
  }

})(jQuery, Drupal, this, this.document);
