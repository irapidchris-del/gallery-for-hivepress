=== Additional Gallery for HivePress ===
Contributors: chrisb
Tags: hivepress, gallery, vendors, portfolio, marketplace
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: hivepress
Stable tag: 1.8.16
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Gives HivePress vendors a front-end photo gallery with public, members-only and private folders, protected files, per-photo pages with likes and comments, and optional monetisation through Memberships or per-vendor paid access.

== Description ==

Additional Gallery for HivePress adds a portfolio-style gallery to every vendor account.

Vendors can:

* Create gallery folders from a new "Gallery" page in their account menu, and drag folders into any order
* Upload, reorder (drag and drop) and remove images - and videos, if the site allows them - in each folder
* Describe each photo or video; descriptions appear on the photo's own page, under its tile, and double as image alt text
* Set each folder to Public, Members only, or Private
* Share their gallery link or a direct link to any folder, and see the gallery linked automatically from their vendor profile and listing pages

Visitors see a gallery of folder covers with an "Updated 2 days ago" line, and click into each folder (every folder has its own shareable URL). A setting can switch this to the classic all-photos-expanded layout. Clicking any photo opens that photo's own page, with its description, likes, previous and next buttons and the comment thread; a Lightbox setting additionally lets visitors click the photo there to enlarge it. Members-only folders appear locked, with heavily blurred previews (or lock placeholders) that tease the content until the visitor unlocks access, and the original image URLs are never present in the page for locked folders.

Site owners can control everything from HivePress > Settings > Vendors > Gallery:

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

In wp-admin, each Gallery Folder has an Images meta box with the same drag-and-drop manager used for listings, plus a Settings meta box for visibility, and the folders list shows the vendor, visibility, image count and size. Photo comments appear on the usual WordPress Comments screen.

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
4. Vendors will find "Gallery" in their account menu; settings live under HivePress > Settings > Vendors > Gallery.

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
plugin is deleted" under HivePress > Settings > Vendors > Removing the Plugin
first. With that ticked, deleting the plugin removes its settings, folders,
likes, comments, purchased access and private directories, and cannot be
undone. Even then the vendors' photos are kept: they stay in your media library
as ordinary uploads, and any file that was in the protected directory is moved
back to its normal location first so it is still viewable without the plugin.
Your OpenAI API key is left alone either way, because other extensions share it.

== Changelog ==

= 1.8.16 =
* Fixed: no PHP warning left on a renamed install folder either. 1.4.0 fixed this for a normally
  named folder; on a renamed one, which is what downloading the source as a zip produces,
  HivePress still raised "Array to string conversion" once per request on sites with no paid
  HivePress extension.
* Fixed: gallery folders no longer appear on vendor profiles and listing pages for a vendor whose
  gallery access has lapsed. Folder titles, media counts and full size cover images were all still
  shown, and every link out of them sent the visitor to the home page. Every other part of the
  plugin already checked.
* Fixed: the Commission Rate description now explains that the fee only reaches you if your
  payment gateway settles gallery orders into the site's own payment account. With a gateway that
  charges each vendor's connected account directly, the whole order including the fee is paid to
  the vendor, so both commission boxes should be left empty. A new `hp_agl/commission` filter lets
  such a gateway switch the commission off by itself.
* Fixed: "Delete all gallery data" now removes four settings it used to leave behind, including a
  stored server path, and clears the AI review marks from photos it deliberately keeps, so a
  reinstall no longer treats those photos as already reviewed.
* Fixed: deleting the plugin now also clears the update check's own leftovers and cancels its
  background update check.

= 1.8.15 =
* Fixed: the padlock on a locked folder's "Members only" label sat hard against the words, unlike
  the padlock on the Unlock buttons below it. The label is laid out in a way that drops the space
  the markup already had, so the gap is now set properly and the two match.
* Fixed: that same label left an HTML tag unclosed. Browsers repaired it silently, so nothing looked
  wrong, but anything added after the label could pick up the label's own styling.

