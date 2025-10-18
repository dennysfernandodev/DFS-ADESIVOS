<?php
/**
 * Popula produtos dummy para DFS Adesivos.
 * Executar via: wp eval-file scripts/seed_products.php -- --force
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'Este script deve ser executado dentro do WordPress.' . PHP_EOL );
}

if ( ! class_exists( 'WooCommerce' ) ) {
    exit( 'WooCommerce não está carregado.' . PHP_EOL );
}

require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

$force = in_array( '--force', $argv, true );

function dfs_log( string $message ): void {
    echo $message . PHP_EOL;
}

function dfs_ensure_terms( string $taxonomy, array $terms ): array {
    $result = [];
    foreach ( $terms as $term_name ) {
        $slug = sanitize_title( $term_name );
        $term = get_term_by( 'slug', $slug, $taxonomy );
        if ( ! $term ) {
            $created = wp_insert_term( $term_name, $taxonomy, [ 'slug' => $slug ] );
            if ( is_wp_error( $created ) ) {
                dfs_log( sprintf( '[WARN] Não foi possível criar termo %s em %s: %s', $term_name, $taxonomy, $created->get_error_message() ) );
                continue;
            }
            $term = get_term( $created['term_id'], $taxonomy );
        }
        if ( $term ) {
            $result[ $slug ] = (int) $term->term_id;
        }
    }
    return $result;
}

function dfs_set_categories( int $product_id, array $slugs ): void {
    $term_ids = [];
    foreach ( $slugs as $slug ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $term ) {
            $term_ids[] = (int) $term->term_id;
        }
    }
    if ( $term_ids ) {
        wp_set_object_terms( $product_id, $term_ids, 'product_cat', false );
    }
}

function dfs_set_attributes( WC_Product $product, array $attributes, bool $for_variation = false ): void {
    $product_attributes = [];
    foreach ( $attributes as $taxonomy => $values ) {
        $taxonomy = strpos( $taxonomy, 'pa_' ) === 0 ? $taxonomy : 'pa_' . $taxonomy;
        $slug = str_replace( 'pa_', '', $taxonomy );
        if ( ! taxonomy_exists( $taxonomy ) ) {
            $attribute_id = wc_create_attribute([
                'name'         => ucfirst( $slug ),
                'slug'         => $slug,
                'type'         => 'select',
                'order_by'     => 'menu_order',
                'has_archives' => false,
            ]);
            if ( is_wp_error( $attribute_id ) ) {
                dfs_log( sprintf( '[WARN] Falha ao criar atributo %s: %s', $taxonomy, $attribute_id->get_error_message() ) );
                continue;
            }
            register_taxonomy( 'pa_' . $slug, [ 'product' ], [ 'hierarchical' => false ] );
        }

        $terms = dfs_ensure_terms( $taxonomy, $values );
        if ( ! $terms ) {
            continue;
        }

        $attr = new WC_Product_Attribute();
        $attr->set_id( (int) wc_attribute_taxonomy_id_by_name( $slug ) );
        $attr->set_name( $taxonomy );
        $attr->set_options( array_values( $terms ) );
        $attr->set_visible( true );
        $attr->set_variation( $for_variation );
        $product_attributes[] = $attr;
    }

    if ( $product_attributes ) {
        $product->set_attributes( $product_attributes );
    }
}

function dfs_upsert_simple_product( array $data, bool $force ): void {
    $sku = $data['sku'];
    $product_id = wc_get_product_id_by_sku( $sku );
    if ( ! $product_id && ! empty( $data['slug'] ) ) {
        $post = get_page_by_path( $data['slug'], OBJECT, 'product' );
        if ( $post ) {
            $product_id = (int) $post->ID;
        }
    }

    if ( $product_id && ! $force ) {
        dfs_log( sprintf( '[SKIP] Produto %s já existe (SKU %s)', $data['name'], $sku ) );
        return;
    }

    $product = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();
    if ( ! $product ) {
        dfs_log( sprintf( '[ERRO] Não foi possível carregar/criar produto %s', $data['name'] ) );
        return;
    }

    $product->set_name( $data['name'] );
    if ( ! empty( $data['slug'] ) ) {
        $product->set_slug( $data['slug'] );
    }
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_sku( $sku );
    $product->set_regular_price( (string) $data['price'] );
    if ( isset( $data['sale_price'] ) ) {
        $product->set_sale_price( (string) $data['sale_price'] );
    }
    $product->set_manage_stock( false );
    $product->set_stock_status( 'instock' );
    if ( ! empty( $data['short_description'] ) ) {
        $product->set_short_description( $data['short_description'] );
    }
    if ( ! empty( $data['description'] ) ) {
        $product->set_description( $data['description'] );
    }

    if ( ! empty( $data['attributes'] ) ) {
        dfs_set_attributes( $product, $data['attributes'], false );
    }

    $product_id = $product->save();
    dfs_set_categories( $product_id, $data['categories'] ?? [] );

    if ( ! empty( $data['meta'] ) ) {
        foreach ( $data['meta'] as $meta_key => $meta_value ) {
            update_post_meta( $product_id, $meta_key, $meta_value );
        }
    }

    dfs_log( sprintf( '[OK] Produto simples sincronizado: %s (#%d)', $data['name'], $product_id ) );
}

function dfs_upsert_variable_product( array $data, bool $force ): void {
    $sku = $data['sku'];
    $product_id = wc_get_product_id_by_sku( $sku );
    if ( ! $product_id && ! empty( $data['slug'] ) ) {
        $post = get_page_by_path( $data['slug'], OBJECT, 'product' );
        if ( $post ) {
            $product_id = (int) $post->ID;
        }
    }

    if ( $product_id && ! $force ) {
        dfs_log( sprintf( '[SKIP] Produto variável %s já existe (SKU %s)', $data['name'], $sku ) );
        return;
    }

    $product = $product_id ? wc_get_product( $product_id ) : new WC_Product_Variable();
    if ( ! $product ) {
        dfs_log( sprintf( '[ERRO] Não foi possível criar produto %s', $data['name'] ) );
        return;
    }

    $product->set_name( $data['name'] );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_sku( $sku );
    if ( ! empty( $data['slug'] ) ) {
        $product->set_slug( $data['slug'] );
    }
    if ( ! empty( $data['description'] ) ) {
        $product->set_description( $data['description'] );
    }

    dfs_set_attributes( $product, $data['attributes'] ?? [], true );
    $product_id = $product->save();
    dfs_set_categories( $product_id, $data['categories'] ?? [] );

    if ( ! empty( $data['meta'] ) ) {
        foreach ( $data['meta'] as $meta_key => $meta_value ) {
            update_post_meta( $product_id, $meta_key, $meta_value );
        }
    }

    // Limpa variações existentes se force
    if ( $force ) {
        $existing_children = $product->get_children();
        foreach ( $existing_children as $child_id ) {
            wp_delete_post( $child_id, true );
        }
    }

    foreach ( $data['variations'] as $variation_data ) {
        $variation_sku = $variation_data['sku'];
        $variation_id   = wc_get_product_id_by_sku( $variation_sku );
        $variation      = $variation_id ? wc_get_product( $variation_id ) : new WC_Product_Variation();
        $variation->set_parent_id( $product_id );
        $variation->set_sku( $variation_sku );
        $variation->set_status( 'publish' );
        $variation->set_regular_price( (string) $variation_data['price'] );
        if ( isset( $variation_data['sale_price'] ) ) {
            $variation->set_sale_price( (string) $variation_data['sale_price'] );
        }
        $variation->set_manage_stock( false );
        $variation->set_stock_status( 'instock' );

        $variation_attributes = [];
        foreach ( $variation_data['attributes'] as $taxonomy => $value ) {
            $taxonomy = strpos( $taxonomy, 'pa_' ) === 0 ? $taxonomy : 'pa_' . $taxonomy;
            dfs_ensure_terms( $taxonomy, [ $value ] );
            $variation_attributes[ $taxonomy ] = sanitize_title( $value );
        }
        $variation->set_attributes( $variation_attributes );
        $variation->save();
    }

    $product->save();
    dfs_log( sprintf( '[OK] Produto variável sincronizado: %s (#%d)', $data['name'], $product_id ) );
}

$products = [
    [
        'type'        => 'simple',
        'name'        => 'Adesivo Automotivo Faixa Racing',
        'slug'        => 'adesivo-automotivo-faixa-racing',
        'sku'         => 'DFS-AUTO-001',
        'price'       => 49.90,
        'sale_price'  => 44.90,
        'categories'  => [ 'automotivos' ],
        'short_description' => 'Faixa esportiva com vinil resistente UV.',
        'meta'        => [
            '_dfs_production_time'    => '3',
            '_dfs_resistencia_externa' => 'yes',
        ],
    ],
    [
        'type'        => 'simple',
        'name'        => 'Kit Adesivos Retro Garage',
        'slug'        => 'kit-adesivos-retro-garage',
        'sku'         => 'DFS-AUTO-002',
        'price'       => 59.90,
        'sale_price'  => 52.90,
        'categories'  => [ 'automotivos' ],
        'short_description' => 'Kit com 10 adesivos estilo retrô para carros e motos.',
        'meta'        => [
            '_dfs_production_time'    => '2',
            '_dfs_resistencia_externa' => 'yes',
        ],
    ],
    [
        'type'        => 'variable',
        'name'        => 'Adesivo Parede Skyline',
        'slug'        => 'adesivo-parede-skyline',
        'sku'         => 'DFS-PAR-001',
        'categories'  => [ 'parede' ],
        'description' => 'Painel adesivo com paisagem urbana em recorte de precisão.',
        'attributes'  => [
            'material' => [ 'Vinil Fosco', 'Vinil Brilho', 'Vinil Holográfico' ],
            'largura'  => [ '60cm', '100cm' ],
        ],
        'meta'        => [
            '_dfs_production_time'    => '5',
            '_dfs_resistencia_externa' => 'no',
        ],
        'variations'  => [
            [
                'sku'       => 'DFS-PAR-001-FO-60',
                'price'     => 129.90,
                'sale_price'=> 119.90,
                'attributes'=> [ 'pa_material' => 'Vinil Fosco', 'pa_largura' => '60cm' ],
            ],
            [
                'sku'       => 'DFS-PAR-001-BR-100',
                'price'     => 189.90,
                'sale_price'=> 179.90,
                'attributes'=> [ 'pa_material' => 'Vinil Brilho', 'pa_largura' => '100cm' ],
            ],
            [
                'sku'       => 'DFS-PAR-001-HO-100',
                'price'     => 219.90,
                'attributes'=> [ 'pa_material' => 'Vinil Holográfico', 'pa_largura' => '100cm' ],
            ],
        ],
    ],
    [
        'type'        => 'simple',
        'name'        => 'Adesivo Notebook Neon Waves',
        'slug'        => 'adesivo-notebook-neon-waves',
        'sku'         => 'DFS-TEC-001',
        'price'       => 39.90,
        'sale_price'  => 34.90,
        'categories'  => [ 'notebook-pc' ],
        'meta'        => [
            '_dfs_production_time'    => '2',
            '_dfs_resistencia_externa' => 'no',
        ],
    ],
    [
        'type'        => 'variable',
        'name'        => 'Adesivo Moto Adventure',
        'slug'        => 'adesivo-moto-adventure',
        'sku'         => 'DFS-MOTO-001',
        'categories'  => [ 'moto' ],
        'attributes'  => [
            'cor'     => [ 'Preto', 'Vermelho', 'Prata' ],
            'material'=> [ 'Vinil Refletivo', 'Vinil Brilho' ],
        ],
        'meta'        => [
            '_dfs_production_time'    => '4',
            '_dfs_resistencia_externa' => 'yes',
        ],
        'variations'  => [
            [
                'sku'       => 'DFS-MOTO-001-PRE-REF',
                'price'     => 89.90,
                'sale_price'=> 79.90,
                'attributes'=> [ 'pa_cor' => 'Preto', 'pa_material' => 'Vinil Refletivo' ],
            ],
            [
                'sku'       => 'DFS-MOTO-001-VER-BRI',
                'price'     => 84.90,
                'attributes'=> [ 'pa_cor' => 'Vermelho', 'pa_material' => 'Vinil Brilho' ],
            ],
            [
                'sku'       => 'DFS-MOTO-001-PRI-REF',
                'price'     => 92.90,
                'attributes'=> [ 'pa_cor' => 'Prata', 'pa_material' => 'Vinil Refletivo' ],
            ],
        ],
    ],
    [
        'type'        => 'simple',
        'name'        => 'Adesivo Automotivo Blackout Premium',
        'slug'        => 'adesivo-automotivo-blackout-premium',
        'sku'         => 'DFS-AUTO-003',
        'price'       => 74.90,
        'categories'  => [ 'automotivos' ],
        'meta'        => [
            '_dfs_production_time'    => '3',
            '_dfs_resistencia_externa' => 'yes',
        ],
    ],
    [
        'type'        => 'simple',
        'name'        => 'Adesivo Decorativo Galáxia',
        'slug'        => 'adesivo-decorativo-galaxia',
        'sku'         => 'DFS-PAR-002',
        'price'       => 59.90,
        'sale_price'  => 54.90,
        'categories'  => [ 'parede' ],
        'meta'        => [
            '_dfs_production_time'    => '3',
            '_dfs_resistencia_externa' => 'no',
        ],
    ],
    [
        'type'        => 'variable',
        'name'        => 'Adesivo Personalizado Nome',
        'slug'        => 'adesivo-personalizado-nome',
        'sku'         => 'DFS-PER-001',
        'categories'  => [ 'personalizados' ],
        'attributes'  => [
            'cor'    => [ 'Preto', 'Branco', 'Vermelho' ],
            'largura'=> [ '30cm', '50cm', '70cm' ],
        ],
        'meta'        => [
            '_dfs_production_time'    => '2',
            '_dfs_resistencia_externa' => 'yes',
        ],
        'variations'  => [
            [
                'sku'       => 'DFS-PER-001-PRE-30',
                'price'     => 44.90,
                'attributes'=> [ 'pa_cor' => 'Preto', 'pa_largura' => '30cm' ],
            ],
            [
                'sku'       => 'DFS-PER-001-BRA-50',
                'price'     => 59.90,
                'attributes'=> [ 'pa_cor' => 'Branco', 'pa_largura' => '50cm' ],
            ],
            [
                'sku'       => 'DFS-PER-001-VER-70',
                'price'     => 74.90,
                'sale_price'=> 69.90,
                'attributes'=> [ 'pa_cor' => 'Vermelho', 'pa_largura' => '70cm' ],
            ],
        ],
    ],
    [
        'type'        => 'simple',
        'name'        => 'Adesivo Parede Frases Motivacionais',
        'slug'        => 'adesivo-parede-frases-motivacionais',
        'sku'         => 'DFS-PAR-003',
        'price'       => 49.90,
        'categories'  => [ 'parede' ],
        'meta'        => [
            '_dfs_production_time'    => '4',
            '_dfs_resistencia_externa' => 'no',
        ],
    ],
    [
        'type'        => 'simple',
        'name'        => 'Skin PC Gamer Neon Grid',
        'slug'        => 'skin-pc-gamer-neon-grid',
        'sku'         => 'DFS-TEC-002',
        'price'       => 64.90,
        'sale_price'  => 58.90,
        'categories'  => [ 'notebook-pc' ],
        'meta'        => [
            '_dfs_production_time'    => '2',
            '_dfs_resistencia_externa' => 'no',
        ],
    ],
];

foreach ( $products as $product ) {
    try {
        if ( $product['type'] === 'variable' ) {
            dfs_upsert_variable_product( $product, $force );
        } else {
            dfs_upsert_simple_product( $product, $force );
        }
    } catch ( Exception $e ) {
        dfs_log( sprintf( '[ERRO] Falha ao processar %s: %s', $product['name'], $e->getMessage() ) );
    }
}

dfs_log( '[INFO] Seed de produtos finalizado.' );
