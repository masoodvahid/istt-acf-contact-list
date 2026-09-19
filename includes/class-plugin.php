<?php
/**
 * Main plugin bootstrap and widget registry.
 */

namespace RDSCO\ElementorWidgets;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    const MINIMUM_ELEMENTOR_VERSION = '3.20.0';

    /** @var self|null */
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'elementor_missing_notice']);
            return;
        }

        if (
            defined('ELEMENTOR_VERSION') &&
            version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '<')
        ) {
            add_action('admin_notices', [$this, 'elementor_version_notice']);
            return;
        }

        // Keep every widget's supporting logic isolated in its own module.
        require_once RDSCO_ELEMENTOR_WIDGETS_DIR . 'widgets/contact-list/functions.php';
        require_once RDSCO_ELEMENTOR_WIDGETS_DIR . 'widgets/ajax-archive/functions.php';
        require_once RDSCO_ELEMENTOR_WIDGETS_DIR . 'widgets/post-display/functions.php';

        add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    /**
     * Every widget is registered here. A future widget only needs its own folder,
     * class and asset handles plus one registry entry.
     */
    private function widget_registry()
    {
        return [
            [
                'file'  => RDSCO_ELEMENTOR_WIDGETS_DIR . 'widgets/contact-list/class-widget.php',
                'class' => '\\RDSCO\\ElementorWidgets\\Widgets\\ContactList\\Widget',
            ],
            [
                'file'  => RDSCO_ELEMENTOR_WIDGETS_DIR . 'widgets/ajax-archive/class-widget.php',
                'class' => '\\RDSCO\\ElementorWidgets\\Widgets\\AjaxArchive\\Widget',
            ],
            [
                'file'  => RDSCO_ELEMENTOR_WIDGETS_DIR . 'widgets/post-display/class-widget.php',
                'class' => '\\RDSCO\\ElementorWidgets\\Widgets\\PostDisplay\\Widget',
            ],
        ];
    }

    public function register_category($elements_manager)
    {
        $elements_manager->add_category(
            'rdsco-elementor-widgets',
            [
                'title' => esc_html__('افزونه های راهکار دیجیتال شریف', 'rdsco-elementor-widgets'),
                'icon'  => 'eicon-apps',
            ]
        );
    }

    public function register_frontend_assets()
    {
        // Existing Contact List assets are intentionally unchanged.
        $base_url = RDSCO_ELEMENTOR_WIDGETS_URL . 'widgets/contact-list/assets/';

        wp_register_style(
            'rdsco-contact-list',
            $base_url . 'css/style.css',
            ['dashicons'],
            RDSCO_ELEMENTOR_WIDGETS_VERSION
        );

        wp_register_script(
            'rdsco-contact-list-qrcode',
            $base_url . 'js/qrcode.min.js',
            [],
            '1.0.0',
            true
        );

        wp_register_script(
            'rdsco-contact-list',
            $base_url . 'js/app.js',
            ['elementor-frontend', 'rdsco-contact-list-qrcode'],
            RDSCO_ELEMENTOR_WIDGETS_VERSION,
            true
        );
    }

    public function register_widgets($widgets_manager)
    {
        foreach ($this->widget_registry() as $widget) {
            if (!is_readable($widget['file'])) {
                continue;
            }

            require_once $widget['file'];

            if (class_exists($widget['class'])) {
                $class_name = $widget['class'];
                $widgets_manager->register(new $class_name());
            }
        }
    }

    public function elementor_missing_notice()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                echo esc_html__(
                    'برای استفاده از «افزونه های المنتور راهکار دیجیتال شریف»، ابتدا افزونه Elementor را نصب و فعال کنید.',
                    'rdsco-elementor-widgets'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function elementor_version_notice()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    esc_html__(
                        'افزونه های المنتور راهکار دیجیتال شریف به Elementor نسخه %s یا جدیدتر نیاز دارد.',
                        'rdsco-elementor-widgets'
                    ),
                    esc_html(self::MINIMUM_ELEMENTOR_VERSION)
                );
                ?>
            </p>
        </div>
        <?php
    }
}