= 1.8.14 =
* Fixed: a fatal error on every request when the plugin ran without HivePress on WordPress
  versions that do not enforce the `Requires Plugins` header. The 1.8.3 upgrade step called
  HivePress unguarded; it now waits, and the upgrade simply completes once HivePress is back.
* Fixed: gallery access could be bought while signed out. Access attaches to an account, so a
  guest order paid and received nothing. Signed-out visitors are now refused at add-to-cart,
  the classic cart and checkout, and the blocks checkout, and asked to sign in first.
* Fixed: the AI photo check never finished for folders holding a video. The video was sent to
  the image endpoint, which cannot judge it, so no photo was ever marked as checked and every
  re-save paid for a full re-review. Videos are now left out of the check, and only photos
  actually reviewed are marked - not ones skipped by the cap or file protection.
= 1.8.13 =
* Fixed: gallery access could not be paid for with a Stripe Connect direct-charges gateway. Such
  gateways find a product's vendor to route the charge, and gallery access - a standalone product
  with no listing behind it - only carried this plugin's own vendor marker, not the standard one
  the gateway reads. It now also stamps the conventional `hp_vendor` product meta, so the vendor
  resolves and the gateway offers itself. Existing access products pick this up next time their
  price or length is saved. Inert for sites not using such a gateway.
= 1.8.12 =
* Added: every photo comment is now a link target (`#agl-comment-N`), and the comment somebody
  arrives at through a notification link announces itself with a soft highlight. Nothing changes
  for anyone browsing normally.

= 1.8.11 =
* Fixed: the one-seller basket rule only ran when an item was ADDED, and WooCommerce also builds
  baskets by merging - signing in merges a saved basket into the current one with no checks, and
  re-ordering a past order rebuilds the basket against checks that see it as empty. Either could
  quietly assemble a basket holding two vendors' gallery passes, which Marketplace would then pay
  entirely to the first vendor. The whole basket is now re-checked at the cart, the classic
  checkout and the blocks checkout, so a mixed basket cannot reach payment however it was made.
* Fixed: the Platform fee was recorded on the first GALLERY line of the order, but HivePress
  Marketplace only reads it from the order's first line of any kind. A basket whose first line was
  the same vendor's ordinary listing purchase - which the one-seller rule rightly allows - had the
  fee paid out to the vendor as earnings instead of staying with the site. It is now recorded on
  the order's first line, and added to anything already recorded there rather than overwriting it.
* Documentation: the note about upgrading from the old site-wide Access Period boxes now says there
  were up to three and that each carries into the matching vendor length slot.

= 1.8.10 =
* Fixed: pressing a Buy button for gallery access added the pass to the basket and then dropped the
  buyer back on another page instead of the checkout, with no confirmation and nothing charged - so
  the button looked broken. The purchase now goes straight to the checkout. This was masked on the
  site it was built against by a third-party "direct checkout" plugin forcing the redirect for every
  product; sites without one got the bounce.

= 1.8.9 =
* Fixed: a basket holding gallery access from two different vendors paid the whole order to the
  first of them. HivePress Marketplace credits an order to whoever sold its first line, so adding a
  second vendor's access is now refused with an explanation at checkout. Buying several lengths from
  one vendor in a single order still works, which is what the basket was kept for.
* Fixed: the View Gallery button lost its full width when it was given its taller shape. It now
  carries the core button classes that supply the width and the icon spacing as well as the theme
  classes that supply the height.
* Documentation: a new "Who actually receives the money" section in the readme sets out exactly who
  is paid under WooCommerce alone, under HivePress Marketplace, and under a Stripe Connect gateway
  using direct charges - including the fact that the Platform fee does not reach the site under
  direct charges, where the gateway's own application fee should be used instead.
* Documentation: removed the description of the site-wide Access Period setting, which per-vendor
  lengths replaced. Existing values still carry over on upgrade.

