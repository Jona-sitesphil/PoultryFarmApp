import 'package:flutter/material.dart';

import '../utils/app_colors.dart';

class CustomSidebar extends StatelessWidget {
  final int selectedIndex;
  final Function(int) onItemSelected;

  const CustomSidebar({
    super.key,
    required this.selectedIndex,
    required this.onItemSelected,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 250,
      color: AppColors.sidebarBackground,
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Column(
        children: [
          _brandHeader(),
          const SizedBox(height: 28),

          // Navigation
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                _buildNavItem(Icons.home, 'Dashboard', 0),
                _buildNavItem(Icons.sensors, 'Sensor Monitoring', 1),
                _buildNavItem(Icons.settings_remote, 'Automated Control', 2),
                _buildNavItem(
                  Icons.notifications_active,
                  'Alerts & Notifications',
                  3,
                ),
                _buildNavItem(Icons.bar_chart, 'Reports & Analytics', 4),
                _buildNavItem(Icons.settings, 'Settings', 5),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Bolbok Poultry Farm',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),

                const SizedBox(height: 2),

                const Text(
                  'House 1',
                  style: TextStyle(color: AppColors.textSecondary),
                ),

                const SizedBox(height: 10),

                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.asset(
                    'assets/images/ce35912a-d6d8-4e25-bf4a-42bc8745f549 (1).jpg',
                    height: 82,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) => Container(
                      height: 82,
                      color: AppColors.softGreen,
                      child: const Center(child: Icon(Icons.agriculture)),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _brandHeader() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18),
      child: Row(
        children: [
          Container(
            height: 48,
            width: 48,
            decoration: BoxDecoration(
              color: AppColors.softGreen,
              borderRadius: BorderRadius.circular(14),
            ),
            clipBehavior: Clip.antiAlias,
            child: Image.asset(
              'assets/images/logo.jpg',
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  const Icon(Icons.agriculture, color: AppColors.primaryGreen),
            ),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'P.O.U.L.T.R.Y.',
                  style: TextStyle(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                    letterSpacing: .5,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Farm monitoring',
                  style: TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNavItem(IconData icon, String title, int index) {
    final bool isSelected = selectedIndex == index;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        child: ListTile(
          leading: Icon(
            icon,
            color: isSelected
                ? AppColors.primaryGreen
                : AppColors.textSecondary,
          ),

          title: Text(
            title,
            style: TextStyle(
              color: isSelected
                  ? AppColors.primaryGreen
                  : AppColors.textPrimary,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            ),
          ),

          tileColor: isSelected ? AppColors.softGreen : Colors.transparent,

          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),

          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 4,
          ),

          onTap: () => onItemSelected(index),
        ),
      ),
    );
  }
}
