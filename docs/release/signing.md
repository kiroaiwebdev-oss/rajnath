# Android release signing

| Item | Value |
|---|---|
| Package | `com.astrorajnathjha.app` |
| Version | 1.0.0 (versionCode 1) |
| Target SDK | 36 · min SDK 24 |
| Keystore | PKCS12, RSA 4096, valid until 2054, alias `astrorajnathjha` |
| Certificate SHA-256 | `85:5D:CE:96:03:05:B1:3E:7E:AD:49:CE:BF:04:A7:6C:D0:8F:FC:3E:7B:8E:5A:8E:9C:DA:B9:93:0E:1C:4A:3D` |
| Certificate SHA-1 | `F4:FE:EC:40:E9:8B:02:F4:7F:3C:AE:8C:15:B3:6F:2A:31:E5:40:BC` |

The keystore and its password are **not** in Git. They are in the private build workspace at
`/projects/sandbox/.tools/signing/` (`arj-release.jks` and `store-password.txt`).

**Owner action (important):**
1. Download both files using the workspace file explorer.
2. Store them in at least two safe places, for example an encrypted USB drive and a password manager. **If this key is lost, the app can never be updated on Play under the same listing.**
3. When uploading to Google Play, use **Play App Signing**. This key then becomes your *upload key*, and Google can reset it if it is lost.

## Rebuilding a signed release
Create `mobile/android/key.properties` (it is git-ignored):
```
storePassword=<from store-password.txt>
keyPassword=<same>
keyAlias=astrorajnathjha
storeFile=/absolute/path/arj-release.jks
```
Then:
```
cd mobile
flutter build apk --release --dart-define=API_BASE=https://astrorajnathjha.com/api/v1
flutter build appbundle --release
```
A release build **fails** without `key.properties`. It never silently uses debug keys.

## Verification performed (28 Oct 2026)
- `apksigner verify`: v2 signature valid, 1 signer, DN `CN=Astro Rajnath Jha, O=Astro Rajnath Jha, L=Patna, ST=Bihar, C=IN`, SHA-256 as above.
- `zipalign -c -P 16 4`: OK.
- `jarsigner -verify` on the AAB: verified with the same certificate.
- `aapt2 dump badging`: package `com.astrorajnathjha.app`, versionName 1.0.0, targetSdk 36. The only permission requested is INTERNET (the second one is Android's internal receiver permission).
- SHA-256 of the artifacts:
  - `app-release.apk` `52fcc7c16527865b385a1ace11d28b286edd7984f0bd7cc72cc669cccac5b1fb`
  - `app-release.aab` `cb755a399518eb0c27a5b42f9353001e3592e8ce9504a5789b6aa1175e0daa45`
- **Not done:** installation on a physical phone (owner responsibility).
