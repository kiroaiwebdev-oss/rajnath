import 'dart:async';

import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'push/push_service.dart';
import 'screens/account_screens.dart';
import 'screens/assistant_screen.dart';
import 'screens/astro_screens.dart';
import 'screens/blog_screens.dart';
import 'screens/content_screens.dart';
import 'screens/info_screens.dart';
import 'screens/purchase_screens.dart';
import 'screens/review_screens.dart';
import 'screens/routing.dart';
import 'screens/services_screens.dart';
import 'screens/showcase_screens.dart';
import 'screens/store_screens.dart';
import 'screens/tool_screens.dart';
import 'state/app_state.dart';
import 'theme/app_theme.dart';
import 'util/validators.dart';
import 'widgets/common.dart';

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
      MoreScreen(state: widget.state),
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
          const NavigationDestination(icon: Icon(Icons.menu), label: 'More'),
        ],
      ),
    );
  }
}

class HomeTab extends StatefulWidget {
  const HomeTab({super.key, required this.state, required this.onOpenServices});
  final AppState state;
  final VoidCallback onOpenServices;
  @override
  State<HomeTab> createState() => _HomeTabState();
}

String _or(Object? v, String fallback) {
  final s = v == null ? '' : '$v'.trim();
  return s.isEmpty ? fallback : s;
}

class _HomeTabState extends State<HomeTab> {
  /// GET /profile (hero text, stats, portrait). Until it loads, or if it fails, the built-in text and portrait show.
  Map<String, dynamic>? _profile;
  String? _locale;

  /// Latest 3 from GET /blog; the strip stays hidden while empty or when the request fails.
  List<Map<String, dynamic>> _articles = const [];

  AppState get state => widget.state;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  @override
  void didUpdateWidget(HomeTab old) {
    super.didUpdateWidget(old);
    if (state.locale != _locale) _loadProfile(); // the profile text is translated
  }

  Future<void> _loadArticles() async {
    try {
      final list = unwrapList(await state.api.get('/blog')).take(3).toList();
      if (mounted) setState(() => _articles = list);
    } catch (_) {/* the strip stays hidden */}
  }

