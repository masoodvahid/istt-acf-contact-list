<?php
/**
 * Search Box widget bootstrap.
 */

namespace RDSCO\ElementorWidgets\Widgets\SearchBox;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-search-service.php';

Search_Service::init();

add_action('wp_enqueue_scripts', static function () {
    $base_url = RDSCO_ELEMENTOR_WIDGETS_URL . 'widgets/search-box/assets/';

    wp_register_style(
        'rdsco-search-box',
        $base_url . 'css/style.css',
        ['dashicons'],
        RDSCO_ELEMENTOR_WIDGETS_VERSION
    );

    wp_register_script(
        'rdsco-search-box',
        $base_url . 'js/app.js',
        [],
        RDSCO_ELEMENTOR_WIDGETS_VERSION,
        true
    );

    wp_localize_script(
        'rdsco-search-box',
        'RDSCOSearchBox',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]
    );
});
