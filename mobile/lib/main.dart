import 'package:flutter/material.dart';

import 'theme/app_theme.dart';

void main() => runApp(const ArjApp());

/// Placeholder shell. Real screens (auth, catalogue, bookings, wallet, calls …) arrive in phase 11,
/// built on the shared /api/v1 backend.
class ArjApp extends StatelessWidget {
  const ArjApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Astro Rajnath Jha',
      theme: buildArjTheme(),
      home: const _Home(),
    );
  }
}

class _Home extends StatelessWidget {
  const _Home();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Astro Rajnath Jha')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text('Vedic astrology · Vastu · Remedies',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(color: ArjColors.gold700, letterSpacing: 1.5)),
            const SizedBox(height: 8),
            Text('Clear, practical guidance', style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w600)),
            const SizedBox(height: 24),
            FilledButton(onPressed: null, child: const Text('Opening soon')),
          ],
        ),
      ),
    );
  }
}
