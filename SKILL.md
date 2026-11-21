---
name: ww-resources
description: Conventions, architecture and learning rules for William's WordPress resource library + tech news site (resources.williamwu.info). Use when building, reviewing or deploying any part of the ww-resources plugin, ww-theme, or its Docker/deploy setup.
---

# WW Resources — working rules

## Purpose & mode
- This is **interview prep by building**. William writes the code; Claude explains, gives hints and requirements, and reviews like an interviewer.
- Go **one step at a time**. Don't scaffold or write whole features unless William asks. When he's stuck, give the next hint before the full answer.
- When reviewing, call out: security gaps, missing prefixes, wrong hook, missing `show_in_rest`, unescaped output, and anything an interviewer would ask "why?" about.
- Tie concepts back to the job: Gutenberg blocks, external APIs, security, performance, LearnDash (which is built on CPTs/meta/hooks).

## Architecture
- Local: Docker (`docker-compose.yml`) — WordPress at localhost:8080, MariaDB, plugin folder mounted. Debug on.
- Live: Hostinger WordPress at resources.williamwu.info. DNS = `A` record `resources` in Vercel DNS (nameservers are Vercel; registrar Namecheap). Never change nameservers.
- **Plugin `ww-resources/` = all functionality. Theme `ww-theme/` = presentation only.**
- Deploy: code via Git → GitHub Actions → rsync over SSH to Hostinger. Content is entered on the live site, never deployed.

## Naming & conventions
- Prefix everything with `ww_` (functions, options, meta keys as `_ww_*`, nonces, transients). Block namespace `ww/`.
- Text domain: `ww-resources`.
- Post type key `resource`, rewrite slug `library`. Taxonomy key `resource_type`, rewrite slug `type`, hierarchical.
- Every PHP file starts with the `ABSPATH` guard.
- Register CPTs, taxonomies and blocks on `init`. Settings on `admin_init`/`admin_menu`. REST routes on `rest_api_init`.
- `show_in_rest => true` on CPTs/taxonomies (required for the block editor).

## Non-negotiables
- Security quartet on every form/save: nonce (`wp_nonce_field` / `wp_verify_nonce` or `check_ajax_referer`), capability check (`current_user_can`), sanitize input (`sanitize_text_field`, `esc_url_raw`, `absint`), escape output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- `save_post` handlers also bail on autosave and wrong post type.
- External HTTP: `wp_remote_get` with a timeout, check `is_wp_error` and response code, cache with transients, never expose API keys client-side.
- Direct SQL only via `$wpdb->prepare()`. Prefer `WP_Query` / `pre_get_posts`; never `query_posts()`.
- Enqueue assets properly, versioned, and only where used (`has_block()`).
- After changing rewrite slugs, flush rewrites (Settings → Permalinks → Save, or `flush_rewrite_rules()` on activation only — never on every load).

## Roadmap (see README.md for status)
1. CPT + taxonomy → 2. Meta box (URL, difficulty) → 3. Settings page → 4. Dynamic Tech News block (dev.to / Hacker News, transient-cached) → 5. Archive filtering → 6. Block theme matching williamwu.info → 7. Query Monitor pass + CI deploy.

## Useful commands
- `docker compose up -d` / `down` / `down -v`
- `docker compose logs -f wordpress` for PHP errors

## MORE INFO ON WHAT THE INTERVIEW IS ON BELOW
# Interview Prep — The West Harbour, Intermediate Developer

