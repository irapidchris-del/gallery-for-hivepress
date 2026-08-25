=== Additional Gallery for HivePress ===
Contributors: chrisb
Tags: hivepress, gallery, vendors, portfolio, marketplace
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: hivepress
Stable tag: 1.9.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A front-end photo gallery for HivePress vendors, with public, members-only and private folders, per-photo pages, and optional paid access.

== Description ==

Additional Gallery for HivePress adds a portfolio-style gallery to every vendor account.

Vendors can:

* Create gallery folders from a new "Gallery" page in their account menu, and drag folders into any order
* Upload, reorder (drag and drop) and remove images - and videos, if the site allows them - in each folder
* Describe each photo or video; descriptions appear on the photo's own page, under its tile, and double as image alt text
* Set each folder to Public, Members only, or Private
* Share their gallery link or a direct link to any folder, and see the gallery linked automatically from their vendor profile and listing pages

Visitors see a gallery of folder covers with an "Updated 2 days ago" line, and click into each folder (every folder has its own shareable URL). A setting can switch this to the classic all-photos-expanded layout. Clicking any photo opens that photo's own page, with its description, likes, previous and next buttons and the comment thread; a Lightbox setting additionally lets visitors click the photo there to enlarge it. Members-only folders appear locked, with heavily blurred previews (or lock placeholders) that tease the content until the visitor unlocks access, and the original image URLs are never present in the page for locked folders.

Site owners can control everything from HivePress > Settings > Gallery:

* Hide the gallery link on vendor profiles and/or listing pages
* Limit the number of folders per vendor, and images per folder (default 30)
* Gate the gallery to membership plans, set up natively on each plan in HivePress Memberships (an "Allow using the photo gallery" and an "Allow viewing members-only gallery folders" option per plan)
* Choose how locked folders look: blurred previews, lock placeholders, or hidden entirely
* Choose the Upgrade Page that "Unlock Access" links point to (e.g. your pricing page)
* Protect private and members-only image files so their URLs cannot be opened directly
* Keep galleries light: cap upload file size, restrict image formats, and resize photos on upload
* Set a storage quota per vendor (site-wide or per membership plan); vendors then see "X used of X allowed" on their Gallery page
* Let signed-in visitors like and comment on individual photos, or switch either feature off
* Choose which side of the photo pages the sidebar sits on, and fill its "Photo Page (sidebar)" widget area from Appearance > Widgets
* Add a sidebar to the gallery page and to folder pages too, on either side, each with a widget area of its own
* Lay the folder covers out as a grid: choose how many columns, cap the rows where a gallery is embedded in a profile or listing, and pick horizontal, vertical or square covers
* Choose what one paid unlock buys: the vendor's whole gallery, or each folder separately, so vendors can price individual folders

In wp-admin, gallery folders live under Vendors > Gallery Folders. Each one has an Images meta box with the same drag-and-drop manager used for listings, plus a Gallery Settings meta box holding the vendor and the visibility, and the folders list shows the vendor, visibility, image count and size. A folder's owner is its vendor, and nothing else: the post author follows the vendor automatically. Photo comments appear on the usual WordPress Comments screen.

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
these options: "Allow using the photo gallery" (vendor access) in the plan's
Settings box, and "Allow viewing members-only gallery folders" (viewer access)
plus optional per-plan folder and photo limits, in its Restrictions (General)
box. Tick them on the plans that should include gallery access.

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

= Keeping galleries light =

Three upload rules keep a gallery from bloating a site: a maximum file size,
a list of allowed image formats, and a maximum width and height. Photos
straight from a phone or camera are far bigger than any screen needs, so the
resize is the biggest single saving; it runs before WordPress makes its
thumbnails, so every size comes from the scaled image. Under a storage quota, vendors see their
usage on their Gallery page; the admin folders list always shows sizes.

Compression, metadata stripping, WebP conversion and re-processing images you
already have are deliberately left to dedicated image plugins such as
Imagify, ShortPixel or FlyingPress, which do all of that better and across
your whole media library rather than just the gallery.

= Photo pages, likes and comments =

Every photo has its own page, with previous and next buttons, the photo's
title and description, a heart anyone signed in can press, and a comment
thread beneath, where people can also reply and like comments. Public photo
pages can be linked to directly. Both likes and comments can be switched off
in the Gallery settings, and everything respects folder visibility: a photo
in a folder someone cannot open can be neither viewed, liked nor commented
on. Comments are stored as ordinary WordPress comments, so they show up in
wp-admin alongside everything else. People can remove their own comments,
and a vendor can remove any comment on their own photos.

Each photo page also has a sidebar (left or right, chosen in settings). It
shows the vendor's profile card, so visitors can reach the vendor from any
photo, and it is a normal WordPress widget area named "Photo Page (sidebar)"
that you can fill from Appearance > Widgets. When the photo's owner views
their own photo, a Manage Photo card appears there too, with the title and
description fields, a move-to-another-folder choice with confirmation, and
deletion.

= Paid access (optional) =

