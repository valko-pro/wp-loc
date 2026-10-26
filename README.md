# WP-LOC

WP-LOC is a lightweight multilingual plugin for WordPress, built by Vitalii Kaplia for client and personal projects. It replaces WPML on typical custom-built sites — posts, pages, custom post types, categories and tags, menus, media and settings in several languages — without WPML's weight.

It keeps translation links in the same `icl_translations` table and provides the same `icl_*` / `wpml_*` functions and filters as WPML, so an existing WPML site can switch over without converting its data, and most themes and plugins written for WPML keep working.

- **Language management** — Multilingual > Languages page with drag-and-drop ordering (auto-saves via AJAX)
- **Automatic language detection** — install a language in WP General Settings, it auto-appears in WP-LOC. Delete from WP-LOC — removes language files too.
- **Translatable post type translations** — auto-create translation drafts for enabled post types, translation metabox in editor with per-language `+` button for on-demand creation
- **AI-assisted names** — translate enabled post type titles and term names from admin list actions, Gutenberg/classic editor title controls, and term edit controls through the selected WordPress AI connector
- **Translated posts-page editor parity** — localized `page_for_posts` values let WordPress recognize every translated posts page in both Classic Editor and Gutenberg, including the native latest-posts notice and editor restrictions
- **Post translation lifecycle** — shared status/trash state can stay synchronized across a translation group, and permanently deleting any translated post permanently deletes its sibling translations and translation rows
- **Bidirectional post taxonomy sync** — when a translated post changes its multilingual categories/tags/custom taxonomies, sibling posts receive the mapped term translations in their own language and stale foreign-language relationships are removed; REST/Gutenberg `set_object_terms` updates are handled after `save_post`
- **Taxonomy/term translations** — migration-compatible term translation groups for `category`, `post_tag`, and selected custom taxonomies
- **Term translation UI** — translation column in term lists, translation panel on term edit screens, and per-language `+` buttons for on-demand term translation creation
- **Automatic term translation creation** — creating a term can auto-create sibling translations in all active languages
- **Hierarchical taxonomy sync** — translated parent terms are mapped automatically when creating/editing child terms
- **Same slug in different languages** — term slugs are unique per language, not globally
- **Cascade delete for term translations** — deleting any translated term deletes the whole translation group
- **Protected default category group** — default category and all its translations cannot be deleted
- **Multilingual nav menus** — translated menu groups, translated menu items, language-aware menu locations, auto-created menu translations, and cascade deletion for menu translation groups
- **Tools page** — Multilingual > Tools with tabbed utilities for WP Menus Sync and Config Migration
- **WP Menus Sync** — AJAX preview/apply for syncing menu structure from the default language to secondary languages
- **AI-assisted custom menu links** — optional AI translation for `custom` nav menu items during menu sync, while preserving URLs and other menu item settings, with safe fallback when the AI provider refuses a short-field translation
- **Config Migration tool** — detects legacy multilingual config files (`wpml-config.xml`), reads only translatable post types and taxonomies, generates lightweight `wp-loc-config.xml`, and can remove theme-level legacy config files
- **Database Optimization Wizard** — appears in admin after activation, scans multilingual data left by another plugin, adopts compatible translation links, imports languages, detects translated post types/taxonomies/options, and removes obsolete service data after confirmation
- **Manual language mapping during optimization** — the wizard auto-matches detected languages through a built-in registry, shows match confidence, and lets admins override the target WP-LOC language before applying cleanup
- **Language registry** — central locale/code/slug/name/flag normalization for common WordPress and multilingual-plugin language codes, including aliases like `uk` → `ua` and legacy `iw` → `he`
- **Separate URL slugs and compatibility codes** — languages can use URL slugs like `ua` while compatible database/API language codes remain `uk`
- **Non-translatable post types** — work correctly with language URL prefixes (shared content across languages)
- **Translatable post type detection** — if compatible translation rows already exist, WP-LOC detects translated custom post types and taxonomies and merges them into runtime settings even when older saved settings are incomplete
- **Detected translation visibility** — the settings screen shows the number of existing compatible translation records beside each detected post type, so administrators can see why it remains multilingual
- **Selectable content types** — public post types/taxonomies and custom non-public registered objects can be enabled from settings, while internal WordPress objects and specially handled objects stay excluded
- **Frontend/admin query filtering** — translatable posts are filtered by the current language for main, secondary, AJAX, REST, and Gutenberg preview `WP_Query` calls when filters are not suppressed
- **Language-scoped link picker** — WordPress's native Insert/edit Link dialog only lists translatable content from the language currently being edited
- **Frontend AJAX language context** — standard `admin-ajax.php` handlers inherit the current frontend language through request parameters, the referring URL, and compatible cookies, in that order, so AJAX started from a page served by a full-page cache still answers in the language on screen
- **Runtime language switching** — WPML-style `wpml_switch_language` / `$sitepress->switch_lang()` calls temporarily switch WP-LOC's language and WordPress locale, so background handlers and transactional email flows can render content in a user's preferred language
- **URL structure** — the default language has no prefix; additional languages use `/{slug}/page-slug/`, for example `/en/page-slug/`
- **Migrated default language** — the migration wizard preserves the legacy multilingual default language for no-prefix URLs
- **Admin language switcher** — in the admin bar with flags, cookie-based
- **Frontend language switcher** — `wp_loc_get_lang_switcher()`, `wp_loc_get_language_switcher_html()`, `wp_loc_the_language_switcher()` with translated post, custom post type, taxonomy, author, search, date, paginated, query-filtered, and archive URLs
- **SEO** — frontend hreflang alternate tags for translated singular, front page, posts page, and taxonomy contexts; canonical URL fallback when no SEO plugin outputs one; proper `<html lang="">`
- **Yoast SEO compatibility** — localized `wpseo_titles` / `wpseo_social` / `wpseo_rss` options, translated primary category resolution, copied Yoast term SEO meta for translated terms, multilingual sitemap alternate links, stripped category-base compatibility, and Yoast indexable invalidation after multilingual updates
- **Localized options** — `blogname`, `blogdescription`, `page_on_front`, `page_for_posts`, plus options registered through `wp_loc_multilingual_options` or compatible `wpml_multilingual_options`, per language and with localized front page / posts page routing
- **Custom settings page support** — localized options are displayed and saved correctly on WordPress settings pages, including custom submenu pages under Settings that register option names dynamically
- **AI settings** — detect configured WordPress AI provider connectors, choose an available provider and text-generation model, and enable AI translation for custom menu links during menu sync
- **External translation engines** — add-ons can register their own engines (`wp_loc_ai_engines` + `wp_loc_ai_translate_content`); they appear in the engine select on **Multilingual > Settings > AI** and drive the same title, term-name, and menu-link translation actions without a WordPress AI connector
- **Translation workflow settings** — control automatic creation of post, term, and menu translations and enable multilingual behavior for public or custom non-public post types and taxonomies from **Multilingual > Settings > Content Translation**
- **Sync policy settings** — control taxonomy sync, featured image sync, and shared post-attribute sync for translation groups
- **Switcher behavior settings** — control whether the frontend switcher shows flags and names, hides the current language, hides untranslated targets, or falls back to language home URLs
- **Integration toggles** — enable or disable ACF compatibility, Yoast compatibility, and Yoast sitemap alternate links from **Multilingual > Settings > Integrations**
- **Third-party compatibility** — `icl_object_id()`, `$sitepress`, `ICL_LANGUAGE_CODE`, common multilingual filters
- **Multilingual media** — uploads receive linked attachment records for all active languages while sharing one physical file; media queries, featured images, frontend image resolution, and deletion are language-aware
- **ACF integration** — ACFML-like field/group translation config for DB, local JSON, and PHP-registered field groups, plus language-aware `options_{lang}` routing for options pages
- **ACF field translation modes** — `shared`, `copy_once`, `translatable`, and editable shared-value `none` behavior for multilingual field workflows
- **ACF media/relation mapping** — translated attachment, post, term, and nav menu IDs are resolved per language for fields like `image`, `file`, `gallery`, `post_object`, `page_link`, `relationship`, `taxonomy`, and `nav_menu`
- **ACF container field support** — multilingual behavior for `group`, `repeater`, `flexible_content`, and `clone` fields across options pages, posts/pages, and term edit screens
- **ACF nav_menu field support** — translated menu values resolve to the correct menu in the current language context
- **Yoast Duplicate Post integration** — cloning a translatable post also clones its whole translation group; the copies are duplicated through Duplicate Post's own engine and linked together as a new translation group, while Rewrite & Republish is left untouched
- **Timber integration** — Twig functions `wp_loc_language_switcher()` and `wp_loc_languages()`
- **Activation safety** — on activation, WP-LOC deactivates known conflicting multilingual add-ons instead of deleting them
- **GitHub updates** — native WordPress plugin updates from the public GitHub repository by comparing the remote `wp-loc.php` `Version:` header on the configured branch
- **Ukrainian slug** — `uk` locale → `ua` URL slug out of the box

## Requirements

WordPress 6.0 or newer (7.0 for the AI features) and PHP 8.1 or newer.

## Installation

Upload the `wp-loc` folder to `wp-content/plugins/` and activate it. A language is added by picking it once as the Site Language in **Settings → General**; after that it is managed under **Multilingual → Languages**. Updates come through the normal WordPress updater, straight from this repository.

## License

GPLv2 or later. Author: Vitalii Kaplia — [kaplia.pro](https://kaplia.pro/). Plugin site: [wp-loc.com](https://wp-loc.com/).
