import '../models/alert_model.dart';
import '../models/firebase_settings_model.dart';
import '../models/report_model.dart';
import '../models/sensor_data_model.dart';
import 'firebase_service.dart';

/// Realtime Database data access for the mobile app.
///
/// The paths intentionally match the tables in
/// `assets/images/thesis_poultry_system_db.sql`. Collection-like paths use a
/// Firebase child key for each SQL row, while settings are stored under their
/// named singleton paths.
class DataService {
  DataService._();

  static final DataService instance = DataService._();

  static const String sensorReadingsCollection = 'sensor_readings';
  static const String alertsCollection = 'alerts';
  static const String reportsCollection = 'report_history';
  static const String settingsCollection = 'settings';
  static const String systemSettingsCollection = 'system_settings';

  bool get isConnected => FirebaseService.isConfigured;

  Stream<SensorData?> latestReading() {
    if (!isConnected) return Stream.value(_fallbackReadings.first);

    return FirebaseService.database
        .ref(sensorReadingsCollection)
        .orderByChild('reading_time')
        .limitToLast(1)
        .onValue
        .map((event) {
          final readings = _sensorReadings(event.snapshot.value);
          return readings.isEmpty ? null : readings.first;
        });
  }

  Stream<List<SensorData>> sensorReadings({int limit = 24}) {
    if (!isConnected) {
      return Stream.value(_fallbackReadings.take(limit).toList());
    }

    return FirebaseService.database
        .ref(sensorReadingsCollection)
        .orderByChild('reading_time')
        .limitToLast(limit)
        .onValue
        .map((event) => _sensorReadings(event.snapshot.value));
  }

  Stream<List<AlertModel>> alerts({int limit = 50}) {
    if (!isConnected) return Stream.value(_fallbackAlerts);

    return FirebaseService.database
        .ref(alertsCollection)
        .orderByChild('created_at')
        .limitToLast(limit)
        .onValue
        .map((event) => _alerts(event.snapshot.value));
  }

  Stream<FirebaseSettings> settings() {
    if (!isConnected) return Stream.value(const FirebaseSettings());

    return FirebaseService.database
        .ref('$settingsCollection/general')
        .onValue
        .map(
          (event) => FirebaseSettings.fromMap(_valueMap(event.snapshot.value)),
        );
  }

  Stream<SystemSettings> systemSettings() {
    if (!isConnected) return Stream.value(const SystemSettings());

    return FirebaseService.database
        .ref('$systemSettingsCollection/control')
        .onValue
        .map(
          (event) => SystemSettings.fromMap(_valueMap(event.snapshot.value)),
        );
  }

  Stream<List<ReportModel>> reports({int limit = 50}) {
    if (!isConnected) return const Stream<List<ReportModel>>.empty();

    return FirebaseService.database
        .ref(reportsCollection)
        .orderByChild('generated_on')
        .limitToLast(limit)
        .onValue
        .map((event) => _reports(event.snapshot.value));
  }

  Future<void> updateDevice(String device, bool enabled) async {
    _requireFirebase('update device state');
    await FirebaseService.database
        .ref('$systemSettingsCollection/control')
        .update(<String, dynamic>{device: enabled});
  }

  Future<void> updateSystemEnabled(bool enabled) async {
    _requireFirebase('update system power');
    final updates = <String, dynamic>{'system_enabled': enabled};
    if (!enabled) {
      updates['exhaust_fan'] = false;
      updates['heater'] = false;
      updates['cooling_mist'] = false;
    }
    await FirebaseService.database
        .ref('$systemSettingsCollection/control')
        .update(updates);
  }

  Future<void> updateSystemMode(String mode) async {
    _requireFirebase('update system mode');
    await FirebaseService.database
        .ref('$systemSettingsCollection/control')
        .update(<String, dynamic>{'system_mode': mode});
  }

