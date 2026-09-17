<?php
/**
 * Category filter and image metadata.
 */

namespace RDSCO\ElementorWidgets\Widgets\AjaxArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Category_Meta {

    public const FILTER_META_KEY = '_rdsco_has_filter';
    public const IMAGE_META_KEY  = '_rdsco_category_image_id';
    public const LEGACY_FILTER_META_KEY = '_istt_has_filter';
    public const LEGACY_IMAGE_META_KEY  = '_istt_category_image_id';
    public const NONCE_ACTION    = 'rdsco_category_meta_action';
    public const NONCE_NAME      = 'rdsco_category_meta_nonce';

    public static function init(): void {
        add_action( 'category_add_form_fields', [ __CLASS__, 'render_add_fields' ] );
        add_action( 'category_edit_form_fields', [ __CLASS__, 'render_edit_fields' ] );
        add_action( 'created_category', [ __CLASS__, 'save' ] );
        add_action( 'edited_category', [ __CLASS__, 'save' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_filter( 'manage_edit-category_columns', [ __CLASS__, 'add_image_column' ] );
        add_filter( 'manage_category_custom_column', [ __CLASS__, 'render_image_column' ], 10, 3 );
    }

    public static function render_add_fields(): void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        ?>
        <div class="form-field">
            <label for="rdsco_has_filter">دارای فیلتر</label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="rdsco_has_filter" id="rdsco_has_filter" value="1">
                نمایش فیلتر در آرشیو این دسته
            </label>
        </div>

        <div class="form-field">
            <label>تصویر دسته</label>
            <?php self::render_image_picker( 0 ); ?>
        </div>
        <?php
    }

    public static function render_edit_fields( \WP_Term $term ): void {
        $has_filter = self::has_filter( $term->term_id ) ? '1' : '';
        $image_id   = self::get_image_id( $term->term_id );

        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="rdsco_has_filter">دارای فیلتر</label></th>
            <td>
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="rdsco_has_filter" id="rdsco_has_filter" value="1" <?php checked( $has_filter, '1' ); ?>>
                    نمایش فیلتر در آرشیو این دسته
                </label>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label>تصویر دسته</label></th>
            <td><?php self::render_image_picker( $image_id ); ?></td>
        </tr>
        <?php
    }

    private static function render_image_picker( int $image_id ): void {
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
        ?>
        <div class="rdsco-category-image-picker">
            <input type="hidden" class="rdsco-category-image-id" name="rdsco_category_image_id" value="<?php echo esc_attr( $image_id ); ?>">

            <div class="rdsco-category-image-preview" style="width:150px;height:100px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;overflow:hidden;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <?php if ( $image_url ) : ?>
                    <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php endif; ?>
            </div>

            <button type="button" class="button rdsco-category-image-select">انتخاب / تغییر تصویر</button>
            <button type="button" class="button rdsco-category-image-remove">حذف تصویر</button>
        </div>
        <?php
    }

    public static function save( int $term_id ): void {
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }

        if ( isset( $_POST['rdsco_has_filter'] ) ) {
            update_term_meta( $term_id, self::FILTER_META_KEY, '1' );
        } else {
            delete_term_meta( $term_id, self::FILTER_META_KEY );
            delete_term_meta( $term_id, self::LEGACY_FILTER_META_KEY );
        }

        $image_id = isset( $_POST['rdsco_category_image_id'] )
            ? absint( $_POST['rdsco_category_image_id'] )
            : 0;

        if ( $image_id ) {
            update_term_meta( $term_id, self::IMAGE_META_KEY, $image_id );
        } else {
            delete_term_meta( $term_id, self::IMAGE_META_KEY );
            delete_term_meta( $term_id, self::LEGACY_IMAGE_META_KEY );
        }
    }

    public static function has_filter( int $term_id ): bool {
        $value = get_term_meta( $term_id, self::FILTER_META_KEY, true );
        if ( '1' === (string) $value ) {
            return true;
        }

        return '1' === (string) get_term_meta( $term_id, self::LEGACY_FILTER_META_KEY, true );
    }

    public static function get_image_id( int $term_id ): int {
        $image_id = absint( get_term_meta( $term_id, self::IMAGE_META_KEY, true ) );
        if ( $image_id ) {
            return $image_id;
        }

        return absint( get_term_meta( $term_id, self::LEGACY_IMAGE_META_KEY, true ) );
    }

    public static function add_image_column( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            if ( 'name' === $key ) {
                $new_columns['rdsco_category_image'] = 'تصویر';
            }
            $new_columns[ $key ] = $label;
        }
        return $new_columns;
    }

    public static function render_image_column( string $content, string $column_name, int $term_id ): string {
        if ( 'rdsco_category_image' !== $column_name ) {
            return $content;
        }

        $image_id = self::get_image_id( $term_id );
        if ( ! $image_id ) {
            return '<span style="color:#a7aaad;">—</span>';
        }

        return wp_get_attachment_image(
            $image_id,
            [ 48, 48 ],
            false,
            [
                'style' => 'width:48px;height:48px;object-fit:cover;border-radius:6px;',
                'alt'   => '',
            ]
        );
    }

    public static function enqueue_admin_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'category' !== $screen->taxonomy ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'rdsco-category-meta',
            RDSCO_ELEMENTOR_WIDGETS_URL . 'widgets/ajax-archive/assets/js/category-meta.js',
            [ 'jquery' ],
            RDSCO_ELEMENTOR_WIDGETS_VERSION,
            true
        );
    }
}