= 1.8.8 =
* **Added - show the gallery on vendor profiles and on listings**, rather than only linking to it.
  Two new settings, both off to begin with and both independent of the sidebar buttons, so you can
  have the section instead of the button, as well as it, or neither. On a profile the Gallery
  section sits below the vendor's listings; on a listing it sits after the tags and before the
  reviews, which keeps its place whether either of those extensions is active. A vendor with no
  gallery gets no section and no empty heading.
* Fixed - the "Members only" badge on a locked folder left a `<span>` unclosed. Browsers repaired it
  quietly, so nothing looked wrong, but the markup was invalid.

= 1.8.7 =
* **Added - vendors can choose which photo is a folder's cover.** Open any photo and use "Use as
  folder cover"; without a choice the first photo is still used, exactly as before. A cover that is
  later deleted or moved elsewhere quietly falls back to the first photo rather than leaving a gap.
* Changed - the fee shown to buyers at checkout is now called "Platform fee" rather than "Gallery
  Access Fee", which said less than it should have.
* Fixed - the View Gallery button now matches the height of the Send Message button above it. It
  carries the same classes HivePress puts on that button, and an old rule of ours that overrode the
  padding has gone.
* Fixed - the sidebar on a photo page no longer has its own scroll bar. It is made sticky by
  HivePress's own component, and a stylesheet rule here was fighting it and forcing the sidebar to
  scroll within itself, which no native HivePress sidebar does.
* Fixed - the "Delete this photo" control is centred.

= 1.8.6 =
**Vendors now choose their own access lengths.** Setting up paid access is a good deal simpler.

* **Changed - a vendor picks how long their access lasts** from a short list: a day, a week, a
  month, three months, or permanent. Before this, you set the lengths in Settings and each vendor
  could only price what you had decided. Vendors know their own work best, and the list keeps the
  offers comparable between them.
* **Changed - one row to fill in, not three boxes.** A vendor sets a length and a price, and adds
  another row only if they want to offer more than one. Up to three at a time, so a buyer is
  choosing between offers rather than reading a price list.
* **Changed - the three Access Period settings have gone** from the Gallery tab, because vendors now
  set their own. Nothing anybody is already selling changes: a vendor who had priced access keeps
  selling exactly the same length at the same price until they choose otherwise.
* Removed the background job that kept access products in step with those settings. It only existed
  because you owned the lengths; a vendor changing their own now updates their own product.

= 1.8.5 =
**Fixes a fault in 1.8.4 that could short-change a buyer. Update if you sell gallery access.**

* **Fixed - buying two lengths of access in one go granted only one of them.** A buyer could put,
  say, seven days and ninety days in the basket together and pay for both, and only the first was
  counted: they were charged for ninety-seven days and given seven. Access was being recorded
  against the order rather than against each thing bought, so the second line was quietly dropped.
  Both are now counted. Access already granted is unaffected, and a refund still takes back only
  what that order paid for.
* Fixed - filling in an access length for the very first time did not update the products already
  selling it, so the unlock button could advertise one length while the checkout named another.
  Setting a length for the first time and changing an existing one now both bring the products back
  in step. WordPress fires a different hook when an option is created than when it is changed, and
  only the second was being listened for.

= 1.8.4 =
* **Added - vendors can charge different prices for different lengths of access.** Until now a site
  offered one length and a vendor set one price for it. You can now set up to three lengths in
  Settings, and each vendor prices whichever of them they want to sell; a buyer picks from the ones
  that vendor has priced. Sites offering a single length carry on exactly as before, and every price
  already set stays where it is.
* **Added - commission on gallery access sales.** Set a percentage, a fixed amount, or both, and
  leave both empty to take nothing. The amount is added on top at checkout and appears to the buyer
  as its own line, on the block checkout and the classic one alike, so the vendor still receives the
  price they set. With HivePress Marketplace running, the fee is recorded against the order so that
  it does not count towards the vendor's earnings.
