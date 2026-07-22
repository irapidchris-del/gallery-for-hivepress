# Additional Gallery for HivePress

Gives HivePress vendors a front-end photo gallery with public and private folders.

**Author:** [Chris B @ HivePress Community](https://community.hivepress.io/u/chrisb)
**Version:** 1.1.0 · **Requires:** WordPress 5.0+, PHP 7.4+, HivePress 1.x

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
- **Vendor Access Plans**: membership plans that include the gallery feature. When set, vendors without an active plan lose the account menu item, the gallery pages, folder create/update via REST and their public gallery link and page (fails closed if Memberships is deactivated). Their existing folders and images are left untouched, and they keep the right to delete their own folders
- **Viewer Access Plans**: membership plans that unlock members-only folders
- **Locked Folder Display**: blurred previews / lock placeholders / hide
- **Upgrade Page**: where "Unlock Access" links point. When left empty, links fall back to the Memberships Select Plan page automatically, then to login for logged-out visitors
- **Protect Files**: new uploads get random unguessable file names (the core HivePress mechanism); gallery images are also excluded from public REST media queries and their attachment pages are access-checked

**In wp-admin:** each folder has an Images meta box (the native drag-and-drop manager, so you can see and manage exactly which images belong to each folder), a Settings meta box for visibility, and the folders list shows vendor, visibility and image count columns.

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
| Memberships | Verified against the Memberships (2.2.0) source: active memberships are `hp_membership` posts (user = `post_author`, plan = `post_parent`, `publish` = Active, `draft` = Expired, `pending` = Paused), plans are `hp_membership_plan` posts, and the access check matches the extension's own `get_membership()` query (publish status only). The plan post type is still resolved at runtime (`hp_agl_get_plan_post_type()`) as a safeguard against future renames. |
| Blurred previews | Generated once per image with GD (tiny downscale, repeated Gaussian passes, upscale) and cached in `uploads/hp-agl-teasers/{id}.jpg`; regenerated if the image is edited and deleted with it. Tunable via the `hp_agl/teaser_args` filter. If a preview cannot be generated (no GD, unreadable source), a lock placeholder tile is rendered instead, so originals are never exposed. |

Rewrite rules are flushed once after activation (after the HivePress router registers them on `init`) and again on deactivation.

### Ownership and security

- Creating a folder requires a published vendor profile; updating/deleting requires being the folder owner (or `edit_others_posts`/`delete_others_posts`).
- Image uploads are validated by the core HivePress attachments controller, which checks that the current user owns the parent folder.
- Deleting a folder fires `hivepress/v1/models/post/delete`, and the core attachment component deletes all of the folder's images — no orphaned media.

### Privacy and file protection

Private and members-only folders are unlisted everywhere the plugin controls: gallery pages, sidebar links, public REST media queries (when Protect Files is on) and attachment pages (always access-checked for gallery images). The Protect Files setting adds random unguessable file names for new uploads.

Honest limits: image files remain ordinary WordPress media uploads, so anyone who already has a direct file URL can open that file, and existing files are not renamed when the setting is switched on. Blocking direct file requests entirely would require web server rules outside WordPress. In practice, unguessable names plus never printing locked URLs covers the realistic risk.

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

Two honest limits. OpenAI fetches each image by URL, so the site must be publicly reachable; on localhost or password-protected staging the check cannot run and fails open. And moderation runs when a folder is saved: images are visible in the gallery from the moment they finish uploading, so a vendor who uploads and never presses Save is not checked until their next save. Admin edits in wp-admin are not moderated.

## Development

The repo ships its full static-analysis setup:

```
composer install
composer check
```

`composer check` clones HivePress into `.hivepress/` (analysis sources), then runs PHP_CodeSniffer with the WordPress Coding Standards (`phpcs.xml`) and PHPStan at **level 9** with the WordPress extension (`phpstan.neon.dist`). Both pass with zero findings; every suppression in the config documents an upstream pattern verified against the HivePress or WordPress source. Level 10 is deliberately out of scope: it treats HivePress core's untyped signatures as implicit `mixed`, which would force annotations across code that intentionally follows the HivePress house style. GitHub Actions runs the same checks on every push (`.github/workflows/ci.yml`).

A fillable manual test plan lives in `TESTING.md`. Release archives exclude the dev files via `.gitattributes`.

## Roadmap ideas

Per-vendor gallery purchases (see above), per-image captions, folder cover images, direct per-folder share links, and an optional "gallery" tab on the vendor profile itself.
