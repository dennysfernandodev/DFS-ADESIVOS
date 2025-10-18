<?php
/**
 * Helpers de template para DFS Adesivos.
 */

declare(strict_types=1);

if ( ! function_exists('dfs_get_whats_link') ) {
    function dfs_get_whats_link(): string
    {
        $link = (string) get_option('dfs_whats_link');
        if ( empty($link) ) {
            $link = 'https://wa.me/5561XXXXXXXX?text=Quero%20um%20adesivo%20personalizado';
        }

        return esc_url($link);
    }
}

if ( ! function_exists('dfs_render_benefits_band') ) {
    function dfs_render_benefits_band(): void
    {
        $benefits = [
            __('Frete calculado na hora', 'dfs-adesivos-child'),
            __('Pix com desconto', 'dfs-adesivos-child'),
            __('Produção rápida', 'dfs-adesivos-child'),
            __('Qualidade profissional', 'dfs-adesivos-child'),
        ];
        echo '<div class="dfs-benefits-band">';
        foreach ( $benefits as $benefit ) {
            printf('<span>%s</span>', esc_html($benefit));
        }
        echo '</div>';
    }
}

if ( ! function_exists('dfs_render_newsletter_form') ) {
    function dfs_render_newsletter_form(): void
    {
        echo '<form class="dfs-newsletter-form" action="#" method="post">';
        echo '<label class="screen-reader-text" for="dfs-newsletter-email">' . esc_html__('Receba novidades', 'dfs-adesivos-child') . '</label>';
        echo '<input type="email" id="dfs-newsletter-email" name="email" placeholder="' . esc_attr__('Seu melhor e-mail', 'dfs-adesivos-child') . '" required />';
        echo '<button type="submit">' . esc_html__('Quero receber', 'dfs-adesivos-child') . '</button>';
        echo '</form>';
    }
}
