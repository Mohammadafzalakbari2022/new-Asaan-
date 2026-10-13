# Building and releasing the Asaan mobile app

This guide covers how to build the Android app (on any machine) and the iOS app
(on a Mac only), and where to publish each one.

The app name on the phone is **Asaan**. The Android package id (also called
`applicationId`) is `com.cartxis.app`. The version is set in one place, the
`version:` line in `pubspec.yaml` — currently `2.5.6+7`, which means version
name `2.5.6` and build number `7`.

---

## Android

### 1. One-time setup on the build machine

The release build is signed with a keystore. Two files are involved:

- `android/app/asaan-release.jks` — the keystore itself. **Back this up.** If it
  is lost you cannot ship an update to an app already on the Play Store; you
  would have to publish a new app.
- `android/key.properties` — the passwords. This file is **git-ignored and is
  never committed**. Every machine that builds a release needs its own copy.

`android/key.properties` looks like this:

```properties
storePassword=YOUR_STORE_PASSWORD
keyPassword=YOUR_KEY_PASSWORD
keyAlias=asaan
storeFile=asaan-release.jks
```

- `storeFile` is resolved relative to `android/app/`, so `asaan-release.jks`
  means `android/app/asaan-release.jks`.
- The passwords live only in this file and in your password manager. Keep both
  copies safe. If `key.properties` is missing, the release build still runs but
  will not be signed correctly, so always check the file is present before a
  release build.

The Android keystore was created with:

```powershell
& "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe" -genkeypair `
  -v -keystore android/app/asaan-release.jks -alias asaan \
  -keyalg RSA -keysize 2048 -validity 10000
```

### 2. Build the release APK

From the `mobile/` folder:

```bash
flutter pub get
flutter build apk --release
```

The finished file is:

```
mobile/build/app/outputs/flutter-apk/app-release.apk
```

### 2b. (Optional) Build an App Bundle for Google Play

The Play Store prefers an Android App Bundle (`.aab`) rather than an APK:

```bash
flutter build appbundle --release
# output: build/app/outputs/bundle/release/app-release.aab
```

### 3. Publish the Android build

- **Direct download from the website:** copy the APK to
  `public/downloads/asaan-2.5.6.apk` in the store repository. The website links
  to `/downloads/asaan-2.5.6.apk`, so the download works as soon as the site is
  deployed. This is the current method.
- **GitHub Releases:** attach the APK to a release on the client repository
  (`Asaan2026/ASAAN`). Note: this needs write access to that repository.
- **Google Play:** upload the `.aab` in the Play Console once the app is
  registered. Then paste the Play Store URL into the dashboard under
  **Settings → Store Configuration → Mobile App Links → Google Play URL**. The
  website and the app will start using it automatically — no code change.

---

## iOS (Mac only)

iOS cannot be built on Windows. This must be done on a Mac with Xcode.

### 1. One-time setup

1. Install Flutter (`flutter doctor` should be happy) and Xcode.
2. You need an Apple Developer account (paid) to publish to the App Store.
3. Set the signing team once: open `ios/Runner.xcworkspace` in Xcode, select the
   **Runner** target → **Signing & Capabilities**, pick your Team, and let Xcode
   manage signing.

### 2. Build

```bash
flutter build ipa --release
# output: build/ios/ipa/*.ipa
```

### 3. Publish

Upload the `.ipa` with **Transporter** (or Xcode's Organizer) to App Store
Connect, then submit it for review through App Store Connect. Once it is live,
paste the App Store link into the dashboard under
**Settings → Store Configuration → Mobile App Links → App Store URL**.

Until an App Store URL is set, the website shows the iPhone button as
"coming soon" instead of a broken link.

---

## Notes

- Changing the version number: edit `version:` in `pubspec.yaml`, for example
  `2.5.7+8`. The Android `versionCode` / `versionName` and the iOS build number
  are both taken from that one line.
- The dashboard owns anything the app shows that can change over time: the
  WhatsApp number, the support phone and email, and the app store links. They
  are read from `GET /api/v1/storefront/contact`, so none of them are baked into
  the app.
