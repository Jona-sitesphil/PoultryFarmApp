import 'package:flutter/material.dart';

import '../utils/app_colors.dart';
import '../widgets/farm_background.dart';
import '../widgets/custom_sidebar.dart';

import 'package:thesis_poultry_farm_app/utils/responsive.dart';

import 'dashboard/dashboard_screen.dart';
import 'sensor_monitoring/sensor_monitoring_screen.dart';
import 'automated_control/automated_control_screen.dart';
import 'alerts_notifications/alerts_screen.dart';
import 'reports_analytics/reports_screen.dart';
import 'settings/settings_screen.dart';

class MainLayout extends StatefulWidget {
  const MainLayout({super.key});

  @override
  State<MainLayout> createState() => _MainLayoutState();
}

class _MainLayoutState extends State<MainLayout> {
  int _selectedIndex = 0;

  final List<Widget> _screens = [
    const DashboardScreen(),
    const SensorMonitoringScreen(),
    const AutomatedControlScreen(),
    const AlertsScreen(),
    const ReportsScreen(),
    const SettingsScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    final bool isMobile = Responsive.isMobile(context);

    return Scaffold(
      backgroundColor: AppColors.background,

      drawer: isMobile
          ? Drawer(
              child: CustomSidebar(
                selectedIndex: _selectedIndex,
                onItemSelected: (index) {
                  setState(() {
                    _selectedIndex = index;
                  });
                  Navigator.pop(context);
                },
              ),
            )
          : null,

      appBar: isMobile
          ? AppBar(
              title: Text(
                _getScreenTitle(_selectedIndex),
                style: const TextStyle(
                  color: AppColors.textPrimary,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              iconTheme: const IconThemeData(color: AppColors.textPrimary),
              titleSpacing: 8,
              actions: [
                IconButton(
                  icon: const Icon(
                    Icons.notifications,
                    color: AppColors.alertRed,
                  ),
                  onPressed: () => setState(() => _selectedIndex = 3),
                ),
                const SizedBox(width: 8),
              ],
            )
          : null,

      body: Row(
        children: [
          if (!isMobile)
            CustomSidebar(
              selectedIndex: _selectedIndex,
              onItemSelected: (index) {
                setState(() {
                  _selectedIndex = index;
                });
              },
            ),

          Expanded(
            child: Column(
              children: [
                if (!isMobile)
                  Container(
                    height: 72,
                    padding: const EdgeInsets.symmetric(horizontal: 28),
                    color: AppColors.cardBackground,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          _getScreenTitle(_selectedIndex),
                          style: const TextStyle(
                            color: AppColors.textPrimary,
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        IconButton(
                          tooltip: 'Open alerts',
                          icon: const Icon(
                            Icons.notifications_none_rounded,
                            color: AppColors.textSecondary,
                          ),
                          onPressed: () => setState(() => _selectedIndex = 3),
                        ),
                      ],
                    ),
                  ),

                Expanded(
                  child: FarmBackground(child: _screens[_selectedIndex]),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Helper method to set App Bar title dynamically on mobile view
  String _getScreenTitle(int index) {
    switch (index) {
      case 0:
        return 'Dashboard';
      case 1:
        return 'Sensors';
      case 2:
        return 'Controls';
      case 3:
        return 'Alerts';
      case 4:
        return 'Reports';
      case 5:
        return 'Settings';
      default:
        return 'Poultry Farm';
    }
  }
}
