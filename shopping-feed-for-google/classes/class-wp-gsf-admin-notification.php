<?php

if ( ! class_exists( 'WP_GSF_Admin_Notifications' ) ) {

    class WP_GSF_Admin_Notifications {
    
        const TRANSIENT_KEY = 'gsf_wp_dashboard_notifications';

        // Constructor to initialize the class with JSON data and add hooks
        public function __construct() {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
            add_action('admin_notices', [ $this, 'display_notifications' ] );
            add_action( 'wp_ajax_gsf_dismiss_notice', [ $this, 'gsf_dismiss_notice' ] );
        }

        public function enqueue_assets() {
            $screen = get_current_screen();
            if ( ! $screen || ! in_array($screen->id,WP_GSF_NOTIFICATION_SCREENS) ) {
                return;
            }

            wp_enqueue_style( 'gsf-notification-admin-style', plugin_dir_url( __DIR__ ) . 'assets/css/gsf-styles.min.css' );
            wp_enqueue_script( 'gsf-notification-admin-js', plugin_dir_url( __DIR__ ) . 'assets/js/gsf-script.min.js', [], false, true );
            wp_localize_script( 'gsf-notification-admin-js', 'GSF_Ajax', [
                'nonce' => wp_create_nonce( 'gsf_dismiss_nonce' ),
                'ajax_url' => admin_url( 'admin-ajax.php' )
            ]);
        }
    
        // Retrieve notifications from the API
        protected function get_notifications() {
            $refresh_notifications = get_option( 'wp_gsf_refresh_notifications', false );
            if($refresh_notifications == false){
                $cached = get_transient( self::TRANSIENT_KEY );
                if ( false !== $cached ) {
                    return $cached;
                }
            }
            
            $client = new WP_GSF_HttpClient();
            $data = $client->callAPI('wc-api/get-notifications', [], 'POST');

            if ( is_wp_error( $data ) ) {
                return [];
            }
            $data = json_decode(json_encode($data), true);
            if ( $data['error'] == 1 ) {
                return [];
            }
            $data = $data['data'] ?? [];

            if ( ! is_array( $data ) ) {
                return [];
            }
            // Save to transient for 6 hours
            set_transient( self::TRANSIENT_KEY, $data, 6 * HOUR_IN_SECONDS );
            update_option( 'wp_gsf_refresh_notifications', false );

            return $data;
        }

        // Function to display all notifications
        public function display_notifications() {
            $screen = get_current_screen();
            if ( ! $screen || ! in_array($screen->id,WP_GSF_NOTIFICATION_SCREENS) ) {
                return;
            }

            $notifications = $this->get_notifications();   
            if ( empty( $notifications ) || ! is_array( $notifications )) {
                return;
            }

            $dismissed = get_user_meta( get_current_user_id(), '_gsf_dismissed_notices', true );
            $dismissed = is_array( $dismissed ) ? $dismissed : [];

            $notifications = array_filter( $notifications, function($value, $key) use ($dismissed) {
                    return !in_array($value['notification_id'], array_keys($dismissed)) || 
                           (!empty($dismissed[$value['notification_id']]) && time() > strtotime($dismissed[$value['notification_id']]));
                }, ARRAY_FILTER_USE_BOTH
            );

            if ( empty( $notifications ) || ! is_array( $notifications )) {
                return;
            }    

            $this->render_notification($notifications);
        }

        private function render_notification($notifications) {
            $notification_type = $notifications[0]['type'] ?? 'info';
            echo "<div class='gsf-wp-carousel-main notice notice-{$notification_type}'>";
                echo "<div class='gsf-wp-carousel-outer'>";
                    echo "<div class='gsf-wp-carousel-container'>";
                        foreach ($notifications as $notification) {
                            $this->render_notification_item(
                                isset($notification['title']) ? $notification['title'] : WP_GSF_PLUGIN_NAME,
                                isset($notification['notification_id']) ? $notification['notification_id'] : '',
                                isset($notification['message']) ? $notification['message'] : '',
                                isset($notification['type']) ? $notification['type'] : 'info',
                                isset($notification['dismissible']) ? $notification['dismissible'] : true,
                                isset($notification['icon']) ? $notification['icon'] : ''
                            );
                        }
                        echo "</div>";
                        echo "<div class='gsf-wp-carousel-controls'>";
                            echo "<div class='gsf-wp-notification-dismiss'> 
                                <button type='button' class='gsf-wp-notification-dismiss-button-group btn btn-white-outline dropdown-toggle' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>Dismiss <span class='gsf-down-dashicons dashicons dashicons-arrow-down-alt2'></span></button>
                                <ul class='gsf-wp-notification-dropdown-menu'>
                                    <li><a href='javascript:void(0);' class='gsf-wp-notification-dismiss-btn' data-days='1'>Today</a></li>
                                    <li><a href='javascript:void(0);' class='gsf-wp-notification-dismiss-btn' data-days='3'>3 Days</a></li>
                                    <li><a href='javascript:void(0);' class='gsf-wp-notification-dismiss-btn' data-days='5'>5 Days</a></li>
                                    <li><a href='javascript:void(0);' class='gsf-wp-notification-dismiss-btn' data-days='7'>7 Days</a></li>
                                </ul>
                            </div>";
                            echo "<div class='gsf-slide-navigation'> <a href='javascript:void(0);' class='gsf-wp-slide-navigation gsf-wp-slide-prev dashicons dashicons-arrow-left-alt2'></a>
                                    <span class='gsf-wp-carousel-counter'>1 / 1</span>
                                <a href='javascript:void(0);' class='gsf-wp-slide-navigation gsf-wp-slide-next dashicons dashicons-arrow-right-alt2'></a></div>";
                    echo "</div>";
                echo "</div>";
            echo "</div>";
        }

        // Function to render a single notification
        private function render_notification_item($title, $notification_id, $message, $type , $dismissible = true, $icon = '',$is_active = true) {
            $types = array('info', 'warning', 'error', 'success');
            
            // Ensure the type is valid; if not, default to 'info'
            if (!in_array($type, $types)) {
                $type = 'info';
            }

            $classes = '';
            if ($is_active) {
                $classes .= ' active';
            }

            echo "<div class='gsf-wp-carousel-slide {$classes}' data-type='{$type}' data-id='{$notification_id}'>
                <div class='gsf-wp-carousel-inner'>
                    <div class='gsf-notification-icon'>
                        <img src='".plugin_dir_url( __DIR__ )."assets/img/WC_GSF_Notification_icon.svg' alt='Simprosys' />
                    </div>
                    <div>
                        <div class='gsf-notification-title'>{$title}</div>
                        <div class='gsf-notification-content'><div class='gsf-notification-detail'>{$message}</div></div>
                    </div>
                </div>
            </div>";
        }

        public function gsf_dismiss_notice() {
            check_ajax_referer( 'gsf_dismiss_nonce', 'nonce' );
            
            $gsf_notice_id = sanitize_text_field( $_POST['gsf_notice_id'] ?? '' );
            $dismiss_days = sanitize_text_field( $_POST['dismiss_days'] ?? 1 );
            if ( empty( $gsf_notice_id ) || empty($dismiss_days) ) wp_send_json_error();
    
            $user_id   = get_current_user_id();
            $dismissed = get_user_meta( $user_id, '_gsf_dismissed_notices', true );
            $dismissed = is_array( $dismissed ) ? $dismissed : [];
    
            $dismissed[$gsf_notice_id] = date('Y-m-d H:i:s', strtotime('+' . $dismiss_days . ' days'));;
            update_user_meta( $user_id, '_gsf_dismissed_notices', $dismissed );
    
            wp_send_json_success();
        }
    

    }
    new WP_GSF_Admin_Notifications();
}
