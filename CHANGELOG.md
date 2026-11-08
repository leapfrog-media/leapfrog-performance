# Changelog

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
