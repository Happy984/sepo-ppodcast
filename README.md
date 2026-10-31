# Sepo Podcast

Publish podcast episodes on WordPress with a clean player, likes, download counts, sign-in-only downloads and a feed that's ready for Apple Podcasts and Spotify.

<p align="center">
  <a href="https://edu.sepodesk.com/sponsor/projects-maintenance/">
    <img src="https://img.shields.io/badge/%E2%9D%A4%20SUPPORT%20OUR%20OPEN--SOURCE%20PROJECTS-0b6b45?style=for-the-badge&logo=githubsponsors&logoColor=white" alt="Support Our Open-Source Projects" />
  </a>
</p>

<p align="center">
  Your support helps us maintain, improve and build open-source software for everyone.
  <br />
  <strong>Every contribution makes a difference. Thank you for supporting our work!</strong>
</p>


![Version](https://img.shields.io/badge/version-1.1.0-0b3d33)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)



## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Usage](#usage)
- [Importing episodes](#importing-episodes)
- [Customising the look](#customising-the-look)
- [FAQ](#faq)
- [Uninstalling](#uninstalling)
- [Privacy](#privacy)
- [Changelog](#changelog)
- [License](#license)

## Features

Every episode is a normal WordPress post with its own page, so it works with your theme, comments, search and SEO plugins.

**For listeners**

- A player with 15 seconds back, 30 seconds forward and playback speed. It remembers where each listener stopped.
- An episode list with series filters, search and pages. Each view has its own address, and the list works without JavaScript.
- Press play on any episode in the list and it keeps playing in a small player docked at the bottom of the screen.
- A Like button for signed-in visitors, and a Download button with a live count.

**For you**

- Episodes, series (in an order you choose) and episode tags.
- Audio from the Media Library, from another site (a CDN or your old host), or as a **protected file**.
- **Only signed-in visitors may download.** Protected files are stored outside the Media Library and are only sent after the sign-in check, so copying the file address doesn't get round it.
- Download counts that ignore double clicks, and one like per person. Sort the episode list by either, and reset them from the row actions.
- A podcast feed with Apple Podcasts and Podcasting 2.0 tags, ready to submit to Apple Podcasts, Spotify and other apps. Each series can have its own feed too.
- An importer for Markdown episode files, including their audio, artwork and old like and download totals. It also runs from WP-CLI.
- A **Podcast episodes** block and a `[sepo_podcast]` shortcode.
- Matches the SepoDesk theme, including its dark mode.
- Works with the WordPress privacy tools: personal data export and erase, plus suggested privacy policy text.

**No tracking and no outside services.** The plugin never contacts another server. Its only outgoing request is to your own site, to check that the protected folder is really blocked.

## Requirements

| | Minimum |
| --- | --- |
| WordPress | 6.4 |
| PHP | 8.0 |
| Web server | Apache, LiteSpeed or nginx (nginx needs [one extra line](#protected-files-on-nginx)) |

## Installation

### From a release zip (recommended)

1. Download `sepo-podcast.zip`.
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the zip, click **Install Now**, then **Activate**.

> [!NOTE]
> GitHub's **Code → Download ZIP** names the folder after the branch, for example `sepo-ppodcast-main`. Rename it to `sepo-podcast` before uploading.

### With Git

Clone the repository into your plugins folder, then activate **Sepo Podcast** under **Plugins**:

```bash
cd wp-content/plugins
git clone <repository-url> sepo-podcast
```

The folder must be named `sepo-podcast`.

### With WP-CLI

```bash
wp plugin install /path/to/sepo-podcast.zip --activate
```

### Protected files on nginx

Apache and LiteSpeed block the protected audio folder automatically. nginx ignores `.htaccess` files, so on nginx **Settings → Sepo Podcast** shows a red notice until you add this line to your server configuration:

```nginx
location ^~ /wp-content/uploads/sepo-podcast-private/ { deny all; }
```

Everything else is set up in the WordPress admin. You don't need to edit `wp-config.php` or any other file.

## Getting started

1. When you activate the plugin, it creates a page called **Podcast** with the episode list on it.
2. Go to **Podcast → Add episode**. Give it a title and show notes, then choose the audio in the **Episode details** box.
3. Go to **Settings → Sepo Podcast** and fill in the feed details: show title, artwork, category and owner email.
4. Submit your feed address to Apple Podcasts, Spotify or any other podcast app.

## Usage

### Episode list

The **Podcast** page uses the shortcode below. You can put it on any page, or use the **Podcast episodes** block instead.

```text
[sepo_podcast series="season-2" per_page="10" search="yes" series_filter="yes" order="newest"]
```

| Attribute | Default | What it does |
| --- | --- | --- |
| `series` | all | Show one series only, by its slug |
| `per_page` | from settings | Episodes per page |
| `search` | `yes` | Show the search box |
| `series_filter` | `yes` | Show the series buttons |
| `order` | `newest` | `newest`, `oldest` or `custom` (uses each episode's Order field) |

### Audio sources

In the **Episode details** box, choose one of these:

- **Media Library**: any audio file you've uploaded.
- **Address on another site**: a link to an audio file hosted elsewhere.
- **Protected file**: uploaded into a private folder. Use this when downloads must require sign-in.

### Feed

| Feed | Address |
| --- | --- |
| Whole show | `https://your-site/feed/podcast-feed/` |
| One series | `https://your-site/feed/podcast-feed/?series=series-slug` |

The feed leaves out episodes that need sign-in to download, because podcast apps can't sign in.

## Importing episodes

Go to **Tools → Import podcast** and upload a `.zip` with one Markdown file per episode. Each file starts with front matter:

```markdown
---
title: Building in public
src: audio/ep-12.mp3
image: images/ep-12.jpg
duration: 32:10
season: 2
episode: 12
published: 2025-03-04
host: Jane Doe
guest: John Roe
group: Founders
tags: [startups, product]
gated: false
---
Show notes in **Markdown**.
```

- Audio and images inside the zip are added to your site. Addresses starting with `http` stay where they are.
- Common alternative field names (`audio`, `cover`, `series`, `date` and so on) are understood too.
- Add `likes.json` and `downloads.json` (`{"episode-key": 42}`) to carry old totals over.
- Importing the same zip again updates the episodes instead of duplicating them, and never changes whether they're published.

For large archives, use WP-CLI:

```bash
wp sepo-podcast import episodes.zip --status=publish --base-url=https://old.example.com/
```

## Customising the look

<img src="screenshots/episode-page.png" alt="The player, Like and Download buttons and More episodes on an episode page" width="560">

- **Colours:** with **Use my theme's colours** ticked (the default), the plugin follows the SepoDesk theme, its skins and its dark mode. Untick it to pick your own accent colour; the text on it is picked automatically so it stays readable.
- **Templates:** copy any file from `templates/` into `your-theme/sepo-podcast/` and edit it there. Your copy is used instead of the plugin's.
- **CSS:** every colour is a `--sp-*` CSS variable you can override in your theme.

| Hook | Type | Use |
| --- | --- | --- |
| `sepo_podcast_episode_data` | filter | Change the episode data passed to templates, the REST API and the feed |
| `sepo_podcast_list_query` | filter | Change the episode list query |
| `sepo_podcast_feed_query` | filter | Change the feed query |
| `sepo_podcast_template` | filter | Use a different template file |
| `sepo_podcast_stream_ttl` | filter | How long player addresses for protected files stay valid, in seconds (default two days) |
| `sepo_podcast_show_episode_art` | filter | Show the artwork next to the player on episode pages |
| `sepo_podcast_before_serve` | action | Runs before an audio file is sent |



## FAQ

<details>
<summary>Can guests still listen to a protected episode?</summary>

Yes, on the episode page. The player uses a signed address that expires after two days by default (filter `sepo_podcast_stream_ttl`), and the Download button asks guests to sign in. Anyone who can play audio can record it, so treat "sign in to download" as a strong deterrent, not copy protection.

</details>

<details>
<summary>I use a page cache. Anything to know?</summary>

Like and download counts on episode pages refresh themselves after the page loads. If you cache pages for longer than two days, raise `sepo_podcast_stream_ttl` or exclude episode pages from the cache, so player addresses for protected files don't expire inside cached pages.

</details>

<details>
<summary>Does it use cookies or track listeners?</summary>

No tracking. Likes and downloads are stored only for signed-in users. To stop double counting, a one-minute marker made from a one-way hash of the visitor's IP address and browser is kept, and it isn't stored with the counts. The player remembers where a listener stopped in their own browser.

</details>

## Uninstalling

**Deactivating** the plugin (**Plugins → Deactivate**) only switches it off. Your episodes, likes, counts and settings stay.

**Deleting** the plugin (**Plugins → Delete**) also keeps everything, **unless** you first choose otherwise in **Settings → Sepo Podcast → When the plugin is deleted**:

| Option | What is removed |
| --- | --- |
| Delete likes, download counts and settings | The likes and downloads tables, saved counts and plugin settings |
| Also delete every episode and the protected audio files | All episodes (including trashed ones), series, episode tags and the protected audio folder |

With WP-CLI, this deactivates the plugin, deletes it and runs the same clean-up:

```bash
wp plugin uninstall sepo-podcast --deactivate
```

Media Library files you used for episodes are never deleted, and neither is the **Podcast** page. Remove those yourself if you no longer need them.

On multisite, the clean-up runs for each site where those options are ticked.

## Privacy

Sepo Podcast works with **Tools → Export Personal Data** and **Tools → Erase Personal Data**. Erasing removes a user's likes and makes their downloads anonymous, so the totals stay correct. Suggested text for your privacy policy appears under **Settings → Privacy**.

## Changelog

### 1.1.0

- Follows the SepoDesk theme's colours, skins and dark mode automatically.
- New setting: **Use my theme's colours** (on by default). A custom accent colour now gets readable text picked automatically.
- Flat design: no shadows. The episode that's playing is shown with a tinted card instead of a side stripe.
- Player times are brighter in dark mode, for readability.
- Fixed: the theme's heading spacing no longer pushes episode titles down in cards.
- Fixed: the bottom player no longer leaves a gap when an episode has no artwork.

### 1.0.0

- First release.

## License

Sepo Podcast is free software, released under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).