  void _push(Widget Function() page) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page()));

  /// Services filtered to one family (puja, chadhava), with its own app bar.
  Widget _family(String title, String family) => Scaffold(appBar: AppBar(title: Text(title)), body: SafeArea(child: ServicesScreen(state: state, initialFamily: family)));

  /// The "Everything in one place" tiles: icon, label, screen.
  List<(IconData, String, Widget Function())> get _ecosystem => [
        (Icons.auto_awesome_outlined, 'Free Kundli', () => KundliScreen(state: state)),
        (Icons.favorite_border, 'Kundli matching', () => KundliScreen(state: state, matching: true)),
        (Icons.local_fire_department_outlined, 'Online puja', () => _family('Online puja', 'puja')),
        (Icons.volunteer_activism_outlined, 'Chadhava', () => _family('Chadhava', 'chadhava')),
        (Icons.diamond_outlined, 'Gemstones & samagri', () => StoreScreen(state: state)),
        (Icons.wb_sunny_outlined, 'Panchang', () => PanchangScreen(state: state)),
        (Icons.event_available_outlined, 'Muhurat', () => MuhuratScreen(state: state)),
        (Icons.public_outlined, 'Grah gochar', () => GocharScreen(state: state)),
        (Icons.celebration_outlined, 'Festivals', () => FestivalsScreen(state: state)),
        (Icons.calculate_outlined, 'Calculators', () => Scaffold(appBar: AppBar(title: const Text('Calculators')), body: SafeArea(child: ToolsTab(state: state)))),
        (Icons.menu_book_outlined, 'Articles', () => BlogScreen(state: state)),
        (Icons.school_outlined, 'Courses', () => CoursesScreen(state: state)),
      ];

  Widget _tile(IconData icon, String label, Widget Function() page) => Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => _push(page),
          child: Ink(
            decoration: BoxDecoration(borderRadius: BorderRadius.circular(16), border: Border.all(color: ArjColors.gold200)),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: const BoxDecoration(shape: BoxShape.circle, gradient: LinearGradient(colors: [ArjColors.gold50, ArjColors.gold200])),
                  child: Icon(icon, color: ArjColors.gold700, size: 24),
                ),
                const SizedBox(height: 8),
                Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: ArjColors.ink900, height: 1.2)),
              ]),
            ),
          ),
        ),
      );

  Future<void> _loadProfile() async {
    _locale = state.locale;
    _loadArticles(); // titles are translated too
    try {
      final p = await loadProfile(state);
      if (mounted) setState(() => _profile = p);
    } catch (_) {/* keep the built-in hero */}
  }

  @override
  Widget build(BuildContext context) {
    final p = _profile ?? const <String, dynamic>{};
    final home = (p['home'] as Map?) ?? const {};
    final name = _or(p['name'], 'Acharya Dr. Rajnath Jha');
    final eyebrow = _or(home['eyebrow'], 'Vedic astrology · Vastu · Remedies').toUpperCase();
    return ListView(padding: const EdgeInsets.all(20), children: [
      Text(eyebrow, style: Theme.of(context).textTheme.labelMedium?.copyWith(color: ArjColors.gold700, letterSpacing: 1.5)),
      const SizedBox(height: 8),
      Text('Clear, practical guidance from $name', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
      const SizedBox(height: 8),
      Text(_or(home['subtitle'], 'Ph.D. in Astrology · Director, Jyotirveda Vigyan Sansthan, Patna · 30+ years of practice'), style: const TextStyle(color: ArjColors.ink600)),
      const SizedBox(height: 16),
      ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: NetImage('${(p['photos'] as Map?)?['home'] ?? ''}', semanticLabel: name, fallback: Image.asset(portraitAsset, fit: BoxFit.cover, semanticLabel: name)),
      ),
      if (p['stats'] != null) ...[const SizedBox(height: 12), ProfileStats(p['stats'])],
      const SizedBox(height: 16),
      FilledButton(onPressed: widget.onOpenServices, child: const Text('Book a consultation')),
      const SizedBox(height: 8),
      const SectionTitle('Everything in one place'),
      LayoutBuilder(
        builder: (context, c) => GridView.count(
          crossAxisCount: c.maxWidth >= 600 ? 6 : (c.maxWidth >= 340 ? 3 : 2),
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 0.95,
          children: [for (final (icon, label, page) in _ecosystem) _tile(icon, label, page)],
        ),
      ),
      const SectionTitle('Your sign today'),
      SizedBox(
        height: 44,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: 12,
          separatorBuilder: (_, _) => const SizedBox(width: 8),
          itemBuilder: (context, i) => ActionChip(
            avatar: const Icon(Icons.brightness_3_outlined, size: 16, color: ArjColors.gold700),
            label: Text(signNames[i].split(' ').first),
            tooltip: signNames[i],
            onPressed: () => _push(() => RashiDetailScreen(state: state, slug: rashiSlugs[i], title: signNames[i])),
          ),
        ),
      ),
      if (_articles.isNotEmpty) ...[
        Row(children: [
          const Expanded(child: SectionTitle('Latest articles')),
          TextButton(onPressed: () => _push(() => BlogScreen(state: state)), child: const Text('See all')),
        ]),
        SizedBox(
          height: 290,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _articles.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (context, i) => SizedBox(width: 250, child: ArticleCard(state: state, article: _articles[i], compact: true)),
          ),
        ),
      ],
      const SectionTitle('More for you'),
      OutlinedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => HoroscopeScreen(state: state))), child: const Text('Rashifal')),
      const SizedBox(height: 8),
      OutlinedButton.icon(
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => GalleryScreen(state: state))),
        icon: const Icon(Icons.photo_library_outlined),
        label: const Text('Photo gallery'),
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
      HomeReviews(state: state),
      const SizedBox(height: 24),
      const Text('Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
    ]);
  }
}
