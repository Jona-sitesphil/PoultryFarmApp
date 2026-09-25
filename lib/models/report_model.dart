class ReportModel {
  const ReportModel({
    this.id = '',
    required this.reportName,
    required this.dateRange,
    required this.generatedOn,
    required this.generatedBy,
  });

  final String id;
  final String reportName;
  final String dateRange;
  final DateTime generatedOn;
  final String generatedBy;

  factory ReportModel.fromMap(String id, Map<String, dynamic> data) {
    return ReportModel(
      id: id,
      reportName: data['report_name'] as String? ?? 'Sensor report',
      dateRange: data['date_range'] as String? ?? 'Last 7 Days',
      generatedOn: _asDateTime(data['generated_on']),
      generatedBy: data['generated_by'] as String? ?? 'system',
    );
  }

  static DateTime _asDateTime(dynamic value) {
    if (value is DateTime) return value;
    if (value is num) return DateTime.fromMillisecondsSinceEpoch(value.toInt());
    if (value is String) return DateTime.tryParse(value) ?? DateTime.now();
    return DateTime.now();
  }
}
