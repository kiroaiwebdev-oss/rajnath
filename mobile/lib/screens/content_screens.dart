import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'astro_screens.dart';

/// Sidereal sign names by index (0 = Mesha), matching the API.
const signNames = ['Mesha (Aries)', 'Vrishabha (Taurus)', 'Mithuna (Gemini)', 'Karka (Cancer)', 'Simha (Leo)', 'Kanya (Virgo)',
    'Tula (Libra)', 'Vrishchika (Scorpio)', 'Dhanu (Sagittarius)', 'Makara (Capricorn)', 'Kumbha (Aquarius)', 'Meena (Pisces)'];

String signName(Object? i) => i is num && i >= 0 && i < 12 ? signNames[i.toInt()] : '${i ?? ''}';

String _date(Object? ymd, [String pattern = 'd MMM yyyy']) {
  final d = DateTime.tryParse('${ymd ?? ''}');
  return d == null ? '${ymd ?? ''}' : DateFormat(pattern).format(d);
}

/// Horizontally scrolling row of choice chips.
Widget _chipRow(List<Widget> chips) => SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Row(children: [for (final c in chips) Padding(padding: const EdgeInsets.only(right: 8), child: c)]),
    );

class _Note extends StatelessWidget {
  const _Note(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: ArjColors.gold50, borderRadius: BorderRadius.circular(12), border: Border.all(color: ArjColors.gold200)),
        child: Text(text),
      );
}

// ───────────────────────────── Rashifal ─────────────────────────────

class HoroscopeScreen extends StatefulWidget {
  const HoroscopeScreen({super.key, required this.state});
  final AppState state;
  @override
  State<HoroscopeScreen> createState() => _HoroscopeScreenState();
}

class _HoroscopeScreenState extends State<HoroscopeScreen> {
  String _kind = 'rashi', _period = 'daily', _topic = 'general', _lang = 'hi';
  int _year = DateTime.now().year;

  bool get _yearly => _period == 'yearly' || _period == 'halfyearly';
  bool get _hi => _lang == 'hi';

  Map<String, dynamic> get _query => {
        'kind': _kind,
        'period': _period,
        if (_kind == 'rashi') 'topic': _topic,
        'lang': _lang,
        if (_yearly) 'year': _year,
      };

  String _label(Map t) => '${_hi ? t['hi'] ?? t['en'] : t['en'] ?? t['hi']}';

  List<Widget> _chips(Object? items, String selected, ValueChanged<String> onSelected) => [
        for (final t in (items as List? ?? const []).whereType<Map>())
          ChoiceChip(label: Text(_label(t)), selected: t['key'] == selected, onSelected: (_) => setState(() => onSelected('${t['key']}'))),
      ];

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Rashifal')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await widget.state.api.get('/horoscopes/types')),
            builder: (context, types, _) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                child: SegmentedButton<String>(
                  segments: const [ButtonSegment(value: 'hi', label: Text('हिन्दी')), ButtonSegment(value: 'en', label: Text('English'))],
                  selected: {_lang},
                  onSelectionChanged: (s) => setState(() => _lang = s.first),
                ),
              ),
              _chipRow(_chips(types['kinds'], _kind, (k) => _kind = k)),
              _chipRow(_chips(types['periods'], _period, (k) => _period = k)),
              if (_kind == 'rashi') _chipRow(_chips(types['topics'], _topic, (k) => _topic = k)),
              if (_yearly)
                _chipRow([
                  for (final y in [DateTime.now().year, DateTime.now().year + 1])
                    ChoiceChip(label: Text('$y'), selected: _year == y, onSelected: (_) => setState(() => _year = y)),
                ]),
              Expanded(
                child: AsyncView<Map<String, dynamic>>(
                  key: ValueKey(_query.toString()),
                  load: () => widget.state.api.get('/horoscopes', query: _query),
                  builder: (context, r, _) => _list(context, r),
                ),
              ),
            ]),
          ),
        ),
      );

  Widget _list(BuildContext context, Map<String, dynamic> r) {
    final data = unwrapList(r);
    final meta = (r['meta'] as Map?) ?? const {};
    final hi = (meta['lang'] ?? _lang) == 'hi';
    if (data.isEmpty) {
      return ListView(padding: const EdgeInsets.all(32), children: [
        const Text('Not published yet', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600)),
        if (hi) const Text('अभी प्रकाशित नहीं हुआ', textAlign: TextAlign.center, style: TextStyle(color: ArjColors.ink600)),
      ]);
    }
    final raw = meta['subjects'];
    final subjects = <(String, String)>[
      if (raw is Map) for (final e in raw.entries) ('${e.key}', '${e.value}'),
      if (raw is List) for (var i = 0; i < raw.length; i++) ('$i', '${raw[i]}'),
    ];
    if (subjects.isEmpty) {
      for (final d in data) {
        if (!subjects.any((s) => s.$2 == '${d['name']}')) subjects.add(('${d['subject']}', '${d['name']}'));
      }
    }
    final byName = <String, List<Map<String, dynamic>>>{};
    final bySubject = <String, List<Map<String, dynamic>>>{};
    for (final d in data) {
      byName.putIfAbsent('${d['name']}', () => []).add(d);
      bySubject.putIfAbsent('${d['subject']}', () => []).add(d);
    }
    return ListView(padding: const EdgeInsets.all(16), children: [
      for (final s in subjects)
        Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Builder(builder: (context) {
              final items = byName[s.$2] ?? (byName.isEmpty ? bySubject[s.$1] : null) ?? const [];
              return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(s.$2, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                if (items.isEmpty) Text(hi ? 'जल्द आ रहा है' : 'Coming soon', style: const TextStyle(color: ArjColors.ink600)),
                for (final i in items) ...[
                  if (i['starts_on'] != null && i['ends_on'] != null && i['starts_on'] != i['ends_on'])
                    Text('${_date(i['starts_on'])} – ${_date(i['ends_on'])}', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                  SelectableText('${i['body'] ?? ''}', style: const TextStyle(height: 1.5)),
                  const SizedBox(height: 6),
                ],
              ]);
            }),
          ),
        ),
      const Text('Rashifal is a general reading for each sign. Your own chart gives a fuller picture.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
    ]);
  }
}

