=== Sepo Podcast ===
Contributors: sepodesk
Tags: podcast, audio, player, rss, episodes
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish podcast episodes with a clean player, likes, download counts, sign-in-only downloads and an Apple Podcasts / Spotify ready feed.

== Description ==

Sepo Podcast turns WordPress into a home for your show. Every episode is a normal WordPress post with its own page, so it works with your theme, comments, search and SEO plugins.

**For listeners**

* A styled player with 15-second back, 30-second forward and playback speed. It remembers where each listener stopped.
* An episode list with series filters, search and pages — each view has its own address, and it all works without JavaScript too.
* Press play on any episode in the list and it keeps playing in a small player docked at the bottom of the screen.
* Like button for signed-in visitors, and a download button with a live count.

**For you**

* Episodes, Series (with your own order) and Episode tags.
* Audio from the Media Library, from another site (a CDN or your old host), or as a **protected file**.
* **Only signed-in visitors may download**: protected files are stored outside the Media Library and are only sent after the sign-in check, so copying the address does not get round it.
* Download counts that ignore double clicks, and likes that are one per person. Sort the episode list by either; reset them from the row actions.
* A podcast feed with Apple Podcasts (itunes:) and Podcasting 2.0 (podcast:guid, podcast:locked, podcast:person) tags, ready to submit to Apple Podcasts, Spotify and other apps. One feed per series too.
* An importer for Markdown episode files (front matter + show notes), including audio, artwork and old like/download totals. Also available as a WP-CLI command.
* A "Podcast episodes" block and a `[sepo_podcast]` shortcode.
* Works with the WordPress privacy tools: personal data export and erase, and suggested privacy policy text.

**No tracking, no external services.** The plugin never contacts another server. The only outgoing request is a check of your own site, to see if the protected folder is really blocked.

== Installation ==

1. Install and activate the plugin.
2. A page called **Podcast** is created with the episode list on it.
3. Go to **Podcast → Add episode**, give it a title and show notes, then in **Episode details** choose the audio.
4. Go to **Settings → Sepo Podcast** to fill in the feed details (show title, artwork, category, owner email) before submitting your feed.

= Protected files on nginx =

Apache and LiteSpeed hosts block the protected folder automatically. nginx ignores `.htaccess` files, so Settings → Sepo Podcast shows a red notice with the one line to add to your server configuration:

`location ^~ /wp-content/uploads/sepo-podcast-private/ { deny all; }`

== Frequently Asked Questions ==

= What is the feed address? =

`https://your-site/feed/podcast-feed/`. For one series: `https://your-site/feed/podcast-feed/?series=series-slug`. The address is shown on the settings page.

= Are sign-in-only episodes in the feed? =

No. Podcast apps cannot sign in, so those episodes are left out of the public feed.

= Can a guest still listen to a protected episode? =

Yes, on the episode page. The player uses a signed address that expires (after two days by default, filter `sepo_podcast_stream_ttl`), and the Download button asks for sign-in. Anyone who can play audio can record it, so treat "sign-in to download" as a strong deterrent, not DRM.

= I use a page cache. Anything to know? =

Counts on episode pages refresh themselves after the page loads. If you cache pages for longer than two days, raise `sepo_podcast_stream_ttl` or exclude episode pages, so player addresses for protected files do not expire inside cached pages.

= Does it match my theme? =

It uses your theme's font everywhere. With the SepoDesk theme it also takes the theme's colour tokens (--sd-*), so the player, buttons, links, cards and borders follow the theme, its skins and its dark mode automatically. Leave "Use my theme's colours" ticked in Settings → Sepo Podcast for that.

= How do I change the look? =

Untick "Use my theme's colours" and pick an accent colour in Settings → Sepo Podcast; the text on it is chosen automatically for contrast. For more, copy any file from the plugin's `templates/` folder into `your-theme/sepo-podcast/` and edit it there. All styles use `--sp-*` CSS variables.

= What does the importer accept? =

A .zip of Markdown files. Each starts with front matter such as `title`, `src` (audio), `image`, `duration`, `season`, `episode`, `published`, `host`, `guest`, `group` (series), `tags`, `explicit`, `download`, `gated` and `order`; common alternative names (`audio`, `cover`, `series`, `date`…) are understood too. Files next to them in the zip are added to your site; addresses starting with http are linked. `likes.json` and `downloads.json` (`{"episode-key": 42}`) carry old totals over. Importing the same zip again updates episodes instead of duplicating them, and never changes whether they are published.

From the command line: `wp sepo-podcast import episodes.zip --status=publish --base-url=https://old.example.com/`

= What happens to my data if I delete the plugin? =

Nothing, unless you tick the boxes under Settings → Sepo Podcast → "When the plugin is deleted". You can choose to remove likes, counts and settings, and optionally every episode and the protected audio files.

== Screenshots ==

1. An episode page: the player, Like and Download buttons, and more episodes from the same series.
2. The episode list with series filters, search and the player docked at the bottom of the screen.
3. The same list in dark mode, following the SepoDesk theme.

== Changelog ==

= 1.1.2 =
* Cleaner admin: the episode list, Series and Episode tags screens get rounded, flat controls and a rounded table.
* New Audio column with a format badge (MP3, M4A, LINK), a shield for protected files and a lock for sign-in-only downloads.
* Author and Comments columns are hidden by default (turn them back on under Screen Options).

= 1.1.1 =
* WordPress.org Plugin Check clean-up: database queries use identifier placeholders, template variables are prefixed, templates print directly instead of echoing strings.
* Faster "More episodes" and protected-file checks (no post__not_in queries).

= 1.1.0 =
* Follows the SepoDesk theme's colour tokens, skins and dark mode automatically.
* New setting: "Use my theme's colours" (on by default). A custom accent colour now gets readable text picked automatically.
* Flat design: no shadows; the episode playing is shown with a tinted card instead of a side stripe.
* Brighter player times in dark mode for readability.
* Fix: the theme's heading spacing no longer pushes episode titles down in cards.
* Fix: the bottom player no longer leaves a gap when an episode has no artwork.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.1.2 =
A tidier, easier-to-scan episode list in the admin.

= 1.1.1 =
Code quality and compatibility update for WordPress 7.1. No settings change.

= 1.1.0 =
Matches the SepoDesk theme and its dark mode, flat styling and better contrast.

= 1.0.0 =
First release.
