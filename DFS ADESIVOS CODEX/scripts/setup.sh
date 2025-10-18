#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}"

if [[ ! -f .env ]]; then
  echo "[ERRO] Arquivo .env não encontrado em ${PROJECT_ROOT}" >&2
  exit 1
fi

# Carrega variáveis do .env
set -a
# shellcheck disable=SC1091
source .env
set +a

if command -v "docker" >/dev/null 2>&1; then
  if docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE=(docker compose)
  else
    DOCKER_COMPOSE=(docker-compose)
  fi
else
  echo "[ERRO] Docker não está instalado ou no PATH." >&2
  exit 1
fi

run_dc() {
  "${DOCKER_COMPOSE[@]}" "$@"
}

wp() {
  run_dc run --rm wp-cli "$@"
}

wait_for_db() {
  echo "[INFO] Aguardando banco de dados responder..."
  local attempt=0
  until run_dc exec -T db mysqladmin ping -h "db" -u"${DB_USER}" -p"${DB_PASS}" --silent >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if (( attempt > 60 )); then
      echo "[ERRO] Banco de dados não respondeu após 5 minutos." >&2
      exit 1
    fi
    sleep 5
  done
  echo "[INFO] Banco de dados disponível."
}

ensure_wordpress() {
  echo "[INFO] Verificando instalação do WordPress..."
  if ! wp core is-installed >/dev/null 2>&1; then
    echo "[INFO] Instalando núcleo do WordPress..."
    wp core download --skip-content --force

    echo "[INFO] Criando arquivo wp-config.php customizado..."
    wp config create \
      --dbname="${DB_NAME}" \
      --dbuser="${DB_USER}" \
      --dbpass="${DB_PASS}" \
      --dbhost="${DB_HOST}" \
      --dbprefix=wp_ \
      --skip-check \
      --force

    echo "[INFO] Aplicando constantes adicionais no wp-config.php"
    wp config set WP_HOME "${WP_URL}" --type=constant
    wp config set WP_SITEURL "${WP_URL}" --type=constant
    wp config set FORCE_SSL_ADMIN true --type=constant --raw
    wp config set DISALLOW_FILE_EDIT true --type=constant --raw
    wp config set WP_MEMORY_LIMIT "256M" --type=constant
    wp config set WP_REDIS_HOST redis --type=constant
    wp config set WP_REDIS_PASSWORD "${REDIS_PASS}" --type=constant
    wp config set WP_REDIS_PORT 6379 --type=constant --raw
    wp config set WP_CACHE true --type=constant --raw
    wp config set WP_ENVIRONMENT_TYPE production --type=constant

    echo "[INFO] Instalando WordPress (${WP_TITLE}) em ${WP_URL}"
    wp core install \
      --url="${WP_URL}" \
      --title="${WP_TITLE}" \
      --admin_user="${WP_ADMIN_USER}" \
      --admin_password="${WP_ADMIN_PASS}" \
      --admin_email="${WP_ADMIN_EMAIL}"
  else
    echo "[INFO] WordPress já instalado. Garantindo configurações principais..."
    wp option update siteurl "${WP_URL}"
    wp option update home "${WP_URL}"
  fi

  echo "[INFO] Atualizando senha e email do administrador"
  wp user update "${WP_ADMIN_USER}" \
    --user_pass="${WP_ADMIN_PASS}" \
    --user_email="${WP_ADMIN_EMAIL}" \
    --first_name=DFS \
    --last_name=Adesivos
}

configure_general_settings() {
  echo "[INFO] Ajustando configurações gerais"
  wp option update blogname "DFS Adesivos"
  wp option update blogdescription "Adesivos automotivos e personalizados com impressão profissional."
  wp option update timezone_string "${TZ}"
  wp option update permalink_structure "/%postname%/"
  wp rewrite structure "/%postname%/" --hard
  wp rewrite flush --hard
  wp option update dfs_whats_link "${WHATS_LINK}"
}

