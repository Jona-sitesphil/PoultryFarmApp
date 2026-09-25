import 'package:flutter/material.dart';

import '../utils/app_colors.dart';

/// A shared warm, farm-inspired canvas for every app screen.
///
/// The illustration is deliberately subtle so the translucent content blocks
/// remain easy to read on both Android phones and larger screens.
class FarmBackground extends StatelessWidget {
  final Widget child;

  const FarmBackground({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.farmBackgroundTop,
            AppColors.farmBackgroundMiddle,
            AppColors.farmBackgroundBottom,
          ],
          stops: [0, .56, 1],
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          const IgnorePointer(
            child: Stack(
              children: [
                Positioned(
                  top: -110,
                  right: -70,
                  child: _GlowOrb(size: 300, opacity: .22),
                ),
                Positioned(
                  bottom: -150,
                  left: -100,
                  child: _GlowOrb(size: 360, opacity: .15),
                ),
              ],
            ),
          ),
          Positioned.fill(
            child: IgnorePointer(
              child: Opacity(
                opacity: .46,
                child: Image.asset(
                  'assets/images/farm_ui_background_brown.png',
                  fit: BoxFit.cover,
                ),
              ),
            ),
          ),
          Positioned.fill(child: child),
        ],
      ),
    );
  }
}

class _GlowOrb extends StatelessWidget {
  final double size;
  final double opacity;

  const _GlowOrb({required this.size, required this.opacity});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}