Prepared 2026-10-14. Role: [Intermediate Developer](https://thewestharbour.com/opportunity/intermediate-developer/)

## Role snapshot (know the facts cold)

- **Full-time (40h), hybrid 2 days/week in office, Vancouver.** Office at 2230 Ontario St, Mt Pleasant.
- **One-year contract with potential for extension.** It's contract, not permanent — you've been open to contract work, so this is consistent.
- **Single client**, centred on their WordPress + LearnDash (LMS) ecosystem.
- Core stack: custom Gutenberg blocks, their proprietary block-based boilerplate, HTML5/CSS3/SCSS, PHP, JavaScript, Figma-to-theme builds, external API integrations, performance/security work.
- Asks **3+ years professional experience** (you have 2+ — they invited you anyway, so don't apologize for it; talk about what you've shipped).

## What to refresh, in priority order

### 1. Hooks — actions vs filters (guaranteed to come up)

```php
// Action: do something at a point in execution
add_action('wp_enqueue_scripts', 'wh_enqueue_assets');

// Filter: modify a value passing through
add_filter('the_content', 'wh_add_banner', 10, 1);
```

Key hooks to have on the tip of your tongue: `init` (register post types/blocks), `wp_enqueue_scripts`, `save_post`, `pre_get_posts`, `admin_init`, `rest_api_init`. Know the difference between `add_action`/`do_action` and `add_filter`/`apply_filters`, plus priority and accepted-args parameters.

### 2. Custom Gutenberg blocks (their whole boilerplate is block-based)

Modern block anatomy — `block.json` + `register_block_type`:

```json
{
  "apiVersion": 3,
  "name": "wh/api-card",
  "title": "API Card",
  "attributes": { "endpoint": { "type": "string" } },
  "render": "file:./render.php"
}
```

- **Static blocks**: `save.js` returns markup stored in post_content.
- **Dynamic blocks**: `save` returns null, PHP `render_callback` (or `render.php`) generates markup server-side. This is the pattern for anything pulling live data — and the posting explicitly asks for "connect external APIs to custom blocks."
- Inside `edit.js`: `useBlockProps()`, `InspectorControls` for sidebar settings, `InnerBlocks` for nested layouts.

If they ask "how would you build a block that shows data from an external API": register the block, store the endpoint/params as attributes, and render server-side with `wp_remote_get()` + `set_transient()` caching so the API isn't hit on every page load. Mention cache invalidation (delete the transient on `save_post`).

### 3. Talking to external APIs

```php
$response = wp_remote_get('https://api.example.com/v1/items', [
    'timeout' => 10,
    'headers' => ['Authorization' => 'Bearer ' . $token],
]);
if (is_wp_error($response)) { /* handle */ }
$data = json_decode(wp_remote_retrieve_body($response), true);
```

Points to mention: always check `is_wp_error()`, set timeouts, cache with transients, never expose API keys in front-end JS (proxy through a REST route or `admin-ajax.php` with a nonce).

### 4. WordPress database + `$wpdb`

- `wp_posts` holds everything (posts, pages, attachments, revisions); `wp_postmeta` is the key/value store joined on `post_id`. This is where N+1/query-bloat problems live.
- `WP_Query` with `meta_query` / `tax_query`; `pre_get_posts` to modify the main query instead of running a second one.
- Direct queries: always `$wpdb->prepare()`:

```php
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}my_table WHERE status = %s", $status)
);
```

### 5. Security (they list it explicitly)

The four things to name, with functions:
1. **Nonces** — `wp_nonce_field()` / `wp_create_nonce()`, verified with `check_admin_referer()` or `check_ajax_referer()`.
2. **Capability checks** — `current_user_can('edit_posts')` before doing anything privileged.
3. **Sanitize input** — `sanitize_text_field()`, `absint()`, `sanitize_email()`.
4. **Escape output** — `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` for limited HTML.

One-liner for the interview: "Verify intent with a nonce, verify permission with a capability check, sanitize on the way in, escape on the way out."

### 6. Performance (they list diagnosing speed issues)

- **Query Monitor** plugin — first thing you install when a page is slow; shows slow queries, duplicate queries, hook timing.
- **Transients API** for expensive computations/API calls: `set_transient()` / `get_transient()` / `delete_transient()`.
- Object cache (Redis/Memcached) on bigger sites; page caching via host or plugin.
- Enqueue properly: `wp_enqueue_script()` with version strings, `defer`/`async` strategy, only load assets where needed (`is_page()`, `has_block()`).
- Images: `srcset`/`sizes`, modern formats, lazy loading.

### 7. LearnDash (their ecosystem — biggest knowledge gap to close)

LearnDash models courses as custom post types in a hierarchy:
- `sfwd-courses` > `sfwd-lessons` > `sfwd-topic` > `sfwd-quiz`

Things worth knowing before you walk in:
- Courses/lessons/topics/quizzes are all post types — so `WP_Query`, meta, and hooks all apply normally.
- Key hooks: `learndash_lesson_completed`, `learndash_course_completed`, `learndash_update_course_access`.
- Access/enrollment is managed through user meta and course access lists.
- Reporting and progress live in user meta (`_sfwd-course_progress`).

You don't need to fake deep LearnDash experience — the honest line is: "I haven't built on LearnDash specifically, but I've worked with WordPress custom post types, hooks, and plugin APIs, and LearnDash exposes its LMS model through exactly those primitives, so I'd ramp fast." Read the [LearnDash developer docs](https://developers.learndash.com/) hooks reference for an hour and you'll sound credible.

### 8. Modern PHP (shake off the rust)

- PHP 8: typed properties/params, constructor promotion, `match`, null-safe operator `?->`, named arguments, arrow functions `fn() =>`.
- Namespaces + autoloading (Composer) in plugin boilerplates.
- OOP patterns you'll see: singletons in older plugins, service/container patterns in modern ones.

### 9. Figma-to-theme workflow

They do "pixel-perfect Figma prototypes to custom themes." Be ready to describe your process: extract spacing/typography/color tokens, set up SCSS variables and mixins, build mobile-first, use their block boilerplate for sections, check against the design at breakpoints, cross-browser/device testing (they list this too).

## Likely technical questions (from an intermediate dev) + talking points

**"Walk me through building a custom Gutenberg block that displays external API data."**
Register via `block.json`, endpoint as an attribute, server-side render with `render_callback`, fetch with `wp_remote_get`, cache with transients, invalidate on content save. Mention error states and loading fallbacks.

**"A client page takes 8 seconds to load. How do you find the problem?"**
Install Query Monitor, look for slow/duplicate queries (usually `wp_postmeta` bloat or uncached API calls), check for unoptimized images and render-blocking scripts, then fix with transients/object cache, proper enqueueing, and image optimization. Give a real example if you have one.

**"How do you handle a form submission securely in WordPress?"**
Nonce field, capability check, sanitize each input, validate, escape on output. If AJAX: `check_ajax_referer()`.

**"How do you keep customizations update-safe?"**
Child themes, hooks and filters instead of editing core/plugin files, custom functionality in plugins not themes, version control everything.

**"How would you extend LearnDash — say, custom logic when a user completes a lesson?"**
Hook into `learndash_lesson_completed`, run custom logic (award something, call an API, update user meta). Honest framing from section 7 applies.

**"What's the difference between `WP_Query` and `query_posts()`?"**
Never use `query_posts()` — it clobbers the main query. Use `WP_Query` for secondary loops, `pre_get_posts` to alter the main one.

## Hands-on polish plan (do before the interview)

1. **Spin up a local WordPress** (Local by Flywheel is fastest) and build one dynamic Gutenberg block that fetches from a public API (e.g. JSONPlaceholder) with transient caching. This single exercise covers blocks, `wp_remote_get`, transients, and `render.php` — half their requirements.
2. **Write a tiny plugin** with an admin settings page and a nonce-protected form. Covers plugin structure, Settings API, security.
3. **Read the LearnDash developer docs** hooks list for one focused hour — enough to speak their language.
4. **Re-read your own past WordPress work** and pull 2–3 stories: one performance fix, one custom build, one tricky bug. Interviewers remember stories, not definitions.

## Questions to ask them

- Is the single client an existing LearnDash site you're extending, or a new build?
- What does the extension decision look like at the end of the one-year contract?
- How is the dev team structured — who would I pair with day to day?
- What's the state of the Gutenberg boilerplate — documented, or tribal knowledge?
- How do you handle deploys and environments (staging, CI)?

## One honest gap to carry

The posting wants deep LearnDash experience and "very strong" WordPress/PHP. You have the WordPress/PHP foundation and the Gutenberg-era skills are learnable fast — lead with what's real, name the LearnDash gap once with the ramp plan, and don't oversell. They invited you knowing your background.