* **Changed - buying access while you still have some now extends it.** Before, a second purchase
  was ignored, which was harmless when there was only one thing to buy and would have meant taking
  someone's money for nothing now that there is more than one. Refunding one of those purchases now
  takes back only the days it paid for, instead of everything.
* **Changed - the length of access is recorded on the order.** An order that takes days to clear,
  such as a bank transfer, now grants what the site was offering when it was paid for, rather than
  whatever the setting happens to say when the money arrives.
* Changed - the gallery's settings have moved out of the Vendors tab into a Gallery tab of their
  own. Nothing you have configured moves or resets.
* Fixed - the Settings link beside the plugin on the Plugins screen, and the warning shown when
  protected photos cannot be moved out of the published folder, both pointed at the old tab.

= 1.8.3 =
**Security fix. Update if you use private or members-only folders.**

* **Protected photos are now stored outside the folder your web server publishes.** Until now they
  were kept inside it with a deny rule beside them. That rule works on Apache on its own, but it is
  never read where another web server hands out files before Apache sees the request, which is how
  most shared hosting is arranged. On those sites every private and members-only photo could be
  opened by anyone who had the address. The unguessable file name was obscurity, not protection.
  Confirmed on a real host on 20 August 2026: the photo came back in full while the folder listing
  and the deny rule itself both correctly refused.
* Existing protected photos are moved for you when you update. Nothing about how they are shown
  changes, and their addresses are unchanged, because they were already served through the
  access-checked link rather than directly.
* **If your hosting has nowhere outside the published folder that can be written to**, the photos
  stay where they are and a notice on the settings screen tells you so plainly, rather than the
  setting promising protection your hosting cannot give. You can point the plugin at a folder
  yourself by defining `HP_AGL_PROTECTED_DIR` in `wp-config.php`.
* Corrected the AI moderation tooltip, which claimed these files "cannot be reached from outside".
  That was not true on every host, and a security claim has to be true everywhere.
* **New - likes and comments show up for everyone straight away.** A page cache serves signed-out
  visitors the copy it stored, counts and all, so a new like or comment stayed invisible to
  everybody but the person who left it until that copy expired. Measured on a real host: still
  reading zero more than eighty minutes later. The affected pages are now refreshed the moment a
  like or comment lands, in the background rather than while somebody waits, and a burst on one
  photo is handled as a single refresh. Works with FlyingPress, SiteGround Optimizer, WP Rocket and
  LiteSpeed, and any other cache can hook the new `hp_agl/purge_urls` action.
* Fixed - the photo count beside the like button stayed at zero after posting a comment while the
  heading right below it already counted it. Both now update together.
* Fixed - counting a folder's photos no longer loads every photo in it as a full record just to
  read its file type. It is one small query, cached, and cleared whenever the folder's photos
  change. A vendor with many folders paid for the old way on every view of their gallery.
* Fixed - the AI photo check no longer re-checks photos it has already seen. Re-saving a folder to
  correct a typo used to send every photo in it to OpenAI again and wait for the answer, measured at
  around four seconds a time. Editing a folder with no new photos now costs nothing.
* Fixed - you can open your own private folder from its address. Everyone was refused, including its
  owner, and the refusal quietly sent you to the gallery index with nothing to say why.
* Fixed - checking for updates no longer holds up an admin page, and now reads github.com rather
  than the GitHub API, which has an hourly limit shared by every plugin on your server. A failed
  check also no longer erases the last good answer.
* Fixed - "View details" is back on the Plugins screen. WordPress only offers that link for a
  plugin that has told it about itself, and this one stayed quiet whenever there was nothing to
  update to, which is almost always. The details popup, its changelog and the donate link inside
  it were all unreachable from the Plugins screen as a result.

