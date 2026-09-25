import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../models/sensor_data_model.dart';
import '../../services/data_service.dart';
import '../../utils/app_colors.dart';

class ReportsScreen extends StatelessWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<SensorData>>(
      stream: DataService.instance.sensorReadings(limit: 50),
      builder: (context, snapshot) {
        final readings = snapshot.data ?? const <SensorData>[];
        final temps = readings.map((r) => r.temperature).toList();
        final humidity = readings.map((r) => r.humidity).toList();
        final avgTemp = _average(temps);
        final avgHumidity = _average(humidity);
        return SingleChildScrollView(
          padding: EdgeInsets.all(
            MediaQuery.sizeOf(context).width < 600 ? 16 : 24,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _header(context, readings),
              const SizedBox(height: 24),
              LayoutBuilder(
                builder: (context, constraints) {
                  final columns = constraints.maxWidth < 360
                      ? 1
                      : constraints.maxWidth < 700
                      ? 2
                      : 4;
                  return GridView.count(
                    crossAxisCount: columns,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    childAspectRatio: 1.7,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _stat(
                        'Average Temperature',
                        '${avgTemp.toStringAsFixed(1)} °C',
                        Icons.thermostat,
                        Colors.red,
                      ),
                      _stat(
                        'Average Humidity',
                        '${avgHumidity.toStringAsFixed(1)} %',
                        Icons.water_drop,
                        Colors.blue,
                      ),
                      _stat(
                        'Readings',
                        '${readings.length}',
                        Icons.sensors,
                        AppColors.primaryGreen,
                      ),
                      _stat(
                        'Fan ON',
                        '${readings.where((r) => r.fanStatus == 'ON').length}',
                        Icons.air,
                        Colors.orange,
                      ),
                    ],
                  );
                },
              ),
              const SizedBox(height: 24),
              _summary(readings, temps, humidity),
            ],
          ),
        );
      },
    );
  }

  Widget _header(BuildContext context, List<SensorData> readings) {
    const title = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Reports & Analytics',
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        Text(
          'Summary from the latest Realtime Database sensor readings',
          style: TextStyle(color: AppColors.textSecondary),
        ),
      ],
    );
    final action = ElevatedButton.icon(
      onPressed: () => _copyReport(context, readings),
      icon: const Icon(Icons.copy, color: Colors.white),
      label: const Text('Copy report', style: TextStyle(color: Colors.white)),
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.primaryGreen,
        minimumSize: const Size(0, 44),
      ),
    );
    if (MediaQuery.sizeOf(context).width < 560) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          title,
          const SizedBox(height: 12),
          SizedBox(width: double.infinity, child: action),
        ],
      );
    }
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [title, action],
    );
  }

  Future<void> _copyReport(
    BuildContext context,
    List<SensorData> readings,
  ) async {
    final buffer = StringBuffer(
      'reading_time,temperature,humidity,fan_status\n',
    );
    for (final reading in readings) {
      buffer.writeln(
        '${reading.readingTime.toIso8601String()},${reading.temperature},${reading.humidity},${reading.fanStatus}',
      );
    }
    await Clipboard.setData(ClipboardData(text: buffer.toString()));
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Report copied to the clipboard.')),
      );
    }
  }

  Widget _stat(String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 10),
          Flexible(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 21,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _summary(
    List<SensorData> readings,
    List<double> temps,
    List<double> humidity,
  ) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Data Summary',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final fanRuntime = readings.isEmpty
                  ? '--'
                  : '${(readings.where((r) => r.fanStatus == 'ON').length / readings.length * 100).toStringAsFixed(1)}%';
              if (constraints.maxWidth < 520) {
                return Column(
                  children: [
                    _mobileSummaryRow(
                      'Temperature (°C)',
                      _minimum(temps),
                      _maximum(temps),
                      _average(temps).toStringAsFixed(1),
                    ),
                    _mobileSummaryRow(
                      'Humidity (%)',
                      _minimum(humidity),
                      _maximum(humidity),
                      _average(humidity).toStringAsFixed(1),
                    ),
                    _mobileSummaryRow('Fan runtime', '--', '--', fanRuntime),
                  ],
                );
              }
              return Column(
                children: [
                  _row('Parameter', 'Min', 'Max', 'Average', header: true),
                  const Divider(),
                  _row(
                    'Temperature (°C)',
                    _minimum(temps),
                    _maximum(temps),
                    _average(temps).toStringAsFixed(1),
                  ),
                  _row(
                    'Humidity (%)',
                    _minimum(humidity),
                    _maximum(humidity),
                    _average(humidity).toStringAsFixed(1),
                  ),
                  _row('Fan runtime', '--', '--', fanRuntime),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _mobileSummaryRow(
    String parameter,
    String min,
    String max,
    String average,
  ) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              parameter,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
          _summaryValue('Min', min),
          _summaryValue('Max', max),
          _summaryValue('Avg', average),
        ],
      ),
    );
  }

  Widget _summaryValue(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(left: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 10,
              color: AppColors.textSecondary,
            ),
          ),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _row(
    String parameter,
    String min,
    String max,
    String average, {
    bool header = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(
            flex: 2,
            child: Text(
              parameter,
              style: TextStyle(
                fontWeight: header ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ),
          Expanded(
            child: Text(
              min,
              style: TextStyle(
                fontWeight: header ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ),
          Expanded(
            child: Text(
              max,
              style: TextStyle(
                fontWeight: header ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ),
          Expanded(
            child: Text(
              average,
              style: TextStyle(
                fontWeight: header ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ),
        ],
      ),
    );
  }

  double _average(List<double> values) =>
      values.isEmpty ? 0 : values.reduce((a, b) => a + b) / values.length;

  String _minimum(List<double> values) => values.isEmpty
      ? '--'
      : values.reduce((a, b) => a < b ? a : b).toStringAsFixed(1);

  String _maximum(List<double> values) => values.isEmpty
      ? '--'
      : values.reduce((a, b) => a > b ? a : b).toStringAsFixed(1);
}
