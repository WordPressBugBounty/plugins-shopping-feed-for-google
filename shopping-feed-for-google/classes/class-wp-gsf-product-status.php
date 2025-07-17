<?php
class GSF_WC_Product_Status {

	private $meta_data_key = 'gsfwc_product_feed_status';
    private $icon_map = 
        [
            'google'    => WP_GSF_CDN_IMAGES_PATH.'plugin/images/table_google_icon.svg',
            'facebook'  => WP_GSF_CDN_IMAGES_PATH.'images/meta-icon.svg',
            'microsoft' => WP_GSF_CDN_IMAGES_PATH.'images/microsoft-icon.svg',
        ];
    public function __construct() {
        add_filter('manage_edit-product_columns', [$this, 'gsfwc_add_product_column']);
        add_action('manage_product_posts_custom_column', [$this, 'gsfwc_show_product_column_data'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'gsfwc_enqueue_admin_scripts']);
        add_action('add_meta_boxes', [$this, 'gsfwc_add_status_meta_box']);
        add_action('wp_ajax_refresh_product_status', [$this, 'gsfwc_ajax_refresh_product_status']);
        add_action('gsfwc_get_feed_status', [$this, 'gsfwc_ajax_refresh_product_status_api']);
        add_action('transition_post_status', [$this,'gsfwc_delete_specific_product_meta_on_trash'],10,3);
        
    }
	
    public function gsfwc_add_product_column($columns) {
        $new_columns  = [];
        $inserted     = false;

        $icon_url     = plugin_dir_path(__DIR__) . 'assets/img/gsfwc-simp-color-icon.svg';
        $tooltip      = __('Product Feed Status', 'shopping-feed-for-google');
        
        $img_tag = '<span class="screen-reader-text">' . __('Feed Status', 'shopping-feed-for-google') . '</span>
                    <img src="'.WP_GSF_CDN_IMAGES_PATH.'images/simp-color-icon.svg"
                    onerror="this.src="'. $icon_url .'"
                    alt="' . esc_attr($tooltip) . '" data-tip="' . esc_attr($tooltip) . '" class="gsfwc-feed-icon tips" 
                    title="' . esc_attr__('Feed Status', 'shopping-feed-for-google') . '" />';
        
        $last_synced        = (int)get_option('gsfwc_last_refresh_time');
        $current_time       = time();
        $diff               = $current_time - $last_synced;
        $show_refresh_btn   = !empty($last_synced) && ($diff >= 86400);
        $last_synced_text   = !empty($last_synced) ? 'Last synced: ' . date_i18n('M j, Y g:i a', strtotime($last_synced)) : 'Not synced yet';
        $refresh_btn        = '';

        if ($show_refresh_btn) {
            $refresh_btn = '<a class="gsfwc-refresh-feed-btn" data-tip="' . esc_attr($last_synced_text) . '" 
            class="gsfwc-refresh-feed-btn tips">
            <img src="'.WP_GSF_CDN_IMAGES_PATH.'images/resync-status-btn.png" alt="Refresh" data-tip="' . esc_attr($last_synced_text) . '" style="width:30px; height:30px;" />
            </a>';
        }
        
        foreach ($columns as $key => $value) {
            if ($key === 'date') { //If we don't find this column 
                $new_columns['gsfwc_feed'] = $img_tag . $refresh_btn;
                $inserted = true;
            }
            $new_columns[$key] = $value;
        }

        if (!$inserted) {
            $new_columns['gsfwc_feed'] = $img_tag . $refresh_btn;
        }
        
        return $new_columns;
    }

