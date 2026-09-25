import 'package:flutter/material.dart';

import '../../models/firebase_settings_model.dart';
import '../../services/data_service.dart';
import '../../utils/app_colors.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _farmNameController = TextEditingController();
  final _descriptionController = TextEditingController();
  bool _loaded = false;
  bool _autoRefresh = true;
  bool _dataLogging = true;
  bool _confirmAction = true;
  String _theme = 'Light';

  @override
  void dispose() {
    _farmNameController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<FirebaseSettings>(
      stream: DataService.instance.settings(),
      builder: (context, snapshot) {
        final settings = snapshot.data ?? const FirebaseSettings();
        if (!_loaded) {
          _farmNameController.text = settings.farmName;
          _descriptionController.text = settings.description;
          _autoRefresh = settings.autoRefresh;
          _dataLogging = settings.dataLogging;
          _confirmAction = settings.confirmAction;
          _theme = settings.theme;
          _loaded = true;
        }
        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Settings',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),
              const Text(
                'Configuration stored in the Realtime Database settings/general path',
                style: TextStyle(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 24),
              LayoutBuilder(
                builder: (context, constraints) {
                  final isMobile = constraints.maxWidth < 900;
                  final cards = [
                    _generalCard(settings),
                    _preferencesCard(settings),
                    _displayCard(settings),
                  ];
                  if (isMobile) {
                    return Column(
                      children: cards
                          .map(
                            (card) => Padding(
                              padding: const EdgeInsets.only(bottom: 16),
                              child: card,
                            ),
                          )
                          .toList(),
                    );
                  }
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: cards
                        .map(
                          (card) => Expanded(
                            child: Padding(
                              padding: const EdgeInsets.only(right: 16),
                              child: card,
                            ),
                          ),
                        )
                        .toList(),
                  );
                },
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _generalCard(FirebaseSettings settings) {
    return _card(
      title: 'General Information',
      icon: Icons.check_box_outlined,
      child: Column(
        children: [
          TextField(
            controller: _farmNameController,
            decoration: _decoration('Farm / Location Name'),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _descriptionController,
            maxLines: 3,
            decoration: _decoration('Description'),
          ),
          const SizedBox(height: 16),
          _valueRow('Timezone', settings.timezone),
          _valueRow('Language', settings.language),
          const SizedBox(height: 16),
          _saveButton(() => _save(settings)),
        ],
      ),
    );
  }

  Widget _preferencesCard(FirebaseSettings settings) {
    return _card(
      title: 'System Preferences',
      icon: Icons.tune,
      child: Column(
        children: [
          _toggle(
            'Auto Refresh Dashboard',
            'Automatically refresh dashboard data',
            _autoRefresh,
            (value) => setState(() => _autoRefresh = value),
          ),
          _toggle(
            'Data Logging',
            'Enable data logging to Realtime Database',
            _dataLogging,
            (value) => setState(() => _dataLogging = value),
          ),
          _toggle(
            'Confirm Before Control Action',
            'Ask before changing equipment state',
            _confirmAction,
            (value) => setState(() => _confirmAction = value),
          ),
          const SizedBox(height: 16),
          _saveButton(() => _save(settings)),
        ],
      ),
    );
  }

  Widget _displayCard(FirebaseSettings settings) {
    return _card(
      title: 'Display Settings',
      icon: Icons.desktop_windows,
      child: Column(
        children: [
          _valueRow('Unit system', settings.unitSystem),
          _valueRow('Temperature unit', settings.tempUnit),
          _valueRow('Humidity unit', settings.humidityUnit),
          _valueRow('Chart data points', settings.chartDataPoints),
          DropdownButtonFormField<String>(
            initialValue: _theme,
            decoration: _decoration('Theme'),
            items: const [
              DropdownMenuItem(value: 'Light', child: Text('Light')),
              DropdownMenuItem(value: 'Dark', child: Text('Dark')),
            ],
            onChanged: (value) => setState(() => _theme = value ?? 'Light'),
          ),
          const SizedBox(height: 16),
          _saveButton(() => _save(settings)),
        ],
      ),
    );
  }

  Widget _card({
    required String title,
    required IconData icon,
    required Widget child,
  }) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: AppColors.primaryGreen),
              const SizedBox(width: 8),
              Text(
                title,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          child,
        ],
      ),
    );
  }

  InputDecoration _decoration(String label) => InputDecoration(
    labelText: label,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
  );

  Widget _valueRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label)),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.end,
              style: const TextStyle(color: AppColors.textSecondary),
            ),
          ),
        ],
      ),
    );
  }

  Widget _toggle(
    String title,
    String subtitle,
    bool value,
    ValueChanged<bool> onChanged,
  ) {
    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(title),
      subtitle: Text(subtitle),
      value: value,
      activeThumbColor: AppColors.primaryGreen,
      onChanged: onChanged,
    );
  }

  Widget _saveButton(VoidCallback onPressed) {
    return Align(
      alignment: Alignment.centerRight,
      child: ElevatedButton(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primaryGreen,
        ),
        onPressed: onPressed,
        child: const Text(
          'Save Changes',
          style: TextStyle(color: Colors.white),
        ),
      ),
    );
  }

  Future<void> _save(FirebaseSettings current) async {
    try {
      await DataService.instance.saveSettings(
        current.copyWith(
          farmName: _farmNameController.text.trim(),
          description: _descriptionController.text.trim(),
          autoRefresh: _autoRefresh,
          dataLogging: _dataLogging,
          confirmAction: _confirmAction,
          theme: _theme,
        ),
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Settings saved to Firebase.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
      }
    }
  }
}
