# Firebase Realtime Database reference for the Flutter Android app

The Flutter application is the repository root (`../..`). The old PHP files
in `webapp/` are kept as a legacy reference. The Flutter app uses Firebase
Realtime Database directly through `lib/services/data_service.dart`; it does
not use a login system.

## Realtime Database paths

The path names intentionally match the tables in
`assets/images/thesis_poultry_system_db.sql`:

| SQL table | Realtime Database path | Purpose |
| --- | --- | --- |
| `sensor_readings` | `sensor_readings/{readingId}` | Numeric sensor readings and equipment states |
| `alerts` | `alerts/{alertId}` | Critical, warning, and informational alerts |
| `report_history` | `report_history/{reportId}` | Generated report metadata |
| `settings` | `settings/general` | Farm and display preferences |
| `system_settings` | `system_settings/control` | Operating mode, thresholds, and device states |

The complete field mapping is in `schema.json`. Security rules are in
`database.rules.json` and are referenced by `firebase.json`.

The app expects dates as ISO-8601 strings. This keeps the same format for the
Flutter client and the Node.js migration script, and allows Realtime Database
queries to sort the date fields consistently.

## Configure the Android app

From the repository root:

```text
flutter pub get
flutter run
```

The Android Firebase client is already configured from
`android/app/google-services.json`. The Realtime Database URL is:

```text
https://thesispoultry-ccb04-default-rtdb.firebaseio.com
```

In the Firebase console, enable Realtime Database and deploy the rules in
`database.rules.json`, or paste the same rules into the Realtime Database Rules
tab. These no-login rules allow public reads and writes for the app's data
paths. They are appropriate for a demo/local deployment only; use Firebase
Authentication, App Check, or a trusted backend before exposing this database
to production users.

Deploy the rules from this folder with:

```text
firebase deploy --project thesispoultry-ccb04 --config firebase.json --only database
```

## Migrate the SQL dump

`migrate_sql_to_realtime_database.js` reads the existing MySQL database
represented by `assets/images/thesis_poultry_system_db.sql` and writes the
Realtime Database paths above. It requires Node.js, `firebase-admin`,
`mysql2`, and a Firebase service-account credential. Never commit that
credential.

```text
cd webapp/firebase
npm install
$env:GOOGLE_APPLICATION_CREDENTIALS = 'C:\path\to\service-account.json'
$env:FIREBASE_DATABASE_URL = 'https://thesispoultry-ccb04-default-rtdb.firebaseio.com'
$env:MYSQL_DATABASE = 'thesis_poultry_system_db'
node migrate_sql_to_realtime_database.js
```

The migration intentionally does not copy SQL password hashes or user
profiles because this app no longer has a login system.

## Connect the ESP32 firmware

The sketch in `../#include Wire.h.txt` uses the Firebase Realtime Database
REST API, so it does not need a separate Firebase Arduino library. Install or
enable these libraries in the Arduino IDE:

- ESP32 board support package (provides `WiFi`, `HTTPClient`,
  `WiFiClientSecure`, and `time`)
- `DHT sensor library`
- `LiquidCrystal_PCF8574`

Before uploading, set the Wi-Fi name and password at the top of the sketch.
The sketch uses these paths:

| Firmware action | Realtime Database path |
| --- | --- |
| Enable or disable the ESP32 hardware | `system_settings/control/system_enabled` |
| Read automatic/manual mode, thresholds, and remote equipment commands | `system_settings/control` |
| Send temperature, humidity, heat index, and relay states | `sensor_readings/{esp32-reading-id}` |
| Mirror the actual fan and heater state | `system_settings/control/exhaust_fan` and `system_settings/control/heater` |

The app's `system_enabled` switch is the master power control. When it is
`false` or Firebase is unreachable, the ESP32 turns both relays off. When it
is `true`, the firmware treats `system_mode: "manual"` as remote-control mode;
in `automatic` mode it applies the live `min_temp` and `max_temp` values from
`system_settings/control` (falling back to 27.5°C and 29°C if they are invalid).
The current no-login rules must be deployed for the sketch and the app to
access the database. Those public rules and the sketch's TLS `setInsecure()`
setting are suitable only for a protected demo network; use authenticated
rules and a root CA certificate for production.
