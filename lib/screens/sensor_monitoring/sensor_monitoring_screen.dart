import 'package:flutter/material.dart';

import '../../models/sensor_data_model.dart';
import '../../services/data_service.dart';
import '../../utils/app_colors.dart';

class SensorMonitoringScreen extends StatelessWidget {
  const SensorMonitoringScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<SensorData>>(
      stream: DataService.instance.sensorReadings(),
      builder: (context, snapshot) {
        final readings = snapshot.data ?? const <SensorData>[];
        final latest = readings.isEmpty ? _defaultReading : readings.first;

        return LayoutBuilder(
          builder: (context, constraints) {
            final isMobile = constraints.maxWidth < 800;
            final isSmallPhone = constraints.maxWidth < 380;

            return SingleChildScrollView(
              padding: EdgeInsets.all(isMobile ? 16 : 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ============================================================
                  // HEADER
                  // ============================================================

                  _header(context),

                  const SizedBox(height: 24),

                  // ============================================================
                  // SENSOR CARDS
                  // ============================================================
                  GridView.count(
                    crossAxisCount: isSmallPhone
                        ? 1
                        : isMobile
                        ? 2
                        : 3,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),

                    // FIX:
                    // Lower aspect ratio = taller cards.
                    // This prevents BOTTOM OVERFLOW on small phones.
                    childAspectRatio: isSmallPhone
                        ? 2.0
                        : isMobile
                        ? 1.15
                        : 1.55,

                    children: [
                      _sensorCard(
                        'Temperature',
                        latest.temperature,
                        '°C',
                        latest.status,
                        Icons.thermostat,
                      ),

                      _sensorCard(
                        'Humidity',
                        latest.humidity,
                        '%',
                        latest.status,
                        Icons.water_drop,
                      ),

                      _sensorCard(
                        'Heat Index',
                        latest.heatIndex ?? 0,
                        '°C',
                        latest.status,
                        Icons.device_thermostat,
                      ),

                      _textSensorCard(
                        'Fan Status',
                        latest.fanStatus,
                        Icons.air,
                      ),

                      _textSensorCard(
                        'Bulb Status',
                        latest.bulbStatus,
                        Icons.lightbulb,
                      ),

                      _textSensorCard(
                        'Reading Status',
                        latest.status,
                        Icons.sensors,
                      ),
                    ],
                  ),

                  const SizedBox(height: 24),

                  // ============================================================
                  // RECENT READINGS
                  // ============================================================
                  _history(readings),
                ],
              ),
            );
          },
        );
      },
    );
  }

  // ===========================================================================
  // HEADER
  // ===========================================================================

  Widget _header(BuildContext context) {
    final compact = MediaQuery.sizeOf(context).width < 520;

    const title = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Sensor Monitoring',
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        SizedBox(height: 4),
        Text(
          'Real-time environmental data from House 1',
          style: TextStyle(color: AppColors.textSecondary),
        ),
      ],
    );

    const liveChip = Chip(
      avatar: Icon(Icons.sync, size: 16),
      label: Text('Live data'),
    );

    if (compact) {
      return const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [title, SizedBox(height: 10), liveChip],
      );
    }

    return const Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [title, liveChip],
    );
  }

  // ===========================================================================
  // NUMBER SENSOR CARD
  // ===========================================================================

  Widget _sensorCard(
    String title,
    double value,
    String unit,
    String status,
    IconData icon,
  ) {
    return _card(
      icon: icon,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          // Sensor name
          Text(
            title,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 14,
            ),
          ),

          const SizedBox(height: 6),

          // Sensor value
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                value.toStringAsFixed(1),
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  height: 1,
                ),
              ),

              const SizedBox(width: 4),

              Padding(
                padding: const EdgeInsets.only(bottom: 2),
                child: Text(
                  unit,
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          // Status
          _status(status),
        ],
      ),
    );
  }

  // ===========================================================================
  // TEXT SENSOR CARD
  // ===========================================================================

  Widget _textSensorCard(String title, String value, IconData icon) {
    return _card(
      icon: icon,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          // Sensor name
          Text(
            title,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 14,
            ),
          ),

          const SizedBox(height: 6),

          // Value
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              height: 1,
            ),
          ),

          const SizedBox(height: 8),

          // Status
          _status(
            value.toUpperCase() == 'NORMAL' || value.toUpperCase() == 'OFF'
                ? 'Normal'
                : value,
          ),
        ],
      ),
    );
  }

  // ===========================================================================
  // CARD DESIGN
  // ===========================================================================

  Widget _card({required IconData icon, required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Icon
          Icon(icon, color: AppColors.textSecondary, size: 20),

          const SizedBox(height: 8),

          // Card content
          Expanded(child: child),
        ],
      ),
    );
  }

  // ===========================================================================
  // STATUS
  // ===========================================================================

  Widget _status(String status) {
    final upperStatus = status.toUpperCase();

    final warning =
        upperStatus.contains('WARN') || upperStatus.contains('CRIT');

    return Text(
      status,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: warning ? Colors.orange : AppColors.primaryGreen,
        fontWeight: FontWeight.bold,
        fontSize: 13,
      ),
    );
  }

  // ===========================================================================
  // RECENT READINGS
  // ===========================================================================

  Widget _history(List<SensorData> readings) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Recent readings',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),

          const SizedBox(height: 12),

          if (readings.isEmpty)
            const Text(
              'No readings in Realtime Database yet.',
              style: TextStyle(color: AppColors.textSecondary),
            )
          else
            ...readings.take(8).map((reading) {
              return ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,

                leading: const Icon(Icons.sensors, size: 18),

                title: Text(
                  '${reading.temperature.toStringAsFixed(1)} °C'
                  '  ·  '
                  '${reading.humidity.toStringAsFixed(0)} %',
                ),

                subtitle: Text(reading.readingTime.toLocal().toString()),

                trailing: Text(reading.fanStatus),
              );
            }),
        ],
      ),
    );
  }

  // ===========================================================================
  // DEFAULT DATA
  // ===========================================================================

  static final SensorData _defaultReading = SensorData(
    temperature: 29.8,
    humidity: 71,
    heatIndex: 30.5,
    readingTime: DateTime(2026, 1, 1),
  );
}
