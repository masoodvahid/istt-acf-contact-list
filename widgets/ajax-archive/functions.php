<?php
/**
 * AJAX Archive widget bootstrap.
 */

namespace RDSCO\ElementorWidgets\Widgets\AjaxArchive;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-category-meta.php';
require_once __DIR__ . '/includes/class-archive-service.php';

Category_Meta::init();
Archive_Service::init();

add_action('wp_enqueue_scripts', static function () {
    $base_url = RDSCO_ELEMENTOR_WIDGETS_URL . 'widgets/ajax-archive/assets/';

    wp_register_style(
        'rdsco-ajax-archive-base',
        $base_url . 'css/style.css',
        [],
        RDSCO_ELEMENTOR_WIDGETS_VERSION
    );

    wp_register_style(
        'rdsco-ajax-archive-polish',
        $base_url . 'css/style-2.4.1.css',
        ['rdsco-ajax-archive-base'],
        RDSCO_ELEMENTOR_WIDGETS_VERSION
    );

    wp_register_style(
        'rdsco-ajax-archive',
        $base_url . 'css/style-2.4.2.css',
        ['rdsco-ajax-archive-polish'],
        RDSCO_ELEMENTOR_WIDGETS_VERSION
    );

    wp_register_script(
        'rdsco-ajax-archive',
        $base_url . 'js/app.js',
        [],
        RDSCO_ELEMENTOR_WIDGETS_VERSION,
        true
    );

    wp_localize_script(
        'rdsco-ajax-archive',
        'RDSCOAjaxArchive',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]
    );
});
