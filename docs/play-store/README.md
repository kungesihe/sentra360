# Publishing SENTRA 360 on Google Play

The app is a website. It is published on Google Play as a **Trusted Web Activity (TWA)**: a small Android app that opens the site full-screen, with the app icon, no browser address bar, and Android's location permission. Updates to the website appear in the app immediately, with no new Play release needed.

## What is already done
- ✅ Installable web app: `/manifest.webmanifest`, app icons (`public/icons`), offline page (`/offline.html`) and service worker (`/sw.js`). The service worker never caches account pages, and the offline page says plainly that alerts cannot be sent offline.
- ✅ HTTPS on Render.
- ✅ Digital Asset Links endpoint `/.well-known/assetlinks.json`, filled from two Render settings once the Android app exists (step 4).
- ✅ Account deletion page `/delete-account` (Google requires one for apps with sign-up).
- ✅ Privacy policy `/privacy`.
- ✅ Store graphics and 8 phone screenshots (this folder) and listing texts plus data-safety answers (`LISTING.md`).

## Before you start (decisions and accounts only you can make)
1. **Use your own domain first** (e.g. `app.yourcompany.co.za`, added in Render → Settings → Custom domains). The Android app is permanently tied to the web address it opens; moving from `onrender.com` later means re-linking. It also looks more trustworthy.
2. **Upgrade Render from Free to Starter (about US$7/month).** On the free plan the app sleeps and the first open takes ~50 seconds, which is not acceptable for a panic button, and Google's reviewers may reject a slow app.
3. **Google Play Console developer account:** https://play.google.com/console, one-off **US$25**, with identity verification.
   - An **organisation** account (in the company's name) needs a **D-U-N-S number** (free from Dun & Bradstreet, can take a few weeks).
   - A **personal** account created after November 2023 must first run a **closed test with at least 12 testers for 14 days** before it can publish to everyone.

## Step-by-step
1. **Set the real name and colours** in Admin → Branding (they become the app name and colours). To regenerate the icons and feature graphic for the brand:
   ```
   cd tools
   node make-assets.js "Your Company Name" "#0B3D91" "#111827"
   ```
2. **Generate the Android package** at https://www.pwabuilder.com:
   - Enter your site address, then **Package for stores → Android**.
   - Package ID: e.g. `za.co.yourcompany.sentra` (permanent; can't be changed later).
   - App name / launcher name: your company name. Display mode: standalone.
   - Turn **Location delegation ON** (so the panic button gets Android's GPS permission).
   - Signing key: **Create new**. Download the ZIP. It contains the `.aab` file, the **signing key and its passwords**, and an `assetlinks.json`.
   - ⚠️ **Store the signing key and passwords somewhere safe (two places).** Without them you can never update the app.
3. **Create the app in Play Console**: Create app → name, default language English (South Africa), App, Free.
4. **Link the app to the website** (makes it open full-screen without a browser bar). In Render → your service → Environment, add:
   - `TWA_PACKAGE_NAME` = the package ID from step 2
   - `TWA_SHA256_FINGERPRINTS` = the SHA-256 fingerprint from PWABuilder's `assetlinks.json`, **plus**, comma-separated, the "App signing key certificate" SHA-256 from Play Console → *Test and release → App integrity* (Google re-signs the app).
   - Save, redeploy, and check that `https://<your site>/.well-known/assetlinks.json` shows them.
5. **Store listing**: paste the texts from `LISTING.md`, then upload `play-icon-512.png`, `feature-graphic-1024x500.png` and the screenshots in `phone-screenshots/`.
6. **App content**: privacy policy URL, account deletion URL, Data safety form (answers in `LISTING.md`), content rating questionnaire, target audience **18+**, ads: **No**, and **App access** with a test login.
7. **Release**: upload the `.aab` to *Internal testing* first and install it on your own phone. Check sign-in, the panic button (allow location), photo upload and the full-screen view (no address bar = step 4 worked). Then *Closed testing* (12 testers × 14 days for personal accounts), then *Production*. Google's review usually takes from a few days to a week.

## Google policy points that matter for a safety app
- Don't claim the app guarantees help or replaces the police or ambulance. The listing text and the app already say it doesn't.
- Location is only requested when the user reports an incident or presses the panic button, never in the background. Don't add background location without a strong reason; Google demands a separate justification and a video.
- Keep the privacy policy accurate if you add SMS or email providers (they become data processors).
