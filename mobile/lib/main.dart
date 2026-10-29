import 'dart:async';

import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'push/push_service.dart';
import 'screens/account_screens.dart';
import 'screens/assistant_screen.dart';
import 'screens/astro_screens.dart';
import 'screens/content_screens.dart';
import 'screens/purchase_screens.dart';
import 'screens/routing.dart';
import 'screens/services_screens.dart';
import 'screens/store_screens.dart';
import 'state/app_state.dart';
import 'theme/app_theme.dart';

final navigatorKey = GlobalKey<NavigatorState>();
final messengerKey = GlobalKey<ScaffoldMessengerState>();

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final state = AppState(ApiClient());
  final boot = state.bootstrap();
  runApp(ArjApp(state: state, navigatorKey: navigatorKey, messengerKey: messengerKey));
  final push = PushService(state, navigatorKey, messengerKey: messengerKey, targetBuilder: (kind, ref) => screenForTarget(state, kind, ref));
  // Meta (push config) loads after the session; push failures are swallowed inside start().
  unawaited(boot.then((_) => push.start()).catchError((Object _) {}));
}

class ArjApp extends StatelessWidget {
  const ArjApp({super.key, required this.state, this.navigatorKey, this.messengerKey});
  final AppState state;
  final GlobalKey<NavigatorState>? navigatorKey;
  final GlobalKey<ScaffoldMessengerState>? messengerKey;

  @override
  Widget build(BuildContext context) => MaterialApp(
        navigatorKey: navigatorKey,
        scaffoldMessengerKey: messengerKey,
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
        destinations: [
          const NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          const NavigationDestination(icon: Icon(Icons.auto_awesome_outlined), selectedIcon: Icon(Icons.auto_awesome), label: 'Services'),
          const NavigationDestination(icon: Icon(Icons.calculate_outlined), selectedIcon: Icon(Icons.calculate), label: 'Tools'),
          NavigationDestination(
            icon: Badge(isLabelVisible: widget.state.unread > 0, label: Text('${widget.state.unread}'), child: const Icon(Icons.person_outline)),
            selectedIcon: Badge(isLabelVisible: widget.state.unread > 0, label: Text('${widget.state.unread}'), child: const Icon(Icons.person)),
            label: 'Account',
            tooltip: widget.state.unread > 0 ? 'Account, ${widget.state.unread} unread notifications' : 'Account',
          ),
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
        const SizedBox(height: 8),
        OutlinedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => HoroscopeScreen(state: state))), child: const Text('Rashifal')),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => StoreScreen(state: state))),
          icon: const Icon(Icons.diamond_outlined),
          label: const Text('Gemstone store'),
        ),
        if (state.assistantEnabled) ...[
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () async {
              if (!await ensureSignedIn(context, state, reason: 'Sign in to chat with our assistant.') || !context.mounted) return;
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => AssistantScreen(state: state)));
            },
            icon: const Icon(Icons.chat_bubble_outline),
            label: const Text('Ask our assistant'),
          ),
        ],
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
          ('Panchang', 'Tithi, nakshatra, Rahu Kaal, Choghadiya', () => PanchangScreen(state: state)),
          ('Rashifal', 'Daily to yearly horoscope by sign', () => HoroscopeScreen(state: state)),
          ('Grah Gochar', 'Planetary transits and their effects', () => GocharScreen(state: state)),
          ('Muhurat', 'Auspicious dates for samskaras', () => MuhuratScreen(state: state)),
          ('Festivals', 'Festival calendar by year', () => FestivalsScreen(state: state)),
        ])
          Card(child: ListTile(title: Text(t.$1), subtitle: Text(t.$2), trailing: const Icon(Icons.chevron_right), onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => t.$3())))),
        const Padding(padding: EdgeInsets.all(8), child: Text('All 26 calculators are also on the website.', style: TextStyle(color: ArjColors.ink600))),
      ]);
}
