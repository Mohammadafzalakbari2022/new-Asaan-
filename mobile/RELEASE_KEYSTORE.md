# Asaan Android release key

> **Private and sensitive.** This file is in the repository on purpose so the
> shop owner always has a copy. Do not share the repository, this file, or the
> passwords below with anyone who should not be able to publish the app.

The Android app is signed with one key. That key is what proves an update is
really yours. If it is lost, Google Play will not accept any further updates to
the app under the same listing — the app would have to be published again from
scratch. That is why the key is kept here and not only on the person's laptop.

## Files

| File | What it is |
|---|---|
| `mobile/android/app/asaan-release.jks` | The release signing key (a Java keystore). |
| `mobile/android/key.properties` | The passwords Gradle reads when building. |

## Passwords

Both the store password and the key password are the same:

```
Asaan#Release$2026!Vq7
```

- Key alias: `asaan`

## What the app is signed as

- Package name: `com.cartxis.app`
- Current version at the time of writing: `2.5.7` (version code `8`)

## How to build a signed release

From the `mobile` folder:

```bash
flutter build apk --release
# output: build/app/outputs/flutter-apk/app-release.apk
```

Gradle finds the key automatically through `mobile/android/key.properties`; no
extra step is needed. To publish a new version on the website, copy the built
APK into the store repository next to the other downloads:

```
public/downloads/asaan-<version>.apk
```

and update the file name in:

- `templates/storefront/general/cartxis-default/resources/views/components/AppDownloadBlock.vue`
- `mobile/lib/core/config/api_config.dart`

## Back this up somewhere safe

Keep an offline copy of `asaan-release.jks` and the password. If the repository
is ever deleted, leaked, or made private again, that offline copy is the only
way left to ship updates.
