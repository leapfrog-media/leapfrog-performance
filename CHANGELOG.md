# Changelog

## 0.5.0 — 2026-11-08

Site tools, as four switchable tabs.

- **Redirects**: add redirects one by one or import a CSV (`from,to[,code]`; full old addresses work), export them, and remove ticked ones. 301, 302 or 410 Gone. Sources match by path, ignoring case, query strings and the trailing slash; `/old/*` covers everything under /old/, and a `*` in the target carries the rest across (`/blog/*` → `/news/*`). Redirects only apply to addresses that don't exist, so a page always wins. Targets must start with / or be a full http(s) address. **Missing addresses** log (on by default): the last 200 not-found addresses with counts and where visitors came from, skipping attack probes, with "Add redirect" beside each. Options `leapfrog_performance_redirects`, `leapfrog_performance_404_log`.
- **Site access**: open, **maintenance mode** (503 with Retry-After and noindex; a Bricks template as the page, or a simple page with your title and message; staff chosen by permission see the real site) or **behind a password** (one shared password, stored as a WordPress password hash; visitors stay let in for 30 days; logged-in people go straight in). The log-in page, admin, AJAX, cron, REST API and robots.txt stay open. While the site isn't open: a red reminder in the toolbar, pages aren't cached (FlyingPress and `DONOTCACHEPAGE`), and saving the tab empties FlyingPress's page cache (also WP Rocket, W3 Total Cache, LiteSpeed when present).
- **Search engines**: your own robots.txt text (Settings > Reading's "discourage" still wins; a robots.txt file on disk is detected), WordPress's sitemap on or off, content types and category/tag lists left out, and no users sitemap (the standard).
- **Rollback**: put a WordPress.org plugin or theme back to one of its last 30 versions through WordPress's own updater; a plugin that was active is switched back on afterwards.
- Settings forms gained text, multi-line text, post picker and password ("secret") fields.

## 0.4.0 — 2026-11-08

Load only where used, replacing the Praxis mu-plugin pl-latepoint-assets for any plugin.

- **Load only where used** tab: for each plugin, its CSS and JavaScript load everywhere (as now), only at chosen addresses (/book/, /shop/*, / for the home page), or nowhere on the site. The plugin itself keeps running; whole plugins are never switched off.
- Leapfrog notes which plugin files each page loads while administrators browse the site (option `leapfrog_performance_assets_seen`); files a plugin adds later are also caught by their address. Rules: option `leapfrog_performance_assets`.
- The admin, the Bricks builder and the Customizer always load everything. `?lfp-all-assets=1` (administrators) shows a page with no rules, for comparing. Leapfrog's own plugins aren't listed.
- The importer notes when pl-latepoint-assets is present, so its pages can be set as a LatePoint rule.

## 0.3.0 — 2026-11-08

Security: hardening beside FluentAuth (which keeps log-in limits, two-factor, passkeys, XML-RPC, application passwords, hiding usernames, IP rules, file scans and the audit log).

- **Switch off the file editors** (standard: on): defines `DISALLOW_FILE_EDIT`, unless wp-config.php already sets it (the tab says so). Plugin and theme updates still work.
- **Add security headers** (on): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` (the Bricks builder still works), `Referrer-Policy: strict-origin-when-cross-origin`, on the site, the admin and the log-in page. **Always use HTTPS (HSTS)** is separate and off by default.
- **Hide the WordPress version** (on): no generator tag, and WordPress's own file addresses carry a code instead of the version (it still changes on updates).
- **Hide the users list from visitors** (on): `/wp-json/wp/v2/users` only for logged-in people.
- **Switch off author pages** (on): /author/name/ and ?author=1 go to the home page; author links point there too.
- **Vague log-in errors** (on): wrong usernames and wrong passwords get the same message; lockout and other messages are unchanged.
- Import: ASE's "Disable Plugin and Theme Editor".
- Fix (Bloat): Heartbeat off on the site no longer unregisters the Heartbeat script (WordPress 7 warned that the session-expired check needs it); both are now simply not loaded.

## 0.2.0 — 2026-11-08

Media, replacing Disable Media Pages, Disable Media Sizes, the Praxis mu-plugins pl-social-image-size and pl-hide-captions, and the "Bricks Image Size Options" snippet.

- **Switch off media pages** (standard: on): media pages show "not found" (WordPress no longer redirects them to a guessed address), links to them go to the file, and new uploads get a random address so they don't take page addresses. **Free up media addresses** gives up to 500 existing media items a random address at a time, without changing their files, usage or "last modified" date.
- **Image sizes** table: choose which sizes WordPress makes (Thumbnail always stays) and which appear in the Bricks and media size pickers (Full included). Applies to new uploads.
- **Add the Social image size** (1200 × 630, cropped; standard: on), shown as "Social" in the pickers.
- **No big image copies** (WordPress's "-scaled" copy of uploads over 2560 px), **Hide image captions on the site**, **Allow AVIF uploads** (standard: on).
- **Import** now covers several tabs and sources: ASE (AVIF, disabled sizes), Disable Media Pages, Disable Media Sizes (sizes and big image copies) and the two Praxis mu-plugins, in one table with a "From" column.
- Feature fields can add their own rows to a tab's form, and turn posted form fields into settings (`leapfrog_performance_posted`).

## 0.1.0 — 2026-11-08

Foundations and Bloat.

- Leapfrog > Leapfrog Performance, in the menu shared by all Leapfrog plugins (`includes/menu.php`, same logic as Leapfrog Admin and Leapfrog Framework), after Leapfrog Admin and Leapfrog Framework.
- Tabbed settings page; each tab is a feature that can be switched off on the Features tab (option `leapfrog_performance_disabled_features`). Settings for all features are kept in one option, `leapfrog_performance`, by feature.
- Standard settings: activating on a site with no settings writes Leapfrog's standard settings.
- **Bloat** tab, replacing FlyingPress's Bloat tab and the matching ASE settings:
  - On the site: emoji, embeds (oEmbed), RSS feeds (feed addresses go to the home page), jQuery Migrate, Dashicons for visitors, head clutter (RSD, Windows Live Writer, shortlink, generator, REST API link), block editor CSS and global styles (off by default), WordPress lazy loading (on by default when FlyingPress is active), site search (off by default).
  - Comments off everywhere (the standard), off for chosen content types, or kept; pingbacks and trackbacks off, including self-pings.
  - In the admin: revisions kept (10), Heartbeat in the admin (60 seconds), in the editor (WordPress default) and on the site (off), autosave interval.
  - Emails: admin email check, password change email to the administrator, to the person (kept by default), auto-update emails (failure emails still come).
  - XML-RPC is left to FluentAuth.
- **Import settings** (Features tab): lists what ASE and FlyingPress remove that the Bloat tab covers, with the current and new value, and imports the ticked rows. An option those plugins have switched off is never offered, so an import can't put back something Leapfrog removes.