= 1.8.2 =
* Two new hooks so Notifications for HivePress can tell people about things that used to happen in
  silence: `hp_agl/folder_flagged` when the photo review hides a gallery, and `hp_agl/access_expiring`
  a week before somebody's purchased access lapses.
* New - buyers can be warned before their gallery access runs out, rather than only being told once
  it already has. A daily check looks a week ahead, warns once per purchase, and leaves lifetime
  access alone. Filter `hp_agl/access_warning_days` to change the notice period.
* Fixed - the internal version constant said 1.8.0 while the plugin said 1.8.1, so stylesheets and
  scripts were being cached against the wrong version after the 1.8.1 update.

= 1.8.1 =
* Fixed: AI photo moderation never actually checked a folder containing more than one photo. Every photo was sent to OpenAI in a single request, and OpenAI accepts only one image per request, so the request failed. The plugin is built to allow the save when the check cannot run, so the folder went through and it looked exactly like a clean pass. A folder with one photo worked correctly, which is why this was not obvious. Each photo is now sent in its own request.
* Fixed: saving a gallery folder could hang for as long as OpenAI took to answer, because the photos were checked while the vendor waited. Each of those checks occupies one of the small number of PHP processes your host provides, so several people saving folders at once could leave nothing to serve anybody else and produce site-wide timeouts unconnected to the gallery.
* Changed: photos are now checked in the background, moments after the folder is saved. Saving is immediate again. A folder found to contain inappropriate content is set to Draft, so it stops being publicly visible just as a refused save would have prevented, but the vendor is told on review rather than at the moment they save.
* Changed: photo review now has an overall time limit of 30 seconds and stops at the first photo that is flagged.

= 1.8.0 =
* Changed: Deleting the plugin now KEEPS your galleries by default. Folders, photos, likes, comments, purchased access and settings all survive, so a plugin removed by accident or reinstalled fresh comes back as it was. To remove everything, tick the new "Delete all gallery data when this plugin is deleted" setting under Removing the Plugin first. WordPress's own delete-screen warning about deleting plugin data is generic and does not apply unless you tick that box.
* Fixed: Save and error messages in the gallery's own forms were invisible. The photo's Manage Photo card, the comment box and the access price form all wrote their confirmation into a box that HivePress keeps hidden until its own script reveals it, so "Saved" never appeared.
* Fixed: A membership plan with a gallery folder limit of 0 granted unlimited folders instead of none. The plan limits now start at 1, and 0 falls back to the site-wide limit; use the plan's "Allow using the photo gallery" tick to withhold the gallery entirely.
* Fixed: The AI Moderation setting said every photo in a folder was checked. Only the first ten are, which is what it now says, in the settings and in this readme.
* Fixed: Counts no longer read "1 photos, 1 videos", "1 folders" or "(1 days)".
* Fixed: The OpenAI API key is now masked on the Integrations screen, with a show/hide toggle, instead of sitting in plain sight where it can be caught in a screenshot or a screen share.
* Fixed: Requests to OpenAI and to GitHub now identify themselves as this plugin. Without that, WordPress attaches your site's address and exact WordPress version to every one.
* Fixed: A vendor whose membership no longer includes the gallery could still move photos between folders; that endpoint now checks entitlement like every other one.
* Changed: Every PHP class and file name carries the plugin's own prefix, so it cannot collide with HivePress or a future official extension. A collision silently stops one plugin's code loading, so this matters even though nothing is visibly different.
* Changed: The gallery's own form fields now carry HivePress's field classes on the controls themselves, so they inherit your theme's input styling instead of the plugin's.
* Added: A "Donate" link on the plugin's row on the Plugins screen and in its "View details" popup. Nothing is added inside the plugin's own screens.
* Changed: The "HivePress is missing" notice is now dismissible and only shown to people who can actually install plugins.

