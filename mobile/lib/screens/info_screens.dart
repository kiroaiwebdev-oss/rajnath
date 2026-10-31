import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';
import 'astro_screens.dart';
import 'blog_screens.dart';
import 'content_screens.dart';
import 'review_screens.dart';
import 'showcase_screens.dart';
import 'support_screens.dart';

/// URL slugs for GET /rashi/{sign}, by sign index (first word of the sign name, lower case).
final rashiSlugs = [for (final n in signNames) n.split(' ').first.toLowerCase()];

String _list(Object? v) => v is List ? v.map((e) => e is Map ? e.values.join(' ') : '$e').join(', ') : '${v ?? ''}';

Widget _fact(String label, Object? value) => value == null || _list(value).isEmpty
    ? const SizedBox.shrink()
    : Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 130, child: Text(label, style: const TextStyle(color: ArjColors.ink600))),
          Expanded(child: Text(_list(value))),
        ]),
      );

// ───────────────────────────── More ─────────────────────────────

/// "More" tab: guides, help, about/contact, language and legal pages.
class MoreScreen extends StatefulWidget {
  const MoreScreen({super.key, required this.state});
  final AppState state;
  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  /// Published legal pages from GET /site (`data.legal`); null until loaded or when it fails.
  List<Map>? _legal;
  String? _locale;

  AppState get state => widget.state;

  @override
  void initState() {
    super.initState();
    _loadLegal();
  }

  @override
  void didUpdateWidget(MoreScreen old) {
    super.didUpdateWidget(old);
    if (state.locale != _locale) _loadLegal(); // page titles are translated
  }

  Future<void> _loadLegal() async {
    _locale = state.locale;
    try {
      final legal = (unwrap(await state.api.get('/site'))['legal'] as List?)?.whereType<Map>().where((l) => l['url'] != null && l['title'] != null).toList();
      if (mounted && legal != null && legal.isNotEmpty) setState(() => _legal = legal);
    } catch (_) {/* keep the built-in Terms and Privacy links */}
  }

  static IconData _legalIcon(Object? kind) => switch (kind) {
        'terms' => Icons.gavel_outlined,
        'privacy' => Icons.privacy_tip_outlined,
        'refunds' => Icons.currency_exchange,
        'shipping' => Icons.local_shipping_outlined,
        _ => Icons.description_outlined,
      };

  Widget _tile(BuildContext context, IconData icon, String title, {String? subtitle, Widget Function()? page, String? url}) => Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(
          leading: Icon(icon),
          title: Text(title),
          subtitle: subtitle == null ? null : Text(subtitle),
          trailing: Icon(url != null ? Icons.open_in_new : Icons.chevron_right),
          onTap: () => url != null ? openExternal(context, url) : Navigator.of(context).push(MaterialPageRoute(builder: (_) => page!())),
        ),
      );

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(16), children: [
        const SectionTitle('Guides'),
        _tile(context, Icons.calendar_month_outlined, 'Vrat calendar', page: () => VratCalendarScreen(state: state)),
        _tile(context, Icons.celebration_outlined, 'Festivals', page: () => FestivalsScreen(state: state)),
        _tile(context, Icons.brightness_3_outlined, 'Rashi guide', page: () => RashiListScreen(state: state)),
        _tile(context, Icons.public_outlined, 'Navagraha', page: () => NavagrahaScreen(state: state)),
        const SectionTitle('Learn'),
        _tile(context, Icons.menu_book_outlined, 'Articles', subtitle: 'Jyotish, Vastu, puja and remedies', page: () => BlogScreen(state: state)),
        _tile(context, Icons.school_outlined, 'Courses', subtitle: 'Coming soon', page: () => CoursesScreen(state: state)),
        const SectionTitle('Acharya Ji'),
        _tile(context, Icons.info_outline, 'About Acharya Ji', page: () => AboutScreen(state: state)),
        _tile(context, Icons.emoji_events_outlined, 'Awards and honours', page: () => AwardsScreen(state: state)),
        _tile(context, Icons.photo_library_outlined, 'Gallery', subtitle: 'Pujas, events and media', page: () => GalleryScreen(state: state)),
        _tile(context, Icons.reviews_outlined, 'Client reviews', subtitle: 'What our clients say', page: () => ReviewsScreen(state: state)),
        const SectionTitle('Help'),
        _tile(context, Icons.help_outline, 'FAQ', page: () => FaqScreen(state: state)),
        _tile(context, Icons.support_agent_outlined, 'Help & support', subtitle: 'Your support requests', page: () => SupportGate(state: state)),
        _tile(context, Icons.call_outlined, 'Contact us', page: () => ContactScreen(state: state)),
        const SectionTitle('Settings'),
        _tile(context, Icons.translate, 'Language', subtitle: AppState.localeNames[state.locale ?? 'en'], page: () => LanguageScreen(state: state)),
        const SectionTitle('Legal'),
        if (_legal case final legal?)
          for (final l in legal) _tile(context, _legalIcon(l['kind']), '${l['title']}', url: '${l['url']}')
        else ...[
          _tile(context, Icons.gavel_outlined, 'Terms of service', url: '$siteOrigin/terms'),
          _tile(context, Icons.privacy_tip_outlined, 'Privacy policy', url: '$siteOrigin/privacy'),
        ],
      ]);
}

