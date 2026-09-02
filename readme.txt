=== Additional Gallery for HivePress ===
Contributors: chrisb
Tags: hivepress, gallery, vendors, portfolio, marketplace
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: hivepress
Stable tag: 1.10.3
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

The optional AI Moderation setting reviews a folder's photos with OpenAI's
free Moderation endpoint when the vendor saves it, using the shared API key
from Settings > Integrations. Each save reviews up to ten photos that have
not been reviewed before; a folder holding more than ten catches up over
later saves. To guarantee that every photo is reviewed the moment it can go
public, set the AI Moderation Photo Limit: it caps every folder at your
chosen number of photos, from 1 to 10, so a single save always covers the
whole folder. The site must be publicly reachable for OpenAI to fetch the
photos; on local or private sites, and whenever the service is unavailable,
saving simply proceeds unchecked rather than blocking the vendor. Protected
files (in private and members-only folders) cannot be fetched externally, so
moderation applies to public folders.

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

= 1.10.3 =
* Changed: on the settings tab the help icon now sits directly after each label, and its tooltip opens to the right at full width instead of being cut into a narrow strip to the left. The same placement is used across every extension in this family.

= 1.10.2 =
* Fixed: the warning that says private photos are still reachable by anyone with the address could
  fail to appear. Protect Files cannot always move files out of the folder your web server publishes,
  and the plugin warns you on the Gallery settings tab when that happens. The warning was only shown
  when the address in your browser named the Gallery tab, so opening Settings from the HivePress menu
  showed the tab with no warning on it, and nothing said anything was wrong. It is now shown whenever
  the Gallery tab is on screen, however you reached it. Please open Settings > Gallery once after
  updating: if the warning is there, your private and members-only photos have been openly reachable
  and the notice explains what to do about it.
* Fixed: the OpenAI API key on the Integrations tab lost its show/hide button and stretched across
  the whole screen when you opened Settings from the HivePress menu rather than clicking through to
  the Integrations tab. The key stayed hidden either way, but there was no way to check what you had
  pasted. The button and the normal field width now appear wherever the key is shown.

= 1.10.1 =
* Changed: the settings screen now keeps its quick links in view as you scroll, and adds a Save
  button and a back-to-top button that follow you down the page. The quick links, the Save button and
  the back-to-top button now look and sit exactly the same in every one of these extensions, so
  moving between two of their settings tabs no longer means hunting for the same control in a
  different place.

= 1.10.0 =
* Added: an AI Moderation Photo Limit setting, shown once AI Moderation is ticked. AI review covers
  at most ten photos per folder save, so a folder holding more could be published with photos nobody
  looked at. Set the limit (1 to 10) and no folder can hold more photos than one review covers; it
  overrides higher limits, including per-plan ones, and is enforced when uploading and when moving a
  photo between folders. Folders already over the limit keep their photos but cannot take new ones.
* Added: quick links at the top of the Gallery settings tab, one per section, with a divider between
  sections, so a setting near the bottom no longer means scrolling blind.
* Changed: the settings descriptions are shorter and wrap at a readable width instead of the full
  screen, and the hover tooltips are wider, so their text no longer breaks into ragged slivers.
* Changed: the AI Moderation description no longer says that photos beyond the tenth are never
  checked. Each save reviews up to ten photos that have not been reviewed before, so a folder's
  backlog is worked through over later saves. Only the wording was wrong; the reviewing itself is
  unchanged.

= 1.9.2 =
* Fixed: the Gallery Button Position setting added in 1.9.1 was a single box governing both vendor
  profiles and listing pages, and the two sidebars number their blocks differently, so one figure
  could not hold the same place on each. 24 clears social links on a profile but sits below both
  social links and the action buttons on a listing. There are now two boxes, one per page type, each
  listing that page's own landmarks. A position already set stays with vendor profiles; listing pages
  return to the default placement until their box is filled in.

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

