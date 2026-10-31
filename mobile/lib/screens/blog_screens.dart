import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';
import 'services_screens.dart';

String _s(Object? v) => v == null ? '' : '$v'.trim();

/// "2025-03-14" → "14 Mar 2025" (raw text if it does not parse).
String articleDate(Object? iso) {
  final d = DateTime.tryParse(_s(iso));
  return d == null ? _s(iso) : DateFormat('d MMM yyyy').format(d);
}

/// "5 min read · 14 Mar 2025".
String articleMeta(Map a) => [
      if ((a['reading_minutes'] as num?) != null && (a['reading_minutes'] as num) > 0) '${a['reading_minutes']} min read',
      if (_s(a['published_at']).isNotEmpty) articleDate(a['published_at']),
    ].join(' · ');

/// "kundli-consultation" → "Kundli consultation".
String slugLabel(Object? slug) => humanize(_s(slug).replaceAll('-', ' '));

// ───────────────────────────── List ─────────────────────────────

/// Articles from GET /blog: search, category chips and paged "Load more".
class BlogScreen extends StatefulWidget {
  const BlogScreen({super.key, required this.state, this.initialCategory});
  final AppState state;
  final String? initialCategory;
  @override
  State<BlogScreen> createState() => _BlogScreenState();
}

class _BlogScreenState extends State<BlogScreen> {
  final _search = TextEditingController();
  late String? _category = widget.initialCategory;
  String _q = '';
  List<Map<String, dynamic>> _items = [];
  List<Map> _categories = [];
  int _page = 0, _last = 1;
  bool _loading = false;
  ApiException? _error;

  /// Bumped on every new search so answers to an older query are dropped.
  int _gen = 0;

  @override
  void initState() {
    super.initState();
    _fetch(reset: true);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _fetch({bool reset = false}) async {
    final gen = reset ? ++_gen : _gen;
    final page = reset ? 1 : _page + 1;
    setState(() {
      _loading = true;
      _error = null;
      if (reset) _items = [];
    });
    try {
      final r = await widget.state.api.get('/blog', query: {'category': ?_category, if (_q.isNotEmpty) 'q': _q, 'page': page});
      if (!mounted || gen != _gen) return;
      final meta = (r['meta'] as Map?) ?? const {};
      setState(() {
        _items = [..._items, ...unwrapList(r)];
        final cats = (meta['categories'] as List?)?.whereType<Map>().toList();
        if (cats != null && cats.isNotEmpty) _categories = cats;
        _page = (meta['page'] as num?)?.toInt() ?? page;
        _last = (meta['last_page'] as num?)?.toInt() ?? _page;
      });
    } on ApiException catch (e) {
      if (mounted && gen == _gen) setState(() => _error = e);
    } finally {
      if (mounted && gen == _gen) setState(() => _loading = false);
    }
  }

  void _setCategory(String? c) {
    if (c == _category) return;
    _category = c;
    _fetch(reset: true);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Articles')),
        body: SafeArea(
          child: RefreshIndicator(
            onRefresh: () => _fetch(reset: true),
            child: ListView(padding: const EdgeInsets.all(16), children: [
              Text('Insights on Jyotish, Vastu, puja and remedies, written for everyday life.', style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: ArjColors.ink600)),
              const SizedBox(height: 12),
              TextField(
                controller: _search,
                decoration: InputDecoration(
                  labelText: 'Search articles',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _q.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Clear search',
                          icon: const Icon(Icons.clear),
                          onPressed: () {
                            _search.clear();
                            _q = '';
                            _fetch(reset: true);
                          }),
                ),
                textInputAction: TextInputAction.search,
                onSubmitted: (v) {
                  _q = v.trim();
                  _fetch(reset: true);
                },
              ),
              if (_categories.isNotEmpty) ...[
                const SizedBox(height: 12),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  ChoiceChip(label: const Text('All'), selected: _category == null, onSelected: (_) => _setCategory(null)),
                  for (final c in _categories) ChoiceChip(label: Text(_s(c['label'])), selected: _category == c['key'], onSelected: (_) => _setCategory(_s(c['key']))),
                ]),
              ],
              const SizedBox(height: 16),
              for (final a in _items) Padding(padding: const EdgeInsets.only(bottom: 16), child: ArticleCard(state: widget.state, article: a)),
              if (_error != null)
                ErrorPanel(message: _error!.message, offline: _error!.isOffline, onRetry: () => _fetch(reset: _items.isEmpty))
              else if (_loading)
                const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator(semanticsLabel: 'Loading articles')))
              else if (_items.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(_q.isNotEmpty || _category != null ? 'No articles match. Try another word or category.' : 'No articles yet. Please check back soon.', textAlign: TextAlign.center),
                )
              else if (_page < _last)
                OutlinedButton(onPressed: _fetch, child: const Text('Load more')),
            ]),
          ),
        ),
      );
}