// ───────────────────────────── Grah Gochar ─────────────────────────────

String _retroMark(Map p) => p['retro'] == true && !const ['rahu', 'ketu'].contains(p['planet']) ? ' (R)' : '';

class GocharScreen extends StatelessWidget {
  const GocharScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Grah Gochar')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/gochar')),
            isEmpty: (l) => l.isEmpty,
            builder: (context, list, _) => ListView(padding: const EdgeInsets.all(16), children: [
              const Text('Where the planets are now (sidereal, Lahiri). Tap a planet for its dates and effects by Moon sign.', style: TextStyle(color: ArjColors.ink600)),
              const SizedBox(height: 12),
              for (final p in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text('${p['name']}${_retroMark(p)}${p['hi'] != null ? ' · ${p['hi']}' : ''}'),
                    subtitle: Text('${p['sign_name']} ${p['degree'] ?? ''} · ${p['nakshatra'] ?? ''}'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => GocharDetailScreen(state: state, planet: '${p['planet']}', title: '${p['name']}'))),
                  ),
                ),
            ]),
          ),
        ),
      );
}

class GocharDetailScreen extends StatefulWidget {
  const GocharDetailScreen({super.key, required this.state, required this.planet, this.title});
  final AppState state;
  final String planet;
  final String? title;
  @override
  State<GocharDetailScreen> createState() => _GocharDetailScreenState();
}

class _GocharDetailScreenState extends State<GocharDetailScreen> {
  final _now = DateTime.now();
  late int _year = _now.year;
  late DateTime _month = DateTime(_now.year, _now.month);

  bool get _moon => widget.planet == 'moon';

  Map<String, dynamic> get _query => _moon ? {'month': DateFormat('yyyy-MM').format(_month)} : {'year': _year};

  void _step(int by) => setState(() {
        if (_moon) {
          _month = DateTime(_month.year, _month.month + by);
        } else {
          _year += by;
        }
      });

  static String _event(Map e) {
    final deg = e['degree'] != null ? ' at ${e['degree']}' : '';
    return switch (e['type']) {
      'ingress' => 'Enters ${e['sign_name']}${e['retro'] == true ? ' (retrograde)' : ''}',
      'retrograde' => 'Turns retrograde in ${e['sign_name']}$deg',
      'direct' => 'Turns direct in ${e['sign_name']}$deg',
      _ => '${humanize(e['type'])} · ${e['sign_name']}',
    };
  }

  static const _factLabels = {
    'signifies': 'Signifies',
    'stay': 'Time in each sign',
    'gem': 'Gemstone',
    'day': 'Day',
    'colour': 'Colour',
    'exalt': 'Exalted in',
    'debil': 'Debilitated in',
    'nakshatras': 'Lord of nakshatras',
    'dasha_years': 'Mahadasha',
  };

