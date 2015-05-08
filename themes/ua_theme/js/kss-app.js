/*
 Dependencies:
 jquery
 jquery.colorbox.min.js

    To view the remainder of the colorbox config JS from redesign1 theme, visit:
    motoko:apps/web_docs/global/v/redesign1/js/widgets/ui-widget-gallery.js
    - smithy

 */
$(function () {

    function init () {
        var $cboxPrevBtn = $('#cboxPrevious'),
            $cboxNextBtn = $('#cboxNext'),
            $cboxTitle = $('#cboxTitle');

        $(".image-gallery a[rel^=gallery]").colorbox({
            opacity: 0.8,
            maxWidth: "80%",
            maxHeight: "80%",
            onOpen: function() {
                $cboxPrevBtn.attr('title', 'previous');
                $cboxNextBtn.attr('title', 'next');
                // Caption fix from http://groups.google.com/group/colorbox/browse_thread/thread/7d29bc0dcda8339/6a3cacbd83f39ad5?lnk=gst&q=caption
                $cboxTitle.css({
                    'position': 'absolute',
                    'left': '0',
                    'top': '0',
                    'text-align': 'center',
                    'width': '100%',
                    'color': '#fff',
                    'height': 'auto',
                    'font-size': '14px',
                    'padding-top': '2px',
                    'padding-bottom': '2px'
                });
            },
            onComplete: function() {

                // change over so that caption sits at the bottom
                var c_h = $('#cboxContent').outerHeight(true);
                $cboxTitle.css('top', c_h+'px');

                // Add nav sprite
                $cboxPrevBtn.addClass('sprite-slider-btn-prev');
                $cboxNextBtn.addClass('sprite-slider-btn-next');
                
            }
        });

    }

    init();

});

