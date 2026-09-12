<?php
/**
 * Lightweight GitHub updater for ISTT ACF Contact List.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ISTT_ACF_Contact_List_GitHub_Updater
{
    const SLUG = 'istt-acf-contact-list';
    const REPOSITORY = 'masoodvahid/istt-acf-contact-list';
    const MANIFEST_URL = 'https://raw.githubusercontent.com/masoodvahid/istt-acf-contact-list/main/update.json';
    const CACHE_KEY = 'istt_acf_contact_list_update_manifest';

    public static function init()
    {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_information'], 20, 3);
        add_filter('upgrader_source_selection', [__CLASS__, 'normalize_source_folder'], 10, 4);
        add_action('upgrader_process_complete', [__CLASS__, 'clear_cache_after_update'], 10, 2);
    }

    private static function get_manifest($force = false)
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            add_query_arg('installed', ISTT_ACF_CONTACT_LIST_VERSION, self::MANIFEST_URL),
            [
                'timeout' => 10,
                'redirection' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'ISTT-ACF-Contact-List/' . ISTT_ACF_CONTACT_LIST_VERSION,
                ],
            ]
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $manifest = json_decode(wp_remote_retrieve_body($response), true);
        if (
            !is_array($manifest) ||
            empty($manifest['version']) ||
            empty($manifest['download_url'])
        ) {
            return false;
        }

        $manifest['version'] = sanitize_text_field($manifest['version']);
        $manifest['download_url'] = esc_url_raw($manifest['download_url']);

        if (!$manifest['download_url']) {
            return false;
        }

        set_site_transient(self::CACHE_KEY, $manifest, 6 * HOUR_IN_SECONDS);
        return $manifest;
    }

    private static function update_item($manifest)
    {
        $item = new stdClass();
        $item->id = 'github.com/' . self::REPOSITORY;
        $item->slug = self::SLUG;
        $item->plugin = ISTT_ACF_CONTACT_LIST_BASENAME;
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
        if (version_compare(ISTT_ACF_CONTACT_LIST_VERSION, $manifest['version'], '<')) {
            $transient->response[ISTT_ACF_CONTACT_LIST_BASENAME] = $item;
        } else {
            $transient->no_update[ISTT_ACF_CONTACT_LIST_BASENAME] = $item;
        }

        return $transient;
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

        $info = new stdClass();
        $info->name = isset($manifest['name'])
            ? sanitize_text_field($manifest['name'])
            : 'ISTT ACF Contact List';
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
        $is_this_plugin = !empty($hook_extra['plugin'])
            && ISTT_ACF_CONTACT_LIST_BASENAME === $hook_extra['plugin'];

        if (
            !$is_this_plugin &&
            !empty($hook_extra['plugins']) &&
            is_array($hook_extra['plugins'])
        ) {
            $is_this_plugin = in_array(
                ISTT_ACF_CONTACT_LIST_BASENAME,
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

        $installed_folder = dirname(ISTT_ACF_CONTACT_LIST_BASENAME);
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
            return new WP_Error(
                'istt_acl_update_folder',
                'پوشه بسته بروزرسانی افزونه قابل آماده‌سازی نیست.'
            );
        }

        return $normalized_source;
    }

    public static function clear_cache_after_update($upgrader, $options)
    {
        if (
            isset($options['action'], $options['type']) &&
            'update' === $options['action'] &&
            'plugin' === $options['type']
        ) {
            delete_site_transient(self::CACHE_KEY);
        }
    }
}
