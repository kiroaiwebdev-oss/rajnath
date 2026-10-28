import 'package:app/main.dart';
import 'package:app/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('app shell renders with brand theme', (tester) async {
    await tester.pumpWidget(const ArjApp());
    expect(find.text('Astro Rajnath Jha'), findsOneWidget);
    final ctx = tester.element(find.byType(Scaffold));
    expect(Theme.of(ctx).scaffoldBackgroundColor, ArjColors.ivory50);
    expect(Theme.of(ctx).colorScheme.primary, ArjColors.ink900);
  });
}
