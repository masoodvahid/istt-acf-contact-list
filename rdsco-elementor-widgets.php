<?php
/**
 * Plugin Name: افزونه های المنتور راهکار دیجیتال شریف
 * Plugin URI: https://github.com/masoodvahid/istt-acf-contact-list
 * Description: مجموعه ویجت‌های اختصاصی المنتور راهکار دیجیتال شریف.
 * Version: 2.4.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: elementor
 * Author: Masood Vahid
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rdsco-elementor-widgets
 * Update URI: https://github.com/masoodvahid/istt-acf-contact-list
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RDSCO_ELEMENTOR_WIDGETS_VERSION', '2.4.0');
define('RDSCO_ELEMENTOR_WIDGETS_FILE', __FILE__);
define('RDSCO_ELEMENTOR_WIDGETS_BASENAME', plugin_basename(__FILE__));
define('RDSCO_ELEMENTOR_WIDGETS_DIR', plugin_dir_path(__FILE__));
define('RDSCO_ELEMENTOR_WIDGETS_URL', plugin_dir_url(__FILE__));

require_once RDSCO_ELEMENTOR_WIDGETS_DIR . 'includes/class-github-updater.php';
require_once RDSCO_ELEMENTOR_WIDGETS_DIR . 'includes/class-plugin.php';

\RDSCO\ElementorWidgets\GitHub_Updater::init();

add_action('plugins_loaded', static function () {
    \RDSCO\ElementorWidgets\Plugin::instance();
}, 20);
