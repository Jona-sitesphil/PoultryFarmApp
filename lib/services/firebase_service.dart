import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_database/firebase_database.dart';
import 'package:flutter/foundation.dart';

import '../firebase_options.dart';

/// Initializes Firebase once and exposes the Firebase services used by the app.
///
/// The app keeps a local fallback when Firebase is unavailable, but uses the
/// configured Realtime Database automatically when initialization succeeds.
class FirebaseService {
  FirebaseService._();

  static bool isConfigured = false;

  static FirebaseDatabase get database => FirebaseDatabase.instance;

  static Future<void> initialize() async {
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      }
      isConfigured = Firebase.apps.isNotEmpty;
    } on FirebaseException catch (error) {
      isConfigured = false;
      debugPrint(
        'Firebase is not configured yet (${error.code}). '
        'Check the Firebase project configuration before using live data.',
      );
    } catch (error) {
      isConfigured = false;
      debugPrint('Firebase initialization skipped: $error');
    }
  }
}
