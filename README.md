# WW Resources — resources.williamwu.info

A WordPress resource library + tech news site, built as a subdomain of my portfolio (williamwu.info).
Built from scratch as hands-on prep for a WordPress developer role (The West Harbour, Intermediate Developer) —
custom post types, taxonomies, secure meta boxes, Settings API, a dynamic Gutenberg block pulling an external API,
query work, and a custom theme.

## URLs

| Where | URL |
| --- | --- |
| Local | http://localhost:8080 (wp-admin: http://localhost:8080/wp-admin) |
| Live | https://resources.williamwu.info |
| Portfolio | https://williamwu.info (Next.js on Vercel — not part of this repo) |

Planned public URL structure:

- `/` — landing page: featured resources + Tech News block
- `/library/` — archive of all resources (filterable)
- `/library/<resource-slug>/` — single resource
- `/type/<term>/` — resources by type (tutorials, tools, articles, videos)

## Project structure

```
wp-resource/
├── docker-compose.yml        # local WordPress + MariaDB
├── README.md
├── SKILL.md                  # conventions/rules for working on this project with Claude
├── ww-resources/             # the plugin — all functionality lives here
│   └── ww-resources.php
├── ww-theme/                 # (later) block theme — presentation only
└── .github/workflows/
    └── deploy.yml            # (later) GitHub Actions → Hostinger via rsync/SSH
```

Rule of thumb: **functionality in the plugin, looks in the theme.** Switching themes should never lose data or features.

## Local development (Docker)

Requires Docker Desktop running.

```bash
cd ~/Desktop/wp-resource
docker compose up -d                  # start WordPress + database
docker compose logs -f wordpress      # watch PHP errors live
docker compose down                   # stop (data is kept)
docker compose down -v                # wipe everything, fresh start
```

How it works:

- `wordpress` container = Apache + PHP + WordPress core. `db` container = MariaDB (drop-in MySQL).
- `ports: "8080:80"` → site at localhost:8080.
- `WORDPRESS_DB_HOST: db` → WordPress reaches the database container by name.
- `./ww-resources` is mounted into `wp-content/plugins/ww-resources` — edit on the Mac, WordPress sees it instantly.
  WordPress core lives in a Docker volume; never edit it.
- `WORDPRESS_DEBUG: 1` is on, so errors show while developing.
- When the theme starts, add a second mount: `./ww-theme:/var/www/html/wp-content/themes/ww-theme`.

## Hosting & DNS

- Domain registered at **Namecheap**, nameservers point to **Vercel** (`ns1.vercel-dns.com`) — so DNS records are managed in Vercel.
- Subdomain = one `A` record in Vercel DNS: Name `resources`, Value = Hostinger server IP, TTL 60.
  (Don't add the subdomain to a Vercel *project*, and never change nameservers.)
- WordPress installed on **Hostinger** for `resources.williamwu.info`, SSL enabled there.

## Deploying

Code and content move separately:

- **Code** (plugin/theme) → Git push → GitHub Actions rsyncs `ww-resources/` (and later `ww-theme/`) to Hostinger over SSH.
- **Content** (resources, settings) → entered directly on the live site. Local content is throwaway.
  WP Migrate only if a bulk content push/pull is ever needed.

Deploy setup (when ready): enable SSH in Hostinger (port usually 65002), create a deploy key,
add repo secrets `SSH_HOST`, `SSH_USER`, `SSH_PORT`, `SSH_KEY`, `REMOTE_PATH`
(e.g. `/home/<user>/domains/resources.williamwu.info/public_html`), place `deploy.yml` at `.github/workflows/deploy.yml`.

## Roadmap

- [x] Subdomain DNS + Hostinger WordPress live
- [x] Local Docker environment running
- [x] Plugin skeleton (header + `ABSPATH` guard), activated
- [x] **Step 1 — CPT + taxonomy:** `resource` post type (slug `library`, `show_in_rest`), `resource_type` taxonomy (slug `type`, hierarchical)
- [x] **Step 2 — Meta box:** URL + difficulty. Nonce, `current_user_can`, `esc_url_raw` / `sanitize_text_field` on save, escape on output
- [ ] **Step 3 — Settings page:** Settings API — number of news items, news source (dev.to / Hacker News)
- [ ] **Step 4 — Dynamic "Tech News" block:** `block.json` + `render.php`, `wp_remote_get`, `is_wp_error`, timeout, transient cache (whole list cached once — avoids N+1 API calls with HN)
- [ ] **Step 5 — Archive filtering:** `tax_query` / `meta_query` via `pre_get_posts`
- [ ] **Step 6 — Block theme:** `theme.json` styled to match williamwu.info, linked from portfolio nav
- [ ] **Step 7 — Ship:** Query Monitor pass, conditional asset loading (`has_block()`), Git repo + GitHub Actions deploy

## Interview talking points this project covers

- Hooks: `init`, `save_post`, `pre_get_posts`, `admin_init`, `rest_api_init`; actions vs filters
- CPTs + taxonomies (how LearnDash models courses/lessons/topics/quizzes)
- Security quartet: *nonce for intent, capability for permission, sanitize in, escape out*
- External APIs in dynamic blocks with transient caching + invalidation
- Performance: Query Monitor, transients, enqueue only where needed
- Update-safe architecture: plugin vs theme separation, version control, CI deploy
- Local env: Docker for reproducible/team environments, MAMP for quick solo work