/// Cover, category, title, excerpt and meta; opens [ArticleScreen].
class ArticleCard extends StatelessWidget {
  const ArticleCard({super.key, required this.state, required this.article, this.compact = false});
  final AppState state;
  final Map article;

  /// Narrow card for horizontal strips (shorter text).
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final a = article;
    final title = _s(a['title']);
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ArticleScreen(state: state, slug: _s(a['slug']), title: title))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          AspectRatio(aspectRatio: 16 / 9, child: NetImage(_s(a['cover_url']), semanticLabel: title)),
          Padding(
            padding: EdgeInsets.all(compact ? 12 : 16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (_s(a['category_label']).isNotEmpty) ToneChip(_s(a['category_label']), ArjColors.gold700),
              const SizedBox(height: 8),
              Text(title, maxLines: compact ? 2 : 3, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
              if (!compact && _s(a['excerpt']).isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(_s(a['excerpt']), maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: ArjColors.ink600, height: 1.4)),
              ],
              if (articleMeta(a).isNotEmpty) ...[
                const SizedBox(height: 8),
                Row(children: [
                  const Icon(Icons.schedule, size: 14, color: ArjColors.ink600),
                  const SizedBox(width: 4),
                  Flexible(child: Text(articleMeta(a), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: ArjColors.ink600))),
                ]),
              ],
            ]),
          ),
        ]),
      ),
    );
  }
}

// ───────────────────────────── Detail ─────────────────────────────

/// One article from GET /blog/{slug}: body blocks, tags, related services and articles.
class ArticleScreen extends StatelessWidget {
  const ArticleScreen({super.key, required this.state, required this.slug, this.title});
  final AppState state;
  final String slug;
  final String? title;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Article')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await state.api.get('/blog/$slug')),
            builder: (context, a, _) {
              final blocks = ((a['blocks'] as List?) ?? const []).whereType<Map>();
              final tags = ((a['tags'] as List?) ?? const []).map(_s).where((t) => t.isNotEmpty).toList();
              final services = ((a['services'] as List?) ?? const []).map(_s).where((t) => t.isNotEmpty).toList();
              final related = ((a['related'] as List?) ?? const []).whereType<Map>().toList();
              final web = _s(a['web_url']);
              return ListView(padding: const EdgeInsets.all(16), children: [
                if (_s(a['cover_url']).isNotEmpty)
                  ClipRRect(borderRadius: BorderRadius.circular(20), child: AspectRatio(aspectRatio: 16 / 9, child: NetImage(_s(a['cover_url']), semanticLabel: _s(a['title'])))),
                const SizedBox(height: 16),
                if (_s(a['category_label']).isNotEmpty) Align(alignment: Alignment.centerLeft, child: ToneChip(_s(a['category_label']), ArjColors.gold700)),
                const SizedBox(height: 8),
                Text(_s(a['title']), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600, color: ArjColors.ink900)),
                if (articleMeta(a).isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text(articleMeta(a), style: const TextStyle(color: ArjColors.ink600))),
                if (_s(a['excerpt']).isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(_s(a['excerpt']), style: Theme.of(context).textTheme.titleMedium?.copyWith(color: ArjColors.ink700, height: 1.5, fontWeight: FontWeight.w400)),
                  ),
                const Divider(height: 32),
                for (final b in blocks) ArticleBlock(b),
                if (tags.isNotEmpty) ...[
                  const SectionTitle('Tags'),
                  Wrap(spacing: 8, runSpacing: 8, children: [for (final t in tags) Chip(label: Text('#$t'), visualDensity: VisualDensity.compact)]),
                ],
                if (services.isNotEmpty) ...[
                  const SectionTitle('Related services'),
                  for (final s in services)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(alignment: Alignment.centerLeft),
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ServiceDetailScreen(state: state, slug: s))),
                        icon: const Icon(Icons.auto_awesome_outlined),
                        label: Text(slugLabel(s)),
                      ),
                    ),
                ],
                if (related.isNotEmpty) ...[
                  const SectionTitle('Related articles'),
                  for (final r in related)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      clipBehavior: Clip.antiAlias,
                      child: ListTile(
                        leading: ClipRRect(borderRadius: BorderRadius.circular(8), child: SizedBox(width: 64, height: 48, child: NetImage(_s(r['cover_url'])))),
                        title: Text(_s(r['title']), maxLines: 2, overflow: TextOverflow.ellipsis),
                        subtitle: Text(articleMeta(r)),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ArticleScreen(state: state, slug: _s(r['slug']), title: _s(r['title'])))),
                      ),
                    ),
                ],
                if (web.isNotEmpty) ...[
                  const SizedBox(height: 20),
                  FilledButton.icon(onPressed: () => openExternal(context, web), icon: const Icon(Icons.open_in_new), label: const Text('Read on the website')),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: web));
                      if (context.mounted) showSnack(context, 'Link copied. Paste it to share.');
                    },
                    icon: const Icon(Icons.share_outlined),
                    label: const Text('Copy link to share'),
                  ),
                ],
                const SizedBox(height: 20),
                const Text('Astrological guidance is traditional and interpretive. It does not replace medical, legal or financial advice.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
              ]);
            },
          ),
        ),
      );
}