= 1.7.0 =
* Added: A sidebar on every photo page, holding the vendor's profile card and a "Photo Page (sidebar)" widget area you can fill from Appearance > Widgets. A setting chooses whether it sits left or right of the photo.
* Added: A Manage Photo card in that sidebar for the photo's owner, with the title and description fields, the move-to-another-folder choice and deletion. It replaces the separate photo editing page and the pencil icon in the folder editor.
* Added: An Access Period setting for paid access. Purchases can now last a set number of days instead of forever; the unlock button states the period, expired passes can be bought again, and access already bought keeps the period it was bought with. A new hp_agl/access_expired action fires when a pass lapses.
* Changed: A locked folder now shows a single unlock button: the vendor's purchase while they have a price set, otherwise the site's upgrade page link. Previously both could appear together, which asked visitors to choose between paying the vendor and paying the site.
* Changed: Clicking a photo in a gallery now always opens the photo's own page, where the description, likes and comments live. The Lightbox setting instead controls whether the photo can be clicked to enlarge on its page.
* Changed: The gallery settings are now split into a Gallery section and a Gallery Monetisation section, so the monetisation options and their explanation live together.
* Fixed: On gallery and folder pages, the like and comment counts under a captioned photo were pushed a full tile-height down, leaving them floating in space. Captions became links in the previous version and were accidentally given the photo's square frame.
* Fixed: The reply box under a comment now appears full-width beneath the comment text instead of squeezed to the far right, and the Post Comment button sits directly under its box.
* Changed: The comment box invites visitors to "Share your thoughts on this photo...", the Comments heading is smaller, and the vendor-facing paid access copy explains how to stop selling more clearly.
* Added: Every photo now has its own page, with the image, its title and description, previous and next buttons, and a full comment thread beneath it. Public photos can be linked to directly.
* Added: Replies and comment likes. Conversations nest one level deep, and signed-in visitors can heart individual comments.
* Added: Comments are now written in an inline "Comment here..." box on the photo page, replacing the old pop-up. The comment counts on grid tiles remain and link straight to the conversation.
* Added: A per-photo editing page for vendors, with proper Title and Description fields, a move-to-another-folder choice with confirmation, a link to the photo's public page, and deletion. Reached via the pencil on each photo in the folder editor, or the Edit link on your own photo pages.
* Added: Paid gallery access. When enabled in settings (and WooCommerce is active), each vendor can set their own price for unlocking their members-only folders; buying is a normal checkout and the unlock is per vendor. Refunding or cancelling the order takes the access back. With HivePress Marketplace active these sales count towards vendor earnings and your usual commission applies.
* Added: A storage quota (site-wide or per membership plan). When set, vendors see "X used of Y allowed" on their Gallery page and uploads over the cap are rejected; without one, gallery sizes are no longer shown on the front end.
* Added: A Lightbox setting. On (the default), photos open in the pop-up viewer as before; off, clicking a photo goes straight to its page.
* Added: A setting to hide the photo count on the "View Gallery" button.
* Added: A Members-Only Folders setting. Switching it off leaves vendors a simple public-or-private choice, and any existing members-only folders behave as private.
* Added: Developer actions for notification plugins: hp_agl/photo_liked, hp_agl/photo_commented, hp_agl/comment_replied, hp_agl/comment_liked, hp_agl/access_purchased and hp_agl/access_revoked.
* Changed: The Gallery settings description now explains how to monetise the gallery through membership plans.
* Changed: The OpenAI Integrations section now states that moderation checks are free and links to the OpenAI sign-up page.

