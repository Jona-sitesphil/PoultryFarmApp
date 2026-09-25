// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:thesis_poultry_farm_app/main.dart';

void main() {
  testWidgets('renders the poultry dashboard', (WidgetTester tester) async {
    await tester.pumpWidget(const PoultryFarmApp());
    await tester.pump();

    expect(find.text('Dashboard Overview'), findsOneWidget);
    expect(find.text('Temperature'), findsOneWidget);
  });

  testWidgets('renders without overflow on a compact Android phone', (
    WidgetTester tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    await tester.pumpWidget(const PoultryFarmApp());
    await tester.pumpAndSettle();

    expect(find.text('Dashboard Overview'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
