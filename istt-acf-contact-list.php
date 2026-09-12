<?php
/**
 * Plugin Name: ISTT ACF Contact List
 * Plugin URI: https://github.com/masoodvahid/istt-acf-contact-list
 * Description: AJAX contact directory for the contacts category, powered by ACF fields.
 * Version: 1.0.7
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Masood Vahid
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: istt-acf-contact-list
 * Update URI: https://github.com/masoodvahid/istt-acf-contact-list
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISTT_ACF_CONTACT_LIST_VERSION', '1.0.7');
define('ISTT_ACF_CONTACT_LIST_FILE', __FILE__);
define('ISTT_ACF_CONTACT_LIST_BASENAME', plugin_basename(__FILE__));
define('ISTT_ACF_CONTACT_LIST_DIR', plugin_dir_path(__FILE__));
define('ISTT_ACF_CONTACT_LIST_URL', plugin_dir_url(__FILE__));

require_once ISTT_ACF_CONTACT_LIST_DIR . 'includes/core.php';
require_once ISTT_ACF_CONTACT_LIST_DIR . 'includes/settings.php';
require_once ISTT_ACF_CONTACT_LIST_DIR . 'includes/github-updater.php';

ISTT_ACF_Contact_List_GitHub_Updater::init();
