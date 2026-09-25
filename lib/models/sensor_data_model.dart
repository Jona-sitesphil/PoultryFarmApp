class SensorData {
  const SensorData({
    this.id = '',
    required this.temperature,
    required this.humidity,
    this.heatIndex,
    this.status = 'NORMAL',
    this.fanStatus = 'OFF',
    this.bulbStatus = 'OFF',
    required this.readingTime,
  });

  final String id;
  final double temperature;
  final double humidity;
  final double? heatIndex;
  final String status;
  final String fanStatus;
  final String bulbStatus;
  final DateTime readingTime;

  factory SensorData.fromMap(String id, Map<String, dynamic> data) {
    return SensorData(
      id: id,
      temperature: _asDouble(data['temperature']),
      humidity: _asDouble(data['humidity']),
      heatIndex: data['heat_index'] == null
          ? null
          : _asDouble(data['heat_index']),
      status: (data['status'] as String? ?? 'NORMAL').toUpperCase(),
      fanStatus: (data['fan_status'] as String? ?? 'OFF').toUpperCase(),
      bulbStatus: (data['bulb_status'] as String? ?? 'OFF').toUpperCase(),
      readingTime: _asDateTime(data['reading_time']),
    );
  }

  Map<String, dynamic> toMap() => <String, dynamic>{
    'temperature': temperature,
    'humidity': humidity,
    'heat_index': heatIndex,
    'status': status,
    'fan_status': fanStatus,
    'bulb_status': bulbStatus,
    'reading_time': readingTime.toIso8601String(),
  };

  static double _asDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse('$value') ?? 0;
  }

  static DateTime _asDateTime(dynamic value) {
    if (value is DateTime) return value;
    if (value is num) return DateTime.fromMillisecondsSinceEpoch(value.toInt());
    if (value is String) return DateTime.tryParse(value) ?? DateTime.now();
    return DateTime.now();
  }
}
