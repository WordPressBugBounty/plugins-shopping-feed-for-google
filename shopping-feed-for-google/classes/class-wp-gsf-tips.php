<?php 
class GSF_WC_Tips {
    public static function init() {
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'gsf_wc_add_tips'] );
        add_action( 'admin_head', [ __CLASS__, 'add_styles' ] );
    }

    public static function gsf_wc_add_tips() {
        $gsfTipsData = self::fetch_data();
        if ( ! empty( $gsfTipsData ) && is_array($gsfTipsData)) {
            wp_add_dashboard_widget(
                'simprosys_dashboard_tips',
                'Simprotips',
                function() use ( $gsfTipsData ) { self::render_widget( $gsfTipsData ); },
                null, null, 'side', 'high'
            );
        }
    }

    private static function fetch_data() {
        $apiUrl   = "https://simprosys.com/simprotips/wp-json/wp/v2/posts?per_page=5&_embed";
        $response = wp_remote_get( $apiUrl, [ 'timeout' => 5 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $body       = wp_remote_retrieve_body( $response );
        $resultData = json_decode( $body );

        if ( empty( $resultData ) || ! is_array( $resultData ) ) {
            return [];
        }

        $gsfTipsData = [];
        foreach ( $resultData as $post ) {
            $gsfTipsData[] = [
                'title' => $post->title->rendered ?? '',
                'link'  => $post->link ?? '#',
                'date'  => $post->date ?? ''
            ];
        }
        return $gsfTipsData;
    }

    public static function render_widget($gsfTipsData) {
        echo '<div id="simprosys-published-posts" class="simprosys-activity-block">';
        echo '<ul class="simprosys-post-list">';

        $count = 0;
        foreach ( $gsfTipsData as $post ) {
            $dateObj = $post['date'] ? date_create( $post['date'] ) : false;
            if ( ! $dateObj ) continue;

            $day   = date_format( $dateObj, 'd' );
            $month = strtoupper( date_format( $dateObj, 'M' ) );

            echo '<li class="simprosys-post-item">';
            echo '<div class="simprosys-post-date">' . esc_html( $day ) . '<br>' . esc_html( $month ) . '</div>';
            echo '<a href="' . esc_url( $post['link'] ) . '" target="_blank" class="simprosys-post-link">' . esc_html( $post['title'] ) . '</a>';
            echo '</li>';

            if ( ++$count >= 5 ) break;
        }
        echo '<hr>';
        echo '</ul>';
        echo '<div style="text-align: center; margin-top: 10px;">
                <a href="https://simprosys.com/simprotips/" target="_blank" style="text-decoration: none; font-weight: 600;">
                    View All Tips
                    <span aria-hidden="true" class="dashicons dashicons-external"></span>
                </a>
                
              </div>';
        echo '</div>';
    }

    public static function add_styles() {
        $logo_url = esc_url( WP_GSF_CDN_IMAGES_PATH . 'plugin/images/icon-256x256.gif' );

        echo '<style>
            #simprosys_dashboard_tips .hndle {
                display: flex;
                align-items: center;
                gap: 4px;
                justify-content: flex-start;
            }

            #simprosys_dashboard_tips .hndle::before {
                content: "";
                display: inline-block;
                width: 30px;
                height: 25px;
                background-image: url("' . $logo_url . '");
                background-size: auto 100%;
                background-repeat: no-repeat;
            }

            #simprosys-published-posts { margin: 0; padding: 0; }
            .simprosys-widget-title { margin-bottom: 10px; font-size: 14px; font-weight: 600; padding-left: 3px; }
            .simprosys-post-list { list-style: none; margin: 0; padding: 0; }
            .simprosys-post-item { display: flex; align-items: center; margin-bottom: 8px; }
            .simprosys-post-date { min-width: 35px; height: 40px; background-color: #007cba; color: white;
                font-weight: 600; font-size: 13px; text-align: center; line-height: 1.2;
                margin-right: 10px; border-radius: 4px; padding: 3px 5px; display: flex; align-items: center;
                justify-content: center;}
            .simprosys-post-link { font-size: 13px; font-weight: 600; color: #2271b1; text-decoration: none; }
            .simprosys-post-link:hover { text-decoration: underline; }
            #simprosys_dashboard_tips .postbox-header { background-color: #195279; }
            #simprosys_dashboard_tips .hndle.ui-sortable-handle { color: #ffffff; }
            #simprosys_dashboard_tips .handle-order-higher { color: #ffffff; }
            #simprosys_dashboard_tips .order-lower-indicator { color: #ffffff; }
            #simprosys_dashboard_tips .toggle-indicator { color: #ffffff; }
            .dashicons, .dashicons-before:before {
                line-height: 0.80;
            }
        </style>';
    }

}

new GSF_WC_Tips();
