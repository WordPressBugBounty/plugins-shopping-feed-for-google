<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

class WP_GSF_Feedback
{
    public function __construct()
    {
        add_action('admin_footer', array($this, 'gsf_feedback_scripts'));
        add_action( 'rest_api_init', array( $this, 'gsf_feedback_register_routes' ) );
    }

    /**
	 * Register the routes for deactive plugin feedback.
	 */
	public function gsf_feedback_register_routes() {
		register_rest_route(
			'gsf/v1', '/deactive-feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_deactive_feedback_reason' ),
				// 'permission_callback' => array( $this, 'install_plugins' ),
                'permission_callback' => function () {
                    return current_user_can( 'install_plugins' );
                }
			)
		);
	}

    /**
	 * Set Uninstall reasons & Feedback into database
	 *
	 * @param object $request request data.
	 * @return void
	 */
	public function set_deactive_feedback_reason(WP_REST_Request $request ) {
        $reason_data = array(
            'reasons' => $request->get_param('reasons'),
            'feedback' => sanitize_text_field( wp_unslash( $request->get_param('feedback') ) ),
        );
        set_transient('gsf_deactivation_feedback',json_encode($reason_data),120);
    }
    
    /**
     * Method get_feedback_reasons For list all reasons 
     *
     * @return array
     */
    private function get_feedback_reasons()
    {
        return array(
            "I no longer need the plugin",
            "The plugin is difficult to set up or use",
            "I'm not satisfied with support",
            "The plugin lacks required features",
            "The plugin is too expensive or has unexpected costs",
            "I have security or privacy concerns",
            "I'm temporarily deactivating for troubleshooting",
            "The plugin is incompatible with my theme or other plugins",
            "I'm switching to a different platform or solution",
        );
    }
    
    /**
     * Method gsf_feedback_scripts for rendor Popup view
     *
     */
    public function gsf_feedback_scripts()
    {
        global $pagenow;
        if ('plugins.php' != $pagenow) {
            return;
        }
        $reasons = $this->get_feedback_reasons();
?>
        <div class="gsf-feedback-modal" id="gsf-feedback-modal">
            <div class="gsf-feedback-modal-wrap">
                <div class="gsf-feedback-modal-header">
                    <h3><?php echo esc_html('Quick Feedback'); ?></h3>
                    <button type="button" class="gsf-feedback-modal-close"></button>
                </div>
                <div class="gsf-feedback-modal-body">
                    <h4 class="gsf-feedback-caption"><?php echo esc_html('If you have a moment, please let us know why you are deactivating ').WP_GSF_PLUGIN_NAME; ?></h4>
                    <div class="gsf-form-checkbox-group gsf-form-group">
                        <div class="reason-heading-title"><strong>Select all that apply:</strong></div>
                        <?php foreach ($reasons as $key => $reason) { ?>
                            <div class="gsf-form-item">
                                <input class="gsf-feedback-input-checkbox" id="reason_<?php echo $key; ?>" type="checkbox" value="<?php echo $reason; ?>" name="feedback[]" />
                                <label class="gsf-feedback-label" for="reason_<?php echo $key; ?>"><?php echo $reason; ?></label>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="gsf-form-group">
                        <textarea rows="5" cols="45" maxlength="500"  class="gsf-feedback-input-field" name="other_feedback" placeholder="Other (Please specify)"></textarea>
                    </div>
                    <div>
                        <strong>Note: </strong>Your plugin subscription will be automatically canceled <strong>7 hours</strong> after deactivation
                    </div>
                </div>
                <div class="gsf-feedback-modal-footer">
                    <button class="button-primary gsf-feedback-modal-submit" disabled>
                        <?php echo esc_html('Submit & Deactivate'); ?>
                    </button>
                    <button class="button-secondary gsf-feedback-modal-skip">
                        <?php echo esc_html('Skip & Deactivate'); ?>
                    </button>
                    <button class="button-secondary button-link-delete gsf-cancel-modal">
                        <?php echo esc_html('Cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
        <style type="text/css">.gsf-feedback-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgb(0 0 0 / .5);z-index:999}.gsf-feedback-modal.modal-active{display:flex;align-items:center;justify-content:center}.gsf-feedback-modal-wrap{max-width:700px;position:relative;background:#fff;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);border-radius:8px;box-shadow:0 4px 10px rgb(0 0 0 / .3);z-index:1000}.gsf-feedback-modal-header{background:#195279;padding:12px 20px;position:relative}.gsf-feedback-modal-header h3{display:inline-block;color:#fff;line-height:150%;margin:0}.gsf-feedback-modal-body{font-size:14px;line-height:1.5em;padding:5px 30px 20px 30px;box-sizing:border-box}.gsf-feedback-modal-body h3{font-size:15px}.gsf-feedback-modal-body .input-text,.gsf-feedback-modal-body{width:100%}.gsf-feedback-modal .gsf-form-group{margin-bottom:1rem}.gsf-feedback-modal-footer{padding:0 20px 15px 20px;display:flex}.gsf-feedback-modal-footer .gsf-feedback-modal-submit{background-color:#1863DC;border-color:#1863DC;color:#FFF}.gsf-feedback-modal-footer .gsf-feedback-modal-skip, .gsf-feedback-modal-footer .gsf-cancel-modal{margin-left:20px}.gsf-feedback-modal-footer .gsf-feedback-modal-skip{margin-left:auto;color: #a7aaad !important;background: #f6f7f7 !important;}.gsf-feedback-modal-close{background:#fff0;border:none;color:#fff;cursor:pointer;display:inline-block;width:50px;height:50px;font-size:34px;font-weight:400;text-align:center;padding-bottom:5px;position:absolute;right:0;top:0}button.gsf-feedback-modal-close::before{content:"\00d7";display:inline-block}.gsf-feedback-caption{font-weight:700;font-size:15px;color:#27283C;line-height:1.5;margin:10px 0}input[type="checkbox"].gsf-feedback-input-checkbox{margin:0 5px 0 0;box-shadow:none}.gsf-feedback-label{font-size:13px;cursor:pointer}.gsf-feedback-modal .gsf-feedback-input-field{width:98%;display:flex;padding:5px;-webkit-box-shadow:none;box-shadow:none;font-size:13px}.gsf-form-checkbox-group div.reason-heading-title{margin: 12px 0px 7px 0px;}</style>
        <script type="text/javascript">
            (function($) {
                $(function() {
                    var modal = $('#gsf-feedback-modal');
                    var deactivateLink = '';
                    $('a#deactivate-shopping-feed-for-google').click(function(e) {
                        e.preventDefault();
                        modal.find("input, textarea").each(function() {
                            if ($(this).is(":checkbox")) {
                                $(this).prop("checked", false);
                            } else {
                                $(this).val("");
                            }
                        });
                        modal.addClass('modal-active');
                        deactivateLink = $(this).attr('href');
                        modal.find('a.dont-bother-me').attr('href', deactivateLink).css('float', 'right');
                    });
                    modal.on('click', '.gsf-feedback-modal-skip', function(e) {
						e.preventDefault();
						modal.removeClass('modal-active');
						window.location.href = deactivateLink;
					});
                    modal.on('click', 'button.gsf-feedback-modal-close, button.gsf-cancel-modal', function(e) {
                        e.preventDefault();
                        modal.removeClass('modal-active');
                    }); 
                    modal.on('click',function(e) {
                        if ($(event.target).closest(".gsf-feedback-modal-wrap").length === 0) {
                            e.preventDefault();
                            modal.removeClass('modal-active');
                        }
                    });

                    modal.on('change keyup','input[name="feedback[]"], textarea[name="other_feedback"]',function () {
                        if ($('input[name="feedback[]"]:checked').length > 0 || 
                            $.trim($('textarea[name="other_feedback"]').val()).length > 0) {
                            $('button.gsf-feedback-modal-submit').prop('disabled', false);
                        } else {
                            $('button.gsf-feedback-modal-submit').prop('disabled', true);
                        }
                    });
                    modal.on('click', 'button.gsf-feedback-modal-submit', function(e) {
                        e.preventDefault();
                        var button = $(this);
                        var reasons = $("input[type='checkbox']:checked",modal).map(function () {
                                    return $(this).val();
                                }).get();

                        var feedback = $.trim($("textarea[name='other_feedback']",modal).val());
                        $.ajax({
							url: "<?php echo esc_url_raw( rest_url() . 'gsf/v1/deactive-feedback' ); ?>",
							type: 'POST',
							data: {
								reasons: reasons,
								feedback: feedback,
							},
							beforeSend: function(xhr) {
								button.addClass('disabled');
								button.text('Processing...');
								xhr.setRequestHeader( 'X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
							},
							complete: function() {
								window.location.href = deactivateLink;
							}
						});
                    }); 
                });
            }(jQuery));
        </script>
<?php
    }
}