install_plugins() {
  echo "[INFO] Instalando e ativando plugins obrigatórios"
  local plugins=(
    woocommerce
    woocommerce-gutenberg-products-block
    woocommerce-extra-checkout-fields-for-brazil
    woocommerce-correios
    woo-pix
    woo-variation-swatches
    wp-mail-smtp
    redis-cache
    w3-total-cache
    seo-by-rank-math
    disable-xml-rpc
    limit-login-attempts-reloaded
    webp-express
    wpforms-lite
  )

  for plugin in "${plugins[@]}"; do
    if wp plugin is-installed "${plugin}" >/dev/null 2>&1; then
      wp plugin activate "${plugin}" >/dev/null 2>&1 || true
    else
      wp plugin install "${plugin}" --activate
    fi
  done

  # Garante que WooCommerce esteja atualizado
  wp plugin update woocommerce >/dev/null 2>&1 || true
}

configure_mailhog() {
  echo "[INFO] Configurando Mailhog via WP Mail SMTP"
  if wp option get wp_mail_smtp >/dev/null 2>&1; then
    wp option patch update wp_mail_smtp mail mailer smtp >/dev/null 2>&1 || true
    wp option patch update wp_mail_smtp mail from_name "DFS Adesivos" >/dev/null 2>&1 || true
    wp option patch update wp_mail_smtp mail from_email "${WP_ADMIN_EMAIL}" >/dev/null 2>&1 || true
    wp option patch update wp_mail_smtp smtp host mailhog >/dev/null 2>&1 || true
    wp option patch update wp_mail_smtp smtp port 1025 >/dev/null 2>&1 || true
    wp option patch update wp_mail_smtp smtp encryption none >/dev/null 2>&1 || true
    wp option patch update wp_mail_smtp smtp auth 0 >/dev/null 2>&1 || true
  fi
}

configure_woocommerce() {
  echo "[INFO] Ajustando opções básicas do WooCommerce"
  wp option update woocommerce_currency "BRL"
  wp option update woocommerce_currency_pos "left"
  wp option update woocommerce_price_num_decimals 2
  wp option update woocommerce_price_decimal_sep ","
  wp option update woocommerce_price_thousand_sep "."
  wp option update woocommerce_weight_unit "kg"
  wp option update woocommerce_dimension_unit "cm"
  wp option update woocommerce_default_country "BR:DF"
  wp option update woocommerce_store_address "Endereço Exemplo"
  wp option update woocommerce_store_address_2 "Sala 1"
  wp option update woocommerce_store_city "Brasília"
  wp option update woocommerce_store_postcode "70000000"

  echo "[INFO] Configurando plugin Correios e Pix"
  wp option update woocommerce_correios_settings '{
    "enabled":"yes",
    "title":"Correios",
    "origin_postcode":"70000000",
    "corporate_login":"",
    "corporate_password":"",
    "debug":"no",
    "tracking":"yes"
  }' --format=json

  wp option update woocommerce_woo_pix_settings '{
    "enabled":"yes",
    "type":"dynamic",
    "title":"Pix",
    "description":"Pagamento instantâneo via Pix.",
    "instructions":"Finalize o pedido e escaneie o QR Code ou copie o código Pix.",
    "expire":"15",
    "key":"00000000-0000-0000-0000-PIXKEY"
  }' --format=json

  echo "[INFO] Habilitando serviços PAC/SEDEX"
  wp option update woocommerce_correios_pac_settings '{"enabled":"yes"}' --format=json
  wp option update woocommerce_correios_sedex_settings '{"enabled":"yes"}' --format=json
}

configure_redis_cache() {
  echo "[INFO] Habilitando Redis Object Cache"
  wp plugin activate redis-cache >/dev/null 2>&1 || true
  wp redis update-dropin --force >/dev/null 2>&1 || true
  wp redis enable --force >/dev/null 2>&1 || true
}

