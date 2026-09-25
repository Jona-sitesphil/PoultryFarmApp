import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/firebase_settings_model.dart';
import '../../services/data_service.dart';
import '../../utils/app_colors.dart';

class AutomatedControlScreen extends StatefulWidget {
  const AutomatedControlScreen({super.key});

  @override
  State<AutomatedControlScreen> createState() => _AutomatedControlScreenState();
}

class _AutomatedControlScreenState extends State<AutomatedControlScreen> {
  final _minTempController = TextEditingController();
  final _maxTempController = TextEditingController();
  final _maxHumidityController = TextEditingController();
  bool _controllersLoaded = false;
  bool _thresholdsDirty = false;
  bool _syncingThresholdControllers = false;
  Timer? _thresholdSaveTimer;

  @override
  void initState() {
    super.initState();
    _minTempController.addListener(_onThresholdChanged);
    _maxTempController.addListener(_onThresholdChanged);
    _maxHumidityController.addListener(_onThresholdChanged);
  }

  @override
  void dispose() {
    _thresholdSaveTimer?.cancel();
    _minTempController.removeListener(_onThresholdChanged);
    _maxTempController.removeListener(_onThresholdChanged);
    _maxHumidityController.removeListener(_onThresholdChanged);
    _minTempController.dispose();
    _maxTempController.dispose();
    _maxHumidityController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<SystemSettings>(
      stream: DataService.instance.systemSettings(),
      builder: (context, snapshot) {
        final settings = snapshot.data ?? const SystemSettings();
        if (!_controllersLoaded || !_thresholdsDirty) {
          _setThresholdControllers(settings);
        }
        return LayoutBuilder(
          builder: (context, constraints) {
            final isMobile = constraints.maxWidth < 800;
            return SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Automated Control',
                    style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                  ),
                  const Text(
                    'Manage thresholds and equipment through Realtime Database',
                    style: TextStyle(color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 24),
                  _systemPowerCard(settings),
                  const SizedBox(height: 16),
                  if (isMobile)
                    Column(
                      children: [
                        _modeCard(settings),
                        const SizedBox(height: 16),
                        _equipmentCard(settings),
                      ],
                    )
                  else
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(child: _modeCard(settings)),
                        const SizedBox(width: 24),
                        Expanded(flex: 2, child: _equipmentCard(settings)),
                      ],
                    ),
                  const SizedBox(height: 24),
                  _thresholdCard(),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _systemPowerCard(SystemSettings settings) {
    return _card(
      title: 'System Power',
      child: SwitchListTile(
        title: Text(settings.systemEnabled ? 'System ON' : 'System OFF'),
        subtitle: Text(
          settings.systemEnabled
              ? 'The ESP32 can run the automatic or manual equipment logic.'
              : 'The ESP32 keeps the fan and bulb turned off.',
        ),
        value: settings.systemEnabled,
        activeThumbColor: AppColors.primaryGreen,
        contentPadding: EdgeInsets.zero,
        onChanged: _updateSystemEnabled,
      ),
    );
  }

  Widget _modeCard(SystemSettings settings) {
    return _card(
      title: 'System Mode',
      child: Column(
        children: [
          RadioGroup<String>(
            groupValue: settings.systemMode,
            onChanged: _updateMode,
            child: Column(
              children: [
                RadioListTile<String>(
                  title: const Text('Fully Automatic'),
                  subtitle: const Text(
                    'Use sensor thresholds to control equipment.',
                  ),
                  value: 'automatic',
                  activeColor: AppColors.primaryGreen,
                  contentPadding: EdgeInsets.zero,
                ),
                RadioListTile<String>(
                  title: const Text('Manual Override'),
                  subtitle: const Text(
                    'Allow an operator to control equipment.',
                  ),
                  value: 'manual',
                  activeColor: AppColors.primaryGreen,
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _equipmentCard(SystemSettings settings) {
    final manualControlsEnabled =
        settings.systemEnabled && settings.systemMode == 'manual';

    return _card(
      title: 'Equipment Status',
      child: Column(
        children: [
          _deviceSwitch(
            'Exhaust Fan',
            'exhaust_fan',
            settings.exhaustFan,
            isEnabled: manualControlsEnabled,
          ),
          _deviceSwitch(
            'Heater',
            'heater',
            settings.heater,
            isEnabled: manualControlsEnabled,
          ),
          _deviceSwitch(
            'Cooling Mist',
            'cooling_mist',
            settings.coolingMist,
            isEnabled: manualControlsEnabled,
          ),
        ],
      ),
    );
  }

  Widget _deviceSwitch(
    String label,
    String key,
    bool value, {
    required bool isEnabled,
  }) {
    return SwitchListTile(
      title: Text(label),
      value: value,
      activeThumbColor: AppColors.primaryGreen,
      contentPadding: EdgeInsets.zero,
      onChanged: isEnabled ? (enabled) => _updateDevice(key, enabled) : null,
    );
  }

  Widget _thresholdCard() {
    return _card(
      title: 'Environmental Thresholds',
      child: Column(
        children: [
          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Changes are saved automatically in automatic or manual mode.',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final availableWidth = constraints.maxWidth;
              final fieldWidth = availableWidth < 600
                  ? availableWidth
                  : (availableWidth - 24) / 3;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  SizedBox(
                    width: fieldWidth,
                    child: _input(
                      'Minimum temperature',
                      _minTempController,
                      '°C',
                    ),
                  ),
                  SizedBox(
                    width: fieldWidth,
                    child: _input(
                      'Maximum temperature',
                      _maxTempController,
                      '°C',
                    ),
                  ),
                  SizedBox(
                    width: fieldWidth,
                    child: _input(
                      'Maximum humidity',
                      _maxHumidityController,
                      '%',
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          Align(
            alignment: Alignment.centerRight,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primaryGreen,
              ),
              onPressed: () => _saveThresholds(),
              child: const Text(
                'Save Thresholds',
                style: TextStyle(color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _input(String label, TextEditingController controller, String unit) {
    return TextField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(
        labelText: label,
        suffixText: unit,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
      ),
    );
  }

  Widget _card({required String title, required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.cardBackground,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }

  Future<void> _updateMode(String? value) async {
    if (value == null) return;
    await _runAction(() => DataService.instance.updateSystemMode(value));
  }

  Future<void> _updateSystemEnabled(bool enabled) async {
    await _runAction(() => DataService.instance.updateSystemEnabled(enabled));
  }

  Future<void> _updateDevice(String key, bool value) async {
    await _runAction(() => DataService.instance.updateDevice(key, value));
  }

  void _setThresholdControllers(SystemSettings settings) {
    _syncingThresholdControllers = true;
    final minTemp = settings.minTemp.toString();
    final maxTemp = settings.maxTemp.toString();
    final maxHumidity = settings.maxHumidity.toString();
    if (_minTempController.text != minTemp) {
      _minTempController.text = minTemp;
    }
    if (_maxTempController.text != maxTemp) {
      _maxTempController.text = maxTemp;
    }
    if (_maxHumidityController.text != maxHumidity) {
      _maxHumidityController.text = maxHumidity;
    }
    _syncingThresholdControllers = false;
    _controllersLoaded = true;
  }

  void _onThresholdChanged() {
    if (!_controllersLoaded || _syncingThresholdControllers) return;
    _thresholdsDirty = true;
    _thresholdSaveTimer?.cancel();
    _thresholdSaveTimer = Timer(
      const Duration(milliseconds: 600),
      () => _saveThresholds(showError: false, showSuccess: true),
    );
  }

  Future<void> _saveThresholds({
    bool showError = true,
    bool showSuccess = true,
  }) async {
    if (showError) _thresholdSaveTimer?.cancel();
    final minTemp = double.tryParse(_minTempController.text);
    final maxTemp = double.tryParse(_maxTempController.text);
    final maxHumidity = double.tryParse(_maxHumidityController.text);

    if (minTemp == null || maxTemp == null || maxHumidity == null) {
      if (showError) _showError('Enter valid numeric threshold values.');
      return;
    }
    if (minTemp >= maxTemp) {
      if (showError) {
        _showError(
          'Minimum temperature must be lower than maximum temperature.',
        );
      }
      return;
    }

    try {
      await DataService.instance.updateThresholds(
        minTemp: minTemp,
        maxTemp: maxTemp,
        maxHumidity: maxHumidity,
      );
      _thresholdsDirty = false;
      if (showSuccess) _showSuccess('Environmental thresholds saved.');
    } catch (error) {
      if (showError) _showError('$error');
    }
  }

  void _showSuccess(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: AppColors.primaryGreen,
        ),
      );
    }
  }

  void _showError(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _runAction(Future<void> Function() action) async {
    try {
      await action();
    } catch (error) {
      _showError('$error');
    }
  }
}
