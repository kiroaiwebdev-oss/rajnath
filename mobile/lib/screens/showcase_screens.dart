import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';

// ───────────────────────────── About, awards and gallery (GET /profile, /gallery) ─────────────────────────────

const portraitAsset = 'assets/images/portrait-navy-480.jpg';

/// Acharya Ji's profile (GET /profile): biography, stats, portraits and the About / Awards lists.
Future<Map<String, dynamic>> loadProfile(AppState state) async => unwrap(await state.api.get('/profile'));

String _s(Object? v) => v == null ? '' : '$v'.trim();

List<Map<String, dynamic>> _maps(Object? v) => ((v as List?) ?? const []).whereType<Map>().map((m) => m.cast<String, dynamic>()).toList();

Map<String, dynamic>? _section(Map profile, String key) => _maps(profile['sections']).where((s) => s['key'] == key).firstOrNull;

/// Entries of one profile section (award, education, position, expertise, research, book, media).
List<Map<String, dynamic>> profileEntries(Map profile, String key) => _maps(_section(profile, key)?['entries']);

String _sectionLabel(Map profile, String key, String fallback) => _s(_section(profile, key)?['label']).isEmpty ? fallback : _s(_section(profile, key)?['label']);

/// "Detail · year" (the year only when the title or detail doesn't already mention it).
String _secondary(Map e) {
  final title = _s(e['title']), detail = _s(e['detail']), year = _s(e['year']);
  return [if (detail.isNotEmpty) detail, if (year.isNotEmpty && !title.contains(year) && !detail.contains(year)) year].join(' · ');
}

String _plural(Object? n, String one) {
  final c = n is num ? n.toInt() : int.tryParse('${n ?? ''}') ?? 0;
  return '$c $one${c == 1 ? '' : 's'}';
}

typedef ViewerPhoto = ({String url, String caption});

void openPhotoViewer(BuildContext context, List<ViewerPhoto> photos, int index) =>
    Navigator.of(context).push(MaterialPageRoute(fullscreenDialog: true, builder: (_) => PhotoViewerScreen(photos: photos, initial: index)));

/// Stats cards (years, clients, awards, languages); empty and "0" values are skipped.
class ProfileStats extends StatelessWidget {
  const ProfileStats(this.stats, {super.key});
  final Object? stats;

  static const labels = {'years': 'Years of practice', 'clients': 'Clients guided', 'awards': 'Awards and honours', 'languages': 'Languages'};

  @override
  Widget build(BuildContext context) {
    final m = stats is Map ? stats as Map : const {};
    final items = [
      for (final l in labels.entries)
        if (_s(m[l.key]).isNotEmpty && _s(m[l.key]) != '0') (_s(m[l.key]), l.value),
    ];
    if (items.isEmpty) return const SizedBox.shrink();
    return LayoutBuilder(builder: (context, box) {
      final cols = box.maxWidth >= 600 ? 4 : 2;
      final w = (box.maxWidth - 8 * (cols - 1)) / cols;
      return Wrap(spacing: 8, runSpacing: 8, children: [
        for (final i in items)
          SizedBox(
            width: w,
            child: Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(i.$1, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, color: ArjColors.ink900)),
                  Text(i.$2, style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                ]),
              ),
            ),
          ),
      ]);
    });
  }
}

/// Lays out cards in 2–5 equal columns depending on the width.
class _CardGrid extends StatelessWidget {
  const _CardGrid({required this.children, this.minWidth = 160});
  final List<Widget> children;
  final double minWidth;

  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, box) {
        final cols = (box.maxWidth / minWidth).floor().clamp(2, 5);
        final w = (box.maxWidth - 12 * (cols - 1)) / cols;
        return Wrap(spacing: 12, runSpacing: 12, children: [for (final c in children) SizedBox(width: w, child: c)]);
      });
}

