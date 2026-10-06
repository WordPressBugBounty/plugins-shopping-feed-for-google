/**
 * Attach nonce to the listing page ajaxRequestGSF call made by the tracking script.
 * If the nonce has expired (page served from cache), fetch a fresh one and retry once.
 */
(function ($) {
    var gsfwcNonce = GSF_Tracking.nonce;

    $.ajaxPrefilter(function (options, originalOptions) {
        if (typeof options.data !== 'string' || !/(^|&)action=ajaxRequestGSF(&|$)/.test(options.data)) {
            return;
        }
        options.data += '&gsfwc_nonce=' + encodeURIComponent(gsfwcNonce);
        if (originalOptions.gsfwcRetried) {
            return;
        }
        var originalError = options.error;
        options.error = function (xhr) {
            var context = this, args = arguments;
            if (xhr.status !== 403) {
                if (typeof originalError === 'function') { originalError.apply(context, args); }
                return;
            }
            $.post(GSF_Tracking.ajax_url, { action: 'gsfwc_refresh_nonce' }, null, 'json')
                .done(function (response) {
                    if (response && response.success) { gsfwcNonce = response.data.nonce; }
                    $.ajax($.extend({}, originalOptions, { gsfwcRetried: true }));
                })
                .fail(function () {
                    if (typeof originalError === 'function') { originalError.apply(context, args); }
                });
        };
    });
})(jQuery);
