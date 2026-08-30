# Additional Gallery for HivePress — Manual Test Walkthrough (v1.2.0)

Work through the phases in order; later phases assume earlier ones passed. For each check, fill in the **Result** line. If something fails, paste whatever you have: the on-screen error, the browser dev console (F12 → Console), the Network tab response for REST calls, or the PHP error log line.

Static analysis (PHPStan level 9, WPCS, two taint analysers) and 51 isolated tests are already green; this walkthrough covers the one thing those cannot: behaviour on a real WordPress install.

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

Needs the Memberships extension. Configure Vendor Access Plans and Viewer Access Plans in Gallery settings, and make one folder Members only.

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

### 6.6 Vendor without a Manage plan
Log in as a vendor whose membership lacks the gallery feature.

- Expected: no Gallery item in their account menu; `/account/gallery/` redirects; their public gallery URL redirects home; no "View Gallery" button on their profile.
- Result: `______`

### 6.7 Fail closed
With plans configured, deactivate the Memberships extension entirely, then view a vendor gallery.

- Expected: galleries behave as locked-down (redirect home), never as suddenly free.
- Result: `______`

Reactivate Memberships afterwards.

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

## Phase 8 — Protect Files

Turn on **Protect Files**, then upload a new image to any folder.

### 8.1 Obscured names
Check the new file's URL (via the lightbox link).

- Expected: the filename ends with a random suffix (e.g. `photo-a1b2c3.jpg`).
- Result: `______`

### 8.2 Attachment page guard
Find the attachment page URL of an image in a members-only folder (wp-admin → Media → the file → View attachment page) and open it logged out.

- Expected: redirected away rather than shown.
- Result: `______`

### 8.3 Media API
Logged out, open `/wp-json/wp/v2/media?per_page=100`.

- Expected: gallery-folder images are absent from the results.
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

## Anything else

Anything odd, slow, ugly, or confusing that the checks above didn't ask about:

`______`