// ───────────────────────────── About ─────────────────────────────

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key, required this.state});
  final AppState state;

  static const _lists = [('education', 'Education'), ('position', 'Positions'), ('research', 'Research papers'), ('book', 'Books'), ('media', 'Media and columns')];

  Widget _entry(BuildContext context, Map e) {
    final sub = _secondary(e), link = _s(e['link_url']);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(_s(e['title']), style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: sub.isEmpty ? null : Text(sub, style: const TextStyle(color: ArjColors.ink600)),
      trailing: link.isEmpty ? null : const Icon(Icons.open_in_new, size: 18),
      onTap: link.isEmpty ? null : () => openExternal(context, link),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('About')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () => loadProfile(state),
            builder: (context, p, _) {
              final name = _s(p['name']);
              final job = _s(p['job']), headline = _s(p['headline']);
              final bio = [for (final b in (p['biography'] as List?) ?? const []) if (_s(b).isNotEmpty) _s(b)];
              final languages = [for (final l in (p['languages'] as List?) ?? const []) _s(l)].where((l) => l.isNotEmpty);
              final expertise = profileEntries(p, 'expertise');
              final awards = profileEntries(p, 'award');
              final web = (p['web'] as Map?) ?? const {};
              return ListView(padding: const EdgeInsets.all(16), children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: NetImage(_s((p['photos'] as Map?)?['about']), semanticLabel: name, fallback: Image.asset(portraitAsset, fit: BoxFit.cover, semanticLabel: name)),
                ),
                const SizedBox(height: 16),
                Text(name, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
                if (job.isNotEmpty) Text(job, style: const TextStyle(color: ArjColors.ink600)),
                if (headline.isNotEmpty && headline != job) Text(headline, style: const TextStyle(color: ArjColors.ink600)),
                const SizedBox(height: 12),
                for (final para in bio.isEmpty && _s(p['summary']).isNotEmpty ? [_s(p['summary'])] : bio)
                  Padding(padding: const EdgeInsets.only(bottom: 10), child: SelectableText(para, style: const TextStyle(height: 1.5))),
                if (languages.isNotEmpty) Text('Languages: ${languages.join(', ')}', style: const TextStyle(color: ArjColors.ink600)),
                const SizedBox(height: 12),
                ProfileStats(p['stats']),
                if (expertise.isNotEmpty) ...[
                  SectionTitle(_sectionLabel(p, 'expertise', 'Areas of expertise')),
                  Wrap(spacing: 8, runSpacing: 8, children: [for (final e in expertise) ToneChip(_s(e['title']), ArjColors.ink700)]),
                ],
                for (final (key, label) in _lists)
                  if (profileEntries(p, key).isNotEmpty) ...[
                    SectionTitle(_sectionLabel(p, key, label)),
                    for (final e in profileEntries(p, key)) _entry(context, e),
                  ],
                if (awards.isNotEmpty) ...[
                  SectionTitle(_sectionLabel(p, 'award', 'Awards and honours')),
                  for (final a in awards.take(5))
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('•  ', style: TextStyle(color: ArjColors.gold700)),
                        Expanded(child: Text(_s(a['line']).isEmpty ? _s(a['title']) : _s(a['line']))),
                      ]),
                    ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AwardsScreen(state: state, profile: p))),
                    icon: const Icon(Icons.emoji_events_outlined),
                    label: const Text('All awards'),
                  ),
                ],
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => GalleryScreen(state: state))),
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('Open the gallery'),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(onPressed: () => openExternal(context, _s(web['about']).isEmpty ? '$siteOrigin/about' : _s(web['about'])), icon: const Icon(Icons.open_in_new), label: const Text('Read more on the website')),
              ]);
            },
          ),
        ),
      );
}

// ───────────────────────────── Awards ─────────────────────────────

/// Award photos, then the awards grouped International / National / other labels / "More honours".
class AwardsScreen extends StatelessWidget {
  const AwardsScreen({super.key, required this.state, this.profile});
  final AppState state;

  /// Already-loaded profile (from the About screen); loaded again when null.
  final Map<String, dynamic>? profile;

  static int _rank(Map e) => switch (e['group']) { 'international' => 0, 'national' => 1, _ => _s(e['group_label']).isEmpty ? 3 : 2 };

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Awards and honours')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => profile ?? await loadProfile(state),
            isEmpty: (p) => profileEntries(p, 'award').isEmpty,
            emptyText: 'No awards listed yet.',
            builder: (context, p, _) {
              final awards = profileEntries(p, 'award');
              final withPhoto = awards.where((a) => _s(a['image_url']).isNotEmpty).toList();
              final photos = [for (final a in withPhoto) (url: _s(a['image_url']), caption: _s(a['line']).isEmpty ? _s(a['title']) : _s(a['line']))];
              final groups = <String, List<Map<String, dynamic>>>{};
              for (final a in [...awards]..sort((x, y) => _rank(x).compareTo(_rank(y)))) {
                groups.putIfAbsent(_rank(a) == 3 ? 'More honours' : _s(a['group_label']), () => []).add(a);
              }
              final web = _s((p['web'] as Map?)?['awards']);
              return ListView(padding: const EdgeInsets.all(16), children: [
                if (withPhoto.isNotEmpty)
                  _CardGrid(children: [
                    for (final (i, a) in withPhoto.indexed)
                      Card(
                        margin: EdgeInsets.zero,
                        clipBehavior: Clip.antiAlias,
                        child: InkWell(
                          key: ValueKey('award-photo-$i'),
                          onTap: () => openPhotoViewer(context, photos, i),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                            AspectRatio(aspectRatio: 4 / 3, child: NetImage(_s(a['image_url']), semanticLabel: _s(a['title']))),
                            Padding(
                              padding: const EdgeInsets.all(10),
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(_s(a['title']), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
                                if (_s(a['year']).isNotEmpty) Text(_s(a['year']), style: const TextStyle(fontSize: 12, color: ArjColors.gold700)),
                              ]),
                            ),
                          ]),
                        ),
                      ),
                  ]),
                for (final g in groups.entries) ...[
                  SectionTitle(g.key),
                  for (final a in g.value)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: _s(a['year']).isEmpty
                          ? const Icon(Icons.emoji_events_outlined, color: ArjColors.gold700)
                          : SizedBox(width: 48, child: Text(_s(a['year']), style: const TextStyle(color: ArjColors.gold700, fontWeight: FontWeight.w600))),
                      title: Text(_s(a['title']), style: const TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: _s(a['detail']).isEmpty ? null : Text(_s(a['detail']), style: const TextStyle(color: ArjColors.ink600)),
                      trailing: _s(a['link_url']).isEmpty ? null : const Icon(Icons.open_in_new, size: 18),
                      onTap: _s(a['link_url']).isEmpty ? null : () => openExternal(context, _s(a['link_url'])),
                    ),
                ],
                const SizedBox(height: 16),
                OutlinedButton.icon(onPressed: () => openExternal(context, web.isEmpty ? '$siteOrigin/awards' : web), icon: const Icon(Icons.open_in_new), label: const Text('See on the website')),
              ]);
            },
          ),
        ),
      );
}