configure_w3tc() {
  echo "[INFO] Configurando W3 Total Cache"
  if wp plugin is-active w3-total-cache >/dev/null 2>&1; then
    if [[ -f wp-content/w3tc-config/master.php ]]; then
      wp w3-total-cache import wp-content/w3tc-config/master.php >/dev/null 2>&1 || true
    fi
    wp w3-total-cache option set pgcache.enabled true >/dev/null 2>&1 || true
    wp w3-total-cache option set pgcache.engine disk_enhanced >/dev/null 2>&1 || true
    wp w3-total-cache option set objectcache.enabled true >/dev/null 2>&1 || true
    wp w3-total-cache option set objectcache.engine redis >/dev/null 2>&1 || true
    wp w3-total-cache option set objectcache.redis.servers "redis:6379" >/dev/null 2>&1 || true
    wp w3-total-cache option set objectcache.redis.password "${REDIS_PASS}" >/dev/null 2>&1 || true
    wp w3-total-cache option set browsercache.enabled true >/dev/null 2>&1 || true
    wp w3-total-cache option set minify.enabled false >/dev/null 2>&1 || true
    wp w3-total-cache flush all >/dev/null 2>&1 || true
  fi
}

ensure_page() {
  local title="$1"
  local slug="$2"
  local content="$3"
  local template="${4:-}"

  local page_id
  page_id="$(wp post list --post_type=page --name="${slug}" --field=ID --format=ids)"
  if [[ -z "${page_id}" ]]; then
    page_id="$(wp post create --post_type=page --post_title="${title}" --post_name="${slug}" --post_status=publish --post_content="${content}" ${template:+--page_template="${template}"} --porcelain)"
    echo "[INFO] Página '${title}' criada (ID ${page_id})."
  else
    wp post update "${page_id}" --post_title="${title}" --post_content="${content}" --post_status=publish >/dev/null
    echo "[INFO] Página '${title}' atualizada (ID ${page_id})."
  fi
  echo "${page_id}"
}

