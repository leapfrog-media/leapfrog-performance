=== Leapfrog Performance ===
Contributors: Leapfrog Media
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Leapfrog Media performance and hardening plugin. It removes what WordPress
loads but your site doesn't use; holds the media settings, adds hardening beside
FluentAuth, loads plugin files only where used, and has site tools.

== Installation ==
1. Install and activate the plugin. A site with no Leapfrog Performance
   settings gets Leapfrog's standard settings straight away.
2. Open Leapfrog > Leapfrog Performance.

== Features ==
Each tab is a feature that can be switched off on the Features tab; a feature
that is off loads no code.

Bloat: emoji, embeds, RSS feeds, jQuery Migrate, Dashicons for visitors, head
clutter, block editor CSS, WordPress lazy loading, site search, comments,
pingbacks, revisions kept, Heartbeat (admin, editor, site), autosave interval,
the admin email check, password change emails and auto-update emails.

Media: switch off media pages (and free up the page addresses media items
hold), choose which image sizes are made and shown in the Bricks and media
pickers, add the 1200 x 630 Social size, stop big image copies, hide captions on
the site, allow AVIF uploads.

Security (beside FluentAuth): switch off the file editors, security headers,
optional HSTS, hide the WordPress version, hide the users list from visitors,
switch off author pages, vague log-in errors.

Load only where used: for each plugin, its CSS and JavaScript load everywhere,
only at chosen addresses, or nowhere on the site. Plugins appear once their
files have been seen on the site; ?lfp-all-assets=1 shows a page without rules.

Redirects: 301, 302 and 410, one by one or from a CSV file, with wildcards, plus
a log of missing addresses. Site access: maintenance mode (a Bricks template as
the page) or one shared password for previews; page caches are emptied and
skipped while the site isn't open. Search engines: robots.txt and what the
WordPress sitemap lists. Rollback: earlier versions of WordPress.org plugins and
themes.

== Moving from other plugins ==
Features > Import settings lists what ASE, FlyingPress, Disable Media Pages,
Disable Media Sizes and the Praxis mu-plugins do that Leapfrog's tabs cover,
with what each setting would become. Only what those plugins actually do is
offered, so nothing Leapfrog removes is put back. Afterwards, switch the same
settings off in those plugins (or remove the mu-plugins).

== Left to other plugins ==
Log-in security (FluentAuth), image compression and resizing (ShortPixel),
caching and CSS/JavaScript optimisation (FlyingPress).

== Updates ==
Updates come from GitHub through WordPress's own updater (Dashboard > Updates,
the Plugins screen, automatic updates). The newest version tag (v1.2.3) on
github.com/leapfrog-media/leapfrog-performance is the newest version, so a release is just a
tagged commit, pushed. Public repositories need nothing more. Private ones need
a GitHub token that can read them: LEAPFROG_GITHUB_TOKEN in wp-config.php, or
the Updates tab of Leapfrog Admin or Leapfrog Performance (one token covers all
Leapfrog plugins). A copy that is a Git working folder is never updated.

== Changelog ==
See CHANGELOG.md.
