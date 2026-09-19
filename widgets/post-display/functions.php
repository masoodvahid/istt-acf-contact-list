<?php
/**
 * Post Display widget bootstrap.
 */

namespace RDSCO\ElementorWidgets\Widgets\PostDisplay;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-post-display-service.php';

Post_Display_Service::init();

add_action('wp_enqueue_scripts', static function () {
    $base_url = RDSCO_ELEMENTOR_WIDGETS_URL . 'widgets/post-display/assets/';

    wp_register_style(
        'rdsco-post-display',
        $base_url . 'css/style.css',
        ['dashicons'],
        RDSCO_ELEMENTOR_WIDGETS_VERSION
    );

    wp_register_script(
        'rdsco-post-display',
        $base_url . 'js/app.js',
        [],
        RDSCO_ELEMENTOR_WIDGETS_VERSION,
        true
    );

    wp_localize_script(
        'rdsco-post-display',
        'RDSCOPostDisplay',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]
    );
});
