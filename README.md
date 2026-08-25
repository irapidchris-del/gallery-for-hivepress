# Additional Gallery for HivePress

Gives HivePress vendors a front-end photo gallery with public, members-only and private folders, protected files, per-photo pages with likes and comments, and optional monetisation through Memberships or per-vendor paid access.

**Author:** [ChrisB @ HivePress Community](https://community.hivepress.io/u/chrisb/summary)
**Version:** 1.9.1 · **Requires:** WordPress 5.8+, PHP 7.4+, HivePress 1.x

> **Installation folder:** the release zip installs as `additional-gallery-for-hivepress` in `wp-content/plugins/`, which is the recommended folder name. Since 1.4.0 the plugin registers itself with HivePress explicitly, so it also works from a differently named folder (for example the `-main` suffix a GitHub "Download ZIP" adds), where earlier versions would have loaded nothing at all.

## What it does

**For vendors** (via a new "Gallery" item in the account menu, shown only to users with a published vendor profile):

- Create folders at `/account/gallery/`, each with a name, description and a visibility setting: Public, Members only, or Private
- Upload images (and optionally videos) to a folder, drag-and-drop to reorder, and remove them (all handled by the core HivePress upload field)
- Add a title and description to each image from the Manage Photo card on its page; descriptions show under the photo, on its page and become the image alt text
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

**For site owners** (HivePress → Settings → Gallery):

- Hide the gallery link on vendor profiles and/or listing pages
- Limit folders per vendor and images per folder (image limit defaults to 30 and is enforced server-side by the core attachments endpoint)
- **Membership gating** (optional): configured natively on each membership plan in HivePress Memberships, an "Allow using the photo gallery" option (vendor access) and an "Allow viewing members-only gallery folders" option (viewer access). When at least one plan enables gallery access, vendors without an active plan lose the account menu item, the gallery pages, folder create/update via REST and their public gallery link and page (fails closed if Memberships is deactivated). Their existing folders and images are left untouched, and they keep the right to delete their own folders. Leaving every plan unticked keeps the gallery open to all vendors
- **Locked Folder Display**: blurred previews / lock placeholders / hide
- **Upgrade Page**: where "Unlock Access" links point. When left empty, links fall back to the Memberships Select Plan page automatically, then to login for logged-out visitors
- **Protect Files** (on by default): files in private and members-only folders are moved to a protected directory and served through an access-checked link, so their URLs can't be opened directly; new uploads get unguessable file names; gallery images are excluded from public REST media queries; and attachment pages are access-checked. Public folder images stay directly served for speed and SEO
- **Keeping galleries light**: maximum file size, allowed formats and maximum dimensions (resize on upload, applied before thumbnails are generated). Compression, WebP conversion and bulk re-processing are deliberately left to dedicated image plugins (Imagify, ShortPixel, FlyingPress), which do them better and across the whole media library
- **Photo pages**: every photo gets its own URL with prev/next navigation, its title and description, likes, and a threaded comment section (replies one level deep, likeable comments) with an inline composer. Grid clicks always open the photo page; the Lightbox setting controls whether the photo there can be clicked to enlarge in the pop-up viewer
- **Sidebars**: a real `register_sidebar` widget area for each of the three page types ("Gallery Page", "Folder Page" and "Photo Page", all fillable from Appearance → Widgets) plus the vendor's profile card. The photo page sidebar is positioned left or right; the gallery and folder page sidebars are off until switched on, then left or right
- **Folder grid**: how many columns of folder covers a full-width screen shows, whether covers crop horizontal, vertical or square, and a row cap for a gallery embedded in a profile or listing (with a "View N more folders" link for the remainder). Narrow screens always drop to two columns, then one
- **Per-photo editing** for vendors: title, description, move-to-folder (with confirmation) and delete, from the Manage Photo card in the sidebar of their own photo pages
- **Paid access** (optional, WooCommerce): vendors price their own gallery unlock; purchases grant access, refunds revoke it, and Marketplace commission applies when that extension is active. A scope setting decides what one purchase buys: the vendor's whole gallery (prices on the account gallery page, grants stored as `hp_agl_access_{vendor_id}` user meta) or each folder separately (prices on each folder's own edit page, grants stored as `hp_agl_faccess_{folder_id}`). The two are stored apart, so switching scope loses neither prices nor access already sold. An Access Period setting makes purchases lapse after a set number of days (stored per grant, so changing it never rewrites access already bought); locked folders always show a single unlock button, the vendor's purchase when priced, else the site's upgrade link
- **Storage quotas** (site-wide or per plan) with "X used of Y allowed" on the vendor dashboard
- **Notification bridge**: `hp_agl/photo_liked`, `hp_agl/photo_commented`, `hp_agl/comment_replied`, `hp_agl/comment_liked`, `hp_agl/access_purchased`, `hp_agl/access_revoked`, `hp_agl/access_expired` fire with documented arguments, ready for Notifications for HivePress or any listener

