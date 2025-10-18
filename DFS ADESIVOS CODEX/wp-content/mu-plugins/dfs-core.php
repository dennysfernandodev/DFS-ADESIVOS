<?php
/**
 * Plugin central DFS Adesivos.
 */

declare(strict_types=1);

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined('ABSPATH') ) {
    exit;
}

if ( ! function_exists('dfs_core_get_whats_link') ) {
    function dfs_core_get_whats_link(): string
    {
        $link = (string) get_option('dfs_whats_link');
        if ( empty($link) ) {
            $link = 'https://wa.me/5561XXXXXXXX?text=Quero%20um%20adesivo%20personalizado';
        }
        return esc_url($link);
    }
}

add_action('wp_body_open', function (): void {
    $link = dfs_core_get_whats_link();
    echo '<div class="dfs-top-cta" style="position:fixed;top:12px;right:16px;z-index:9999;">';
    echo '<a class="dfs-cta-button" href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html__('Pedir Personalizado', 'dfs-adesivos-core') . '</a>';
    echo '</div>';
});

add_action('plugins_loaded', function (): void {
    if ( ! class_exists('WooCommerce') ) {
        return;
    }

    add_action('woocommerce_product_options_general_product_data', function (): void {
        if ( ! function_exists('woocommerce_wp_text_input') ) {
            return;
        }
        woocommerce_wp_text_input([
            'id'          => '_dfs_production_time',
            'label'       => __('Tempo de produção (dias)', 'dfs-adesivos-core'),
            'type'        => 'number',
            'desc_tip'    => true,
            'description' => __('Informe o prazo de produção para exibir na página do produto.', 'dfs-adesivos-core'),
            'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
        ]);

        woocommerce_wp_select([
            'id'      => '_dfs_resistencia_externa',
            'label'   => __('Resistência externa', 'dfs-adesivos-core'),
            'options' => [
                ''    => __('Selecionar', 'dfs-adesivos-core'),
                'yes' => __('Sim', 'dfs-adesivos-core'),
                'no'  => __('Não', 'dfs-adesivos-core'),
            ],
        ]);
    });

    add_action('woocommerce_process_product_meta', function (int $post_id): void {
        if ( isset($_POST['_dfs_production_time']) ) {
            update_post_meta($post_id, '_dfs_production_time', sanitize_text_field($_POST['_dfs_production_time']));
        }
        if ( isset($_POST['_dfs_resistencia_externa']) ) {
            update_post_meta($post_id, '_dfs_resistencia_externa', sanitize_text_field($_POST['_dfs_resistencia_externa']));
        }
    });

    add_action('woocommerce_single_product_summary', function (): void {
        global $product;
        if ( empty($product) || ! is_a($product, 'WC_Product') ) {
            return;
        }
        $production = get_post_meta($product->get_id(), '_dfs_production_time', true);
        $resistance = get_post_meta($product->get_id(), '_dfs_resistencia_externa', true);

        if ( empty($production) && empty($resistance) ) {
            return;
        }

        echo '<div class="dfs-product-specs">';
        if ( $production ) {
            printf('<p><strong>%s:</strong> %s %s</p>', esc_html__('Tempo de produção', 'dfs-adesivos-core'), esc_html($production), esc_html__('dias', 'dfs-adesivos-core'));
        }
        if ( $resistance ) {
            printf('<p><strong>%s:</strong> %s</p>', esc_html__('Resistência externa', 'dfs-adesivos-core'), $resistance === 'yes' ? esc_html__('Sim', 'dfs-adesivos-core') : esc_html__('Não', 'dfs-adesivos-core'));
        }
        echo '</div>';
    }, 25);

    add_action('woocommerce_single_product_summary', function (): void {
        global $product;
        if ( empty($product) || ! is_a($product, 'WC_Product') ) {
            return;
        }

        $base_link = dfs_core_get_whats_link();
        $message = sprintf(
            'Quero saber mais sobre o produto %s',
            rawurlencode( $product->get_name() )
        );

        $link = add_query_arg('text', $message, $base_link);
        echo '<a class="dfs-cta-button dfs-product-whats" data-dfs-product-whats href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html__('Pedir este adesivo no WhatsApp', 'dfs-adesivos-core') . '</a>';
    }, 35);

    add_action('wp_enqueue_scripts', function (): void {
        if ( ! is_product() ) {
            return;
        }

        $handle = 'dfs-product-inline';
        if ( ! wp_script_is($handle, 'registered') ) {
            wp_register_script($handle, '', [], null, true);
        }
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, <<<'JS'
(function(){
  const button = document.querySelector('[data-dfs-product-whats]');
  if (!button) return;
  const baseHref = new URL(button.getAttribute('href'));
  const update = () => {
    let message = baseHref.searchParams.get('text') || '';
    const selected = document.querySelectorAll('.variations select');
    const extra = [];
    selected.forEach(select => {
      if (select.value) {
        const label = select.closest('tr')?.querySelector('label');
        const name = label ? label.textContent.trim() : select.name;
        const selectedOption = select.options[select.selectedIndex].text;
        extra.push(name + ': ' + selectedOption);
      }
    });
    const finalMessage = message.replace(/( \| .*)?$/, '') + (extra.length ? ' | ' + extra.join(' | ') : '');
    const newUrl = new URL(baseHref.href);
    newUrl.searchParams.set('text', finalMessage);
    button.setAttribute('href', newUrl.toString());
  };
  document.body.addEventListener('change', function(event){
    if (event.target && event.target.matches('.variations select')) {
      update();
    }
  });
})();
JS
        , 'after');
    }, 20);

    add_filter('woocommerce_sale_flash', function ($html, $post, $product) {
        if ( is_a($product, 'WC_Product') && $product->is_on_sale() ) {
            return '<span class="onsale dfs-pix-badge">' . esc_html__('Desconto no Pix', 'dfs-adesivos-core') . '</span>';
        }
        return $html;
    }, 10, 3);
});

