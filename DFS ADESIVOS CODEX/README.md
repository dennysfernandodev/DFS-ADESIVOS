# DFS Adesivos – Stack WordPress/WooCommerce

## Pré-requisitos
- Docker 24+ e Docker Compose Plugin 2+
- Porta 80 (e opcionalmente 443) liberada para o Nginx
- Domínio apontado para o servidor (opcionalmente atrás do Cloudflare)

## Variáveis de ambiente
Edite `.env` conforme necessário:

| Variável | Descrição |
| --- | --- |
| `WP_URL` | URL pública do WordPress (https://dfs.niid.com.br) |
| `WP_TITLE` | Título do site |
| `WP_ADMIN_USER`, `WP_ADMIN_PASS`, `WP_ADMIN_EMAIL` | Credenciais do admin |
| `DB_NAME`, `DB_USER`, `DB_PASS` | Banco MariaDB |
| `REDIS_PASS` | Senha do Redis Object Cache |
| `WHATS_LINK` | Link/CTA do WhatsApp |

> **Trocar domínio**: altere `WP_URL`, ajuste o DNS e rode `./scripts/setup.sh --rerun` (idempotente) + `wp search-replace antiga.novadom.com nova.novadom.com` via WP-CLI, se necessário.

## Desenvolvimento
```bash
# dentro de Sites/DFS ADESIVOS
cp .env .env.local  # opcional para customizações locais

# subir stack
docker compose up -d

# executar provisionamento completo
./scripts/setup.sh
```

### Tarefas VS Code
`/.vscode/tasks.json` contém automações úteis:
- `up`: `docker compose up -d`
- `build`: rebuild sem cache
- `setup`: roda `scripts/setup.sh`
- `logs-*`: tail de logs (wordpress/nginx/db)
- `backup` / `restore`
- `wp-cli`: shell interativo `wp`
- `seed-products`: reexecuta seed de produtos dummy

## Serviços
- **Nginx**: reverse proxy (porta 80 exposta; adapte para HTTPS conforme necessidade).
- **WordPress**: `wordpress:php8.2-fpm` servido pelo Nginx otimizado.
- **MariaDB 10.11**: volume `db_data` persistente.
- **Redis 7**: cache de objetos.
- **Mailhog**: captura de e-mails em dev (`http://localhost:8025`).

## Produção
1. Ajuste DNS para apontar para o VPS/servidor que hospedará a stack (proxy laranja opcional no Cloudflare).
2. Configure certificados TLS diretamente no Nginx (adicione arquivos e atualize `nginx/conf.d/default.conf` se desejar HTTPS).
3. `docker compose up -d --build`.
4. `./scripts/setup.sh` (idempotente: pode ser reexecutado para garantir estado).
5. Caso use Cloudflare, ative modo "Sempre usar HTTPS" e regras de cache (`/cart/`, `/checkout/`, `/my-account/` – *bypass*).

## Backups
```bash
# gera backup em backups/dfs-adesivos-YYYYmmdd-HHMMSS.tar.gz
./scripts/backup.sh

# restaura a partir de arquivo
./scripts/restore.sh backups/dfs-adesivos-20240101-120000.tar.gz
```
Inclui dump do banco (`db.sql`) + `wp-content` completo.

## Healthcheck
Execute para validar o ambiente:
```bash
docker compose run --rm wp-cli eval-file scripts/healthcheck.php
```
Saída `OK/FAIL` para:
- WordPress instalado e permalink `/%postname%/`
- Plugins essenciais ativos (WooCommerce, Pix, Correios, Redis, W3TC, Rank Math etc.)
- Redis ativo (`wp_using_ext_object_cache`)
- Páginas fundamentais publicadas e atribuídas
- Correios + Pix habilitados
- Schema JSON-LD via `dfs_output_schema`
- CTA WhatsApp no menu
- Tentativa de Lighthouse (usa `docker run femtopixel/google-lighthouse` – execute manualmente se necessário)

## Conteúdo inicial
- Tema pai **Blocksy** + child theme `dfs-adesivos-child` (cores, tipografia, header/footer customizados).
- MU Plugin `dfs-core.php` com:
  - Campos extras de produto (tempo de produção, resistência externa)
  - Botões WhatsApp (topo + página do produto com variações dinâmicas)
  - Badge “Desconto no Pix”
  - Schema.org (Organization + Product)
  - Endpoint `/wp-json/dfs/v1/rastreio?code=` redirecionando para Correios
- Seed com 10 produtos dummy (simples/variáveis), atributos globais, categorias, páginas institucionais, menus, Rank Math configurado.

## HTTPS & Cloudflare
- Certifique-se de que o origin está acessível nas portas expostas (80/443).
- Use política de cache estático (HTML bypass para `/cart/`, `/checkout/`, `/my-account/`).
- Ative `Auto Minify` (HTML/CSS/JS) e `Brotli` no Cloudflare.
- Configure redirect 80→443, HSTS e demais cabeçalhos diretamente no `nginx/conf.d/default.conf` caso utilize HTTPS.

## Troubleshooting
- `docker compose logs -f wordpress` para ver WP-FPM.
- `docker compose exec db mysql -u dfs_wp -p` para acessar banco.
- `docker compose run --rm wp-cli plugin list` para auditar plugins.
- Caso Lighthouse falhe por ausência de Docker dentro do container, execute manualmente na máquina host.

Bons deploys! 😄
