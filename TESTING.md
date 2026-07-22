# Additional Gallery for HivePress — Manual Test Walkthrough (v1.3.0)

Work through the phases in order; later phases assume earlier ones passed. For each check, fill in the **Result** line. If something fails, paste whatever you have: the on-screen error, the browser dev console (F12 → Console), the Network tab response for REST calls, or the PHP error log line.

This walkthrough covers behaviour on a real WordPress install — the one thing static checks cannot. Pay particular attention to the 1.3.0 additions: native Memberships gating (Phase 6), strong file protection (Phase 8) and image optimization (Phase 12).

**Test environment**

- WordPress version: `______`
- HivePress version: `______`
- Theme: `______`
- Memberships extension active? `Yes / No`
- PHP version: `______`
- Browser(s): `______`

---

## Phase 1 — Installation

### 1.1 Activation
Upload the zip via Plugins → Add New → Upload, activate.

- Expected: activates with no errors or notices; a "Gallery" section appears in HivePress → Settings.
- Result: `______`

### 1.2 Permalinks / routes
Without visiting Settings → Permalinks first, open `/account/gallery/` while logged in as a vendor.

- Expected: the page resolves (the plugin flushes rewrite rules on activation).
- Result: `______`

### 1.3 Deactivate HivePress briefly
Deactivate HivePress (leave this plugin active), load the front end and wp-admin, then reactivate HivePress.

- Expected: no fatal error anywhere; an admin notice says the plugin needs HivePress.
- Result: `______`

---

## Phase 2 — Folders (vendor account)

Log in as a vendor for this phase.

### 2.1 Create
Account → Gallery → New Folder. Create "Balayage", visibility Public, with a description.

- Expected: folder appears in the list with a Public badge and "0 photos".
- Result: `______`

### 2.2 Limits
Set Max Folders to 2 in settings, create a second folder, then look for the create form.

- Expected: after the second folder, the form is replaced by "You have reached the limit of 2 folders."
- Result: `______`

### 2.3 Edit
Open a folder, change its title and visibility to Members only, save.

- Expected: success message; the account list shows the new title and a Members only badge.
- Result: `______`

### 2.4 Delete
Use Delete Folder on a test folder.

- Expected: a confirmation dialog appears first; after confirming, the folder and its images are gone.
- Result: `______`

### 2.5 Reordering
With 3+ folders, drag rows into a new order using the grip handle, then reload the page.

- Expected: rows drag smoothly; the new order survives the reload; the public gallery shows the same order.
- Result: `______`
- If it fails: any red rows in the Network tab for `.../gallery-folders/{id}/sort`? Response body: `______`

---

## Phase 3 — Images and descriptions

### 3.1 Upload
Open a folder, upload several images (jpg, png, webp).

- Expected: thumbnails appear as each finishes; drag to reorder works (core behaviour).
- Result: `______`

### 3.2 Image limit
Set Max Images per folder to a small number and try exceeding it.

- Expected: uploads beyond the limit are rejected with a HivePress error message.
- Result: `______`

### 3.3 Descriptions — save as you type
Each thumbnail should show a text input underneath ("Add a description..."). Type into one and pause.

- Expected: after ~1 second the input border briefly turns green (saved). Reload the page: the description is still there.
- Result: `______`

### 3.4 Descriptions — new uploads
Upload one more image and check its row.

- Expected: the new thumbnail also gets a description input without reloading.
- Result: `______`

### 3.5 Alt text mirror
In wp-admin → Media, open an image you described.

- Expected: its Alternative Text matches the description you typed.
- Result: `______`

### 3.6 Length limit
Paste 600+ characters into a description input.

- Expected: the input stops at 500 characters (browser limit); nothing breaks.
- Result: `______`

### 3.7 Clearing
Delete a description entirely, wait for the green tick, reload.

- Expected: it stays empty.
- Result: `______`

---

## Phase 4 — Public gallery (folder covers layout)

Log out or use a private window for the visitor checks. Gallery Layout setting: **Folder covers** (default).