  static String _fact(String k, Object? v) => switch (k) {
        'exalt' when v is List && v.isNotEmpty => '${signName(v.first)}${v.length > 1 ? ' ${v[1]}°' : ''}',
        'debil' => v is List ? v.map(signName).join(', ') : signName(v),
        'dasha_years' => '$v years',
        _ => v is List ? v.join(', ') : '$v',
      };

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title ?? planetName(widget.planet))),
        body: SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: PeriodStepper(
                label: _moon ? DateFormat('MMMM yyyy').format(_month) : '$_year',
                unit: _moon ? 'month' : 'year',
                onPrevious: () => _step(-1),
                onNext: () => _step(1),
              ),
            ),
            Expanded(
              child: AsyncView<Map<String, dynamic>>(
                key: ValueKey(_query.toString()),
                load: () async => unwrap(await widget.state.api.get('/gochar/${widget.planet}', query: _query)),
                builder: (context, g, _) {
                  final now = (g['now'] as Map?) ?? const {};
                  final events = ((g['events'] as List?) ?? const []).whereType<Map>().toList();
                  final moons = ((g['by_moon_sign'] as List?) ?? const []).whereType<Map>().toList();
                  final facts = (g['facts'] as Map?) ?? const {};
                  return ListView(padding: const EdgeInsets.all(16), children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${g['name']}${g['hi'] != null ? ' · ${g['hi']}' : ''}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
                          const SizedBox(height: 4),
                          Text('Now in ${now['sign_name']} ${now['degree'] ?? ''}${now['retro'] == true && !const ['rahu', 'ketu'].contains(widget.planet) ? ' · Retrograde' : ''}'),
                          if (now['nakshatra'] != null) Text('Nakshatra: ${now['nakshatra']}'),
                        ]),
                      ),
                    ),
                    SectionTitle(_moon ? 'Sign changes this month' : 'Dates in $_year'),
                    const Text('Times are in Indian Standard Time (IST).', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
                    if (events.isEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('No sign changes or retrograde turns in this period.')),
                    for (final e in events)
                      ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(e['type'] == 'ingress' ? Icons.login : Icons.sync, color: ArjColors.gold700),
                        title: Text(_event(e)),
                        subtitle: Text(formatAtOffset(e['at'])),
                      ),
                    if (moons.isNotEmpty) ...[
                      const SectionTitle('Effect by Moon sign'),
                      for (final m in moons)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text('${m['name']}${m['hi'] != null ? ' · ${m['hi']}' : ''}'),
                          subtitle: Text('House ${m['house']} from the Moon${m['note'] != null && '${m['note']}'.isNotEmpty ? '\n${m['note']}' : ''}'),
                          trailing: m['favourable'] == true ? const ToneChip('Favourable', ArjColors.success700) : const ToneChip('Needs care', ArjColors.gold700),
                        ),
                    ],
                    if (facts.isNotEmpty) ...[
                      const SectionTitle('About this planet'),
                      for (final e in _factLabels.entries)
                        if (facts[e.key] != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              SizedBox(width: 140, child: Text(e.value, style: const TextStyle(color: ArjColors.ink600))),
                              Expanded(child: Text(_fact(e.key, facts[e.key]))),
                            ]),
                          ),
                    ],
                  ]);
                },
              ),
            ),
          ]),
        ),
      );
}

// ───────────────────────────── Muhurat ─────────────────────────────

const defaultPlace = <String, dynamic>{'id': 1260086, 'label': 'Patna, Bihar, India'};

class MuhuratScreen extends StatelessWidget {
  const MuhuratScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Muhurat')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/muhurat')),
            isEmpty: (l) => l.isEmpty,
            builder: (context, list, _) => ListView(padding: const EdgeInsets.all(16), children: [
              const Text('Auspicious dates for samskaras, worked out from the Panchang.', style: TextStyle(color: ArjColors.ink600)),
              const SizedBox(height: 12),
              for (final e in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text('${e['name']}'),
                    subtitle: e['hi'] != null ? Text('${e['hi']}') : null,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MuhuratDetailScreen(state: state, event: '${e['key']}', title: '${e['name']}'))),
                  ),
                ),
            ]),
          ),
        ),
      );
}

class MuhuratDetailScreen extends StatefulWidget {
  const MuhuratDetailScreen({super.key, required this.state, required this.event, this.title});
  final AppState state;
  final String event;
  final String? title;
  @override
  State<MuhuratDetailScreen> createState() => _MuhuratDetailScreenState();
}

