<?php
/** Footer DFS Adesivos */
?>
</div><!-- #content -->
<footer class="dfs-footer" itemscope itemtype="https://schema.org/Organization">
    <meta itemprop="name" content="DFS Adesivos" />
    <div class="dfs-footer__inner">
        <div class="dfs-footer__about">
            <h4 class="dfs-footer__title"><?php esc_html_e('DFS Adesivos', 'dfs-adesivos-child'); ?></h4>
            <p><?php esc_html_e('Adesivos automotivos, decorativos e personalizados com impressão e recorte profissional.', 'dfs-adesivos-child'); ?></p>
            <p>
                <strong>CNPJ:</strong> 00.000.000/0000-00<br />
                <strong><?php esc_html_e('Endereço:', 'dfs-adesivos-child'); ?></strong> SIA Trecho 1, Brasília/DF
            </p>
            <p><a class="dfs-cta-button" href="<?php echo dfs_get_whats_link(); ?>" target="_blank" rel="noopener">WhatsApp</a></p>
        </div>
        <div class="dfs-footer__menu">
            <h4 class="dfs-footer__title"><?php esc_html_e('Institucional', 'dfs-adesivos-child'); ?></h4>
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'menu_class'     => 'dfs-footer-menu',
                'container'      => false,
                'fallback_cb'    => '__return_empty_string',
            ]);
            ?>
        </div>
        <div class="dfs-footer__payments">
            <h4 class="dfs-footer__title"><?php esc_html_e('Pagamentos', 'dfs-adesivos-child'); ?></h4>
            <p>Pix • Cartão de Crédito • Boleto</p>
            <h4 class="dfs-footer__title"><?php esc_html_e('Newsletter', 'dfs-adesivos-child'); ?></h4>
            <?php dfs_render_newsletter_form(); ?>
        </div>
        <div class="dfs-footer__track">
            <h4 class="dfs-footer__title"><?php esc_html_e('Atendimento', 'dfs-adesivos-child'); ?></h4>
            <p>Email: contato@dfsadesivos.com.br<br />WhatsApp: <a href="<?php echo dfs_get_whats_link(); ?>" target="_blank" rel="noopener">(61) 9 9999-9999</a></p>
            <p><a href="<?php echo esc_url( home_url('/rastreamento-de-pedido') ); ?>"><?php esc_html_e('Rastrear pedido', 'dfs-adesivos-child'); ?></a></p>
        </div>
    </div>
    <div class="dfs-footer__credit">
        <p>© <?php echo esc_html( gmdate('Y') ); ?> DFS Adesivos. <?php esc_html_e('Todos os direitos reservados.', 'dfs-adesivos-child'); ?></p>
    </div>
</footer>
<a class="dfs-floating-whats dfs-cta-button" href="<?php echo dfs_get_whats_link(); ?>" target="_blank" rel="noopener">WhatsApp</a>
<?php wp_footer(); ?>
</body>
</html>