### 4.1 Covers grid
Visit the vendor's gallery via the "View Gallery" sidebar button on their profile.

- Expected: a grid of folder cover cards (first image, title, count such as "8 photos"), plus an "Updated x ago" line near the top.
- Result: `______`

### 4.2 Folder page
Click a cover.

- Expected: the folder opens on its own page (`/gallery/{vendor}/{folder}/`) with a back link, description, "N photos · Updated x ago" line, and the image grid; clicking an image opens the lightbox with your description as its caption.
- Result: `______`

### 4.3 Deep link
Copy that folder URL into a different browser (logged out).

- Expected: it loads directly.
- Result: `______`

### 4.4 Copy buttons
As the vendor: use the link icon on a folder row, and the Copy button on the folder edit page.

- Expected: icon flips to a tick / button says "Link copied!"; pasted URL matches the folder page.
- Result: `______`

### 4.5 Empty folder
A folder with zero images:

- Expected: not shown on the public gallery at all.
- Result: `______`

---

## Phase 5 — Single page layout

Switch Gallery Layout to **Single page**.

### 5.1 Expanded view
Reload the public gallery.

- Expected: every folder is expanded on one page, each heading with a small link icon; the icon links to that folder's own page (deep links still work in this layout).
- Result: `______`

### 5.2 Lightbox grouping
Open an image in folder A and use lightbox arrows.

- Expected: navigation stays within folder A's images.
- Result: `______`

---

## Phase 6 — Members-only locking

Needs the Memberships extension. Edit a Membership Plan (HivePress → Memberships → Plans) and tick **Allow using the photo gallery** and **Allow viewing members-only gallery folders** in its Settings box. Make one folder Members only.

### 6.0 Native plan fields
Open a Membership Plan in wp-admin.

- Expected: the Settings box shows the two gallery checkboxes; ticking and saving persists them. With no plan ticked for gallery access, every vendor keeps the gallery; tick a plan to start gating.
- Result: `______`

### 6.1 Locked (blur)
Locked Folder Display: **Blurred previews**. View the gallery logged out.

- Expected: covers layout shows a blurred cover with a lock; the folder page shows blurred tiles, the "This folder contains N photos" note (single layout) and an Unlock Access button. View the page source: no original image URLs present, only `hp-agl-teasers` ones.
- Result: `______`

### 6.2 Locked (placeholder tiles)
Switch to **Placeholder tiles**, reload.

- Expected: grey lock tiles instead of blurs.
- Result: `______`

### 6.3 Locked (hidden)
Switch to **Hide completely**, reload.

- Expected: the members folder disappears from the gallery; its direct folder URL redirects back to the gallery.
- Result: `______`

### 6.4 Unlock button target
Click Unlock Access (with no Upgrade Page set in settings).

- Expected: lands on the Memberships plan-selection page (`/select-plan`), or the login page when logged out and no plans page exists.
- Result: `______`

### 6.5 Member with the right plan
Log in as a user holding a Viewer Access plan.

- Expected: the members folder appears sharp, no locks.
- Result: `______`

### 6.6 Vendor without a gallery plan
Log in as a vendor whose membership plan does not enable gallery access.

- Expected: no Gallery item in their account menu; `/account/gallery/` redirects; their public gallery URL redirects home; no "View Gallery" button on their profile.
- Result: `______`

### 6.7 Fail closed
With at least one plan enabling gallery access, deactivate the Memberships extension entirely, then view a vendor gallery.

- Expected: galleries behave as locked-down (redirect home), never as suddenly free (the gating flag is remembered).
- Result: `______`

Reactivate Memberships afterwards.

### 6.8 Migration from 1.2.0 (upgrade installs only)
If you upgraded from 1.2.0 where the old Vendor/Viewer Access Plans settings were set:

- Expected: those plans now show the gallery checkboxes ticked automatically, and gating behaves as before; the old settings fields are gone.
- Result: `______`

---

## Phase 7 — Videos

Turn on **Allow uploading videos**.

### 7.1 Upload
Upload an mp4 into a folder alongside images.