// ───────────────────────────── Gallery ─────────────────────────────

class GalleryScreen extends StatefulWidget {
  const GalleryScreen({super.key, required this.state});
  final AppState state;
  @override
  State<GalleryScreen> createState() => _GalleryScreenState();
}

class _GalleryScreenState extends State<GalleryScreen> {
  String? _category;
  List<Map<String, dynamic>> _categories = const [];

  Future<List<Map<String, dynamic>>> _load() async {
    final r = await widget.state.api.get('/gallery', query: _category == null ? null : {'category': _category});
    final meta = r['meta'];
    if (meta is Map && meta['categories'] is List && mounted) setState(() => _categories = _maps(meta['categories']));
    return unwrapList(r);
  }

  Widget _chip(String label, String? key) => Padding(
        padding: const EdgeInsets.only(right: 8),
        child: ChoiceChip(label: Text(label), selected: _category == key, onSelected: (_) => setState(() => _category = key)),
      );

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Gallery')),
        body: SafeArea(
          child: Column(children: [
            if (_categories.isNotEmpty)
              SizedBox(
                height: 56,
                child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8), children: [
                  _chip('All', null),
                  for (final c in _categories) _chip(_s(c['label']), _s(c['key'])),
                ]),
              ),
            Expanded(
              child: AsyncView<List<Map<String, dynamic>>>(
                key: ValueKey(_category ?? ''),
                load: _load,
                isEmpty: (l) => l.isEmpty,
                emptyText: 'No albums here yet.',
                builder: (context, albums, _) => ListView(padding: const EdgeInsets.all(16), children: [
                  _CardGrid(minWidth: 220, children: [for (final a in albums) _AlbumCard(state: widget.state, album: a)]),
                ]),
              ),
            ),
          ]),
        ),
      );
}

String _whenWhere(Map a) => [formatWhen(a['event_date'], pattern: 'd MMM yyyy'), _s(a['location'])].where((s) => s.isNotEmpty).join(' · ');

class _AlbumCard extends StatelessWidget {
  const _AlbumCard({required this.state, required this.album});
  final AppState state;
  final Map<String, dynamic> album;

