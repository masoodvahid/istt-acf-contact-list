<?php
/**
 * AJAX archive querying and rendering service.
 */

namespace RDSCO\ElementorWidgets\Widgets\AjaxArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Archive_Service {

    public const AJAX_ACTION  = 'rdsco_archive_fetch';
    public const NONCE_ACTION = 'rdsco_archive_fetch';

    public static function init(): void {
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ __CLASS__, 'ajax_fetch' ] );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ __CLASS__, 'ajax_fetch' ] );
    }

    public static function get_current_category(): ?\WP_Term {
        if ( ! is_category() ) {
            return null;
        }

        $term = get_queried_object();
        return ( $term instanceof \WP_Term && 'category' === $term->taxonomy ) ? $term : null;
    }

    public static function get_filter_root_id( int $term_id ): int {
        if ( Category_Meta::has_filter( $term_id ) ) {
            return $term_id;
        }

        foreach ( get_ancestors( $term_id, 'category' ) as $ancestor_id ) {
            if ( Category_Meta::has_filter( $ancestor_id ) ) {
                return absint( $ancestor_id );
            }
        }

        return 0;
    }

    public static function get_child_categories( int $root_id ): array {
        $terms = get_terms(
            [
                'taxonomy'   => 'category',
                'parent'     => $root_id,
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        return is_wp_error( $terms ) ? [] : $terms;
    }

    public static function get_category_tags( int $category_id ): array {
        $post_ids = get_posts(
            [
                'post_type'              => 'post',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'tax_query'              => [
                    [
                        'taxonomy'         => 'category',
                        'field'            => 'term_id',
                        'terms'            => [ $category_id ],
                        'include_children' => true,
                    ],
                ],
            ]
        );

        if ( empty( $post_ids ) ) {
            return [];
        }

        $tags = wp_get_object_terms(
            $post_ids,
            'post_tag',
            [
                'orderby' => 'name',
                'order'   => 'ASC',
            ]
        );

        return is_wp_error( $tags ) ? [] : $tags;
    }

    public static function query( array $args ): \WP_Query {
        $root_id      = absint( $args['root_id'] ?? 0 );
        $category_id  = absint( $args['category_id'] ?? 0 );
        $tag_id       = absint( $args['tag_id'] ?? 0 );
        $search       = sanitize_text_field( $args['search'] ?? '' );
        $page         = max( 1, absint( $args['page'] ?? 1 ) );
        $per_page     = max( 1, min( 48, absint( $args['per_page'] ?? 16 ) ) );
        $query_cat_id = $category_id ?: $root_id;

        $tax_query = [
            [
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms'            => [ $query_cat_id ],
                'include_children' => true,
            ],
        ];

        if ( $tag_id ) {
            $tax_query[] = [
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => [ $tag_id ],
            ];
        }

        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }

        return new \WP_Query(
            [
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $per_page,
                'paged'               => $page,
                'ignore_sticky_posts' => true,
                's'                   => $search,
                'tax_query'           => $tax_query,
            ]
        );
    }

    public static function render_posts( \WP_Query $query, array $settings ): string {
        ob_start();

        if ( ! $query->have_posts() ) {
            $empty_text = $settings['empty_text'] ?? 'مطلبی پیدا نشد.';
            ?>
            <div class="rdsco-archive-empty">
                <span class="rdsco-archive-empty-icon" aria-hidden="true">⌕</span>
                <strong><?php echo esc_html( $empty_text ); ?></strong>
                <span>عبارت یا فیلتر دیگری را امتحان کنید.</span>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        $show_date     = 'yes' === ( $settings['show_date'] ?? 'yes' );
        $show_category = 'yes' === ( $settings['show_category'] ?? 'yes' );
        $show_excerpt  = 'yes' === ( $settings['show_excerpt'] ?? 'yes' );
        $excerpt_words = max( 0, absint( $settings['excerpt_length'] ?? 18 ) );
        $display_style = $settings['display_style'] ?? 'card';
        if ( ! in_array( $display_style, [ 'card', 'card_2', 'news_card', 'timeline', 'icon', 'featured_icon', 'icon_card_2', 'hover_box_1' ], true ) ) {
            $display_style = 'card';
        }
        ?>
        <div class="rdsco-archive-grid style-<?php echo esc_attr( $display_style ); ?>">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <?php
                $post_id           = get_the_ID();
                $categories        = get_the_category( $post_id );
                $has_featured_icon = in_array( $display_style, [ 'featured_icon', 'icon_card_2' ], true ) && has_post_thumbnail( $post_id );
                ?>
                <article class="rdsco-archive-card style-<?php echo esc_attr( $display_style ); ?>">
                    <?php if ( 'hover_box_1' === $display_style ) : ?>
                        <a class="rdsco-archive-hover-link" href="<?php the_permalink(); ?>">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <?php echo get_the_post_thumbnail( $post_id, 'medium_large', [ 'loading' => 'lazy', 'class' => 'rdsco-archive-hover-image' ] ); ?>
                            <?php else : ?>
                                <span class="rdsco-archive-hover-placeholder" aria-hidden="true"></span>
                            <?php endif; ?>
                            <div class="rdsco-archive-hover-content">
                                <?php if ( $show_category && ! empty( $categories ) ) : ?>
                                    <span class="rdsco-archive-hover-category"><?php echo esc_html( $categories[0]->name ); ?></span>
                                <?php endif; ?>
                                <h3 class="rdsco-archive-hover-title"><?php echo esc_html( get_the_title() ); ?></h3>
                                <?php if ( $show_date ) : ?>
                                    <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                                <?php endif; ?>
                                <?php if ( $show_excerpt && $excerpt_words > 0 ) : ?>
                                    <span class="rdsco-archive-hover-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerpt_words, '…' ) ); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php else : ?>
                        <?php if ( in_array( $display_style, [ 'card', 'card_2', 'news_card', 'timeline' ], true ) ) : ?>
                            <a class="rdsco-archive-card-image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php echo get_the_post_thumbnail( $post_id, 'medium_large', [ 'loading' => 'lazy' ] ); ?>
                                <?php else : ?>
                                    <span class="rdsco-archive-no-image" aria-hidden="true"></span>
                                <?php endif; ?>
                            </a>
                        <?php else : ?>
                            <a class="rdsco-archive-icon-media <?php echo $has_featured_icon ? 'has-image' : 'has-default-icon'; ?>" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
                                <?php if ( $has_featured_icon ) : ?>
                                    <?php echo get_the_post_thumbnail( $post_id, 'thumbnail', [ 'loading' => 'lazy' ] ); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                        <div class="rdsco-archive-card-content">
                            <?php if ( $show_date || $show_category ) : ?>
                                <div class="rdsco-archive-card-meta">
                                    <?php if ( $show_category && ! empty( $categories ) ) : ?>
                                        <span class="rdsco-archive-card-category"><?php echo esc_html( $categories[0]->name ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $show_date ) : ?>
                                        <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <h3 class="rdsco-archive-card-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>

                            <?php if ( $show_excerpt && $excerpt_words > 0 ) : ?>
                                <div class="rdsco-archive-card-excerpt">
                                    <?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerpt_words, '…' ) ); ?>
                                </div>
                            <?php endif; ?>

                            <a class="rdsco-archive-card-more" href="<?php the_permalink(); ?>">
                                مشاهده مطلب <span aria-hidden="true">←</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public static function render_pagination( int $current_page, int $max_pages ): string {
        if ( $max_pages <= 1 ) {
            return '';
        }

        $current_page = max( 1, $current_page );
        $pages        = [ 1 ];

        for ( $i = max( 2, $current_page - 2 ); $i <= min( $max_pages - 1, $current_page + 2 ); $i++ ) {
            $pages[] = $i;
        }

        if ( $max_pages > 1 ) {
            $pages[] = $max_pages;
        }

        $pages = array_values( array_unique( $pages ) );
        sort( $pages );

        ob_start();
        ?>
        <div class="rdsco-archive-pagination">
            <?php if ( $current_page > 1 ) : ?>
                <button type="button" class="rdsco-page-button is-prev" data-page="<?php echo esc_attr( $current_page - 1 ); ?>">قبلی</button>
            <?php endif; ?>

            <?php $previous = 0; ?>
            <?php foreach ( $pages as $page ) : ?>
                <?php if ( $previous && $page > ( $previous + 1 ) ) : ?>
                    <span class="rdsco-page-dots">…</span>
                <?php endif; ?>
                <button type="button" class="rdsco-page-button <?php echo $page === $current_page ? 'is-active' : ''; ?>" data-page="<?php echo esc_attr( $page ); ?>"><?php echo esc_html( $page ); ?></button>
                <?php $previous = $page; ?>
            <?php endforeach; ?>

            <?php if ( $current_page < $max_pages ) : ?>
                <button type="button" class="rdsco-page-button is-next" data-page="<?php echo esc_attr( $current_page + 1 ); ?>">بعدی</button>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_tag_filters( array $tags, int $active_tag = 0 ): string {
        if ( empty( $tags ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="rdsco-archive-tags" aria-label="فیلتر مطالب بر اساس تگ">
            <button type="button" class="rdsco-archive-tag <?php echo 0 === $active_tag ? 'is-active' : ''; ?>" data-tag="0" aria-pressed="<?php echo 0 === $active_tag ? 'true' : 'false'; ?>">همه</button>
            <?php foreach ( $tags as $tag ) : ?>
                <button type="button" class="rdsco-archive-tag <?php echo (int) $tag->term_id === $active_tag ? 'is-active' : ''; ?>" data-tag="<?php echo esc_attr( $tag->term_id ); ?>" aria-pressed="<?php echo (int) $tag->term_id === $active_tag ? 'true' : 'false'; ?>"><?php echo esc_html( $tag->name ); ?></button>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_filter_sidebar( int $filter_root_id, int $selected_category_id, int $content_term_id, array $settings ): string {
        if ( ! $filter_root_id || 'yes' !== ( $settings['show_filters'] ?? 'yes' ) ) {
            return '';
        }

        $children = 'yes' === ( $settings['show_subcategories'] ?? 'yes' )
            ? self::get_child_categories( $filter_root_id )
            : [];

        $sidebar_html = Category_Meta::get_sidebar_html( $content_term_id );

        if ( empty( $children ) && '' === trim( $sidebar_html ) ) {
            return '';
        }

        $show_images = 'yes' === ( $settings['show_category_images'] ?? 'yes' );

        ob_start();
        ?>
        <aside class="rdsco-archive-sidebar">
            <div class="rdsco-sidebar-heading">فیلتر مطالب</div>

            <?php if ( ! empty( $children ) ) : ?>
                <div class="rdsco-filter-group">
                    <div class="rdsco-filter-group-title">دسته‌بندی</div>
                    <div class="rdsco-filter-list">
                        <button type="button" class="rdsco-filter-item is-category <?php echo 0 === $selected_category_id ? 'is-active' : ''; ?>" data-category="0">
                            <span class="rdsco-filter-name">
                                <span class="rdsco-filter-marquee" title="همه">
                                    <span class="rdsco-filter-marquee-track">همه</span>
                                </span>
                            </span>
                        </button>

                        <?php foreach ( $children as $child ) : ?>
                            <?php
                            $image_id  = Category_Meta::get_image_id( $child->term_id );
                            $image_url = ( $show_images && $image_id ) ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
                            ?>
                            <button type="button" class="rdsco-filter-item is-category <?php echo $selected_category_id === $child->term_id ? 'is-active' : ''; ?>" data-category="<?php echo esc_attr( $child->term_id ); ?>">
                                <span class="rdsco-filter-name">
                                    <?php if ( $image_url ) : ?>
                                        <img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <span class="rdsco-filter-marquee" title="<?php echo esc_attr( $child->name ); ?>">
                                        <span class="rdsco-filter-marquee-track"><?php echo esc_html( $child->name ); ?></span>
                                    </span>
                                </span>
                                <small><?php echo esc_html( $child->count ); ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( '' !== trim( $sidebar_html ) ) : ?>
                <div class="rdsco-archive-sidebar-html">
                    <?php echo $sidebar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    private static function sanitize_settings( array $raw ): array {
        $display_style = $raw['display_style'] ?? 'card';
        if ( ! in_array( $display_style, [ 'card', 'card_2', 'news_card', 'timeline', 'icon', 'featured_icon', 'icon_card_2', 'hover_box_1' ], true ) ) {
            $display_style = 'card';
        }

        return [
            'per_page'             => max( 1, min( 48, absint( $raw['per_page'] ?? 16 ) ) ),
            'show_date'            => ( 'yes' === ( $raw['show_date'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_category'        => ( 'yes' === ( $raw['show_category'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_excerpt'         => ( 'yes' === ( $raw['show_excerpt'] ?? 'yes' ) ) ? 'yes' : 'no',
            'excerpt_length'       => max( 0, min( 100, absint( $raw['excerpt_length'] ?? 18 ) ) ),
            'empty_text'           => sanitize_text_field( $raw['empty_text'] ?? 'مطلبی پیدا نشد.' ),
            'show_filters'         => ( 'yes' === ( $raw['show_filters'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_subcategories'   => ( 'yes' === ( $raw['show_subcategories'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_tags'            => ( 'yes' === ( $raw['show_tags'] ?? 'yes' ) ) ? 'yes' : 'no',
            'display_style'        => $display_style,
            'show_category_images' => ( 'yes' === ( $raw['show_category_images'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_pagination'      => ( 'yes' === ( $raw['show_pagination'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_load_more'       => ( 'yes' === ( $raw['show_load_more'] ?? 'yes' ) ) ? 'yes' : 'no',
            'show_result_count'    => ( 'yes' === ( $raw['show_result_count'] ?? 'yes' ) ) ? 'yes' : 'no',
        ];
    }

    public static function ajax_fetch(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $root_id       = isset( $_POST['root_id'] ) ? absint( $_POST['root_id'] ) : 0;
        $filter_root   = isset( $_POST['filter_root'] ) ? absint( $_POST['filter_root'] ) : 0;
        $category_id   = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
        $tag_id        = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;
        $page          = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $search        = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $settings_json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}';
        $settings_raw  = json_decode( $settings_json, true );
        $settings      = self::sanitize_settings( is_array( $settings_raw ) ? $settings_raw : [] );

        $root_term = get_term( $root_id, 'category' );
        if ( ! $root_term || is_wp_error( $root_term ) ) {
            wp_send_json_error( [ 'message' => 'Invalid archive category.' ], 400 );
        }

        $settings['display_style'] = Category_Meta::get_display_style( $root_id );
        $settings['show_tags']     = Category_Meta::should_show_tags( $root_id ) ? 'yes' : 'no';

        if ( $filter_root ) {
            $expected_filter_root = self::get_filter_root_id( $root_id );
            if ( $expected_filter_root !== $filter_root ) {
                $filter_root = $expected_filter_root;
            }
        }

        if ( $category_id ) {
            $category_term = get_term( $category_id, 'category' );
            $validation_root = $filter_root ?: $root_id;

            if (
                ! $category_term ||
                is_wp_error( $category_term ) ||
                ( $category_id !== $validation_root && ! term_is_ancestor_of( $validation_root, $category_id, 'category' ) )
            ) {
                $category_id = 0;
            }
        }

        $query_root = $filter_root ?: $root_id;
        $tag_scope  = $category_id ?: $query_root;
        $tags       = 'yes' === $settings['show_tags'] ? self::get_category_tags( $tag_scope ) : [];
        $tag_ids    = array_map( 'intval', wp_list_pluck( $tags, 'term_id' ) );

        if ( $tag_id && ! in_array( $tag_id, $tag_ids, true ) ) {
            $tag_id = 0;
        }

        $query      = self::query(
            [
                'root_id'     => $query_root,
                'category_id' => $category_id,
                'tag_id'      => $tag_id,
                'search'      => $search,
                'page'        => $page,
                'per_page'    => $settings['per_page'],
            ]
        );

        wp_send_json_success(
            [
                'html'       => self::render_posts( $query, $settings ),
                'pagination' => 'yes' === $settings['show_pagination'] ? self::render_pagination( $page, (int) $query->max_num_pages ) : '',
                'page'       => $page,
                'maxPages'   => (int) $query->max_num_pages,
                'total'      => number_format_i18n( $query->found_posts ),
                'totalRaw'   => (int) $query->found_posts,
                'tagsHtml'   => self::render_tag_filters( $tags, $tag_id ),
                'activeTag'  => $tag_id,
            ]
        );
    }
}