With the Paid Access setting on and WooCommerce active, each vendor can set
their own price for unlocking their members-only folders, from their Gallery
page. Buying is a normal WooCommerce checkout; the unlock covers that one
vendor's locked folders and is taken back automatically if the order is
refunded or cancelled.

Each vendor chooses their own lengths and prices, on their own Gallery page.
A length is picked from a list - a day, a week, a month, three months, or
permanent - and given a price, and a vendor can offer up to three at once. A
locked folder then shows the buyer whichever lengths that vendor sells,
shortest first with permanent last, each with its price. Someone who buys again
while a pass is still running has the new days added to what they have left,
rather than starting over.

There is no site-wide access period to set. Earlier versions had up to three
Access Period boxes on this tab; they have been replaced by the per-vendor
lengths above. If you are upgrading, each period you had set carries over into
the matching vendor length slot, so nobody's passes change and nothing needs
doing.

You can also take a commission on these sales: a percentage, a fixed amount,
or both, with both boxes empty meaning none. It is added on top at checkout
and shown to the buyer as its own line called Platform fee, so the vendor
still receives the price they set.

= Who actually receives the money =

Worth reading before you set a price, because the answer depends on how your
site takes payments and not on anything in this plugin.

A gallery access sale is an ordinary WooCommerce product sale. The product is
created for you, owned by the vendor selling the access, and it goes through
your checkout and your payment gateway exactly like anything else you sell.
This plugin never moves money itself. What it does is make sure the sale is
attributed to the right vendor, and that anything you charge on top stays
yours.

There are three arrangements, and they behave differently:

**1. WooCommerce on its own, no HivePress Marketplace.** Everything the buyer
pays arrives in your own payment account. Vendors are not paid anything
automatically and no earnings are recorded. This is the right setup if you are
the one monetising the galleries - if the galleries are yours, or you settle up
with vendors outside the site.

**2. WooCommerce with HivePress Marketplace.** The money still arrives in your
payment account, because the buyer pays you. Marketplace records what the
vendor has earned as a balance and you pay it out through its payouts, and your
usual vendor commission is taken off that balance. The Platform fee above is
recorded against the order so it stays out of the vendor's balance entirely.

  A worked example. A vendor sells 90 days' access for 100.00, your vendor
  commission is 20% and your Platform fee is 10%. The buyer pays 110.00. The
  vendor's balance goes up by 80.00 and you keep 30.00 - your 20.00 commission
  plus the whole 10.00 fee. The fee is not shared with the vendor.

**3. A Stripe Connect gateway using direct charges.** Read this carefully if
you use one, because it reverses the first sentence of case 2. With direct
charges the buyer's card is charged on the VENDOR'S connected Stripe account,
so the whole order total lands with the vendor and never touches your Stripe
balance. Your income is the application fee that gateway is configured to
take, and nothing else.

  This has one consequence people are caught by: under direct charges the
  Platform fee above does NOT reach you. It is a line on the order, so it is
  part of the total that is charged to the vendor's account, and it simply
  increases what the buyer pays and what the vendor receives. If you use a
  direct-charges gateway and want a cut, set it as that gateway's application
  fee and leave the Platform fee here empty, or you will be charging your
  buyers for something you never collect. Marketplace balances still show
  numbers in this arrangement, but they are bookkeeping - the money has
  already gone to the vendor.

Whichever applies, the vendor a sale is credited to is the vendor whose gallery
it is. HivePress Marketplace works this out from the owner of the product, and
this plugin creates each access product owned by that vendor, so the two agree.

One last rule follows from that. HivePress Marketplace credits an entire order
to whoever sold its FIRST line, which is fine for it because its own buy button
empties the basket first. This plugin lets a buyer collect several passes from
one vendor and pay once, which is deliberate and safe, but it will refuse to add
gallery access to a basket that already holds another seller's goods, and will
say so at checkout. Without that refusal a basket holding two vendors' galleries
would pay both to the first vendor.

While a vendor sells access their locked folders offer that purchase; those
without a price fall back to the site's Upgrade Page link, so visitors are
never asked to choose between paying the vendor and paying the site.
Membership plans that include viewing still unlock folders either way.

= AI moderation =

The optional AI Moderation setting reviews a folder's photos with OpenAI
when the vendor saves it, using the shared API key from Settings >
Integrations. The first ten photos in the folder are checked together in one
free request; any beyond the tenth are not checked. The site
must be publicly reachable for OpenAI to fetch the photos; on local or
private sites, and whenever the service is unavailable, saving simply
proceeds unchecked rather than blocking the vendor. Protected files (in
private and members-only folders) cannot be fetched externally, so moderation
applies to public folders.

== Installation ==

1. Install and activate HivePress.
2. Upload the `additional-gallery-for-hivepress` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins screen.
4. Vendors will find "Gallery" in their account menu; settings live under HivePress > Settings > Gallery, and the folders themselves under Vendors > Gallery Folders in wp-admin.

== Frequently Asked Questions ==

= Where do vendors manage their gallery? =