- Expected: accepted; shows as a row in the upload field. A webm and ogv should also be accepted; a .mov should be rejected.
- Result: `______`

### 7.2 Display
View that folder publicly.

- Expected: the video renders as an inline player showing its first frame, with controls; images still open in the lightbox; the count label reads like "6 photos, 1 video".
- Result: `______`

### 7.3 Video description
Add a description to the video's row in the folder edit page.

- Expected: saves with the green tick; shows under the player on the folder page.
- Result: `______`

### 7.4 Locked videos
Make that folder Members only with blurred previews, view logged out.

- Expected: the video shows as a plain lock tile (no blurred frame, no video URL in the page source).
- Result: `______`

### 7.5 Setting off
Turn the video setting off again.

- Expected: existing videos disappear from public folders and counts; new video uploads are rejected.
- Result: `______`

---

## Phase 8 — Protect Files (strong protection)

**Protect Files** is on by default. Confirm it is ticked in Gallery settings.

### 8.1 Private/members files are relocated
Upload a new image to a **private** folder, then in wp-admin → Media open that image and copy its File URL (or read `_wp_attached_file`).

- Expected: the path is under `wp-content/uploads/hp-agl-protected/...`. On the server, the file physically lives there, and `uploads/hp-agl-protected/.htaccess` exists.
- Result: `______`

### 8.2 Direct URL is blocked
Take a private/members image's real file path under `hp-agl-protected/` and request it directly in a logged-out browser (Apache), and also open the image's proxy link (`/gallery-file/{id}`) logged out.

- Expected: on Apache the direct `hp-agl-protected/...` URL is forbidden; the proxy link returns 401/403 for a logged-out visitor with no access. (On Nginx, add the deny rule from the readme; the proxy still 401/403s regardless.)
- Result: `______`

### 8.3 Authorised access works
As the folder owner (and as an admin), view the folder edit page and the gallery.

- Expected: thumbnails and full images load (served via the proxy). A member with the right plan can view a members-only folder's images; a logged-out visitor sees only blurred teasers/placeholders, never a working original URL (check page source).
- Result: `______`

### 8.4 Visibility change moves files
Change a protected folder from Members only to **Public**, save.

- Expected: its files move back out of `hp-agl-protected/` to the normal uploads path, and the images now load from direct URLs (no proxy). Switch back to Private and confirm they return to the protected location.
- Result: `______`

### 8.5 Video seeking
In a members-only folder (as an authorised viewer), play a video and seek.

- Expected: the video streams and seeks correctly (the proxy supports byte ranges).
- Result: `______`

### 8.6 Public images stay direct (SEO)
View a **public** folder's image URL.

- Expected: a normal `wp-content/uploads/...` URL, directly served (not the proxy).
- Result: `______`

### 8.7 Attachment page + Media API
Open a members-only image's attachment page logged out, and `/wp-json/wp/v2/media?per_page=100` logged out.

- Expected: the attachment page redirects away; gallery-folder images are absent from the media API results.
- Result: `______`

### 8.8 Toggle off
Turn Protect Files off and save.

- Expected: protected files move back to normal locations and load directly again (honest-limits mode). Turn it back on afterwards.
- Result: `______`

---

## Phase 9 — wp-admin

### 9.1 Folder screen
wp-admin → Gallery Folders → edit a folder.

- Expected: an Images meta box with the drag-drop manager showing existing images, and a Settings box with a searchable Vendor dropdown and Visibility select; both save correctly.
- Result: `______`

### 9.2 List columns
The Gallery Folders list table.

- Expected: Vendor, Visibility and Images columns are populated.
- Result: `______`

---

## Phase 10 — Odds and ends

### 10.1 Last-updated accuracy
Upload a new image to any listed folder, then view the public gallery.

- Expected: "Updated a few seconds ago" (or similar).
- Result: `______`

### 10.2 Image edit regenerates the blur
Edit (crop/rotate) an image in a blurred members folder via wp-admin Media, then view the locked gallery again.

- Expected: the blurred tile reflects the edit (the cached preview regenerated).
- Result: `______`

