=== Additional Gallery for HivePress ===
Contributors: chrisb
Tags: hivepress, gallery, vendors, portfolio, marketplace
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Gives HivePress vendors a front-end photo gallery with public, members-only and private folders, with optional HivePress Memberships monetisation.

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
* Charge vendors for the gallery feature: pick the membership plans that include it (requires the HivePress Memberships extension)
* Charge users to view members-only folders: pick the membership plans that unlock them
* Choose how locked folders look: blurred previews, lock placeholders, or hidden entirely
* Choose the Upgrade Page that "Unlock Access" links point to (e.g. your pricing page)
* Protect image files with unguessable file names

In wp-admin, each Gallery Folder now has an Images meta box with the same drag-and-drop manager used for listings, plus a Settings meta box for visibility, and the folders list shows the vendor, visibility and image count.

= How it works =

The plugin registers as a native HivePress extension. Folders are stored as a
`hp_gallery_folder` post type (owner = post author, vendor = post parent, visibility
= `hp_visibility` meta), and images are standard WordPress attachments handled by
the core HivePress upload field, so uploads, sorting and deletion all reuse
battle-tested core code. Deleting a folder automatically deletes its images via
the core HivePress attachment component.

= Memberships integration =

Membership access is read directly from the Memberships data (active memberships
are `hp_membership` records linked to a plan), matching the access check the
Memberships extension itself uses (verified against its source, version 2.2.0),
so it works with your existing plans and purchase flow. The "Unlock Access"
links fall back to the Memberships plan selection page automatically when no
Upgrade Page is chosen. If plans are selected for vendor access, vendors without
an active plan lose the gallery menu, the gallery pages, folder editing and their public gallery
until they upgrade; this also fails closed if the Memberships extension is
deactivated, so paid access is never given away by accident. The plan list in
the settings is detected at runtime, so it adapts to the Memberships version
installed.

= Blurred previews =

Blurred previews are generated server side (a heavily blurred derivative is
cached in `uploads/hp-agl-teasers/`), because a CSS-only blur would leave the
original image URLs in the page source. If a preview cannot be generated, a
lock placeholder is shown instead; originals are never exposed.

= A note on file protection =

Private and members-only folders are hidden from all gallery pages, links,
attachment pages and public media API queries.

The optional AI Moderation setting reviews a folder's photos with OpenAI
when the vendor saves it, using the shared API key from Settings >
Integrations. All photos are checked together in one free request. The site
must be publicly reachable for OpenAI to fetch the photos; on local or
private sites, and whenever the service is unavailable, saving simply
proceeds unchecked rather than blocking the vendor.

The optional Protect Files
setting additionally stores new uploads with random, unguessable file names
(the HivePress protection mechanism). Be aware of the honest limits: existing
files are not renamed, and anyone who already has a direct file URL can still
open that file. True file-level access control would require web server rules;
this setting prevents URL guessing and enumeration, which covers the realistic
risk for a gallery.

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