  @override
  Widget build(BuildContext context) {
    final a = album;
    final meta = _whenWhere(a);
    final counts = [
      if ((a['photo_count'] ?? 0) != 0) _plural(a['photo_count'], 'photo'),
      if ((a['video_count'] ?? 0) != 0) _plural(a['video_count'], 'video'),
    ].join(' · ');
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AlbumScreen(state: state, slug: _s(a['slug'])))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AspectRatio(aspectRatio: 4 / 3, child: NetImage(_s(a['cover_url']), semanticLabel: _s(a['title']))),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_s(a['title']), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
              if (meta.isNotEmpty) Text(meta, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
              if (counts.isNotEmpty) Text(counts, style: const TextStyle(fontSize: 12, color: ArjColors.gold700)),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ───────────────────────────── Album ─────────────────────────────

class AlbumScreen extends StatelessWidget {
  const AlbumScreen({super.key, required this.state, required this.slug});
  final AppState state;
  final String slug;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Gallery')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await state.api.get('/gallery/${Uri.encodeComponent(slug)}')),
            builder: (context, a, _) {
              final items = _maps(a['items']);
              final photoItems = items.where((i) => i['kind'] == 'photo').toList();
              final photos = [for (final p in photoItems) (url: _s(p['url']).isEmpty ? _s(p['thumb_url']) : _s(p['url']), caption: _s(p['caption']))];
              final meta = _whenWhere(a);
              return CustomScrollView(slivers: [
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                  sliver: SliverList.list(children: [
                    Text(_s(a['title']), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
                    if (meta.isNotEmpty) Text(meta, style: const TextStyle(color: ArjColors.ink600)),
                    if (_s(a['description']).isNotEmpty) ...[const SizedBox(height: 8), SelectableText(_s(a['description']), style: const TextStyle(height: 1.5))],
                    if (items.isEmpty) const Padding(padding: EdgeInsets.only(top: 24), child: Text('No photos in this album yet.')),
                  ]),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  sliver: SliverGrid.builder(
                    gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 140, mainAxisSpacing: 4, crossAxisSpacing: 4),
                    itemCount: items.length,
                    itemBuilder: (context, i) {
                      final it = items[i];
                      final video = it['kind'] == 'video';
                      final caption = _s(it['caption']);
                      return Material(
                        child: InkWell(
                          key: ValueKey('album-item-$i'),
                          onTap: video ? () => openExternal(context, _s(it['url'])) : () => openPhotoViewer(context, photos, photoItems.indexOf(it)),
                          child: Stack(fit: StackFit.expand, children: [
                            NetImage(_s(it['thumb_url']), semanticLabel: caption.isNotEmpty ? caption : (video ? 'Video' : 'Photo ${photoItems.indexOf(it) + 1}')),
                            if (video)
                              const Center(
                                child: DecoratedBox(
                                  decoration: BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                                  child: Padding(padding: EdgeInsets.all(6), child: Icon(Icons.play_arrow, color: Colors.white, size: 32, semanticLabel: 'Play video')),
                                ),
                              ),
                          ]),
                        ),
                      );
                    },
                  ),
                ),
              ]);
            },
          ),
        ),
      );
}

// ───────────────────────────── Full-screen photo viewer ─────────────────────────────

/// Swipe between photos, pinch to zoom; caption at the bottom and "n / total" at the top.
class PhotoViewerScreen extends StatefulWidget {
  const PhotoViewerScreen({super.key, required this.photos, this.initial = 0});
  final List<ViewerPhoto> photos;
  final int initial;
  @override
  State<PhotoViewerScreen> createState() => _PhotoViewerScreenState();
}

class _PhotoViewerScreenState extends State<PhotoViewerScreen> {
  late int _i = widget.initial.clamp(0, widget.photos.isEmpty ? 0 : widget.photos.length - 1);
  late final _pages = PageController(initialPage: _i);
  bool _zoomed = false;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.photos.length;
    final caption = n == 0 ? '' : widget.photos[_i].caption;
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Stack(children: [
          PageView.builder(
            controller: _pages,
            itemCount: n,
            // while zoomed in, one-finger drags pan the photo instead of turning the page
            physics: _zoomed ? const NeverScrollableScrollPhysics() : null,
            onPageChanged: (i) => setState(() {
              _i = i;
              _zoomed = false;
            }),
            itemBuilder: (_, i) => _ZoomablePhoto(photo: widget.photos[i], index: i, onZoomed: (z) {
              if (z != _zoomed) setState(() => _zoomed = z);
            }),
          ),
          if (n > 0) Positioned(top: 16, left: 16, child: Text('${_i + 1} / $n', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600))),
          Positioned(top: 4, right: 4, child: IconButton(tooltip: 'Close', color: Colors.white, icon: const Icon(Icons.close), onPressed: () => Navigator.of(context).pop())),
          if (caption.isNotEmpty)
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                color: Colors.black54,
                padding: const EdgeInsets.all(16),
                child: Text(caption, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, height: 1.4)),
              ),
            ),
        ]),
      ),
    );
  }
}

/// One page of the viewer: pinch to zoom, pan while zoomed in.
class _ZoomablePhoto extends StatefulWidget {
  const _ZoomablePhoto({required this.photo, required this.index, required this.onZoomed});
  final ViewerPhoto photo;
  final int index;
  final ValueChanged<bool> onZoomed;
  @override
  State<_ZoomablePhoto> createState() => _ZoomablePhotoState();
}

class _ZoomablePhotoState extends State<_ZoomablePhoto> {
  final _zoom = TransformationController();

  @override
  void dispose() {
    _zoom.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => InteractiveViewer(
        transformationController: _zoom,
        maxScale: 5,
        onInteractionEnd: (_) => widget.onZoomed(_zoom.value.getMaxScaleOnAxis() > 1.01),
        child: Center(
          child: NetImage(widget.photo.url, fit: BoxFit.contain, dark: true, semanticLabel: widget.photo.caption.isEmpty ? 'Photo ${widget.index + 1}' : widget.photo.caption),
        ),
      );
}