### 10.3 Non-Latin description
Save a description in another script (e.g. 50 Chinese characters or emoji), reload.

- Expected: saves and displays intact.
- Result: `______`

### 10.4 Asset scope
On an unrelated page (e.g. the blog), view source.

- Expected: no `hivepress-gallery-frontend` CSS/JS enqueued there; present on gallery, vendor and listing pages.
- Result: `______`

### 10.5 Caching layer
With FlyingPress active, repeat 4.2 and 6.1 on cached loads.

- Expected: identical behaviour; locked content stays locked on cached pages for logged-out visitors.
- Result: `______`

---

## Phase 11 — AI moderation (optional)

Needs an OpenAI API key (free Moderation endpoint, but an API account is required) and a publicly reachable site; on localhost the check fails open by design, so 11.3 will pass trivially there.

### 11.1 Shared key field
HivePress → Settings → Integrations.

- Expected: an OpenAI section with a single API Key field. If Automated Listing Moderation is also active, the field still appears exactly once.
- Result: `______`

### 11.2 Clean save
Enter the key, enable AI Moderation in Gallery settings, save a folder of ordinary photos.

- Expected: saves normally; one request to `api.openai.com` visible in any server-side HTTP log, none from the browser.
- Result: `______`

### 11.3 Unavailable service
Break the key (add a typo), save the folder again.

- Expected: the save still succeeds; the check fails open rather than blocking.
- Result: `______`

### 11.4 Flagged content
With a valid key, add a test image that the endpoint flags (OpenAI's own docs use a violent news photo for this), then save the folder.

- Expected: the save is rejected with "One or more of your photos appears to contain inappropriate content. Please replace or remove it and try again."; removing the image lets the save through.
- Result: `______`
- If it fails: the Network tab response for the folder update request: `______`

### 11.5 Setting off
Disable AI Moderation, repeat 11.4.

- Expected: no OpenAI request; the save succeeds.
- Result: `______`

---

## Phase 12 — Image optimization and weight

Settings live in HivePress → Settings → Vendors → Gallery.

### 12.1 Maximum file size
Set **Maximum File Size** to 1 MB, then try uploading a larger image to a folder.

- Expected: the upload is rejected with "Each file must be smaller than 1 MB."; a smaller image uploads fine.
- Result: `______`

### 12.2 Allowed formats
Set **Allowed Image Formats** to JPG only, then try uploading a PNG.

- Expected: the PNG is rejected; JPG uploads work. Clear the setting to allow all again.
- Result: `______`

### 12.3 Resize on upload
Set **Maximum Image Dimensions** to 1200, upload a larger (e.g. 4000px) image, then check it in wp-admin → Media.

- Expected: the stored original is no larger than 1200px on its longest side.
- Result: `______`

### 12.4 Quality + strip metadata
Set **Image Quality** to 60 and tick **Strip metadata**, upload a large JPG with EXIF (GPS/camera).

- Expected: the uploaded file is noticeably smaller than the source; its EXIF/GPS is gone (check with an EXIF viewer). Nothing looks broken.
- Result: `______`

### 12.5 Convert to WebP
Tick **Convert to WebP** (WebP must be an allowed format and supported by the server), upload a JPG.

- Expected: the stored attachment is a `.webp` file; it displays correctly in the gallery and lightbox. If the server lacks WebP support, the JPG is kept unchanged (no error).
- Result: `______`

### 12.6 Gallery weight
View the account Gallery page and the wp-admin Gallery Folders list.

- Expected: the account page shows a "Gallery size: …" line and each folder row shows its size; the admin Images column shows the folder size in brackets.
- Result: `______`

### 12.7 Bulk optimize + restore
Tick **Keep Originals**. In wp-admin → Gallery Folders, select a folder with images and run the **Optimize images** bulk action, then run **Restore original images**.

- Expected: "N images optimized." notice; folder size drops; images still display. After restore: "N images restored." notice; images return to their pre-optimization files.
- Result: `______`

---

## Anything else

Anything odd, slow, ugly, or confusing that the checks above didn't ask about:

`______`
