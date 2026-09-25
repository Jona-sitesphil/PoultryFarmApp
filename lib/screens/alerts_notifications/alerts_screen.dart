import 'package:flutter/material.dart';

import '../../models/alert_model.dart';
import '../../services/data_service.dart';
import '../../utils/app_colors.dart';

class AlertsScreen extends StatelessWidget {
  const AlertsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<AlertModel>>(
      stream: DataService.instance.alerts(),
      builder: (context, snapshot) {
        final alerts = snapshot.data ?? const <AlertModel>[];
        final critical = alerts.where((a) => a.alertType == 'critical').length;
        final warnings = alerts.where((a) => a.alertType == 'warning').length;
        final info = alerts.where((a) => a.alertType == 'info').length;
        return SingleChildScrollView(
          padding: EdgeInsets.all(
            MediaQuery.sizeOf(context).width < 600 ? 16 : 24,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _header(context, alerts),
              const SizedBox(height: 24),
              LayoutBuilder(
                builder: (context, constraints) {
                  final columns = constraints.maxWidth < 700 ? 2 : 4;
                  return GridView.count(
                    crossAxisCount: columns,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    childAspectRatio: 1.7,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _summary('Critical', critical, Colors.red, Icons.error),
                      _summary(
                        'Warnings',
                        warnings,
                        Colors.orange,
                        Icons.warning,
                      ),
                      _summary('Info', info, Colors.blue, Icons.info),
                      _summary(
                        'Total',
                        alerts.length,
                        AppColors.primaryGreen,
                        Icons.check_circle,
                      ),
                    ],
                  );
                },
              ),
              const SizedBox(height: 24),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColors.cardBackground,
                  border: Border.all(color: AppColors.border),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: alerts.isEmpty
                    ? const Text(
                        'No alerts yet.',
                        style: TextStyle(color: AppColors.textSecondary),
                      )
                    : Column(
                        children: alerts
                            .map((alert) => _alertItem(alert))
                            .toList(),
                      ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _header(BuildContext context, List<AlertModel> alerts) {
    const title = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Alerts & Notifications',
          style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
        ),
        Text(
          'Live alerts from the Realtime Database alerts path',
          style: TextStyle(color: AppColors.textSecondary),
        ),
      ],
    );
    final action = TextButton.icon(
      onPressed: alerts.isEmpty ? null : () => _markRead(context, alerts),
      icon: const Icon(Icons.done_all, size: 18),
      label: const Text('Mark all as read'),
    );
    if (MediaQuery.sizeOf(context).width < 560) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          title,
          Align(alignment: Alignment.centerRight, child: action),
        ],
      );
    }
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [title, action],
    );
  }

  Widget _summary(String title, int value, Color color, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: .3)),
      ),
      child: Row(
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                title,
                style: const TextStyle(color: AppColors.textSecondary),
              ),
              Text(
                '$value',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _alertItem(AlertModel alert) {
    final color = alert.alertType == 'critical'
        ? Colors.red
        : alert.alertType == 'warning'
        ? Colors.orange
        : Colors.blue;
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(vertical: 6),
      leading: CircleAvatar(
        backgroundColor: color.withValues(alpha: .12),
        child: Icon(
          alert.alertType == 'critical'
              ? Icons.error
              : alert.alertType == 'warning'
              ? Icons.warning
              : Icons.info,
          color: color,
        ),
      ),
      title: Text(
        alert.title,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.bold),
      ),
      subtitle: Text(
        '${alert.description}\n${alert.location} · ${alert.sensorName}',
        maxLines: 3,
        overflow: TextOverflow.ellipsis,
      ),
      isThreeLine: true,
      trailing: SizedBox(
        width: 76,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              alert.createdAt.toLocal().toString().substring(0, 16),
              maxLines: 2,
              textAlign: TextAlign.end,
              style: const TextStyle(fontSize: 11),
            ),
            if (!alert.isRead)
              const Text(
                'NEW',
                style: TextStyle(
                  color: AppColors.primaryGreen,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _markRead(BuildContext context, List<AlertModel> alerts) async {
    try {
      await DataService.instance.markAllAlertsRead(alerts);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Alerts marked as read.')));
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
      }
    }
  }
}
