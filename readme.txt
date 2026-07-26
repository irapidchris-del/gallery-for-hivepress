=== Additional Gallery for HivePress ===
Contributors: chrisb
Tags: hivepress, gallery, vendors, portfolio, marketplace
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Gives HivePress vendors a front-end photo gallery with public, members-only and private folders, protected files, image optimization and optional Memberships gating.

== Description ==

Additional Gallery for HivePress adds a portfolio-style gallery to every vendor account.

Vendors can:

* Create gallery folders from a new "Gallery" page in their account menu, and drag folders into any order
* Upload, reorder (drag and drop) and remove images - and videos, if the site allows them - in each folder
* Describe each photo or video; descriptions appear in the lightbox and double as image alt text
* Set each folder to Public, Members only, or Private
* Share their gallery link or a direct link to any folder, and see the gallery linked automatically from their vendor profile and listing pages

Visitors see a gallery of folder covers with an "Updated 2 days ago" line, and click into each folder (every folder has its own shareable URL). A setting can switch this to the classic all-photos-expanded layout. Videos play in the same lightbox as photos. Members-only folders appear locked, with heavily blurred previews (or lock placeholders) that tease the content until the visitor unlocks access, and the original image URLs are never present in the page for locked folders.

Site owners can control everything from HivePress > Settings > Vendors > Gallery:

* Hide the gallery link on vendor profiles and/or listing pages
* Limit the number of folders per vendor, and images per folder (default 30)
* Gate the gallery to membership plans, set up natively on each plan in HivePress Memberships (an "Allow using the photo gallery" and an "Allow viewing members-only gallery folders" option per plan)
* Choose how locked folders look: blurred previews, lock placeholders, or hidden entirely
* Choose the Upgrade Page that "Unlock Access" links point to (e.g. your pricing page)
* Protect private and members-only image files so their URLs cannot be opened directly
* Reduce bandwidth: cap upload file size, restrict image formats, resize on upload, re-compress at a chosen quality, strip metadata, and optionally convert uploads to WebP
* See each gallery's total image "weight" in the folders list and on the vendor's account page

In wp-admin, each Gallery Folder has an Images meta box with the same drag-and-drop manager used for listings, plus a Settings meta box for visibility; the folders list shows the vendor, visibility, image count and size; and bulk "Optimize images" / "Restore original images" actions let you optimize existing galleries.

= How it works =

The plugin registers as a native HivePress extension. Folders are stored as a
`hp_gallery_folder` post type (owner = post author, vendor = post parent, visibility
= `hp_visibility` meta), and images are standard WordPress attachments handled by
the core HivePress upload field, so uploads, sorting and deletion all reuse
battle-tested core code. Deleting a folder automatically deletes its images via
the core HivePress attachment component.

= Memberships integration =

Gallery gating is configured natively on your membership plans, the same way
HivePress Memberships gates its own features. Editing a Membership Plan shows
two extra options: "Allow using the photo gallery" (vendor access) and "Allow
viewing members-only gallery folders" (viewer access). Tick them on the plans
that should include gallery access.

Membership access is read directly from the Memberships data (active memberships
are `hp_membership` records linked to a plan), matching the access check the
Memberships extension itself uses (verified against its source, version 2.2.0),
so it works with your existing plans and purchase flow. The "Unlock Access"
links fall back to the Memberships plan selection page automatically when no
Upgrade Page is chosen. Gating is entirely optional: if no plan enables gallery
access, every vendor can use the gallery. Once at least one plan enables it,
vendors without an active plan lose the gallery menu, the gallery pages, folder
editing and their public gallery until they upgrade. This fails closed if the
Memberships extension is deactivated (the gating state is remembered), so paid
access is never given away by accident.

= Blurred previews =

Blurred previews are generated server side (a heavily blurred derivative is
cached in `uploads/hp-agl-teasers/`), because a CSS-only blur would leave the
original image URLs in the page source. If a preview cannot be generated, a
lock placeholder is shown instead; originals are never exposed.

= File protection =

Private and members-only folders are hidden from all gallery pages, links,
attachment pages and public media API queries.

With the Protect Files setting on (the default), the files in private and
members-only folders are moved to a protected uploads directory and served
through an access-checked link, so their URLs cannot be opened directly - a
guessed or shared link returns a 403 to visitors without access. Owners,
site editors and members with the right plan still see them. Public folder
images stay as ordinary, directly-served media so public galleries remain
fast and SEO-friendly. When a folder's visibility changes, its files move
between the protected and public locations automatically.

The protected directory is guarded with an Apache deny rule. On Nginx, add a
rule so the directory is not served directly, for example:

`location ^~ /wp-content/uploads/hp-agl-protected/ { deny all; }`

The access check runs regardless, so the proxy still protects the files; the
server rule is defence in depth.

= Image optimization =