create_pages() {
  echo "[INFO] Criando/atualizando páginas essenciais"
  local home_content loja_content cart_content checkout_content myaccount_content sobre_content contato_content politica_privacidade_content trocas_content rastreamento_content termos_content

  home_content=$(cat <<HTML
<!-- wp:cover {"url":"/wp-content/uploads/hero-adesivos.jpg","dimRatio":60,"overlayColor":"black","minHeight":520,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:520px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-60"></span><img class="wp-block-cover__image-background" alt="" src="/wp-content/uploads/hero-adesivos.jpg"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"left","level":1,"style":{"typography":{"fontSize":"48px"}},"textColor":"dfs-vermelho"} -->
<h1 class="has-text-align-left has-dfs-vermelho-color has-text-color" style="font-size:48px">O seu estilo, colado onde quiser.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"left","style":{"typography":{"fontSize":"18px"}},"textColor":"dfs-prata"} -->
<p class="has-text-align-left has-dfs-prata-color has-text-color" style="font-size:18px">Adesivos automotivos e personalizados com impressão + recorte profissional.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left"},"style":{"spacing":{"blockGap":"16px"}}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"dfs-vermelho","textColor":"white"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-dfs-vermelho-background-color has-text-color has-background" href="/loja">Ver Coleções</a></div>
<!-- /wp:button -->

<!-- wp:button {"backgroundColor":"dfs-preto","textColor":"white"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-dfs-preto-background-color has-text-color has-background" href="${WHATS_LINK}">Pedir Personalizado</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->

<!-- wp:heading {"level":3} -->
<h3>Coleções em destaque</h3>
<!-- /wp:heading -->

<!-- wp:columns {"className":"dfs-colecoes"} -->
<div class="wp-block-columns dfs-colecoes"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"overlayColor":"dfs-preto","contentPosition":"center center"} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-dfs-preto-background-color has-background"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="has-text-align-center has-white-color has-text-color">Automotivos</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"overlayColor":"dfs-preto","contentPosition":"center center"} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-dfs-preto-background-color has-background"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="has-text-align-center has-white-color has-text-color">Parede</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"overlayColor":"dfs-preto","contentPosition":"center center"} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-dfs-preto-background-color has-background"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="has-text-align-center has-white-color has-text-color">Notebook/PC</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:heading {"level":3} -->
<h3>Benefícios DFS Adesivos</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Frete calculado na hora • Pix com desconto • Produção rápida • Qualidade profissional</p>
<!-- /wp:paragraph -->

<!-- wp:cover {"overlayColor":"dfs-vermelho","className":"dfs-banner-whats"} -->
<div class="wp-block-cover dfs-banner-whats"><span aria-hidden="true" class="wp-block-cover__background has-dfs-vermelho-background-color has-background"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="has-text-align-center has-white-color has-text-color">Faça seu adesivo personalizado</h2>
<!-- /wp:heading -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"dfs-preto"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-dfs-preto-color has-white-background-color has-text-color has-background" href="${WHATS_LINK}">Pedir pelo WhatsApp</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->
HTML

  loja_content=$(cat <<'HTML'
<!-- wp:woocommerce/product-new /-->
HTML
)

  cart_content='[woocommerce_cart]'
  checkout_content='[woocommerce_checkout]'
  myaccount_content='[woocommerce_my_account]'
  sobre_content='DFS Adesivos é especialista em adesivos automotivos e personalizados. Atendimento dedicado via WhatsApp.'
  contato_content=$(printf '[wpforms id="1" title="false"]\n\nEntre em contato também pelo WhatsApp: %s' "${WHATS_LINK}")
  politica_privacidade_content='Conteúdo padrão de política de privacidade. Personalize conforme necessidade.'
  trocas_content='Regras gerais para trocas e devoluções conforme Código de Defesa do Consumidor.'
  rastreamento_content='Informe o código enviado por e-mail para rastrear o pedido nos Correios.'
  termos_content='Termos de uso e condições para compras na DFS Adesivos.'

  HOME_ID=$(ensure_page "Home" "home" "${home_content}")
  LOJA_ID=$(ensure_page "Loja" "loja" "${loja_content}")
  CARRINHO_ID=$(ensure_page "Carrinho" "carrinho" "${cart_content}")
  CHECKOUT_ID=$(ensure_page "Finalizar Compra" "finalizar-compra" "${checkout_content}")
  CONTA_ID=$(ensure_page "Minha Conta" "minha-conta" "${myaccount_content}")
  SOBRE_ID=$(ensure_page "Sobre" "sobre" "${sobre_content}")
  CONTATO_ID=$(ensure_page "Contato" "contato" "${contato_content}")
  PRIVACIDADE_ID=$(ensure_page "Política de Privacidade" "politica-de-privacidade" "${politica_privacidade_content}")
  TROCAS_ID=$(ensure_page "Trocas e Devoluções" "trocas-e-devolucoes" "${trocas_content}")
  RASTREIO_ID=$(ensure_page "Rastreamento de Pedido" "rastreamento-de-pedido" "${rastreamento_content}")
  TERMOS_ID=$(ensure_page "Termos de Uso" "termos-de-uso" "${termos_content}")

  wp option update show_on_front page
  wp option update page_on_front "${HOME_ID}"
  wp option update page_for_posts 0

  wp option update woocommerce_shop_page_id "${LOJA_ID}"
  wp option update woocommerce_cart_page_id "${CARRINHO_ID}"
  wp option update woocommerce_checkout_page_id "${CHECKOUT_ID}"
  wp option update woocommerce_myaccount_page_id "${CONTA_ID}"
}