Account menu > Gallery (only shown to users with a published vendor profile).

= What is the public gallery URL? =

`/gallery/{vendor_id}/` - vendors can copy it with one click from their Gallery page.

= Does deleting a folder delete its images? =

Yes. HivePress's attachment component removes all attached images when the folder is deleted.

= What happens if I deactivate or delete the plugin? =

Nothing is lost either way, unless you ask for it. Deactivating changes nothing:
every folder, photo and setting is still there when you activate it again.
Deleting keeps your data too, so a plugin removed by accident, or removed to
reinstall a clean copy, comes back exactly as it was.

WordPress will still warn you that deleting a plugin "will also delete its
data". That warning is generic and appears for every plugin; it does not
describe this one. Only the regenerable cache of blurred previews is cleared.

If you really do want everything gone, tick "Delete all gallery data when this
plugin is deleted" under HivePress > Settings > Gallery > Removing the Plugin
first. With that ticked, deleting the plugin removes its settings, folders,
likes, comments, purchased access and private directories, and cannot be
undone. Even then the vendors' photos are kept: they stay in your media library
as ordinary uploads, and any file that was in the protected directory is moved
back to its normal location first so it is still viewable without the plugin.
Your OpenAI API key is left alone either way, because other extensions share it.

== Changelog ==

Older entries are in changelog.txt, which ships with the plugin. WordPress truncates this
section at 5,000 characters, so only the most recent releases are repeated here.

= 1.9.1 =
* Fixed: switching "What Access Buys" between the whole gallery and each folder left the other
  choice's products on sale. They are catalogue-hidden but their checkout links keep working, so a
  vendor's old whole-gallery pass could still be bought at its old price after the site had moved to
  per-folder pricing, granting access to the whole gallery. Measured on a real site: three products
  still purchasable at 5.00, 12.00 and 20.00. A product is now on sale only while the site offers
  what it sells. Nothing is deleted and no price changes, so switching the setting back puts the
  same products on sale again, and access already paid for is untouched.
* Added: a Gallery Button Position setting. Leave it empty and the View Gallery button sits with the
  other action buttons beside Send Message, as it does now. Enter a number and it takes a place of
  its own in the sidebar instead, which is what you want if you use HivePress Social Links, because
  that puts itself above the action buttons and pushed the gallery button below them.
* Fixed: the changelog and short description in this readme were being silently truncated, because
  WordPress caps them at 5,000 and 150 characters. Older entries have moved to changelog.txt.

= 1.9.0 =
* Added: gallery and folder pages can now have a sidebar of their own, on either side, each with its
  own widget area under Appearance, then Widgets. Both are off until you switch them on.
* Added: a folder grid you control. Choose how many columns of folder covers a full-width screen
  shows, whether covers are cropped horizontal, vertical or square, and how many rows to show where a
  gallery is embedded in a vendor profile or a listing (with a link to the full gallery for the rest).
  Narrow screens always show fewer columns, so a phone is never asked to draw six.
* Added: an option for what one paid unlock buys. It can be the vendor's whole gallery, as before, or
  each folder separately, so a vendor can price individual folders and a buyer pays only for the one
  they want. Prices set under one choice are kept if you switch to the other and back, and nobody
  loses access they have already paid for.
* Added: a "Gallery Settings" link in the top right of a gallery page, and a "Folder Settings" link on
  a folder page, shown to the vendor who owns it and to the site owner. Visitors see nothing.
* Added: vendors and site owners now see private folders on the public gallery page, marked Private,
  with a note saying visitors do not see them. Everybody else sees exactly what they saw before. The
  folders were already reachable by their own URL, but nothing linked to them.
* Changed: the View Gallery button now sits inside HivePress's own actions box on vendor profiles and
  listings, beside Send Message, instead of floating below it with a gap of its own.
* Changed: gallery folders moved in wp-admin from under HivePress to Vendors, where they belong.
* Changed: the folder edit screen has one owner control instead of two. WordPress's Author box is
  gone, the Vendor field is no longer marked optional, and the post author follows the vendor on save.
  Any folder whose author and vendor already disagreed is put right on upgrade.
* Changed: the folder edit screen's Settings box is now called Gallery Settings.
* Changed: the left-or-right sidebar settings are radio buttons, so the empty "-" choice that read as
  a third position is gone.
* Changed: layout, lightbox and sidebar settings moved out of General into a new Gallery Pages
  section. No option name or stored value changes.
* Changed: names and avatars on photo comments now link to that person's profile, where the site
  publishes one.
* Changed: the Manage Photo description box no longer mentions alt text in its placeholder.
* Fixed: every "Members only" badge now carries a padlock. Folder covers and the vendor's own folder
  list showed the words alone while the folder page and the single-page layout showed a padlock, so
  the same state looked like two different states on one site.
* Fixed: the "New Folder" heading on the account gallery page, and the "Paid Access" and "Delete
  Folder" headings, now use HivePress's own section heading, so they get the accent rule above them
  that every other heading on the page has.

