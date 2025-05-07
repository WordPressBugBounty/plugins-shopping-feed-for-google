var gsf_slide_count = jQuery('.gsf-wp-carousel-slide').length;
var gsf_current_slide = 0;
jQuery(document).ready(function() {
    gsfUpdateSlideCount(0);
    jQuery(document).on('click', '.gsf-wp-slide-next', function() {
        gsfDisplaySlide(gsfGetSliderIndex(gsf_current_slide+1));
    });
    jQuery(document).on('click', '.gsf-wp-slide-prev', function() {
        gsfDisplaySlide(gsfGetSliderIndex(gsf_current_slide-1));
    });

    jQuery(document).on('click', '.gsf-wp-notification-dismiss-button-group', function() {
        jQuery('.gsf-wp-notification-dismiss').toggleClass('open');
    });

    jQuery(document).on('click', function (e) {
        const targetGroup = jQuery('.gsf-wp-notification-dismiss-button-group');
        if (!targetGroup.is(e.target) && targetGroup.has(e.target).length === 0) {
            jQuery('.gsf-wp-notification-dismiss').removeClass('open');
        }
    });
      
    jQuery(document).on('click', '.gsf-wp-notification-dismiss-btn', function() {

        var dismiss_days = jQuery(this).attr('data-days');
        var current_slide = jQuery('.gsf-wp-carousel-slide').eq(gsf_current_slide);
        var current_slide_id = current_slide.attr('data-id');
       
        if(!current_slide_id) return;

        jQuery.ajax({
            url: GSF_Ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'gsf_dismiss_notice',
                gsf_notice_id: current_slide_id,
                dismiss_days: dismiss_days,
                nonce: GSF_Ajax.nonce
            },
            success: function(response) {
                if(response.success) {
                    jQuery('.gsf-wp-carousel-slide').eq(gsf_current_slide).remove();
                    gsf_slide_count = jQuery('.gsf-wp-carousel-slide').length;
                    gsfDisplaySlide(0);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error dismissing notice:', error);
            }
        });
    
       
    });
      
});
function gsfDisplaySlide(index) {
    gsf_current_slide = index;
    var current_slide = jQuery('.gsf-wp-carousel-slide').eq(index);
    gsfUpdateSlideCount(index);
    jQuery('.gsf-wp-carousel-slide').removeClass('active');
    current_slide.addClass('active');
    jQuery('.gsf-wp-carousel-main').attr('class').split(/\s+/).forEach(function (cls) {
        if (/^notice-/.test(cls)) {
            jQuery('.gsf-wp-carousel-main').removeClass(cls);
        }
    });
    jQuery('.gsf-wp-carousel-main').addClass('notice-'+current_slide.attr('data-type'));
}

function gsfGetSliderIndex(index){
    if (index < 0) {
        return gsf_slide_count - 1;
    } else if (index >= gsf_slide_count) {
        return 0;
    } else {
        return index;
    }
}

function gsfUpdateSlideCount(current) {
    current = current || 0;
    console.log(current)
    if (gsf_slide_count > 1) {
        jQuery(".gsf-wp-carousel-counter").html(`${current+1} / ${gsf_slide_count}`);
    } else {
        jQuery('.gsf-slide-navigation').hide();
    }

    if(gsf_slide_count == 0){
        jQuery('.gsf-wp-carousel-main').hide();
    }
}