create_menus() {
  echo "[INFO] Configurando menus"
  local primary_slug="menu-principal"
  local footer_slug="rodape"

  if wp menu list --fields=slug --format=csv | awk -F',' 'NR>1 {print $1}' | grep -q "^${primary_slug}$"; then
    wp menu delete "${primary_slug}" >/dev/null 2>&1 || true
  fi
  if wp menu list --fields=slug --format=csv | awk -F',' 'NR>1 {print $1}' | grep -q "^${footer_slug}$"; then
    wp menu delete "${footer_slug}" >/dev/null 2>&1 || true
  fi

  local primary_menu_id footer_menu_id
  primary_menu_id="$(wp menu create "Menu Principal" --slug="${primary_slug}")"
  footer_menu_id="$(wp menu create "Rodapé" --slug="${footer_slug}")"

  wp menu item add-post "${primary_menu_id}" "${HOME_ID}" --title="Home"
  wp menu item add-post "${primary_menu_id}" "${LOJA_ID}" --title="Loja"
  wp menu item add-custom "${primary_menu_id}" "Automotivos" /categoria-produto/automotivos/
  wp menu item add-custom "${primary_menu_id}" "Parede" /categoria-produto/parede/
  wp menu item add-custom "${primary_menu_id}" "Notebook/PC" /categoria-produto/notebook-pc/
  wp menu item add-custom "${primary_menu_id}" "Moto" /categoria-produto/moto/
  wp menu item add-custom "${primary_menu_id}" "Personalizados" /categoria-produto/personalizados/
  wp menu item add-post "${primary_menu_id}" "${SOBRE_ID}" --title="Sobre"
  wp menu item add-post "${primary_menu_id}" "${CONTATO_ID}" --title="Contato"
  wp menu item add-custom "${primary_menu_id}" "Pedir Personalizado" "${WHATS_LINK}" --attr-title="WhatsApp" --target=_blank

  wp menu item add-post "${footer_menu_id}" "${PRIVACIDADE_ID}" --title="Política de Privacidade"
  wp menu item add-post "${footer_menu_id}" "${TROCAS_ID}" --title="Trocas e Devoluções"
  wp menu item add-post "${footer_menu_id}" "${TERMOS_ID}" --title="Termos de Uso"
  wp menu item add-post "${footer_menu_id}" "${RASTREIO_ID}" --title="Rastreamento de Pedido"

  wp menu location assign "${primary_menu_id}" primary
  wp menu location assign "${footer_menu_id}" footer
}

create_taxonomies() {
  echo "[INFO] Criando categorias e atributos globais"
  local categories=(
    "Automotivos|automotivos"
    "Parede|parede"
    "Notebook/PC|notebook-pc"
    "Moto|moto"
    "Personalizados|personalizados"
  )
  local entry name slug
  local old_ifs="${IFS}"
  for entry in "${categories[@]}"; do
    IFS='|' read -r name slug <<<"${entry}"
    if ! wp term list product_cat --field=slug --format=csv | grep -q "^${slug}$"; then
      wp term create product_cat "${name}" --slug="${slug}"
    fi
  done
  IFS="${old_ifs}"

  if ! wp wc attribute list --field=slug | grep -q "cor"; then
    wp wc attribute create --name="Cor" --slug="cor" --type=select --order_by=name --has_archives=0 >/dev/null
  fi
  if ! wp wc attribute list --field=slug | grep -q "material"; then
    wp wc attribute create --name="Material" --slug="material" --type=select >/dev/null
  fi
  if ! wp wc attribute list --field=slug | grep -q "largura"; then
    wp wc attribute create --name="Largura" --slug="largura" --type=select >/dev/null
  fi
  if ! wp wc attribute list --field=slug | grep -q "altura"; then
    wp wc attribute create --name="Altura" --slug="altura" --type=select >/dev/null
  fi
}

seed_products() {
  echo "[INFO] Inserindo produtos exemplo"
  wp eval-file scripts/seed_products.php -- --force
}

configure_rank_math() {
  echo "[INFO] Configurando Rank Math"
  wp option update rank-math-options-general '{
    "auto_update":false,
    "support":true
  }' --format=json >/dev/null 2>&1 || true
  wp option update rank-math-options-titles '{
    "homepage_title":"DFS Adesivos | Personalize seu mundo",
    "homepage_description":"Adesivos automotivos, decorativos e personalizados com qualidade profissional."
  }' --format=json >/dev/null 2>&1 || true
}

setup_theme() {
  echo "[INFO] Instalando tema Blocksy e ativando child theme"
  if ! wp theme is-installed blocksy >/dev/null 2>&1; then
    wp theme install blocksy --activate
  else
    wp theme activate blocksy
  fi

  if [[ -d "wp-content/themes/dfs-adesivos-child" ]]; then
    wp theme activate dfs-adesivos-child
  fi
}

run_healthcheck() {
  echo "[INFO] Executando verificação healthcheck"
  wp eval-file scripts/healthcheck.php || true
}

main() {
  wait_for_db
  ensure_wordpress
  configure_general_settings
  install_plugins
  configure_mailhog
  configure_woocommerce
  configure_redis_cache
  configure_w3tc
  setup_theme
  create_pages
  create_menus
  create_taxonomies
  seed_products
  configure_rank_math
  run_healthcheck
  echo "[INFO] Setup concluído."
}

main "$@"
