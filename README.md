# Additional Gallery for HivePress

Gives HivePress vendors a front-end photo gallery with public, members-only and private folders, protected files and image optimization.

**Author:** [Chris B @ HivePress Community](https://community.hivepress.io/u/chrisb)
**Version:** 1.3.0 · **Requires:** WordPress 5.0+, PHP 7.4+, HivePress 1.x

> **Installation folder:** install this as `additional-gallery-for-hivepress` in `wp-content/plugins/`. HivePress registers an extension only when its main file matches the plugin directory name, so a folder named differently (for example the `-main` suffix a GitHub "Download ZIP" adds) will load nothing. Distribute the release as a correctly named zip.

## What it does

**For vendors** (via a new "Gallery" item in the account menu, shown only to users with a published vendor profile):

- Create folders at `/account/gallery/`, each with a name, description and a visibility setting: Public, Members only, or Private
- Upload images (and optionally videos) to a folder, drag-and-drop to reorder, and remove them (all handled by the core HivePress upload field)
- Add a description to each image, saved as it is typed; descriptions show under the photo, in the lightbox and become the image alt text
- Drag folders to reorder them, powered by the core HivePress sortable component
- The public gallery shows folder covers by default; each folder opens on its own shareable page at `/gallery/{vendor_id}/{folder_id}/`, and a Gallery Layout setting restores the single-page view
- An "Updated x ago" line based on the newest upload, so an active gallery reads as an active stylist
- Copy their shareable public gallery link with one click
- Delete a folder (with a confirmation prompt; its images are removed automatically)

**For visitors:**

- A public gallery page at `/gallery/{vendor_id}/` showing folder covers with counts and an "Updated x ago" line; each folder opens on its own deep-linkable page at `/gallery/{vendor_id}/{folder_id}/` (or switch the Gallery Layout setting to the classic expanded view)
- Videos (MP4/WebM/OGV) alongside photos when the "Allow uploading videos" setting is on; videos play inline in the grid with the first frame shown via the `#t=0.001` fragment, exactly the way HivePress renders listing videos (FancyBox direct video links are avoided on purpose: WebM links are broken in the bundled FancyBox 3 build)
- Descriptions are stored as native attachment captions, no custom meta
- Drag-and-drop folder reordering (stored as post `menu_order`, mirroring the core attachment pattern; new folders append at the end)
- Members-only folders render locked for visitors without access: heavily blurred previews generated server side (or lock placeholders, or hidden - a site setting), a photo count to tease the content, and an "Unlock Access" button pointing at the site's upgrade page. Original image URLs never appear in the page for locked folders
- A "View Gallery (12 photos)" button in the sidebar of vendor profiles and listing pages, which only appears once the vendor has at least one public photo

**For site owners** (HivePress → Settings → Vendors → Gallery):

- Hide the gallery link on vendor profiles and/or listing pages
- Limit folders per vendor and images per folder (image limit defaults to 30 and is enforced server-side by the core attachments endpoint)
- **Membership gating** (optional): configured natively on each membership plan in HivePress Memberships — an "Allow using the photo gallery" option (vendor access) and an "Allow viewing members-only gallery folders" option (viewer access). When at least one plan enables gallery access, vendors without an active plan lose the account menu item, the gallery pages, folder create/update via REST and their public gallery link and page (fails closed if Memberships is deactivated). Their existing folders and images are left untouched, and they keep the right to delete their own folders. Leaving every plan unticked keeps the gallery open to all vendors
- **Locked Folder Display**: blurred previews / lock placeholders / hide
- **Upgrade Page**: where "Unlock Access" links point. When left empty, links fall back to the Memberships Select Plan page automatically, then to login for logged-out visitors
- **Protect Files** (on by default): files in private and members-only folders are moved to a protected directory and served through an access-checked link, so their URLs can't be opened directly; new uploads get unguessable file names; gallery images are excluded from public REST media queries; and attachment pages are access-checked. Public folder images stay directly served for speed and SEO
- **Image optimization**: maximum file size, allowed formats, maximum dimensions (resize on upload), image quality, strip metadata, and convert to WebP — applied to new uploads before thumbnails are generated

**In wp-admin:** each folder has an Images meta box (the native drag-and-drop manager, so you can see and manage exactly which images belong to each folder), a Settings meta box for visibility, and the folders list shows vendor, visibility, image count and size columns. Bulk **Optimize images** and **Restore original images** actions (with an optional Keep Originals backup) let you optimize existing galleries reversibly.

## Architecture

The plugin registers itself through the `hivepress/v1/extensions` filter, so HivePress autoloads its classes and configs natively. The main file is named after the plugin directory, which HivePress requires to register the extension.

| Piece | Implementation |
| --- | --- |
| Folder | `hp_gallery_folder` post type. Owner = `post_author`, vendor = `post_parent`, visibility = `hp_visibility` meta (`public` / `members` / `private`), status always `publish`. |
| Images | Standard WordPress attachments attached via the core `attachment_upload` field (`parent_model = gallery_folder`, `parent_field = images`), so uploads, sorting, limits and deletion reuse core HivePress code and its ownership checks. |
| Model | `HivePress\Models\Gallery_Folder` mirrors the core Listing model, including a lazy, cached `get_images__id()` (one-to-many relation fields are not populated on read by HivePress). Image IDs are cached per folder in the `models/attachment` group, which HivePress invalidates automatically when an attachment of the folder changes. |
| Routes | Account pages under `user_account_page` (`/account/gallery`, `/account/gallery/{id}`), a public route `/gallery/{vendor_id}`, and REST endpoints under `hivepress/v1/gallery-folders` for create/update/delete. |
| Forms | Native `Model_Form` subclasses posting to the REST endpoints via the standard HivePress form handler (`X-WP-Nonce`, `X-HTTP-Method-Override` for DELETE). |
| UI | Blocks + template classes extending `User_Account_Page` and `Page_Wide`; the sidebar button is injected into `vendor_view_page` and `listing_view_page` via `merge_trees`. |
| Lightbox | The FancyBox build bundled with HivePress core (`data-fancybox` grouping per folder). |
| Assets | Declared in the native `styles`/`scripts` configs, so the core asset component enqueues them and scopes them to the gallery routes only (the stylesheet also loads on listing and vendor pages for the sidebar button). |
| Memberships | Gating is configured natively on membership plans: the plugin adds `gallery_access` and `gallery_view` checkbox fields to the `membership_plan` model and its settings meta box (via the `hivepress/v1/models/membership_plan` and `hivepress/v1/meta_boxes/membership_plan_settings` filters), stored as `hp_gallery_access` / `hp_gallery_view` plan meta. Access is granted when the user has an active `hp_membership` (user = `post_author`, plan = `post_parent`, `publish` = Active) in a plan carrying the flag — matching the Memberships 2.2.0 access model. Persisted `hp_gallery_access_gated` / `hp_gallery_view_gated` option flags (refreshed on plan save) let the checks fail closed if Memberships is deactivated. The plan post type is resolved at runtime (`hp_agl_get_plan_post_type()`). |
| File protection | With Protect Files on, files in private and members-only folders are relocated to `uploads/hp-agl-protected/` (Apache deny rule + index guards) and served by an access-checked proxy route (`/gallery-file/{id}`) that streams with caching, conditional-GET and byte-range support. Attachment URLs and image `src`/`srcset` are filtered to the proxy for protected files. Files move between the protected and public locations automatically when a folder's visibility changes (`hivepress/v1/models/gallery_folder/update` and `.../update_images`). |
| Optimization | New gallery uploads are optimized at the `wp_handle_upload` hook (before thumbnails are generated) via `WP_Image_Editor`: resize, quality, metadata strip and optional WebP conversion; the file-size cap is enforced at `wp_handle_upload_prefilter`. Bulk actions on the folders list re-run optimization (or restore backed-up originals) for existing images. |
| Blurred previews | Generated once per image with GD (tiny downscale, repeated Gaussian passes, upscale) and cached in `uploads/hp-agl-teasers/{id}.jpg`; regenerated if the image is edited and deleted with it. Tunable via the `hp_agl/teaser_args` filter. If a preview cannot be generated (no GD, unreadable source), a lock placeholder tile is rendered instead, so originals are never exposed. |

Rewrite rules are flushed once after activation (after the HivePress router registers them on `init`), once on the 1.3.0 upgrade (for the new file route), and again on deactivation.

### Ownership and security

- Creating a folder requires a published vendor profile; updating/deleting requires being the folder owner (or `edit_others_posts`/`delete_others_posts`).
- Image uploads are validated by the core HivePress attachments controller, which checks that the current user owns the parent folder.
- Deleting a folder fires `hivepress/v1/models/post/delete`, and the core attachment component deletes all of the folder's images — no orphaned media. Protected files delete with the attachment, since their `_wp_attached_file` points into the protected directory.

### Privacy and file protection

Private and members-only folders are unlisted everywhere the plugin controls: gallery pages, sidebar links, public REST media queries (when Protect Files is on) and attachment pages (always access-checked for gallery images).

With Protect Files on (the default), private and members-only files are moved into `uploads/hp-agl-protected/` and served only through the access-checked proxy, so a guessed or shared URL returns a 403 to visitors without access — real file-level protection, not just unguessable names. Public folder images stay as ordinary directly-served media, so public galleries remain fast and SEO-friendly.

The protected directory carries an Apache deny rule. **On Nginx, add a rule** so it is not served directly, e.g. `location ^~ /wp-content/uploads/hp-agl-protected/ { deny all; }`. The proxy re-checks access regardless, so the server rule is defence in depth. If the uploads directory is not writable, files cannot be relocated and remain public — protection therefore requires a writable uploads directory.

### Per-vendor unlocks (roadmap)

Selling access to a single vendor's gallery needs its own purchase flow (e.g. a WooCommerce product per vendor granting the buyer access), which is planned for a future version. The access check is already filterable today, so developers can grant per-vendor access from custom logic:

```php
add_filter( 'hp_agl/user_can_view_member_folders', function( $can, $user_id, $vendor ) {
    // Return true if $user_id purchased access to $vendor's gallery.
    return $can;
}, 10, 3 );
```

### Data on uninstall

Deliberately none of the data is deleted on uninstall — folders and images belong to vendors, so removal is left as a conscious admin action (delete the Gallery Folders under the HivePress admin menu first if you want a clean removal).

### Known considerations

- The component registers as `hivepress()->gallery`. There is currently no official HivePress extension using that name; if one ever ships, this plugin would need a rename before both run together.
- Settings options use the `hp_gallery_*` prefix.

## OpenAI moderation and the shared key

The optional AI Moderation setting checks a folder's photos against OpenAI's free Moderation endpoint (`omni-moderation-latest`) when the vendor saves the folder. All photos go in one request, capped at ten (filterable via `hp_agl/moderation_image_cap`), and a flagged result rejects the save with a polite message. The check fails open on every failure mode: missing key, transport error, non-200 response, malformed JSON. An unavailable service never blocks a vendor.

The API key lives in `hp_openai_api_key` under HivePress > Settings > Integrations, and the field is registered with `isset` guards so it appears exactly once however many OpenAI-based extensions are installed. This plugin interoperates with Automated Listing Moderation for HivePress through that shared key: the moderation plugin checks listing photos, this one checks vendor galleries, and both reading the same key is intended. No `uninstall.php` ships; if one is ever added, it must not delete `hp_openai_api_key`, because it is a shared credential.

Three honest limits. OpenAI fetches each image by URL, so the site must be publicly reachable; on localhost or password-protected staging the check cannot run and fails open. Protected files (in private and members-only folders) have no externally fetchable URL, so moderation applies to public folders. And moderation runs when a folder is saved: images are visible in the gallery from the moment they finish uploading, so a vendor who uploads and never presses Save is not checked until their next save. Admin edits in wp-admin are not moderated.

## Updates and releases

The plugin updates itself from this repository's GitHub **releases**, so sites see update notifications on their Plugins screen and can install updates with one click — exactly like a wordpress.org plugin. This is handled by a small, self-contained updater (`includes/class-github-updater.php`); no external service or library is involved. The GitHub repository is public, so no token is needed (sites can add one via the `hp_agl/github_token` filter to raise the API rate limit or for a private fork).

### Cutting a release

1. Bump the version in **both** places so they match the release tag: the `Version:` header and the `HP_AGL_VERSION` constant in `additional-gallery-for-hivepress.php`. Update `readme.txt` (Stable tag + changelog).
2. Build the distributable zip:
   ```
   bin/build-zip.sh
   ```
   This writes `dist/additional-gallery-for-hivepress.zip` containing a single clean top-level folder, `additional-gallery-for-hivepress/`. That folder name is what makes manual installs land correctly and lets the updater match the plugin — keep it stable. (`bin/build-zip.sh --versioned` writes `…-<version>.zip` for your own tracking; the folder inside is still clean.)
3. Create a GitHub release whose **tag equals the version** (e.g. `1.3.0` or `v1.3.0`), and attach the built zip as a release asset. **Keep the asset filename exactly `additional-gallery-for-hivepress.zip`** on every release — the updater matches that name, and the "latest" link below depends on it.

Once a release's version is higher than a site's installed version, that site is offered the update automatically (WordPress checks roughly twice a day; the plugin row also has a **Check for updates** link to force a check).

### Always-latest download link

For the HivePress community forum, link to:

```
https://github.com/irapidchris-del/gallery-for-hivepress/releases/latest/download/additional-gallery-for-hivepress.zip
```

GitHub resolves `releases/latest/download/<asset>` to the newest release's matching asset, so this URL always downloads the current version and triggers an immediate `.zip` download — no need to edit the forum post when you release an update.

## Development

A fillable manual test plan lives in [`TESTING.md`](TESTING.md), covering behaviour on a real WordPress install (the one thing static checks can't verify). The code follows the WordPress Coding Standards and the HivePress house style (short array syntax, slash-delimited hook names); `phpcs.xml` captures the ruleset.

## Roadmap ideas

Per-vendor gallery purchases (see above), folder cover images, an optional "gallery" tab on the vendor profile itself, and a scheduled/background pass for bulk optimization on very large libraries.
