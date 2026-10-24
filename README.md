---
Title: Sepo Podcast
Contributors: Euneolink Solutions
Tags: podcast, audio, player, rss, episodes
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Description: Sepo Podcast turns WordPress into a home for your show. Every episode is a normal WordPress post with its own page, so it works with your theme, comments, search and SEO plugins.
---

Publish podcast episodes with a clean player, likes, download counts, sign-in-only downloads

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

## Installation

1. Install and activate the plugin.
2. A page called **Podcast** is created with the episode list on it.
3. Go to **Podcast → Add episode**, give it a title and show notes, then in **Episode details** choose the audio.
4. Go to **Settings → Sepo Podcast** to fill in the feed details (show title, artwork, category, owner email) before submitting your feed.

###Protected files on nginx 

Apache and LiteSpeed hosts block the protected folder automatically. nginx ignores `.htaccess` files, so Settings → Sepo Podcast shows a red notice with the one line to add to your server configuration:

`location ^~ /wp-content/uploads/sepo-podcast-private/ { deny all; }`

## Frequently Asked Questions 

[ ] What is the feed address? 

`https://your-site/feed/podcast-feed/`. For one series: `https://your-site/feed/podcast-feed/?series=series-slug`. The address is shown on the settings page.

[ ] Are sign-in-only episodes in the feed? 

No. Podcast apps cannot sign in, so those episodes are left out of the public feed.

[ ] Can a guest still listen to a protected episode? 

Yes, on the episode page. The player uses a signed address that expires (after two days by default, filter `sepo_podcast_stream_ttl`), and the Download button asks for sign-in. Anyone who can play audio can record it, so treat "sign-in to download" as a strong deterrent, not DRM.

[ ] I use a page cache. Anything to know? 

Counts on episode pages refresh themselves after the page loads. If you cache pages for longer than two days, raise `sepo_podcast_stream_ttl` or exclude episode pages, so player addresses for protected files do not expire inside cached pages.

[ ] How do I change the look? 

Change the accent colour in Settings → Sepo Podcast. For more, copy any file from the plugin's `templates/` folder into `your-theme/sepo-podcast/` and edit it there. All styles use `--sp-*` CSS variables.

[ ] What does the importer accept? 

A .zip of Markdown files. Each starts with front matter such as `title`, `src` (audio), `image`, `duration`, `season`, `episode`, `published`, `host`, `guest`, `group` (series), `tags`, `explicit`, `download`, `gated` and `order`; common alternative names (`audio`, `cover`, `series`, `date`…) are understood too. Files next to them in the zip are added to your site; addresses starting with http are linked. `likes.json` and `downloads.json` (`{"episode-key": 42}`) carry old totals over. Importing the same zip again updates episodes instead of duplicating them, and never changes whether they are published.

From the command line: `wp sepo-podcast import episodes.zip --status=publish --base-url=https://old.example.com/`

[ ] What happens to my data if I delete the plugin? 

Nothing, unless you tick the boxes under Settings → Sepo Podcast → "When the plugin is deleted". You can choose to remove likes, counts and settings, and optionally every episode and the protected audio files.

* First release 1.0.0 
