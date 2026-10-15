# Theme instructions

Rules for working inside this theme (Timber 2 + Twig, ACF Pro blocks, SCSS + Webpack, PSR-4 in `app/`). The `README.md` is the full reference — read it before larger changes. Changes that should land in every future project belong in the starter repo this theme is cloned from.

## Layout

- `app/` — PHP classes (namespace from `composer.json`): `Core/` bootstrap, assets, ACF, cache, `Appearance` (colours/fonts/logo/header options), `Tracking` (GTM, Consent Mode, cookie banner); `Blocks/` registry, `BlockContext`, `SectionSettings`, editor; `Rest/` REST routes; `Content/` view models.
- `views/` — Twig: `base.twig`, page templates, `partials/`, `components/` (small UI), `modules/` (larger reusable sections), `blocks/`, `woocommerce/`.
- `patterns/*.php` — ready page layouts shown in the editor under "WCO sections".
- `assets/` — SCSS and JS sources. `public/` is webpack output — never edit it by hand.
- `theme.json` is generated from `assets/scss/base/_variables.scss` (on `npm run dev`/`build`) — change tokens there, not in `theme.json`.
- Site-wide options (colours, fonts, logo, header, tracking, extra head/body code) live in the admin page "Ustawienia motywu"; do not hardcode GTM/analytics snippets in templates.

## Blocks

- Create new blocks with the generator, not by hand: `npm run create-block -- <slug> "<Title>" [--preset=content|items|slider|basic]`.
  - `content` — text, images and buttons are edited directly in the editor preview (InnerBlocks). Default choice for new sections.
  - `items` — parent block + child block `<slug>-item` for repeated tiles, FAQ, testimonials…
  - `slider` — Swiper skeleton; `basic` — older model with all content in ACF fields.
- Preferred model: ACF stores only settings, content is native WP blocks inside the block. Starting content is `inner_template` in `<slug>.include.php` (helpers: `BlockContext::head_template()`, `BlockContext::buttons_template()`); in Twig use `{{ inner_blocks(inner_template) }}`. Repeated items are child blocks (`"parent": [...]` in `block.json`), not ACF repeaters.
- Each block keeps everything in `views/blocks/<slug>/`: `block.json`, `<slug>.twig`, `_<slug>.scss`, `<slug>.js`, `<slug>.include.php`, `group_<slug>.json`.
- Shared section settings (background, width, ID, gaps/spacing) are one ACF group; enable it with `"wco": { "sectionSettings": true }` in `block.json`. Do not copy those fields into block groups. Defaults: `BlockContext::SECTION_DEFAULTS`.
- ACF field groups are Local JSON. Keep field keys stable; edit the JSON directly or via ACF admin (it writes back to the file).
- Before adding markup, reuse existing `components/` and `modules/` and the Twig function `wco_image()` for images.
- Block JS is a vanilla JS class loaded only when the block is in the DOM. Sliders go through `SwiperRegistry`.
- Existing sections: hero, section-heading, text-image, cta, separator, features, faq, testimonials, stats, logos, gallery, latest-posts, pricing, team, timeline, video, map, contact, container-group, two-columns, spacer, testimonials-slider. Check these before building a new one.

## Styles

- Global tokens (colours, fonts, breakpoints, containers): `assets/scss/base/_variables.scss`. Colours, fonts, radii are also exposed as CSS variables (`--color-*`, `--radius-*`) and overridden by the Appearance panel — use the variables, never hardcoded hex values. New appearance option: `Appearance::register_fields()`, `DEFAULTS`, `Appearance::css()`.
- Use the existing mixins (`container()`, `mobile`, `tablet`, `respond-up()`, `inner-layout`, `grid-columns()`…) and section classes (`.section-gap-*` = margin, `.section-space-*` = padding).
- Block styles stay next to the block; plugin overrides go to the matching component partial, not the reset.
- Block stylesheets are also loaded in the whole admin screen: never add rules on `body`, `h1`–`h6`, `a`, `p` there, they would restyle the WordPress UI.

## PHP

- New REST endpoint: class in `app/Rest/Routes/` implementing `RouteInterface`, then add it to `app/Rest/Api.php`.
- Use `Core\Cache::remember()` for expensive queries.
- Escape output in Twig (autoescape is off) and in PHP; sanitize input, check nonces/capabilities.
- Translatable strings use the theme text domain.
- WooCommerce is optional — guard shop code so the theme works without it.
- Third-party embeds (video, map) load only after a click; keep it that way for consent compliance.

## Commands

Run in the theme directory (Node from `.nvmrc`):

```bash
composer install            # once, and after changing composer.json
npm install                 # once
npm run dev                 # watch
npm run build               # production build; run after changing SCSS/JS
composer dump-autoload -o   # after adding/renaming PHP classes
```

After adding a file to `patterns/`, clear WordPress' pattern cache: `wp eval 'wp_get_theme()->delete_pattern_cache();'`.

## Rules

- Content and admin UI are in Polish; code, comments and commit messages in English.
- Do not modify third-party plugins; put customisations in the theme or a dedicated custom plugin.
