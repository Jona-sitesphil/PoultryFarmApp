# Firebase Realtime Database checklist

## Completed in the codebase

- [x] Added `firebase_core` and `firebase_database`.
- [x] Removed the login and account-creation system.
- [x] Added Firebase initialization with an offline-safe fallback.
- [x] Connected the Android app to project `thesispoultry-ccb04`.
- [x] Added the Android client file at `android/app/google-services.json`.
- [x] Added Realtime Database models for sensor readings, alerts, reports, settings, and controls.
- [x] Connected dashboard, sensor monitoring, automated control, alerts, reports, and settings to Firebase.
- [x] Added no-login database rules under `webapp/firebase/database.rules.json`.
- [x] Added the database seed at `webapp/firebase/realtime_database_seed.json`.
- [x] Android debug APK builds successfully.
- [x] Flutter analysis and widget tests pass.

## Required in the Firebase console

- [ ] Enable Realtime Database for project `thesispoultry-ccb04`.
- [ ] Import `webapp/firebase/realtime_database_seed.json` into the Realtime Database.
- [ ] Deploy `webapp/firebase/database.rules.json`.

The no-login rules allow public reads and writes so the app can operate
without authentication. This is suitable for a classroom/demo deployment;
use Authentication, App Check, or a trusted backend before production use.