// ───────────────────────────── Language ─────────────────────────────

class LanguageScreen extends StatelessWidget {
  const LanguageScreen({super.key, required this.state});
  final AppState state;

  Future<void> _pick(BuildContext context, String l) async {
    try {
      await state.setLocale(l);
      if (context.mounted) showSnack(context, 'Language set to ${AppState.localeNames[l]}.');
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Language')),
        body: SafeArea(
          child: ListenableBuilder(
            listenable: state,
            builder: (context, _) => ListView(padding: const EdgeInsets.all(16), children: [
              const Text('Content from our server (services, FAQ and messages) is shown in this language where a translation is available.', style: TextStyle(color: ArjColors.ink600)),
              const SizedBox(height: 12),
              for (final l in state.enabledLocales)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(AppState.localeNames[l] ?? l),
                    trailing: (state.locale ?? 'en') == l ? const Icon(Icons.check, color: ArjColors.success700, semanticLabel: 'Selected') : null,
                    onTap: () => _pick(context, l),
                  ),
                ),
            ]),
          ),
        ),
      );
}

// ───────────────────────────── Vrat calendar ─────────────────────────────

class VratCalendarScreen extends StatefulWidget {
  const VratCalendarScreen({super.key, required this.state});
  final AppState state;
  @override
  State<VratCalendarScreen> createState() => _VratCalendarScreenState();
}

class _VratCalendarScreenState extends State<VratCalendarScreen> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic>? _place;
  bool _eventsOnly = true;

  String get _monthKey => DateFormat('yyyy-MM').format(_month);

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Vrat calendar')),
        body: SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: PeriodStepper(
                label: DateFormat('MMMM yyyy').format(_month),
                unit: 'month',
                onPrevious: () => setState(() => _month = DateTime(_month.year, _month.month - 1)),
                onNext: () => setState(() => _month = DateTime(_month.year, _month.month + 1)),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: PlaceField(api: widget.state.api, labelText: 'City', initial: _place == null ? null : '${_place!['label']}', onSelected: (p) => setState(() => _place = p)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: Row(children: [
                FilterChip(label: const Text('Only vrat & festival days'), selected: _eventsOnly, onSelected: (v) => setState(() => _eventsOnly = v)),
              ]),
            ),
            Expanded(
              child: AsyncView<Map<String, dynamic>>(
                key: ValueKey('$_monthKey|${_place?['id']}'),
                load: () => widget.state.api.get('/calendar', query: {'month': _monthKey, 'place_id': ?_place?['id']}),
                builder: (context, r, _) {
                  final days = unwrapList(r).where((d) => !_eventsOnly || ((d['events'] as List?) ?? const []).isNotEmpty).toList();
                  final place = ((r['meta'] as Map?)?['place'] as Map?)?['name'];
                  return ListView(padding: const EdgeInsets.all(16), children: [
                    if (place != null) Text('Dates for $place.', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                    if (days.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('Nothing listed for this month.', textAlign: TextAlign.center)),
                    for (final d in days)
                      Card(
                        margin: const EdgeInsets.only(top: 8),
                        child: ListTile(
                          leading: SizedBox(
                            width: 44,
                            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Text(formatAtOffset(d['date'], pattern: 'd'), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 18)),
                              Text(formatAtOffset(d['date'], pattern: 'EEE'), style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                            ]),
                          ),
                          title: ((d['events'] as List?) ?? const []).isEmpty
                              ? Text('${d['tithi'] ?? ''}')
                              : Text(((d['events'] as List)).join('\n'), style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text([d['tithi'], d['nakshatra']].whereType<Object>().join(' · ')),
                        ),
                      ),
                  ]);
                },
              ),
            ),
          ]),
        ),
      );
}

