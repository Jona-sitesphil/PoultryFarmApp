import 'package:flutter/material.dart';

import '../../models/alert_model.dart';
import '../../models/firebase_settings_model.dart';
import '../../models/sensor_data_model.dart';
import '../../services/data_service.dart';
import '../../utils/app_colors.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<SensorData?>(
      stream: DataService.instance.latestReading(),
      builder: (context, sensorSnapshot) {
        final reading = sensorSnapshot.data ?? _defaultReading;
        return StreamBuilder<SystemSettings>(
          // The equipment state is read from system_settings/control in
          // Realtime Database, which is also updated by the control screen.
          stream: DataService.instance.systemSettings(),
          builder: (context, settingsSnapshot) {
            final settings = settingsSnapshot.data ?? const SystemSettings();
            return StreamBuilder<List<AlertModel>>(
              stream: DataService.instance.alerts(limit: 10),
              builder: (context, alertSnapshot) {
                final alerts = alertSnapshot.data ?? const <AlertModel>[];
                final critical = alerts
                    .where((a) => a.alertType == 'critical')
                    .length;
                return Scaffold(
                  backgroundColor: Colors.transparent,
                  body: SafeArea(
                    child: LayoutBuilder(
                      builder: (context, constraints) {
                        final isMobile = constraints.maxWidth < 800;
                        final isSmallPhone = constraints.maxWidth < 380;
                        return SingleChildScrollView(
                          padding: EdgeInsets.all(isMobile ? 16 : 28),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _header(isMobile, isSmallPhone),
                              const SizedBox(height: 24),
                              _cards(
                                reading,
                                settings,
                                critical,
                                constraints.maxWidth,
                              ),
                              const SizedBox(height: 16),
                              _environmentNotification(reading, settings),
                              const SizedBox(height: 24),
                              if (isMobile)
                                Column(
                                  children: [
                                    _quickControls(context, reading, settings),
                                    const SizedBox(height: 16),
                                    _activity(alerts),
                                  ],
                                )
                              else
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Expanded(
                                      flex: 2,
                                      child: _quickControls(
                                        context,
                                        reading,
                                        settings,
                                      ),
                                    ),
                                    const SizedBox(width: 24),
                                    Expanded(flex: 3, child: _activity(alerts)),
                                  ],
                                ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                );
              },
            );
          },
        );
      },
    );
  }

  Widget _header(bool isMobile, bool isSmallPhone) {
    final title = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Dashboard Overview',
          style: TextStyle(
            color: AppColors.textPrimary,
            fontSize: isMobile ? 22 : 28,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Live data from House 1',
          style: TextStyle(color: AppColors.textSecondary),
        ),
      ],
    );
    final live = Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.softGreen,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: AppColors.primaryGreen.withValues(alpha: .35),
        ),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.circle, color: AppColors.primaryGreen, size: 9),
          SizedBox(width: 7),
          Text('Live', style: TextStyle(color: AppColors.primaryGreen)),
        ],
      ),
    );
    if (isSmallPhone) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [title, const SizedBox(height: 12), live],
      );
    }
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [title, live],
    );
  }

  Widget _cards(
    SensorData reading,
    SystemSettings settings,
    int critical,
    double width,
  ) {
    final isMobile = width < 800;
    final isSmallPhone = width < 380;
    final cards = [
      _MetricCard(
        'System Power',
        settings.systemEnabled ? 'ON' : 'OFF',
        'ESP32 master switch',
        Icons.power_settings_new,
        settings.systemEnabled ? AppColors.primaryGreen : Colors.grey,
      ),
      _MetricCard(
        'Temperature',
        '${reading.temperature.toStringAsFixed(1)} °C',
        'Current reading',
        Icons.thermostat,
        Colors.redAccent,
      ),
      _MetricCard(
        'Humidity',
        '${reading.humidity.toStringAsFixed(0)} %',
        'Current reading',
        Icons.water_drop,
        Colors.lightBlue,
      ),
      _MetricCard(
        'Fan Status',
        reading.fanStatus,
        'Equipment state',
        Icons.air,
        AppColors.primaryGreen,
      ),
      _MetricCard(
        'Bulb Status',
        settings.heater ? 'ON' : 'OFF',
        'Equipment state',
        Icons.lightbulb,
        Colors.amber,
      ),
      _MetricCard(
        'Critical Alerts',
        '$critical',
        'Requires review',
        Icons.warning_amber,
        Colors.orange,
      ),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: cards.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: isSmallPhone
            ? 1
            : isMobile
            ? 2
            : width >= 1100
            ? 5
            : 4,
        crossAxisSpacing: 14,
        mainAxisSpacing: 14,
        childAspectRatio: isSmallPhone
            ? 2.1
            : isMobile
            ? 1.2
            : 1.55,
      ),
      itemBuilder: (context, index) => _metricCard(cards[index]),
    );
  }

  Widget _metricCard(_MetricCard card) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D173225),
            blurRadius: 18,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  card.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ),
              Icon(card.icon, color: card.color),
            ],
          ),
          Text(
            card.value,
            style: const TextStyle(
              color: AppColors.textPrimary,
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          Text(
            card.subtitle,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _environmentNotification(SensorData reading, SystemSettings settings) {
    final isCritical =
        reading.status.toUpperCase() == 'HOT' ||
        reading.temperature >= settings.maxTemp ||
        reading.temperature <= settings.minTemp;
    final color = isCritical ? AppColors.alertRed : AppColors.primaryGreen;
    final title = isCritical
        ? 'Critical environmental notification'
        : 'Environment normal';
    final description = isCritical
        ? 'Temperature is ${reading.temperature.toStringAsFixed(1)} °C, outside the configured range.'
        : 'Temperature is ${reading.temperature.toStringAsFixed(1)} °C and within the configured range.';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: .35)),
      ),
      child: Row(
        children: [
          Icon(
            isCritical ? Icons.warning_amber_rounded : Icons.check_circle,
            color: color,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(color: color, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                Text(
                  description,
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _quickControls(
    BuildContext context,
    SensorData reading,
    SystemSettings settings,
  ) {
    return _panel(
      title: 'Quick Controls',
      child: Column(
        children: [
          _controlRow(
            context,
            'System Power',
            'system_enabled',
            settings.systemEnabled,
            onChanged: DataService.instance.updateSystemEnabled,
          ),
          const Divider(),
          _controlRow(
            context,
            'Fan',
            'exhaust_fan',
            settings.exhaustFan,
            isEnabled:
                settings.systemEnabled && settings.systemMode == 'manual',
          ),
          const Divider(),
          _controlRow(
            context,
            'Bulb / Heater',
            'heater',
            settings.heater,
            isEnabled:
                settings.systemEnabled && settings.systemMode == 'manual',
          ),
        ],
      ),
    );
  }

  Widget _controlRow(
    BuildContext context,
    String title,
    String key,
    bool value, {
    bool isEnabled = true,
    Future<void> Function(bool)? onChanged,
  }) {
    late final Future<void> Function(bool) update;
    if (onChanged != null) {
      update = onChanged;
    } else {
      update = (enabled) => DataService.instance.updateDevice(key, enabled);
    }
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: const TextStyle(color: AppColors.textPrimary)),
        Switch(
          value: value,
          activeThumbColor: AppColors.primaryGreen,
          onChanged: isEnabled
              ? (enabled) async {
                  try {
                    await update(enabled);
                  } catch (error) {
                    if (context.mounted) {
                      ScaffoldMessenger.of(context)
                          .showSnackBar(SnackBar(content: Text('$error')));
                    }
                  }
                }
              : null,
        ),
      ],
    );
  }

  Widget _activity(List<AlertModel> alerts) {
    return _panel(
      title: 'Recent Activity',
      child: alerts.isEmpty
          ? const Text(
              'No alerts yet.',
              style: TextStyle(color: AppColors.textSecondary),
            )
          : Column(
              children: alerts.take(4).map((alert) {
                final color = alert.alertType == 'critical'
                    ? Colors.redAccent
                    : alert.alertType == 'warning'
                    ? Colors.orange
                    : Colors.lightBlue;
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    alert.alertType == 'critical'
                        ? Icons.error
                        : alert.alertType == 'warning'
                        ? Icons.warning
                        : Icons.info,
                    color: color,
                  ),
                  title: Text(
                    alert.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.textPrimary),
                  ),
                  subtitle: Text(
                    alert.description,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                );
              }).toList(),
            ),
    );
  }

  Widget _panel({required String title, required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D173225),
            blurRadius: 18,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: AppColors.textPrimary,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }

  static final SensorData _defaultReading = SensorData(
    temperature: 29.8,
    humidity: 71,
    heatIndex: 30.5,
    readingTime: DateTime(2026, 1, 1),
  );
}

class _MetricCard {
  const _MetricCard(
    this.title,
    this.value,
    this.subtitle,
    this.icon,
    this.color,
  );

  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
  final Color color;
}
