<?php
/**
 * GitHub updater for RDSCO Elementor Widgets.
 */

namespace RDSCO\ElementorWidgets;

if (!defined('ABSPATH')) {
    exit;
}

final class GitHub_Updater
{
    const SLUG = 'rdsco-elementor-widgets';
    const REPOSITORY = 'masoodvahid/istt-acf-contact-list';
    const MANIFEST_URL = 'https://raw.githubusercontent.com/masoodvahid/istt-acf-contact-list/main/update.json';

    /** @var array|false|null */
    private static $manifest = null;

    public static function init()
    {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('update_plugins_github.com', [__CLASS__, 'update_uri_result'], 10, 4);
        add_filter('plugins_api', [__CLASS__, 'plugin_information'], 20, 3);
        add_filter('upgrader_source_selection', [__CLASS__, 'normalize_source_folder'], 10, 4);
    }

    private static function get_manifest()
    {
        if (null !== self::$manifest) {
            return self::$manifest;
        }

        $url = add_query_arg(
            [
                'installed' => RDSCO_ELEMENTOR_WIDGETS_VERSION,
                'check'     => time(),
            ],
            self::MANIFEST_URL
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => 10,
                'redirection' => 3,
                'headers'     => [
                    'Accept'     => 'application/json',
                    'User-Agent' => 'RDSCO-Elementor-Widgets/' . RDSCO_ELEMENTOR_WIDGETS_VERSION,
                ],
            ]
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            self::$manifest = false;
            return false;
        }

        $manifest = json_decode(wp_remote_retrieve_body($response), true);
        if (
            !is_array($manifest) ||
            empty($manifest['version']) ||
            empty($manifest['download_url'])
        ) {
            self::$manifest = false;
            return false;
        }

        $manifest['version'] = sanitize_text_field($manifest['version']);
        $manifest['download_url'] = esc_url_raw($manifest['download_url']);

        if (!$manifest['download_url']) {
            self::$manifest = false;
            return false;
        }

        // Only cache for the current PHP request. WordPress' own update transient
        // remains authoritative, so “Check again” always performs a fresh request.
        self::$manifest = $manifest;
        return self::$manifest;
    }

    private static function update_item($manifest)
    {
        $item = new \stdClass();
        $item->id = 'github.com/' . self::REPOSITORY;
        $item->slug = self::SLUG;
        $item->plugin = RDSCO_ELEMENTOR_WIDGETS_BASENAME;
        $item->new_version = $manifest['version'];
        $item->url = isset($manifest['homepage'])
            ? esc_url_raw($manifest['homepage'])
            : 'https://github.com/' . self::REPOSITORY;
        $item->package = $manifest['download_url'];
        $item->requires = isset($manifest['requires']) ? $manifest['requires'] : '';
        $item->tested = isset($manifest['tested']) ? $manifest['tested'] : '';
        $item->requires_php = isset($manifest['requires_php']) ? $manifest['requires_php'] : '';

        return $item;
    }

    public static function check_for_update($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $manifest = self::get_manifest();
        if (!$manifest) {
            return $transient;
        }

        $item = self::update_item($manifest);
        if (version_compare(RDSCO_ELEMENTOR_WIDGETS_VERSION, $manifest['version'], '<')) {
            $transient->response[RDSCO_ELEMENTOR_WIDGETS_BASENAME] = $item;
        } else {
            $transient->no_update[RDSCO_ELEMENTOR_WIDGETS_BASENAME] = $item;
        }

        return $transient;
    }

    public static function update_uri_result($update, $plugin_data, $plugin_file, $locales)
    {
        unset($plugin_data, $locales);

        if (RDSCO_ELEMENTOR_WIDGETS_BASENAME !== $plugin_file) {
            return $update;
        }

        $manifest = self::get_manifest();
        if (
            !$manifest ||
            !version_compare(RDSCO_ELEMENTOR_WIDGETS_VERSION, $manifest['version'], '<')
        ) {
            return false;
        }

        return (array) self::update_item($manifest);
    }

    public static function plugin_information($result, $action, $args)
    {
        if (
            'plugin_information' !== $action ||
            empty($args->slug) ||
            self::SLUG !== $args->slug
        ) {
            return $result;
        }

        $manifest = self::get_manifest();
        if (!$manifest) {
            return $result;
        }

        $info = new \stdClass();
        $info->name = isset($manifest['name'])
            ? sanitize_text_field($manifest['name'])
            : 'افزونه های المنتور راهکار دیجیتال شریف';
        $info->slug = self::SLUG;
        $info->version = $manifest['version'];
        $info->author = isset($manifest['author']) ? wp_kses_post($manifest['author']) : '';
        $info->homepage = isset($manifest['homepage']) ? esc_url_raw($manifest['homepage']) : '';
        $info->requires = isset($manifest['requires']) ? $manifest['requires'] : '';
        $info->tested = isset($manifest['tested']) ? $manifest['tested'] : '';
        $info->requires_php = isset($manifest['requires_php']) ? $manifest['requires_php'] : '';
        $info->last_updated = isset($manifest['last_updated']) ? $manifest['last_updated'] : '';
        $info->download_link = $manifest['download_url'];
        $info->sections = isset($manifest['sections']) && is_array($manifest['sections'])
            ? $manifest['sections']
            : [];

        return $info;
    }

    public static function normalize_source_folder($source, $remote_source, $upgrader, $hook_extra)
    {
        unset($upgrader);

        $is_this_plugin = !empty($hook_extra['plugin'])
            && RDSCO_ELEMENTOR_WIDGETS_BASENAME === $hook_extra['plugin'];

        if (
            !$is_this_plugin &&
            !empty($hook_extra['plugins']) &&
            is_array($hook_extra['plugins'])
        ) {
            $is_this_plugin = in_array(
                RDSCO_ELEMENTOR_WIDGETS_BASENAME,
                $hook_extra['plugins'],
                true
            );
        }

        if (!$is_this_plugin) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $source;
        }

        $installed_folder = dirname(RDSCO_ELEMENTOR_WIDGETS_BASENAME);
        if ('.' === $installed_folder || '' === $installed_folder) {
            $installed_folder = self::SLUG;
        }

        $normalized_source = trailingslashit($remote_source)
            . sanitize_file_name($installed_folder)
            . '/';

        if (untrailingslashit($source) === untrailingslashit($normalized_source)) {
            return $source;
        }

        if ($wp_filesystem->exists($normalized_source)) {
            $wp_filesystem->delete($normalized_source, true);
        }

        if (!$wp_filesystem->move($source, $normalized_source, true)) {
            return new \WP_Error(
                'rdsco_elementor_widgets_update_folder',
                'پوشه بسته بروزرسانی افزونه قابل آماده‌سازی نیست.'
            );
        }

        return $normalized_source;
    }
}
