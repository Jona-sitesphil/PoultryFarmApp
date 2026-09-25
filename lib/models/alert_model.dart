class AlertModel {
  const AlertModel({
    this.id = '',
    required this.alertType,
    required this.title,
    required this.description,
    required this.location,
    required this.sensorName,
    this.icon = 'warning',
    required this.createdAt,
    this.isRead = false,
  });

  final String id;
  final String alertType;
  final String title;
  final String description;
  final String location;
  final String sensorName;
  final String icon;
  final DateTime createdAt;
  final bool isRead;

  factory AlertModel.fromMap(String id, Map<String, dynamic> data) {
    return AlertModel(
      id: id,
      alertType: data['alert_type'] as String? ?? 'info',
      title: data['title'] as String? ?? 'System alert',
      description: data['description'] as String? ?? '',
      location: data['location'] as String? ?? 'House 1 - Zone A',
      sensorName: data['sensor_name'] as String? ?? 'Environmental Sensor',
      icon: data['icon'] as String? ?? 'warning',
      createdAt: _asDateTime(data['created_at']),
      isRead: data['is_read'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toMap() => <String, dynamic>{
    'alert_type': alertType,
    'title': title,
    'description': description,
    'location': location,
    'sensor_name': sensorName,
    'icon': icon,
    'created_at': createdAt.toIso8601String(),
    'is_read': isRead,
  };

  static DateTime _asDateTime(dynamic value) {
    if (value is DateTime) return value;
    if (value is num) return DateTime.fromMillisecondsSinceEpoch(value.toInt());
    if (value is String) return DateTime.tryParse(value) ?? DateTime.now();
    return DateTime.now();
  }
}