/// One body block: h2 / h3 heading, paragraph, bullet item or quote.
class ArticleBlock extends StatelessWidget {
  const ArticleBlock(this.block, {super.key});
  final Map block;

  @override
  Widget build(BuildContext context) {
    final text = _s(block['text']);
    if (text.isEmpty) return const SizedBox.shrink();
    final t = Theme.of(context).textTheme;
    return switch (block['type']) {
      'h2' => Padding(padding: const EdgeInsets.only(top: 16, bottom: 8), child: Semantics(header: true, child: Text(text, style: t.titleLarge?.copyWith(fontWeight: FontWeight.w600, color: ArjColors.ink900)))),
      'h3' => Padding(padding: const EdgeInsets.only(top: 12, bottom: 6), child: Semantics(header: true, child: Text(text, style: t.titleMedium?.copyWith(fontWeight: FontWeight.w600, color: ArjColors.ink700)))),
      'li' => Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 6),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(margin: const EdgeInsets.only(top: 8, right: 10), width: 6, height: 6, decoration: const BoxDecoration(color: ArjColors.gold500, shape: BoxShape.circle)),
            Expanded(child: SelectableText(text, style: const TextStyle(height: 1.5))),
          ]),
        ),
      'quote' => Container(
          margin: const EdgeInsets.symmetric(vertical: 10),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: ArjColors.gold50, borderRadius: BorderRadius.circular(12), border: const Border(left: BorderSide(color: ArjColors.gold500, width: 4))),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Icon(Icons.format_quote, color: ArjColors.gold700),
            const SizedBox(width: 8),
            Expanded(child: SelectableText(text, style: const TextStyle(fontStyle: FontStyle.italic, height: 1.5, color: ArjColors.ink700))),
          ]),
        ),
      _ => Padding(padding: const EdgeInsets.only(bottom: 12), child: SelectableText(text, style: const TextStyle(height: 1.6))),
    };
  }
}

// ───────────────────────────── Courses ─────────────────────────────

/// Planned courses (the website's /courses page). Admissions are not open yet.
const plannedCourses = [
  (Icons.auto_awesome_outlined, 'Vedic astrology for beginners', 'Rashis, bhavas, grahas and reading a birth chart step by step.'),
  (Icons.pin_outlined, 'Numerology', 'Mulank, bhagyank and name numbers, and how they are used in practice.'),
  (Icons.back_hand_outlined, 'Palmistry', 'Lines, mounts and hand shapes in the traditional Indian method.'),
  (Icons.home_work_outlined, 'Vastu Shastra', 'Directions, the five elements and practical corrections for homes and offices.'),
  (Icons.local_fire_department_outlined, 'Karmkand and puja vidhi', 'Sankalp, mantra and the correct sequence of common pujas and samskaras.'),
  (Icons.insights_outlined, 'Advanced predictive astrology', 'Dasha, gochar and divisional charts for timing events.'),
];

class CoursesScreen extends StatelessWidget {
  const CoursesScreen({super.key, required this.state});
  final AppState state;

  void _notify(BuildContext context, String title) =>
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => RequestScreen(state: state, title: title)));

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Courses')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(16), children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(20),
                gradient: const LinearGradient(colors: [ArjColors.ink900, ArjColors.ink700], begin: Alignment.topLeft, end: Alignment.bottomRight),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('JYOTIRVEDA VIGYAN SANSTHAN', style: Theme.of(context).textTheme.labelMedium?.copyWith(color: ArjColors.gold200, letterSpacing: 1.5)),
                const SizedBox(height: 8),
                Text('Learn from Acharya Ji', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                const Text('Structured courses in the traditional sciences are being prepared. Admissions are not open yet.', style: TextStyle(color: Colors.white70, height: 1.4)),
              ]),
            ),
            const SectionTitle('Planned courses'),
            for (final (icon, title, blurb) in plannedCourses)
              Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    CircleAvatar(backgroundColor: ArjColors.gold50, child: Icon(icon, color: ArjColors.gold700)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                        const SizedBox(height: 4),
                        Text(blurb, style: const TextStyle(color: ArjColors.ink600, height: 1.4)),
                        const SizedBox(height: 8),
                        const ToneChip('Coming soon', ArjColors.gold700),
                      ]),
                    ),
                  ]),
                ),
              ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: () => _notify(context, 'Courses: tell me when admissions open'),
              icon: const Icon(Icons.notifications_active_outlined),
              label: const Text('Tell me when admissions open'),
            ),
            const SizedBox(height: 8),
            const Text('Leave your number and our team will call you when the first batch opens.', textAlign: TextAlign.center, style: TextStyle(color: ArjColors.ink600)),
          ]),
        ),
      );
}