class _MuhuratDetailScreenState extends State<MuhuratDetailScreen> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic> _place = Map.of(defaultPlace);

  String get _monthKey => DateFormat('yyyy-MM').format(_month);

  static String _window(Map w) {
    final start = formatAtOffset(w['start'], pattern: 'h:mm a');
    final sameDay = formatAtOffset(w['start'], pattern: 'yyyy-MM-dd') == formatAtOffset(w['end'], pattern: 'yyyy-MM-dd');
    final end = formatAtOffset(w['end'], pattern: sameDay ? 'h:mm a' : 'd MMM, h:mm a');
    return '$start – $end';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title ?? 'Muhurat')),
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
              child: PlaceField(api: widget.state.api, labelText: 'City', initial: '${_place['label']}', onSelected: (p) => setState(() => _place = p)),
            ),
            Expanded(
              child: AsyncView<Map<String, dynamic>>(
                key: ValueKey('${widget.event}|$_monthKey|${_place['id']}'),
                load: () => widget.state.api.get('/muhurat/${widget.event}', query: {'month': _monthKey, 'place_id': _place['id']}),
                builder: (context, r, _) {
                  final days = unwrapList(r);
                  final meta = (r['meta'] as Map?) ?? const {};
                  final place = (meta['place'] as Map?)?['name'];
                  return ListView(padding: const EdgeInsets.all(16), children: [
                    if (meta['note'] != null) _Note('${meta['note']}'),
                    const _Note('We do not give dates or times for childbirth; that is a decision for your doctors.'),
                    if (place != null) Text('Times are local to $place.', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                    if (days.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No suitable dates this month. Try the next month.', textAlign: TextAlign.center)),
                    for (final d in days)
                      Card(
                        margin: const EdgeInsets.only(top: 12),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(_date(d['date'], 'EEE, d MMM yyyy'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                            if (lunarMonthText(d['masa'], d['masa_purnimanta']) != null)
                              Text(lunarMonthText(d['masa'], d['masa_purnimanta'])!, style: const TextStyle(color: ArjColors.ink600)),
                            const SizedBox(height: 6),
                            for (final w in ((d['windows'] as List?) ?? const []).whereType<Map>())
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  const Icon(Icons.schedule, size: 18, color: ArjColors.gold700),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text('${_window(w)}${[w['tithi'], w['nakshatra']].whereType<Object>().isEmpty ? '' : '\n${[w['tithi'], w['nakshatra']].whereType<Object>().join(' · ')}'}'),
                                  ),
                                ]),
                              ),
                          ]),
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

// ───────────────────────────── Festivals ─────────────────────────────

class FestivalsScreen extends StatefulWidget {
  const FestivalsScreen({super.key, required this.state});
  final AppState state;
  @override
  State<FestivalsScreen> createState() => _FestivalsScreenState();
}

class _FestivalsScreenState extends State<FestivalsScreen> {
  int _year = DateTime.now().year;
  String? _religion;

  /// Chosen city; null uses the server default (Patna).
  Map<String, dynamic>? _place;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Festivals')),
        body: SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: PeriodStepper(label: '$_year', onPrevious: () => setState(() => _year--), onNext: () => setState(() => _year++)),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: PlaceField(api: widget.state.api, labelText: 'City', initial: _place == null ? null : '${_place!['label']}', onSelected: (p) => setState(() => _place = p)),
            ),
            Expanded(
              child: AsyncView<Map<String, dynamic>>(
                key: ValueKey('$_year|$_religion|${_place?['id']}'),
                load: () => widget.state.api.get('/festivals', query: {'year': _year, 'religion': ?_religion, 'place_id': ?_place?['id']}),
                builder: (context, r, _) {
                  final meta = (r['meta'] as Map?) ?? const {};
                  final religions = ((meta['religions'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v'));
                  final list = unwrapList(r).where((f) => _religion == null || f['religion'] == _religion).toList()
                    ..sort((a, b) => '${a['date']}'.compareTo('${b['date']}'));
                  final months = <String, List<Map<String, dynamic>>>{};
                  for (final f in list) {
                    months.putIfAbsent(_date(f['date'], 'MMMM yyyy'), () => []).add(f);
                  }
                  return ListView(padding: const EdgeInsets.symmetric(vertical: 8), children: [
                    _chipRow([
                      ChoiceChip(label: const Text('All'), selected: _religion == null, onSelected: (_) => setState(() => _religion = null)),
                      for (final e in religions.entries)
                        ChoiceChip(label: Text(e.value), selected: _religion == e.key, onSelected: (_) => setState(() => _religion = e.key)),
                    ]),
                    if ((meta['place'] as Map?)?['name'] != null)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                        child: Text('Dates for ${(meta['place'] as Map)['name']}.', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                      ),
                    if (list.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No festivals listed for this year.', textAlign: TextAlign.center)),
                    for (final m in months.entries) ...[
                      Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: SectionTitle(m.key)),
                      for (final f in m.value)
                        ListTile(
                          leading: SizedBox(
                            width: 44,
                            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Text(_date(f['date'], 'd'), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 18)),
                              Text(_date(f['date'], 'EEE'), style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
                            ]),
                          ),
                          title: Text('${f['name']}'),
                          subtitle: Text([religions['${f['religion']}'] ?? humanize(f['religion']), if (f['basis'] != null && '${f['basis']}'.isNotEmpty) '${f['basis']}'].join(' · ')),
                        ),
                    ],
                  ]);
                },
              ),
            ),
          ]),
        ),
      );
}