    public function gsfwc_enqueue_admin_scripts() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array($screen->id,['edit-product','product']) ) {
            return;
        }
		wp_enqueue_style( 'gsf-product-admin-style', plugin_dir_url( __DIR__ ) . 'assets/css/gsf-product-feed-styles.min.css',[], time());
		wp_enqueue_script( 'gsf-product-feed-admin-js', plugin_dir_url( __DIR__ ) . 'assets/js/gsf-product-feed-admin.min.js', [], false, true );
		wp_localize_script( 'gsf-product-feed-admin-js', 'GSF_Product_Ajax', [
			'nonce'     => wp_create_nonce( 'wp_create_nonce' ),
			'ajax_url'  => admin_url( 'admin-ajax.php' )
		]);
    }

    public function gsfwc_show_product_column_data($column, $post_id) {
        $pending_integration_icon = WP_GSF_CDN_IMAGES_PATH.'images/pending-integration-icon.svg';
        $pending_integration_tooltip = 'Channel is not Integrated.';

        if ($column === 'gsfwc_feed') {
            $meta = get_post_meta($post_id, $this->meta_data_key, true);
            
            if(get_option('wp_gsf_plugin_status_update') === 'Integrated'){
                if (is_array($meta) && !empty($meta)) {
                    $platforms  = ['google', 'facebook', 'microsoft'];
                    $output     = [];

                    foreach ($platforms as $platform) {
                        $status_key = "{$platform}_status";
                        $error_key  = "{$platform}_errors";

                        $status_code    = $meta[$status_key]['label'] ?? '';
                        $status_color   = $meta[$status_key]['color'] ?? 'red';
                        $errors_raw     = $meta[$error_key] ?? '';

                        $img = isset($this->icon_map[$platform]) && $this->icon_map[$platform]
                            ? '<img class="gsfwc-feed-platform-icon" src="'. $this->icon_map[$platform] . '"
                            onerror="this.src=`'. plugin_dir_path(__DIR__) .'assets/img/icon-not-found.svg`">'
                            : ucfirst($platform);

                        if ($status_code !== '') {
                            $tooltip = $class = '';
                            
                            if($errors_raw != ''){
                                $tooltip = '<strong>' . ucwords($platform) . ' Feed Status </strong>' . 
                                '<style=list-style-type: disc;'.$errors_raw.'</style>';
                                $class = 'tips';
                            }

                            $icon = [
                                'Pending'           => WP_GSF_CDN_IMAGES_PATH.'images/pending-icon.svg',
                                'Warning'           => WP_GSF_CDN_IMAGES_PATH.'images/warning-icon.svg',
                                'Error'             => WP_GSF_CDN_IMAGES_PATH.'images/error-icon.svg',
                                'Submitted'         => WP_GSF_CDN_IMAGES_PATH.'images/submission-icon.svg',
                                'Excluded'          => WP_GSF_CDN_IMAGES_PATH.'images/exclude-icon.svg',
                                'ReachedLimit'      => WP_GSF_CDN_IMAGES_PATH.'images/reach-limit-icon.svg',
                                'IntegrationPending'=> WP_GSF_CDN_IMAGES_PATH.'images/pending-integration-icon.svg',
                                'SubmitWithErrors'  => WP_GSF_CDN_IMAGES_PATH.'images/submitted_warning_single.svg',
                            ];

                            $status_icon = "<img src='".$icon[$status_code]."' alt='Error' onerror='this.src=`". plugin_dir_path(__DIR__) ."assets/img/icon-not-found.svg`' class='gsfwc-feed-status-icon'/>";

                            $output[] = "<div class='gsfwc-error-status'>
                                {$img} 
                                <span class='gsfwc-status-code {$class}' data-tip='" . esc_attr($tooltip) . "' > 
                                    {$status_icon}
                                </span>
                            </div>";
                            
                        }
                    }
                    echo implode('', $output);
                } else {
                    echo '<span style="color:#888;">-</span>';
                }
            } else {
                $icon = WP_GSF_CDN_IMAGES_PATH.'images/pending-integration-icon.svg';

                $status_icon = "<img src='".$icon."' alt='Error' onerror='this.src=`". plugin_dir_path(__DIR__) ."assets/img/icon-not-found.svg`' class='gsfwc-feed-status-icon'/>";
                $output[] = "<div class='gsfwc-error-status'>
                    <span class='gsfwc-status-code tips' data-tip='" . esc_attr($pending_integration_tooltip) . "' > 
                        {$status_icon}
                    </span>
                </div>";
                echo implode('', $output);
            }
        }
    }

    public function gsfwc_add_status_meta_box() {
        add_meta_box(
            'gsfwc_product_detail_page',
            __('Simprosys Product Feed Status', 'textdomain'),
            [$this, 'gsfwc_render_status_meta_box'],
            'product',
            'side',
            'core'
        );
    }

    public function gsfwc_render_status_meta_box($post) {
        $value = get_post_meta($post->ID, $this->meta_data_key, true);

        echo '<div class="gsfwc-categorydiv">
		      <ul class="gsfwc-category-tabs">';
        $activeSet = false;
		
		foreach ($this->icon_map as $key => $image_url) {
			$active = !$activeSet ? 'nav-tab-active' : '';
			echo '<li class="tabs"><a href="#tab-' . $key . '" class="' . $active . '"><img src="' . esc_url($image_url) . '"style=height:16px;"></a></li>';
			$activeSet = true;
		}
        echo '</ul>';

        $count = 0;
        $message = [
            'google'    => 'Unlock your growth! Link Google now.',
            'microsoft' => 'Ready for more sales? Connect with Microsoft Advertising.',
            'facebook'  => 'Boost your reach on Meta',
        ];

        foreach ($this->icon_map as $key => $label) {
            $active = $count == 0 ? 'display:block;' : 'display:none;';
			$status_key = "{$key}_status";
			$status_code = $value[$status_key]['label'] ?? '';
            echo '<div style="' . $active . '" id="tab-' . $key . '" class="gsfwc-product-status-icon gsfwc-tabs-panel" ' . $active . '>';
            if(get_option('wp_gsf_plugin_status_update') === 'Integrated'){
                if ($value == "" || empty($value)) { 
                    echo '<div class="gsfwc-product-feed-status"><h2><b>Either product is not synced or missing category or failed to fetch error.</b></h2></div>';
                } else if (isset($value) && !empty($value["{$key}_errors"])) {
                    echo $value["{$key}_errors"];
                } else if(isset($value) && isset($value[$status_key]) && in_array($status_code,['Submitted'])){
                    echo ' <div class="gsfwc-product-feed-status"> <h2><b>Awesome! Product submitted.</b></h2></div>';
                } else if(isset($value) && isset($value[$status_key]) && in_array($status_code,['Excluded'])){
                    echo ' <div class="gsfwc-product-feed-status"> <h2><b>The product is Excluded.</b></h2></div>';
                } else if(isset($value) && isset($value[$status_key]) && in_array($status_code,['ReachedLimit'])){
                    echo '<div class="gsfwc-product-feed-status"><h2><b>Reached limit of products.</b></h2></div>';
                } else if(isset($value) && isset($value[$status_key]) && in_array($status_code,['Pending'])){
                    echo '<div class="gsfwc-product-feed-status"><h2><b>The product is not submitted yet.</b></h2></div>';
                } else {
                    echo '<div class="gsfwc-channel-not-integrated"><span>'.$message[$key].'</span>';
                    echo '<a href="'. admin_url('admin.php?page=shopping-feed-for-google').'" class="button button-primary">
                        Configure Now
                    </a></div>';
                }
            } else {
                echo '<div class="gsfwc-channel-not-integrated"><span>'.$message[$key].'</span>';
                echo '<a href="'. admin_url('admin.php?page=shopping-feed-for-google').'" class="button button-primary">
                    Configure Now
                </a></div>';
            }
            echo '</div>';
            $count++;
        }

        echo '</div>';
    }
	
	public function gsfwc_ajax_refresh_product_status() {
        
        $client = new WP_GSF_HttpClient();
        $response = $client->callAPI('pwc/check-integration-status',[], 'POST');
        if($response->status == 1){
            update_option('wp_gsf_plugin_status_update', "Integrated");
            $selected_product_ids = !empty($_POST['product_ids']) ? array_map('absint', (array) $_POST['product_ids']) : [];
            if (!empty($selected_product_ids)) {
                // Use only selected product IDs
                $product_ids = $selected_product_ids;
            } else {
                $product_ids = wc_get_products([
                    'limit' => -1,
                    'return' => 'ids',
                ]);
            }

            $chunks = array_chunk($product_ids, 100);

            foreach ($chunks as $index => $chunk) {
                as_enqueue_async_action('gsfwc_get_feed_status', ['product_ids' =>$chunk]);
            }
            wp_send_json_success('Started async task');
        } else {
            update_option('wp_gsf_plugin_status_update', "");
        }
	}

    public function gsfwc_ajax_refresh_product_status_api($product_ids) {
        $client = new WP_GSF_HttpClient();
        $data = $client->callAPI('pwc/feed-status', ['product_ids' => $product_ids], 'POST');
		update_option('gsfwc_last_refresh_time', current_time('timestamp'));
		return;
    }

    function gsfwc_delete_specific_product_meta_on_trash($new_status, $old_status, $post) {
        if ($post->post_type === 'product' || $post->post_type === 'product_variation') {
            if (in_array($new_status, ['trash', 'draft', 'private'])) {
                delete_post_meta($post->ID, $this->meta_data_key);
            }
        }
    }
}

new GSF_WC_Product_Status();