**In wp-admin:** folders live under Vendors → Gallery Folders. Each has an Images meta box (the native drag-and-drop manager, so you can see and manage exactly which images belong to each folder), a Gallery Settings meta box holding the vendor and the visibility, and the folders list shows vendor, visibility, image count and size columns. There is no Author box: the vendor is a folder's only owner and `post_author` is written to match it on save, the same rule core applies to a listing. Photo comments appear on the usual Comments screen.

## Architecture

The plugin registers itself through the `hivepress/v1/extensions` filter, so HivePress autoloads its classes and configs natively. Registration picks its form at runtime: the bare directory path every official extension uses when the folder is named normally, and the explicit array form (name, version, path, url) only when the folder has been renamed, because a bare path makes HivePress derive the main file as `{dirname}/{dirname}.php` and register nothing when the two disagree. The array form is the fallback rather than the default because HivePress' updater probe concatenates every entry as a string, so an array entry logs an "Array to string conversion" warning on every request.

| Piece | Implementation |
| --- | --- |
| Folder | `hp_gallery_folder` post type. Owner = `post_author` (kept equal to the vendor's user on save), vendor = `post_parent`, visibility = `hp_visibility` meta (`public` / `members` / `private`), status always `publish`. |
| Images | Standard WordPress attachments attached via the core `attachment_upload` field (`parent_model = gallery_folder`, `parent_field = images`), so uploads, sorting, limits and deletion reuse core HivePress code and its ownership checks. |
| Model | `HivePress\Models\Gallery_Folder` mirrors the core Listing model, including a lazy, cached `get_images__id()` (one-to-many relation fields are not populated on read by HivePress). Image IDs are cached per folder in the `models/attachment` group, which HivePress invalidates automatically when an attachment of the folder changes. |
| Routes | Account pages under `user_account_page` (`/account/gallery`, `/account/gallery/{id}`), a public route `/gallery/{vendor_id}`, and REST endpoints under `hivepress/v1/gallery-folders` for create/update/delete. |
| Forms | Native `Model_Form` subclasses posting to the REST endpoints via the standard HivePress form handler (`X-WP-Nonce`, `X-HTTP-Method-Override` for DELETE). |
| UI | Blocks + template classes extending `User_Account_Page`, `Page_Wide` and `Page_Sidebar_Left` (the photo page flips the column order per the sidebar setting, exactly how core's `Page_Sidebar_Right` differs from Left); the sidebar button is injected into `vendor_view_page` and `listing_view_page` via `hivepress()->template->merge_blocks()`, which locates the sidebar wherever a theme has moved it (and is the method HivePress intends to replace `merge_trees` with). |
| Lightbox | The FancyBox build bundled with HivePress core (`data-fancybox` on the photo page image when the setting is on). |
| Assets | Declared in the native `styles`/`scripts` configs, so the core asset component enqueues them and scopes them to the gallery routes only (the stylesheet also loads on listing and vendor pages for the sidebar button). |
| Memberships | Gating is configured natively on membership plans: the plugin adds `gallery_access` and `gallery_view` checkbox fields to the `membership_plan` model and its settings meta box (via the `hivepress/v1/models/membership_plan` and `hivepress/v1/meta_boxes/membership_plan_settings` filters), stored as `hp_gallery_access` / `hp_gallery_view` plan meta. Access is granted when the user has an active `hp_membership` (user = `post_author`, plan = `post_parent`, `publish` = Active) in a plan carrying the flag, matching the Memberships 2.2.0 access model. Persisted `hp_gallery_access_gated` / `hp_gallery_view_gated` option flags (refreshed on plan save) let the checks fail closed if Memberships is deactivated. The plan post type is resolved at runtime (`hp_agl_get_plan_post_type()`). |
| File protection | With Protect Files on, files in private and members-only folders are relocated to `uploads/hp-agl-protected/` (Apache deny rule + index guards) and served by an access-checked proxy route (`/gallery-file/{id}`) that streams with caching, conditional-GET and byte-range support. Attachment URLs and image `src`/`srcset` are filtered to the proxy for protected files. Files move between the protected and public locations automatically when a folder's visibility changes (`hivepress/v1/models/gallery_folder/update` and `.../update_images`). |
| Resizing | Oversized gallery uploads are scaled at the `wp_handle_upload` hook (before thumbnails are generated) via `WP_Image_Editor`; the file-size cap is enforced at `wp_handle_upload_prefilter`. An image already within bounds is never re-encoded. |
| Likes and comments | `hp_agl_like` and `hp_agl_comment` comments, keyed to the attachment by `comment_post_ID` and the person by `user_id`, mirroring how Favorites and Reviews store theirs. Model class names are short deliberately: the comment type is `hp_` + the class name and the column holds only 20 characters. Counts for a whole folder are fetched in one grouped query; every endpoint reuses the same access check as the protected-file proxy. |
| Blurred previews | Generated once per image with GD (tiny downscale, repeated Gaussian passes, upscale) and cached in `uploads/hp-agl-teasers/{id}.jpg`; regenerated if the image is edited and deleted with it. Tunable via the `hp_agl/teaser_args` filter. If a preview cannot be generated (no GD, unreadable source), a lock placeholder tile is rendered instead, so originals are never exposed. |

Rewrite rules are flushed once after activation (after the HivePress router registers them on `init`) and once on the 1.3.0 upgrade, for the new file route. On deactivation the cached `rewrite_rules` option is deleted rather than flushed: the plugin's routes are still registered on that request, so a flush would write the now-dead gallery rules straight back. WordPress rebuilds them on the next request, by which point the routes are gone.

### Ownership and security

- Creating a folder requires a published vendor profile; updating/deleting requires being the folder owner (or `edit_others_posts`/`delete_others_posts`).
- Image uploads are validated by the core HivePress attachments controller, which checks that the current user owns the parent folder.
- Deleting a folder fires `hivepress/v1/models/post/delete`, and the core attachment component deletes all of the folder's images, no orphaned media. Protected files delete with the attachment, since their `_wp_attached_file` points into the protected directory.

### Privacy and file protection

Private and members-only folders are unlisted everywhere the plugin controls: gallery pages, sidebar links, public REST media queries (when Protect Files is on) and attachment pages (always access-checked for gallery images).

With Protect Files on (the default), private and members-only files are moved into `uploads/hp-agl-protected/` and served only through the access-checked proxy, so a guessed or shared URL returns a 403 to visitors without access, real file-level protection, not just unguessable names. Public folder images stay as ordinary directly-served media, so public galleries remain fast and SEO-friendly.

The protected directory carries an Apache deny rule. **On Nginx, add a rule** so it is not served directly, e.g. `location ^~ /wp-content/uploads/hp-agl-protected/ { deny all; }`. The proxy re-checks access regardless, so the server rule is defence in depth. If the uploads directory is not writable, files cannot be relocated and remain public, protection therefore requires a writable uploads directory.

### Per-vendor unlocks (roadmap)

Selling access to a single vendor's gallery shipped in 1.7.0: each vendor can offer up to three access lengths at their own prices, bought through the normal WooCommerce checkout. Who the money goes to under WooCommerce alone, HivePress Marketplace, or a Stripe Connect direct-charges gateway is set out plainly in readme.txt under "Who actually receives the money" - read it before setting a Platform fee The access check is already filterable today, so developers can grant per-vendor access from custom logic:

```php
add_filter( 'hp_agl/user_can_view_member_folders', function( $can, $user_id, $vendor ) {
    // Return true if $user_id purchased access to $vendor's gallery.
    return $can;
}, 10, 3 );
```

### Data on uninstall

`uninstall.php` runs when the plugin is **deleted** (not on deactivation, which changes nothing), and **retains the owner's data by default**. Destruction is opt-in through the `hp_gallery_delete_data` option, set by the "Delete all gallery data when this plugin is deleted" checkbox in the Removing the Plugin settings section. The flag is read per site inside the multisite loop, so one site opting in never wipes another.

**Retain path** (the default) touches only regenerable caches: the cached GitHub release lookup and the `hp-agl-teasers` preview cache, both of which rebuild themselves. Everything else, including the files in `hp-agl-protected`, is left exactly as it was. Moving those files back into public uploads on this path would be a privacy regression rather than a courtesy: the photos keep their media-library entries, so a private photo would become directly fetchable the moment the proxy route stopped guarding it.

**Delete path** removes the options, the folder posts, the likes/comments/comment-likes, the plan and vendor meta, the purchased-access user meta, the generated WooCommerce products (trashed, since orders may reference them) and the plugin's three private directories. Two deliberate exceptions survive even here:

- **The vendors' photos.** They are the vendors' own content, so instead of being deleted with their folder they are detached and left in the media library as ordinary uploads. Any file still inside the protected directory is moved back to its normal uploads location first, because without the plugin there is no proxy route left to serve it and the deny rule would make it permanently unviewable.
- **`hp_openai_api_key`**, because it is shared with other OpenAI-based HivePress extensions.

Order matters in that file. The options are cleared before the files move, so that if uninstall ever runs in a process where the plugin's hooks are still registered (WP-CLI's `plugin uninstall --deactivate` does exactly that), nothing re-protects the files as they are being moved out. For the same reason the version and gating-flag markers are swept again at the end, since deleting folders and trashing products fires post hooks a still-registered listener reacts to. The `hp_gallery_delete_data` flag itself is deleted last of all, so a run that fails part way leaves it set and a second delete finishes the job rather than silently reverting to "retain" with half the data gone.

Both paths were exercised against real data with a full database snapshot and restore, rather than by actually deleting the plugin.

### Known considerations

- **Every class and file carries the `Agl_` / `class-agl-*.php` prefix**, because HivePress loads exactly one file per class name across every registered extension: a duplicate file name means one extension's class silently never loads, with no error anywhere. The one deliberate exception is `Models\Gallery_Folder`, which **cannot** be renamed: the model name is persisted as `hp_parent_model` on every gallery image and resolved back into a class, core's attachment endpoints 400 the moment that lookup fails, the post type holding the real folder data derives from the same name, and wp-admin's uploader always posts `parent_model` derived from that post type. Renaming it orphans every photo already uploaded; it needs a data migration, not a rename. Names HivePress *derives* from a class were renamed in lockstep (block `type` values, `Blocks\Template` `template` values, and the `hp-form--{name}` CSS class the front-end script matches on). Route names were deliberately left alone: five of them are spelled identically to five template classes, and they also appear in the `scope` arrays of `configs/scripts.php` and `configs/styles.php`, where renaming them would leave every gallery page with no CSS and no JavaScript.
- Settings options use the `hp_gallery_*` prefix.

## OpenAI moderation and the shared key

The optional AI Moderation setting checks a folder's photos against OpenAI's free Moderation endpoint (`omni-moderation-latest`) when the vendor saves the folder. All photos go in one request, capped at ten (filterable via `hp_agl/moderation_image_cap`), and a flagged result rejects the save with a polite message. The check fails open on every failure mode: missing key, transport error, non-200 response, malformed JSON. An unavailable service never blocks a vendor.

The API key lives in `hp_openai_api_key` under HivePress > Settings > Integrations, and the field is registered with `isset` guards so it appears exactly once however many OpenAI-based extensions are installed. This plugin interoperates with Automated Listing Moderation for HivePress through that shared key: the moderation plugin checks listing photos, this one checks vendor galleries, and both reading the same key is intended. `uninstall.php` deliberately leaves `hp_openai_api_key` in place for that reason.

Three honest limits. OpenAI fetches each image by URL, so the site must be publicly reachable; on localhost or password-protected staging the check cannot run and fails open. Protected files (in private and members-only folders) have no externally fetchable URL, so moderation applies to public folders. And moderation runs when a folder is saved: images are visible in the gallery from the moment they finish uploading, so a vendor who uploads and never presses Save is not checked until their next save. Admin edits in wp-admin are not moderated.

## Updates and releases

The plugin updates itself from this repository's GitHub **releases**, so sites see update notifications on their Plugins screen and can install updates with one click, exactly like a wordpress.org plugin. This uses the native WordPress 5.8+ update API: the `Update URI: https://github.com/…` header routes update checks to the `update_plugins_github.com` filter, which a small self-contained updater (`includes/updater.php`) answers from the GitHub releases API. No external library or service is involved, and the public repo needs no token. (This is why the plugin requires WordPress 5.8+.) The plugin row also carries a **Check for updates** link to force an immediate check.

### Packaging

Every release asset is a zip named exactly **`additional-gallery-for-hivepress.zip`** (no version in the filename) containing a single top-level `additional-gallery-for-hivepress/` folder (slug = folder = text domain). That fixed name keeps the updater and the always-latest link working, and the clean folder makes manual installs land correctly with no "folder mismatch" warnings.

The zip is built with the local packaging script, which writes forward-slash entry names and verifies the archive. Never build it with PowerShell's `Compress-Archive`: on Windows PowerShell 5.1 it writes backslash entry names, which the ZIP spec forbids, and spec-following extractors then create files literally named `includes\class-foo.php` so the plugin installs broken.

### Cutting a release

1. Bump the `Version:` header **and** the `HP_AGL_VERSION` constant in `additional-gallery-for-hivepress.php` to the new version (they must match the release tag), and update `readme.txt` (Stable tag + changelog). Regenerate `languages/additional-gallery-for-hivepress.pot` if any strings changed.
2. Run the packaging script to produce `additional-gallery-for-hivepress.zip`, then attach it to a GitHub release tagged `vX.Y.Z`.

Once a release's version is higher than a site's installed version, that site is offered the update automatically (WordPress checks roughly twice a day). Until the first release exists, the Check for updates link reports that the plugin is up to date, and the "View details" popup shows the installed plugin's own details.

> **Match the tag to the header.** If a release is tagged `v1.4.0` but the plugin header still says `1.3.0`, WordPress will re-offer the update in a loop. Bump the header before releasing.

### Always-latest download link

For the HivePress community forum, link to:

```
https://github.com/irapidchris-del/gallery-for-hivepress/releases/latest/download/additional-gallery-for-hivepress.zip
```

GitHub resolves `releases/latest/download/<asset>` to the newest release's matching asset, so this URL always downloads the current version and triggers an immediate `.zip` download, no need to edit the forum post when you release an update.

## Development

The code follows the WordPress Coding Standards and the HivePress house style (short array syntax, slash-delimited hook names, Yoda conditions, tabs for indentation), and is checked with PHPCS plus PHPStan before every release.

## Roadmap ideas

Per-vendor gallery purchases (see above), folder cover images, an optional "gallery" tab on the vendor profile itself, and a scheduled/background pass for bulk optimisation on very large libraries.
