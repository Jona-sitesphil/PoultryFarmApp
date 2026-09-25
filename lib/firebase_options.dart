// Firebase configuration for the thesis poultry farm project.
//
// The Android values come from webapp/google-services.json. The shared
// project-level values are also used for the other Flutter targets because
// the supplied Firebase file contains an Android client only.
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';

class DefaultFirebaseOptions {
  const DefaultFirebaseOptions._();

  static const databaseUrl =
      'https://thesispoultry-ccb04-default-rtdb.firebaseio.com';
  static const apiKey = 'AIzaSyD0f8Sly7xjKF2T1pjORm29J7SLieLIOPU';
  static const appId = '1:1002857871925:android:fba22ddfd27e993e4f8f7c';
  static const messagingSenderId = '1002857871925';
  static const projectId = 'thesispoultry-ccb04';
  static const storageBucket = 'thesispoultry-ccb04.firebasestorage.app';
  static const authDomain = 'thesispoultry-ccb04.firebaseapp.com';

  static FirebaseOptions get currentPlatform {
    if (kIsWeb) return web;

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      case TargetPlatform.macOS:
        return macos;
      case TargetPlatform.windows:
        return windows;
      case TargetPlatform.linux:
        return windows;
      case TargetPlatform.fuchsia:
        return android;
    }
  }

  static const android = FirebaseOptions(
    apiKey: apiKey,
    appId: appId,
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    databaseURL: databaseUrl,
    storageBucket: storageBucket,
  );

  static const ios = FirebaseOptions(
    apiKey: apiKey,
    appId: appId,
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    databaseURL: databaseUrl,
    storageBucket: storageBucket,
    iosBundleId: 'com.thesis.poultryfarm',
  );

  static const macos = FirebaseOptions(
    apiKey: apiKey,
    appId: appId,
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    databaseURL: databaseUrl,
    storageBucket: storageBucket,
    iosBundleId: 'com.thesis.poultryfarm',
  );

  static const web = FirebaseOptions(
    apiKey: apiKey,
    appId: appId,
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    databaseURL: databaseUrl,
    authDomain: authDomain,
    storageBucket: storageBucket,
  );

  static const windows = FirebaseOptions(
    apiKey: apiKey,
    appId: appId,
    messagingSenderId: messagingSenderId,
    projectId: projectId,
    databaseURL: databaseUrl,
    storageBucket: storageBucket,
  );
}