To keep gallery bandwidth under control, site owners can cap the upload file
size, restrict which image formats vendors may upload, resize images on
upload to a maximum width/height, re-compress JPG and WebP at a chosen
quality, strip camera and location metadata, and optionally convert uploads
to WebP (where the server supports it). New uploads are optimized before
their thumbnails are generated, so every size is made from the optimized
original. A "Keep Originals" option, together with the "Optimize images" and
"Restore original images" bulk actions on the Gallery Folders list, lets you
optimize existing galleries reversibly. Each gallery's total image weight is
shown on the account page and in the admin folders list.

= AI moderation =

The optional AI Moderation setting reviews a folder's photos with OpenAI
when the vendor saves it, using the shared API key from Settings >
Integrations. All photos are checked together in one free request. The site
must be publicly reachable for OpenAI to fetch the photos; on local or
private sites, and whenever the service is unavailable, saving simply
proceeds unchecked rather than blocking the vendor. Protected files (in
private and members-only folders) cannot be fetched externally, so moderation
applies to public folders.

== Installation ==

1. Install and activate HivePress.
2. Upload the `additional-gallery-for-hivepress` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins screen.
4. Vendors will find "Gallery" in their account menu; settings live under HivePress > Settings > Vendors > Gallery.

== Frequently Asked Questions ==

= Where do vendors manage their gallery? =

Account menu > Gallery (only shown to users with a published vendor profile).

= What is the public gallery URL? =

`/gallery/{vendor_id}/` - vendors can copy it with one click from their Gallery page.

= Does deleting a folder delete its images? =

Yes. HivePress's attachment component removes all attached images when the folder is deleted.

== Changelog ==

= 1.3.0 =
* Added: Automatic updates from GitHub releases - sites are notified of new versions and can update from the Plugins screen, with a "Check for updates" link. Self-contained, no external service required.
* Added: Native HivePress Memberships integration - gallery access and members-only viewing are now configured per plan, directly in the Membership Plan editor (replacing the separate plan-picker settings, which are migrated automatically). Gating is optional and fails closed if Memberships is deactivated.
* Added: Strong file protection - files in private and members-only folders are moved to a protected directory and served through an access-checked link, so their URLs cannot be opened directly. Public folder images stay directly served for speed and SEO. On visibility change, files move automatically.
* Added: Image optimization settings - maximum file size, allowed formats, maximum dimensions (resize on upload), image quality, strip metadata, and convert to WebP. New uploads are optimized before thumbnails are generated.
* Added: Bulk "Optimize images" and "Restore original images" actions on the Gallery Folders list, with an optional "Keep Originals" backup for reversible optimization of existing galleries.
* Added: Gallery image "weight" (total size) shown on the account page and in the admin folders list.
* Added: GPL-3.0 license header and bundled LICENSE file.
* Changed: The Protect Files setting now enables the protected proxy (on by default) as well as unguessable file names and public media API exclusion.

= 1.2.0 =
* Added: Folder pages - the gallery now shows folder covers that open each folder on its own page with a shareable, deep-linkable URL (a Gallery Layout setting restores the previous expanded view).
* Added: Drag-and-drop folder reordering on the account Gallery page.
* Added: Per-image descriptions with instant saving on the folder edit page; shown under the photo, in the lightbox and used as image alt text.
* Added: Video uploads (MP4, WebM, OGV) behind an "Allow uploading videos" setting; videos play inline in the grid, the same way HivePress renders listing videos, and count labels show photos and videos separately.
* Added: "Updated x ago" line on gallery and folder pages, based on the newest upload.
* Added: Optional AI review of gallery photos via OpenAI's free Moderation endpoint, sharing one API key (Settings > Integrations) with other OpenAI-based HivePress extensions. The check fails open: if the service is unavailable, saving proceeds unchecked.
* Added: Copy-link buttons for shareable folders on the account Gallery page and the folder edit page.
* Changed: Folders and images now use cached media lookups, mirroring the core listing gallery.

= 1.1.0 =
* Added: HivePress Memberships integration - charge vendors for the gallery feature and/or charge users to view members-only folders, using your existing plans.
* Added: Members only folder visibility with locked display modes - server-side blurred previews, lock placeholders, or hidden.
* Added: Upgrade Page setting powering the "Unlock Access" links on locked folders.
* Added: Admin Images meta box with the native drag-and-drop manager, a Settings meta box, and vendor/visibility/image-count columns in the folders list.
* Added: Protect Files setting - random unguessable file names for new uploads, plus gallery images excluded from public media API queries and guarded attachment pages.
* Added: Developer filters - `hp_agl/vendor_can_use_gallery`, `hp_agl/user_can_view_member_folders` (hook per-vendor unlocks here), `hp_agl/teaser_args`, `hp_agl_membership_plan_post_type`.
* Changed: The Public checkbox is now a Visibility select (existing folders are migrated automatically).

= 1.0.0 =
* Initial release: folders with public/private visibility, drag-and-drop image management, public gallery page with lightbox, sidebar links on vendor and listing pages, admin settings and limits.