// ───────────────────────────── Rashi guide ─────────────────────────────

class RashiListScreen extends StatelessWidget {
  const RashiListScreen({super.key, required this.state});
  final AppState state;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Rashi guide')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(16), children: [
            for (var i = 0; i < 12; i++)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  title: Text(signNames[i]),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => RashiDetailScreen(state: state, slug: rashiSlugs[i], title: signNames[i]))),
                ),
              ),
          ]),
        ),
      );
}

class RashiDetailScreen extends StatelessWidget {
  const RashiDetailScreen({super.key, required this.state, required this.slug, this.title});
  final AppState state;
  final String slug;
  final String? title;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title ?? 'Rashi')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await state.api.get('/rashi/$slug', query: {'lang': state.locale == 'hi' ? 'hi' : 'en'})),
            builder: (context, r, _) {
              final transits = ((r['transits'] as List?) ?? const []).whereType<Map>();
              final horoscopes = ((r['horoscopes'] as List?) ?? const []).whereType<Map>();
              final nak = ((r['nakshatras'] as List?) ?? const []).whereType<Map>().map((n) => '${n['name']} (pada ${_list(n['padas'])})');
              return ListView(padding: const EdgeInsets.all(16), children: [
                Text('${r['symbol'] ?? ''} ${r['name']}${r['hi'] != null ? ' · ${r['hi']}' : ''}'.trim(), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                _fact('Lord', (r['lord'] as Map?)?['name']),
                _fact('Element', r['element']),
                _fact('Quality', r['quality']),
                _fact('Nature', r['nature']),
                _fact("Lord's day", r['lord_day']),
                _fact("Lord's gem", r['lord_gem']),
                if (nak.isNotEmpty) _fact('Nakshatras', nak.toList()),
                if (horoscopes.isNotEmpty) ...[
                  const SectionTitle('Rashifal now'),
                  for (final h in horoscopes)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${humanize(h['period'])} · ${humanize(h['topic'])}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          const SizedBox(height: 4),
                          SelectableText('${h['body'] ?? ''}', style: const TextStyle(height: 1.5)),
                        ]),
                      ),
                    ),
                ],
                if (transits.isNotEmpty) ...[
                  const SectionTitle('Transits from this Moon sign'),
                  for (final t in transits)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('${t['name']} in ${t['sign_name']}'),
                      subtitle: Text('House ${t['house']} from the Moon'),
                      trailing: t['favourable'] == true ? const ToneChip('Favourable', ArjColors.success700) : const ToneChip('Needs care', ArjColors.gold700),
                    ),
                ],
              ]);
            },
          ),
        ),
      );
}

// ───────────────────────────── Navagraha ─────────────────────────────

class NavagrahaScreen extends StatelessWidget {
  const NavagrahaScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Navagraha')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/navagraha')),
            isEmpty: (l) => l.isEmpty,
            builder: (context, list, _) => ListView(padding: const EdgeInsets.all(16), children: [
              const Text('The nine planets of Jyotish, where they are now (sidereal, Lahiri) and what they signify.', style: TextStyle(color: ArjColors.ink600)),
              for (final p in list)
                Card(
                  margin: const EdgeInsets.only(top: 12),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => GocharDetailScreen(state: state, planet: '${p['planet']}', title: '${p['name']}'))),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${p['name']}${p['hi'] != null ? ' · ${p['hi']}' : ''}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                        if (p['now'] is Map)
                          Text(
                            'Now in ${p['now']['sign_name']} ${p['now']['degree'] ?? ''}${p['now']['retro'] == true && !const ['rahu', 'ketu'].contains(p['planet']) ? ' (R)' : ''}',
                            style: const TextStyle(color: ArjColors.gold700),
                          ),
                        const SizedBox(height: 6),
                        _fact('Signifies', p['signifies']),
                        _fact('Rules', p['rules']),
                        _fact('Exalted in', p['exalted']),
                        _fact('Debilitated in', p['debilitated']),
                        _fact('Nakshatras', p['nakshatras']),
                        _fact('Mahadasha', p['dasha_years'] == null ? null : '${p['dasha_years']} years'),
                        _fact('Day', p['day']),
                        _fact('Gemstone', p['gem']),
                        _fact('Colour', p['colour']),
                        _fact('Time in each sign', p['stay']),
                      ]),
                    ),
                  ),
                ),
            ]),
          ),
        ),
      );
}