  Future<void> saveSettings(FirebaseSettings settings) async {
    _requireFirebase('save settings');
    await FirebaseService.database
        .ref('$settingsCollection/general')
        .update(settings.toMap());
  }

  Future<void> saveSystemSettings(SystemSettings settings) async {
    _requireFirebase('save system settings');
    await FirebaseService.database
        .ref('$systemSettingsCollection/control')
        .update(settings.toMap());
  }

  Future<void> updateThresholds({
    required double minTemp,
    required double maxTemp,
    required double maxHumidity,
  }) async {
    _requireFirebase('save environmental thresholds');
    await FirebaseService.database
        .ref('$systemSettingsCollection/control')
        .update(<String, dynamic>{
          'min_temp': minTemp,
          'max_temp': maxTemp,
          'max_hum': maxHumidity,
        });
  }

  Future<void> markAllAlertsRead(List<AlertModel> currentAlerts) async {
    _requireFirebase('mark alerts as read');
    final updates = <String, dynamic>{};
    for (final alert in currentAlerts) {
      if (alert.id.isEmpty || alert.isRead) continue;
      updates['${alert.id}/is_read'] = true;
    }
    if (updates.isNotEmpty) {
      await FirebaseService.database.ref(alertsCollection).update(updates);
    }
  }

  void _requireFirebase(String action) {
    if (!isConnected) {
      throw StateError(
        'Cannot $action until Firebase is configured. '
        'Check the Firebase project configuration and rebuild the app.',
      );
    }
  }

  static List<SensorData> _sensorReadings(dynamic value) {
    final readings = _children(value).entries
        .map((entry) => SensorData.fromMap(entry.key, entry.value))
        .toList();
    readings.sort((a, b) => b.readingTime.compareTo(a.readingTime));
    return readings;
  }

  static List<AlertModel> _alerts(dynamic value) {
    final alerts = _children(value).entries
        .map((entry) => AlertModel.fromMap(entry.key, entry.value))
        .toList();
    alerts.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return alerts;
  }

  static List<ReportModel> _reports(dynamic value) {
    final reports = _children(value).entries
        .map((entry) => ReportModel.fromMap(entry.key, entry.value))
        .toList();
    reports.sort((a, b) => b.generatedOn.compareTo(a.generatedOn));
    return reports;
  }

  static Map<String, dynamic> _valueMap(dynamic value) {
    if (value is! Map) return <String, dynamic>{};
    return value.map<String, dynamic>((key, child) => MapEntry('$key', child));
  }

  static Map<String, Map<String, dynamic>> _children(dynamic value) {
    final raw = _valueMap(value);
    return raw.map((key, child) => MapEntry(key, _valueMap(child)));
  }

  static final List<SensorData> _fallbackReadings = List<SensorData>.generate(
    12,
    (index) => SensorData(
      id: 'local-$index',
      temperature: 29.8 + (index % 3) * 0.2,
      humidity: 71 + (index % 4),
      heatIndex: 30.5,
      status: 'NORMAL',
      fanStatus: index.isEven ? 'OFF' : 'ON',
      bulbStatus: 'OFF',
      readingTime: DateTime.now().subtract(Duration(minutes: index * 5)),
    ),
  );

  static final List<AlertModel> _fallbackAlerts = <AlertModel>[
    AlertModel(
      id: 'local-alert-1',
      alertType: 'info',
      title: 'System Operating Normally',
      description: 'Temperature and humidity are within safe limits.',
      location: 'House 1 - Zone A',
      sensorName: 'Environmental Sensor (ENV-01)',
      createdAt: DateTime.now().subtract(const Duration(minutes: 8)),
    ),
    AlertModel(
      id: 'local-alert-2',
      alertType: 'warning',
      title: 'Temperature Warning',
      description: 'Temperature is approaching the configured limit.',
      location: 'House 1 - Zone A',
      sensorName: 'Temperature Sensor (TEMP-01)',
      createdAt: DateTime.now().subtract(const Duration(hours: 1)),
    ),
  ];
}