function dfs_output_schema(): void {
    $logo_path = get_theme_file_path('/assets/img/logo.svg');
    $logo_url  = $logo_path && file_exists($logo_path) ? get_theme_file_uri('/assets/img/logo.svg') : '';

    $organization = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => 'DFS Adesivos',
        'url'      => home_url('/'),
        'logo'     => $logo_url ?: home_url('/'),
        'contactPoint' => [
            [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'areaServed'  => 'BR',
                'availableLanguage' => ['pt-BR'],
                'telephone' => '+55-61-99999-9999'
            ]
        ]
    ];

    if ( has_custom_logo() ) {
        $logo_id = get_theme_mod('custom_logo');
        if ( $logo_id ) {
            $image = wp_get_attachment_image_src($logo_id, 'full');
            if ( $image ) {
                $organization['logo'] = $image[0];
            }
        }
    }

    echo '<script type="application/ld+json">' . wp_json_encode($organization) . '</script>';

    if ( is_product() && function_exists('wc_get_product') ) {
        $product = wc_get_product();
        if ( is_a($product, 'WC_Product') ) {
            echo '<script type="application/ld+json">' . wp_json_encode( dfs_build_product_schema($product) ) . '</script>';
        }
    }
}
add_action('wp_head', 'dfs_output_schema');

function dfs_build_product_schema($product): array {
    $images = [];
    $gallery_ids = $product->get_gallery_image_ids();
    if ( $gallery_ids ) {
        foreach ( $gallery_ids as $attachment_id ) {
            $url = wp_get_attachment_url($attachment_id);
            if ( $url ) {
                $images[] = $url;
            }
        }
    }
    if ( empty($images) ) {
        $cover = wp_get_attachment_url($product->get_image_id());
        if ( $cover ) {
            $images[] = $cover;
        }
    }

    return [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => $product->get_name(),
        'sku'      => $product->get_sku(),
        'image'    => $images,
        'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
        'brand'    => [
            '@type' => 'Brand',
            'name'  => 'DFS Adesivos',
        ],
        'offers'   => [
            '@type'         => 'Offer',
            'priceCurrency' => get_woocommerce_currency(),
            'price'         => $product->get_price(),
            'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url'           => get_permalink($product->get_id()),
        ],
    ];
}

add_action('rest_api_init', function (): void {
    register_rest_route('dfs/v1', '/rastreio', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            $code = strtoupper( sanitize_text_field( (string) $request->get_param('code') ) );
            if ( empty($code) ) {
                return new WP_Error('invalid_code', __('Informe o código de rastreamento.', 'dfs-adesivos-core'), ['status' => 400]);
            }
            $redirect = 'https://rastreamento.correios.com.br/app/index.php#/rastreio/' . rawurlencode($code);
            $response = new WP_REST_Response(null, 302);
            $response->header('Location', $redirect);
            return $response;
        },
    ]);
});
