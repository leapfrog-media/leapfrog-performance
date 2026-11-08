=== Leapfrog Performance ===
Contributors: Leapfrog Media
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Leapfrog Media performance and hardening plugin. It removes what WordPress
loads but your site doesn't use; media, security, per-plugin asset rules and
site tools follow in later versions.

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

== Moving from ASE and FlyingPress ==
Features > Import settings lists what ASE and FlyingPress remove that
Leapfrog's tabs cover, with what each setting would become. Only what those
plugins actually do is offered, so nothing Leapfrog removes is put back.
Afterwards, switch the same settings off in those plugins.

== Left to other plugins ==
Log-in security (FluentAuth), image compression and resizing (ShortPixel),
caching and CSS/JavaScript optimisation (FlyingPress).

== Changelog ==
See CHANGELOG.md.
