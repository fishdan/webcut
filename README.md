# WebCut

WebCut is a WordPress plugin that lets site administrators create branded short links with a configurable prefix.

## Installation
- Copy the `webcut` directory into your site’s `wp-content/plugins` folder.
- Activate **WebCut - URL Shortener** from **Plugins → Installed Plugins**.
- On activation the plugin will create a `wp_webcut` table (prefix will adjust to your site).

## Configuration
- Navigate to **Settings → WebCut** to choose the prefix applied to every short URL. The default prefix is `webcut`.
- The settings screen also lists every existing WebCut with options to open or delete entries.

## Usage
1. Add the `[webcut]` shortcode to any page or post.
2. Visit the page while logged in with a role that can manage options (typically Administrator).
3. Enter the long destination URL and a custom slug, then select **Make WebCut**.
4. Share the generated short URL shown in the success message.

## Managing WebCuts
- Review all existing short links from **Settings → WebCut**.
- Use the **Delete** button alongside an entry to remove it; the slug becomes available again immediately.

## Notes
- Only users with the `manage_options` capability can create or delete WebCuts.
- Short URLs follow the pattern `https://yoursite.com/<prefix>/<slug>` and redirect with a 301 status code.
