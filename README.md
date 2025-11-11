# Giml - Google Images Links

## Overview

Giml is a small PHP web application that helps generate HTML snippets to embed images from Google Drive or videos from Gan Jing World into posts (for example: Moodle course pages or forum posts). The app can extract the real video URL (including HLS `.m3u8`) using Puppeteer and produces embed code compatible with browsers.

## Key features

- Convert Google Drive share links (`/file/d/...`) into embeddable `<img>` tags.
- Extract real video URLs from Gan Jing World using Puppeteer (headless Chrome), returning:
  - An HLS embed (for `.m3u8`) using `hls.js`.
  - A plain `<video>` tag when a direct video file is available.
- Auto-clean temporary files:
  - Images removed after 30 minutes if not accessed.
  - Videos removed after 1 day if not accessed.
- Activity logging to `giml.log` for troubleshooting.

## Project structure

- `index.php` — main file (UI + PHP logic).
- `fetch_video_url.js` — Node.js script using Puppeteer to extract video URLs.
- `package.json` / `package-lock.json` — Node.js dependencies (Puppeteer).
- `images/` — storage for downloaded images (auto-created).
- `videos-gjw/` — storage for downloaded videos (auto-created).
- `giml.log` — runtime log file (auto-created).
- `README_vi.md`, `README_en.md` — project documentation.

## Requirements

- PHP 7.x or later (with `cURL` and `fileinfo` extensions).
- Node.js (>= 16) and npm to install Puppeteer.
- Web server (Apache, Nginx) to run `index.php`.
- VPS/server should have enough RAM/CPU to run headless Chromium (recommend at least 512 MB free RAM for the browser process).

## Installation (server/VPS)

1. Put the project into your web root, e.g. `/var/www/html/giml`.
2. Install Node.js and npm if missing.
3. In the project folder run:

```bash
npm install
# if Puppeteer complains about a missing browser, run:
npx puppeteer browsers install chrome
```

4. Ensure write permissions for `images/` and `videos-gjw/` (or let the app create them):

```bash
chown -R www-data:www-data /path/to/giml
chmod -R 755 /path/to/giml/images /path/to/giml/videos-gjw
```

5. On some distros (CentOS/AlmaLinux) you may need additional system packages/fonts for Chromium to run.

## Usage

1. Open `index.php` in a browser.
2. Paste a Google Drive or Gan Jing World video link into the form.
3. Click "Create HTML snippet".
4. Copy the generated HTML and paste it into Moodle (course page, forum post, or an HTML block).

- For HLS (`.m3u8`) results, the snippet includes `hls.js` to play streams on modern browsers. Example snippet:

```html
<video id="video" controls style="max-width:100%;"></video>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
  var video = document.getElementById('video');
  var videoSrc = 'URL_MASTER_M3U8';
  if (Hls.isSupported()) {
    var hls = new Hls();
    hls.loadSource(videoSrc);
    hls.attachMedia(video);
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = videoSrc; // Safari native support
  }
</script>
```

## Operations & Troubleshooting

- Puppeteer/Chromium consumes resources: a browser instance can use a few hundred MB of RAM and high CPU while loading pages. On small VPS instances, consider upgrading resources or serializing requests so only one Puppeteer job runs at a time.
- If Puppeteer reports missing Chrome, run `npx puppeteer browsers install chrome` or set `PUPPETEER_EXECUTABLE_PATH` to an installed Chrome/Chromium binary.
- If the script returns extra logs merged with stdout (because PHP runs the command with `2>&1`), note that the app is configured to only print the actual URL to stdout.
- When no video is found, check `giml.log` for navigation/timeouts or missing JS-rendered resources. Adjust `fetch_video_url.js` timeouts and `waitUntil` value as needed.

## Security

- The app downloads external media temporarily; avoid storing private/sensitive content in public folders.
- The form does not accept uploads — only remote links.

## Development

- Puppeteer options (timeouts, launch args) are in `fetch_video_url.js`.
- To add new providers, update `index.php` and/or `fetch_video_url.js` to extract the desired links.

## Contact

Developer: Nguyễn Đăng Minh Phúc
Profile: https://ganjingworld.com/@ndmphuc/4

---
Updated: 2025-11-12
