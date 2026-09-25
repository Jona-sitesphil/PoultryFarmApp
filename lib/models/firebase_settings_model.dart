class FirebaseSettings {
  const FirebaseSettings({
    this.farmName = 'Bolbok Poultry Farm - House 1',
    this.description =
        'Main poultry house for Quail monitoring and environmental control.',
    this.timezone = '(GMT+08:00) Asia/Manila',
    this.dateFormat = 'MM/DD/YYYY',
    this.language = 'English',
    this.autoRefresh = true,
    this.dataLogging = true,
    this.unitSystem = 'Metric (°C, %, ppm)',
    this.theme = 'Light',
    this.dashboardView = 'Overview',
    this.confirmAction = true,
    this.tempUnit = '°C',
    this.humidityUnit = '%',
    this.ammoniaUnit = 'ppm',
    this.decimalPlaces = 1,
    this.chartDataPoints = '24 Hours',
  });

  final String farmName;
  final String description;
  final String timezone;
  final String dateFormat;
  final String language;
  final bool autoRefresh;
  final bool dataLogging;
  final String unitSystem;
  final String theme;
  final String dashboardView;
  final bool confirmAction;
  final String tempUnit;
  final String humidityUnit;
  final String ammoniaUnit;
  final int decimalPlaces;
  final String chartDataPoints;

  factory FirebaseSettings.fromMap(Map<String, dynamic> data) {
    return FirebaseSettings(
      farmName: data['farm_name'] as String? ?? 'Bolbok Poultry Farm - House 1',
      description: data['description'] as String? ?? '',
      timezone: data['timezone'] as String? ?? '(GMT+08:00) Asia/Manila',
      dateFormat: data['date_format'] as String? ?? 'MM/DD/YYYY',
      language: data['language'] as String? ?? 'English',
      autoRefresh: _asBool(data['auto_refresh'], true),
      dataLogging: _asBool(data['data_logging'], true),
      unitSystem: data['unit_system'] as String? ?? 'Metric (°C, %, ppm)',
      theme: data['theme'] as String? ?? 'Light',
      dashboardView: data['dashboard_view'] as String? ?? 'Overview',
      confirmAction: _asBool(data['confirm_action'], true),
      tempUnit: data['temp_unit'] as String? ?? '°C',
      humidityUnit: data['humidity_unit'] as String? ?? '%',
      ammoniaUnit: data['ammonia_unit'] as String? ?? 'ppm',
      decimalPlaces: (data['decimal_places'] as num?)?.toInt() ?? 1,
      chartDataPoints: data['chart_data_points'] as String? ?? '24 Hours',
    );
  }

  Map<String, dynamic> toMap() => <String, dynamic>{
    'farm_name': farmName,
    'description': description,
    'timezone': timezone,
    'date_format': dateFormat,
    'language': language,
    'auto_refresh': autoRefresh,
    'data_logging': dataLogging,
    'unit_system': unitSystem,
    'theme': theme,
    'dashboard_view': dashboardView,
    'confirm_action': confirmAction,
    'temp_unit': tempUnit,
    'humidity_unit': humidityUnit,
    'ammonia_unit': ammoniaUnit,
    'decimal_places': decimalPlaces,
    'chart_data_points': chartDataPoints,
  };

  FirebaseSettings copyWith({
    String? farmName,
    String? description,
    String? timezone,
    String? dateFormat,
    String? language,
    bool? autoRefresh,
    bool? dataLogging,
    String? unitSystem,
    String? theme,
    String? dashboardView,
    bool? confirmAction,
    String? tempUnit,
    String? humidityUnit,
    String? ammoniaUnit,
    int? decimalPlaces,
    String? chartDataPoints,
  }) {
    return FirebaseSettings(
      farmName: farmName ?? this.farmName,
      description: description ?? this.description,
      timezone: timezone ?? this.timezone,
      dateFormat: dateFormat ?? this.dateFormat,
      language: language ?? this.language,
      autoRefresh: autoRefresh ?? this.autoRefresh,
      dataLogging: dataLogging ?? this.dataLogging,
      unitSystem: unitSystem ?? this.unitSystem,
      theme: theme ?? this.theme,
      dashboardView: dashboardView ?? this.dashboardView,
      confirmAction: confirmAction ?? this.confirmAction,
      tempUnit: tempUnit ?? this.tempUnit,
      humidityUnit: humidityUnit ?? this.humidityUnit,
      ammoniaUnit: ammoniaUnit ?? this.ammoniaUnit,
      decimalPlaces: decimalPlaces ?? this.decimalPlaces,
      chartDataPoints: chartDataPoints ?? this.chartDataPoints,
    );
  }

  static bool _asBool(dynamic value, bool fallback) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    if (value == null) return fallback;
    return !['0', 'false', 'off', 'no'].contains('$value'.toLowerCase());
  }
}

class SystemSettings {
  const SystemSettings({
    this.systemEnabled = false,
    this.systemMode = 'automatic',
    this.maxTemp = 29,
    this.maxHumidity = 100,
    this.minTemp = 27.5,
    this.exhaustFan = false,
    this.heater = false,
    this.coolingMist = false,
    this.emailNotifications = true,
  });

  final bool systemEnabled;
  final String systemMode;
  final double maxTemp;
  final double maxHumidity;
  final double minTemp;
  final bool exhaustFan;
  final bool heater;
  final bool coolingMist;
  final bool emailNotifications;

  factory SystemSettings.fromMap(Map<String, dynamic> data) {
    double number(String key, double fallback) {
      final value = data[key];
      if (value is num) return value.toDouble();
      return double.tryParse('$value') ?? fallback;
    }

    return SystemSettings(
      systemEnabled: FirebaseSettings._asBool(
        data['system_enabled'],
        false,
      ),
      systemMode: data['system_mode'] as String? ?? 'automatic',
      maxTemp: number('max_temp', 29),
      maxHumidity: number('max_hum', 100),
      minTemp: number('min_temp', 27.5),
      exhaustFan: FirebaseSettings._asBool(data['exhaust_fan'], false),
      heater: FirebaseSettings._asBool(data['heater'], false),
      coolingMist: FirebaseSettings._asBool(data['cooling_mist'], false),
      emailNotifications: FirebaseSettings._asBool(
        data['email_notifications'],
        true,
      ),
    );
  }

  Map<String, dynamic> toMap() => <String, dynamic>{
    'system_enabled': systemEnabled,
    'system_mode': systemMode,
    'max_temp': maxTemp,
    'max_hum': maxHumidity,
    'min_temp': minTemp,
    'exhaust_fan': exhaustFan,
    'heater': heater,
    'cooling_mist': coolingMist,
    'email_notifications': emailNotifications,
  };

  SystemSettings copyWith({
    bool? systemEnabled,
    String? systemMode,
    double? maxTemp,
    double? maxHumidity,
    double? minTemp,
    bool? exhaustFan,
    bool? heater,
    bool? coolingMist,
    bool? emailNotifications,
  }) {
    return SystemSettings(
      systemEnabled: systemEnabled ?? this.systemEnabled,
      systemMode: systemMode ?? this.systemMode,
      maxTemp: maxTemp ?? this.maxTemp,
      maxHumidity: maxHumidity ?? this.maxHumidity,
      minTemp: minTemp ?? this.minTemp,
      exhaustFan: exhaustFan ?? this.exhaustFan,
      heater: heater ?? this.heater,
      coolingMist: coolingMist ?? this.coolingMist,
      emailNotifications: emailNotifications ?? this.emailNotifications,
    );
  }
}
