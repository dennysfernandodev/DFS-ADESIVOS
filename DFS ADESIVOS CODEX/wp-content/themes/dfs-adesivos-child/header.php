<?php
/** Header DFS Adesivos */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> itemscope itemtype="https://schema.org/WebPage">
<?php wp_body_open(); ?>
<div class="dfs-top-banner">
    <?php esc_html_e('Frete calculado na hora • Pix com desconto • Produção rápida • Qualidade profissional', 'dfs-adesivos-child'); ?>
</div>
<header class="dfs-header">
    <div class="dfs-header__inner">
        <div class="dfs-header__logo" itemprop="name">
            <a href="<?php echo esc_url(home_url('/')); ?>">DFS ADESIVOS</a>
        </div>
        <button class="dfs-icon-button" type="button" data-dfs-toggle="menu" aria-label="<?php esc_attr_e('Abrir menu', 'dfs-adesivos-child'); ?>">
            <span class="dfs-icon-menu" aria-hidden="true">☰</span>
        </button>
        <nav class="dfs-header__nav" aria-label="<?php esc_attr_e('Menu principal', 'dfs-adesivos-child'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'dfs-menu',
                'container'      => false,
                'fallback_cb'    => '__return_empty_string',
            ]);
            ?>
        </nav>
        <div class="dfs-header__tools">
            <a class="dfs-cta-button" href="<?php echo dfs_get_whats_link(); ?>" rel="noopener" target="_blank">
                <?php esc_html_e('Pedir Personalizado', 'dfs-adesivos-child'); ?>
            </a>
            <a class="dfs-icon-button" href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" aria-label="<?php esc_attr_e('Minha conta', 'dfs-adesivos-child'); ?>">👤</a>
            <?php if ( function_exists('wc_get_cart_url') ) : ?>
            <a class="dfs-icon-button" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e('Carrinho', 'dfs-adesivos-child'); ?>">
                🛒
                <?php if ( function_exists('WC') && WC()->cart ) : ?>
                    <span class="screen-reader-text"><?php printf( esc_html__( '%d itens no carrinho', 'dfs-adesivos-child' ), WC()->cart->get_cart_contents_count() ); ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a class="dfs-icon-button" href="<?php echo esc_url( home_url('/?s=') ); ?>" aria-label="<?php esc_attr_e('Pesquisar', 'dfs-adesivos-child'); ?>">🔍</a>
        </div>
    </div>
</header>
<?php dfs_render_benefits_band(); ?>
<div id="content" class="site-content">