// ───────────────────────────── FAQ ─────────────────────────────

class FaqScreen extends StatelessWidget {
  const FaqScreen({super.key, required this.state});
  final AppState state;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('FAQ')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/faq')),
            isEmpty: (l) => l.isEmpty,
            emptyText: 'No questions yet.',
            builder: (context, list, _) => ListView(padding: const EdgeInsets.all(16), children: [
              for (final f in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ExpansionTile(
                    title: Text('${f['question']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    expandedCrossAxisAlignment: CrossAxisAlignment.start,
                    children: [SelectableText('${f['answer']}', style: const TextStyle(height: 1.5))],
                  ),
                ),
            ]),
          ),
        ),
      );
}

// ───────────────────────────── Contact (GET /site) ─────────────────────────────

String _digits(String s) => s.replaceAll(RegExp(r'[^\d+]'), '');

class ContactScreen extends StatelessWidget {
  const ContactScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Contact us')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await state.api.get('/site')),
            builder: (context, s, _) {
              final addr = (s['address'] as Map?) ?? const {};
              final hours = ((s['hours'] as List?) ?? const []).whereType<Map>();
              final phones = [s['phone'], s['phone2']].whereType<String>().where((p) => p.isNotEmpty).toSet();
              return ListView(padding: const EdgeInsets.all(16), children: [
                Text('${s['name'] ?? ''}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
                if (s['tagline'] != null) Text('${s['tagline']}', style: const TextStyle(color: ArjColors.ink600)),
                const SizedBox(height: 12),
                for (final p in phones)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(leading: const Icon(Icons.call_outlined), title: Text(p), subtitle: const Text('Call'), onTap: () => openExternal(context, 'tel:${_digits(p)}')),
                  ),
                if (s['whatsapp'] is String && (s['whatsapp'] as String).isNotEmpty)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: const Icon(Icons.chat_outlined),
                      title: Text('${s['whatsapp']}'),
                      subtitle: const Text('WhatsApp'),
                      onTap: () => openExternal(context, 'https://wa.me/${_digits('${s['whatsapp']}').replaceAll('+', '')}'),
                    ),
                  ),
                if (s['email'] is String && (s['email'] as String).isNotEmpty)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(leading: const Icon(Icons.email_outlined), title: Text('${s['email']}'), subtitle: const Text('Email'), onTap: () => openExternal(context, 'mailto:${s['email']}')),
                  ),
                if (addr['line'] != null)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: const Icon(Icons.place_outlined),
                      title: Text('${addr['line']}'),
                      subtitle: const Text('Open map'),
                      onTap: s['map_url'] == null ? null : () => openExternal(context, '${s['map_url']}'),
                    ),
                  ),
                if (hours.isNotEmpty) ...[
                  const SectionTitle('Office hours'),
                  for (final h in hours) _fact('${h['days']}', h['time']),
                ],
                if (s['languages'] is List) ...[const SectionTitle('Languages'), Text(_list(s['languages']))],
                if (s['social'] is List && (s['social'] as List).isNotEmpty) ...[
                  const SectionTitle('Follow us'),
                  for (final u in (s['social'] as List))
                    ListTile(contentPadding: EdgeInsets.zero, leading: const Icon(Icons.link), title: Text(Uri.tryParse('$u')?.host ?? '$u'), onTap: () => openExternal(context, '$u')),
                ],
              ]);
            },
          ),
        ),
      );
}
