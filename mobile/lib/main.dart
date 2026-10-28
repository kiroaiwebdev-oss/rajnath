import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'screens/account_screens.dart';
import 'screens/astro_screens.dart';
import 'screens/services_screens.dart';
import 'state/app_state.dart';
import 'theme/app_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final state = AppState(ApiClient())..bootstrap();
  runApp(ArjApp(state: state));
}

class ArjApp extends StatelessWidget {
  const ArjApp({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'Astro Rajnath Jha',
        debugShowCheckedModeBanner: false,
        theme: buildArjTheme(),
        home: ListenableBuilder(listenable: state, builder: (_, _) => HomeShell(state: state)),
      );
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.state});
  final AppState state;
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomeTab(state: widget.state, onOpenServices: () => setState(() => _tab = 1)),
      ServicesScreen(state: widget.state),
      ToolsTab(state: widget.state),
      AccountScreen(state: widget.state),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('Astro Rajnath Jha')),
      body: SafeArea(child: IndexedStack(index: _tab, children: pages)),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.auto_awesome_outlined), selectedIcon: Icon(Icons.auto_awesome), label: 'Services'),
          NavigationDestination(icon: Icon(Icons.calculate_outlined), selectedIcon: Icon(Icons.calculate), label: 'Tools'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Account'),
        ],
      ),
    );
  }
}

class HomeTab extends StatelessWidget {
  const HomeTab({super.key, required this.state, required this.onOpenServices});
  final AppState state;
  final VoidCallback onOpenServices;

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(20), children: [
        Text('VEDIC ASTROLOGY · VASTU · REMEDIES', style: Theme.of(context).textTheme.labelMedium?.copyWith(color: ArjColors.gold700, letterSpacing: 1.5)),
        const SizedBox(height: 8),
        Text('Clear, practical guidance from Acharya Dr. Rajnath Jha', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        const Text('Ph.D. in Astrology · Director, Jyotirveda Vigyan Sansthan, Patna · 30+ years of practice', style: TextStyle(color: ArjColors.ink600)),
        const SizedBox(height: 16),
        ClipRRect(borderRadius: BorderRadius.circular(20), child: Image.asset('assets/images/portrait-navy-480.jpg', fit: BoxFit.cover, semanticLabel: 'Acharya Dr. Rajnath Jha')),
        const SizedBox(height: 16),
        FilledButton(onPressed: onOpenServices, child: const Text('Book a consultation')),
        const SizedBox(height: 8),
        OutlinedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => KundliScreen(state: state))), child: const Text('Free Kundli')),
        const SizedBox(height: 8),
        OutlinedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PanchangScreen(state: state))), child: const Text("Today's Panchang")),
        const SizedBox(height: 24),
        const Text('Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
      ]);
}

class ToolsTab extends StatelessWidget {
  const ToolsTab({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(16), children: [
        for (final t in [
          ('Free Kundli', 'Birth chart, planets and dasha', () => KundliScreen(state: state)),
          ('Kundli Matching', '36-guna milan', () => KundliScreen(state: state, matching: true)),
          ('Panchang', 'Tithi, nakshatra, Rahu Kaal', () => PanchangScreen(state: state)),
        ])
          Card(child: ListTile(title: Text(t.$1), subtitle: Text(t.$2), trailing: const Icon(Icons.chevron_right), onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => t.$3())))),
        const Padding(padding: EdgeInsets.all(8), child: Text('All 26 calculators are also on the website.', style: TextStyle(color: ArjColors.ink600))),
      ]);
}
