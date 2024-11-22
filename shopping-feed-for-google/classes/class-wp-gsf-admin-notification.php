<?php

if ( ! class_exists( 'WP_GSF_Admin_Notifications' ) ) {

    class WP_GSF_Admin_Notifications {
    
        private $notifications = array(); // Array to hold notifications

        // Constructor to initialize the class with JSON data and add hooks
        public function __construct($json_data) {
            $this->parse_json($json_data);
            add_action('admin_notices', array($this, 'display_notifications'));
            //add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        // Function to parse the JSON data and store notifications
        private function parse_json($json_data) {
            $data = json_decode($json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                /*$this->notifications[] = array(
                    'message' => 'Invalid JSON data.',
                    'type' => 'error',
                    'dismissible' => true
                );*/
                return;
            }

            if (!empty($data) && is_array($data)) {
                $this->notifications = array_merge($this->notifications, $data);
            }
        }

        // Function to display all notifications
        public function display_notifications() {
            foreach (
                $this->notifications as $notification) {
                $this->render_notification(
                    $notification['message'], 
                    $notification['type'], 
                    isset($notification['dismissible']) ? $notification['dismissible'] : true,
                    isset($notification['icon']) ? $notification['icon'] : ''
                );
            }
        }

        // Function to render a single notification
        private function render_notification($message, $type = 'info', $dismissible = true, $icon = '') {
            $types = array('info', 'warning', 'error', 'success');
            
            // Ensure the type is valid; if not, default to 'info'
            if (!in_array($type, $types)) {
                $type = 'info';
            }

            $classes = 'notice notice-' . $type;
            if ($dismissible) {
                $classes .= ' is-dismissible';
            }

            $icon_html = $icon ? "<span class='dashicons $icon' style='margin-right: 8px;'></span>" : '';
            echo "<div class='$classes'><p>$icon_html$message</p></div>";
        }

        // Function to enqueue scripts for dismissible notices
        public function enqueue_scripts() {
            wp_enqueue_script('admin-notification-dismiss', plugin_dir_url(__FILE__) . 'admin-notification.js', array('jquery'), null, true);
        }
    }

}