= 1.5.0 =
* Added: Photo likes. Signed-in visitors can like individual photos, with a count everyone can see. Each person can like a photo once, and likes respect folder visibility, so a photo nobody can open cannot be liked.
* Added: Photo comments. Signed-in visitors can comment on individual photos in a pop-up per photo. People can delete their own comments, the folder's owner can delete any comment on their own photos, and moderators can delete anything. Comments are stored as ordinary WordPress comments, so they are visible in wp-admin.
* Added: Both likes and comments can be switched off in the Gallery settings, and both are on by default.
* Added: Per-plan gallery limits in HivePress Memberships. A membership plan can now set its own gallery folder limit and photos-per-folder limit, in Restrictions (General), so higher plans can offer more. A vendor on more than one plan gets the most generous limit, and an empty limit falls back to the site-wide setting.
* Changed: "Allow viewing members-only gallery folders" moved from the plan's Settings box to its Restrictions (General) box, next to the other viewing restrictions. Existing settings are unaffected.
* Removed: Image quality, metadata stripping, WebP conversion, Keep Originals, and the bulk "Optimise images" / "Restore original images" actions. Dedicated image plugins such as Imagify, ShortPixel or FlyingPress do all of this better, with fallbacks and CDN support. Resizing on upload, the file-size cap and the allowed-format list remain, because those belong to the upload itself. Any backups the removed feature made are cleaned up automatically on update.
* Fixed: A PHP warning ("Array to string conversion") that HivePress logged on every request in 1.4.0, caused by how this plugin registered itself. Registration now uses the form HivePress expects whenever the plugin folder is named normally.
* Changed: Visibility badges now use HivePress's own status pill, and muted text uses its own meta style, so the gallery inherits each theme's colours instead of defining its own.

= 1.4.0 =
* Fixed: The plugin now registers itself with HivePress explicitly, so it works even when installed into a folder whose name does not match the plugin file (for example from a GitHub "Download ZIP"). Previously such an install left the plugin active but completely inactive.
* Fixed: Copy buttons now work on sites that are not served over HTTPS. The browser clipboard API is unavailable outside a secure context, and the fallback needed a text field beside the button, which the folder rows do not have.
* Fixed: Stylesheet and script versions now include the file modification time, so an updated asset is never served from a stale browser cache.
* Fixed: Deactivating the plugin now clears the cached rewrite rules instead of rewriting them, so the gallery URLs are properly removed.
* Fixed: The "Check for updates" link now reports that the plugin is up to date when the repository has no release yet, rather than reporting a failure, and the "View details" popup falls back to the installed plugin's details.
* Fixed: A vendor whose membership no longer includes the gallery can no longer reorder folders through the REST endpoint.
* Added: An uninstall routine. Deleting the plugin now removes all of its options, plan settings, folder posts and private directories. The vendors' photos are deliberately kept, detached into the media library, and any protected file is moved back to its normal location first so it stays viewable. The shared OpenAI key is left in place.
* Added: A Settings link on the Plugins screen.
* Changed: The sidebar gallery button is now injected with HivePress's newer block-merging method, which finds the sidebar wherever a theme has moved it.
* Changed: The gallery settings section gained an explanatory description, units moved into the file size, dimensions and quality labels, and the AI Moderation description now says that photos in protected folders are skipped.
* Changed: The gallery component and controller classes are now prefixed, so they cannot silently collide with a future HivePress class of the same name.

= 1.3.0 =
* Added: Automatic updates from GitHub releases - sites are notified of new versions and can update from the Plugins screen, with a "Check for updates" link. Self-contained, no external service required.
* Added: Native HivePress Memberships integration - gallery access and members-only viewing are now configured per plan, directly in the Membership Plan editor (replacing the separate plan-picker settings, which are migrated automatically). Gating is optional and fails closed if Memberships is deactivated.
* Added: Strong file protection - files in private and members-only folders are moved to a protected directory and served through an access-checked link, so their URLs cannot be opened directly. Public folder images stay directly served for speed and SEO. On visibility change, files move automatically.
* Added: Image optimisation settings - maximum file size, allowed formats, maximum dimensions (resize on upload), image quality, strip metadata, and convert to WebP. New uploads are optimised before thumbnails are generated.
* Added: Bulk "Optimise images" and "Restore original images" actions on the Gallery Folders list, with an optional "Keep Originals" backup for reversible optimisation of existing galleries.
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
