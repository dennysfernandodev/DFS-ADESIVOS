<?php
/**
 * Healthcheck DFS Adesivos - executado via WP-CLI (wp eval-file).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( "Este script deve rodar dentro do WordPress." . PHP_EOL );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$results = [];

function dfs_check( string $label, bool $status, string $detail = '' ): void {
    global $results;
    $results[] = [ $label, $status, $detail ];
}

// 1) WordPress instalado & permalink.
dfs_check( 'WordPress instalado', is_blog_installed() );
$permalink_ok = get_option( 'permalink_structure' ) === '/%postname%/';
dfs_check( 'Permalink /%postname%/', $permalink_ok, get_option( 'permalink_structure' ) );

// 2) Plugins obrigatórios.
$required_plugins = [
    'woocommerce/woocommerce.php'                              => 'WooCommerce',
    'woocommerce-gutenberg-products-block/woocommerce-gutenberg-products-block.php' => 'WooCommerce Blocks',
    'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' => 'Brazilian Market',
    'woocommerce-correios/woocommerce-correios.php'            => 'WooCommerce Correios',
    'woo-pix/woo-pix.php'                                      => 'WooCommerce Pix',
    'woo-variation-swatches/woo-variation-swatches.php'        => 'Variation Swatches',
    'wp-mail-smtp/wp_mail_smtp.php'                            => 'WP Mail SMTP',
    'redis-cache/redis-cache.php'                              => 'Redis Object Cache',
    'w3-total-cache/w3-total-cache.php'                        => 'W3 Total Cache',
    'seo-by-rank-math/rank-math.php'                           => 'Rank Math SEO',
    'disable-xml-rpc/disable-xml-rpc.php'                      => 'Disable XML-RPC',
    'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => 'Limit Login Attempts Reloaded',
    'webp-express/webp-express.php'                            => 'WebP Express',
];
foreach ( $required_plugins as $plugin_file => $plugin_name ) {
    dfs_check( "Plugin ativo: {$plugin_name}", is_plugin_active( $plugin_file ) );
}

// 3) Redis ativo.
$redis_check = false;
if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
    $test_key = 'dfs_health_' . wp_generate_password( 8, false );
    wp_cache_set( $test_key, 'pong', 'dfs-health', 30 );
    $redis_check = wp_cache_get( $test_key, 'dfs-health' ) === 'pong';
    wp_cache_delete( $test_key, 'dfs-health' );
}
dfs_check( 'Redis Object Cache funcional', $redis_check );

// 4) Páginas essenciais.
$pages = [
    'Home'             => (int) get_option( 'page_on_front' ),
    'Loja'             => (int) get_option( 'woocommerce_shop_page_id' ),
    'Carrinho'         => (int) get_option( 'woocommerce_cart_page_id' ),
    'Finalizar Compra' => (int) get_option( 'woocommerce_checkout_page_id' ),
    'Minha Conta'      => (int) get_option( 'woocommerce_myaccount_page_id' ),
];

$page_slugs = [
    'Política de Privacidade' => 'politica-de-privacidade',
    'Trocas e Devoluções'     => 'trocas-e-devolucoes',
    'Rastreamento de Pedido'  => 'rastreamento-de-pedido',
];

foreach ( $page_slugs as $label => $slug ) {
    $page = get_page_by_path( $slug, OBJECT, 'page' );
    $pages[ $label ] = $page ? (int) $page->ID : 0;
}
foreach ( $pages as $page_label => $page_id ) {
    dfs_check( "Página publicada: {$page_label}", $page_id > 0 && get_post_status( $page_id ) === 'publish' );
}

dfs_check( 'Home definida como estática', get_option( 'show_on_front' ) === 'page' );

dfs_check( 'Loja definida como página de produtos', (int) get_option( 'woocommerce_shop_page_id' ) > 0 );

// 5) Correios & Pix.
$correios = get_option( 'woocommerce_correios_settings', [] );
$correios_enabled = isset( $correios['enabled'] ) && 'yes' === $correios['enabled'];
$pac = get_option( 'woocommerce_correios_pac_settings', [] );
$sedex = get_option( 'woocommerce_correios_sedex_settings', [] );
$services_ok = ( isset( $pac['enabled'] ) && 'yes' === $pac['enabled'] ) && ( isset( $sedex['enabled'] ) && 'yes' === $sedex['enabled'] );
dfs_check( 'Correios habilitado', $correios_enabled && $services_ok );

$pix = get_option( 'woocommerce_woo_pix_settings', [] );
dfs_check( 'Pix habilitado no checkout', isset( $pix['enabled'] ) && 'yes' === $pix['enabled'] );

// 6) JSON-LD em produtos (verifica se hook do schema está disponível).
$schema_hooked = has_action( 'wp_head', 'dfs_output_schema' );
dfs_check( 'Schema.org JSON-LD ativo', $schema_hooked );

// 7) Header CTA WhatsApp.
$menu_locations = get_nav_menu_locations();
$primary_menu_id = $menu_locations['primary'] ?? 0;
$cta_present = false;
if ( $primary_menu_id ) {
    $items = wp_get_nav_menu_items( $primary_menu_id );
    foreach ( $items as $item ) {
        if ( false !== stripos( $item->title, 'Pedir Personalizado' ) ) {
            $cta_present = true;
            break;
        }
    }
}
dfs_check( 'CTA WhatsApp no menu', $cta_present );

// 8) Lighthouse (tentativa em ambiente local).
$lighthouse_ok = false;
$docker_path = trim( (string) shell_exec( 'command -v docker' ) );
if ( $docker_path ) {
    $url = getenv( 'WP_URL' ) ?: site_url();
    $cmd = escapeshellcmd( $docker_path ) . ' run --rm --network=host femtopixel/google-lighthouse ' . escapeshellarg( $url ) . ' --quiet --chrome-flags="--headless" --output=json --output-path=stdout --only-categories=performance,best-practices,seo';
    $output = [];
    $status = 0;
    exec( $cmd, $output, $status );
    if ( 0 === $status && ! empty( $output ) ) {
        $json = json_decode( implode( '', $output ), true );
        if ( isset( $json['categories'] ) ) {
            $performance     = ( $json['categories']['performance']['score'] ?? 0 ) * 100;
            $best_practices  = ( $json['categories']['best-practices']['score'] ?? 0 ) * 100;
            $seo             = ( $json['categories']['seo']['score'] ?? 0 ) * 100;
            $lighthouse_ok   = $performance >= 90 && $best_practices >= 90 && $seo >= 95;
            dfs_check( 'Lighthouse Performance >= 90', $performance >= 90, 'Score: ' . $performance );
            dfs_check( 'Lighthouse Best Practices >= 90', $best_practices >= 90, 'Score: ' . $best_practices );
            dfs_check( 'Lighthouse SEO >= 95', $seo >= 95, 'Score: ' . $seo );
        }
    }
}
if ( ! $lighthouse_ok ) {
    dfs_check( 'Lighthouse não executado (verificar manualmente)', false, 'Execute manualmente em ambiente com Docker' );
}

// Saída final.
foreach ( $results as [ $label, $status, $detail ] ) {
    $prefix = $status ? 'OK' : 'FAIL';
    $line   = sprintf( '%s - %s', $prefix, $label );
    if ( $detail ) {
        $line .= sprintf( ' (%s)', $detail );
    }
    echo $line . PHP_EOL;
}
