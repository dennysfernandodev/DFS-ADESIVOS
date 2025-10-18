<?php
/**
 * Funções do tema filho DFS Adesivos.
 */

declare(strict_types=1);

add_action('after_setup_theme', function (): void {
    load_child_theme_textdomain('dfs-adesivos-child', get_stylesheet_directory() . '/languages');

    add_theme_support('woocommerce');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    register_nav_menus([
        'primary' => __('Menu Principal', 'dfs-adesivos-child'),
        'footer'  => __('Menu Rodapé', 'dfs-adesivos-child'),
    ]);

    add_image_size('dfs-hero', 1600, 900, true);
    add_image_size('dfs-collection', 600, 600, true);
});

add_action('widgets_init', function (): void {
    register_sidebar([
        'name'          => __('Footer Newsletter', 'dfs-adesivos-child'),
        'id'            => 'dfs-footer-newsletter',
        'before_widget' => '<div class="dfs-footer__newsletter">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="dfs-footer__title">',
        'after_title'   => '</h4>',
    ]);
});

add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style('dfs-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@600;700;800&display=swap', [], null);
    wp_enqueue_style('dfs-parent', get_template_directory_uri() . '/style.css', [], wp_get_theme(get_template())->get('Version'));
    wp_enqueue_style('dfs-child', get_stylesheet_uri(), ['dfs-parent'], wp_get_theme()->get('Version'));

    $asset_path = get_stylesheet_directory_uri() . '/assets/css/site.css';
    if ( file_exists( get_stylesheet_directory() . '/assets/css/site.css' ) ) {
        wp_enqueue_style('dfs-custom', $asset_path, ['dfs-child'], wp_get_theme()->get('Version'));
    }

    wp_enqueue_script('dfs-theme', get_stylesheet_directory_uri() . '/assets/js/theme.js', [], wp_get_theme()->get('Version'), true);
});

/** Remove emojis, embeds e query strings extras */
add_action('init', function (): void {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');

    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    add_filter('embed_oembed_discover', '__return_false');

    wp_deregister_script('wp-embed');
});

$dfs_remove_asset_version = static function (string $src): string {
    $parts = explode('?', $src);
    return $parts[0];
};
add_filter('style_loader_src', $dfs_remove_asset_version, 10);
add_filter('script_loader_src', $dfs_remove_asset_version, 10);

/** Bloqueia REST para visitantes exceto rotas da API DFS */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if ( is_user_logged_in() ) {
        return $result;
    }

    $route = $request->get_route();
    $allowed_prefixes = [
        '/dfs/',
        '/contact-form-7/',
        '/oembed/',
    ];

    foreach ( $allowed_prefixes as $prefix ) {
        if ( str_starts_with( $route, $prefix ) ) {
            return $result;
        }
    }

    return new WP_Error(
        'rest_forbidden',
        __('Acesso REST restrito. Autentique-se para continuar.', 'dfs-adesivos-child'),
        ['status' => rest_authorization_required_code()]
    );
}, 10, 3);

/** Ajuda visual para lojas: adiciona classe body */
add_filter('body_class', function (array $classes): array {
    $classes[] = 'dfs-theme';
    return array_unique($classes);
});

/** Inclui helpers adicionais */
require_once get_stylesheet_directory() . '/inc/template-tags.php';
