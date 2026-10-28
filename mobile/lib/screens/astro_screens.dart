import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

/// Place search field backed by /places (GeoNames).
class PlaceField extends StatelessWidget {
  const PlaceField({super.key, required this.api, required this.onSelected, this.initial, this.errorText});
  final ApiClient api;
  final void Function(Map<String, dynamic>) onSelected;
  final String? initial;
  final String? errorText;

  @override
  Widget build(BuildContext context) => Autocomplete<Map<String, dynamic>>(
        initialValue: TextEditingValue(text: initial ?? ''),
        displayStringForOption: (p) => '${p['label']}',
        optionsBuilder: (v) async {
          if (v.text.trim().length < 2) return const [];
          try {
            return ((await api.get('/places', query: {'q': v.text.trim()}))['data'] as List).cast<Map<String, dynamic>>();
          } catch (_) {
            return const [];
          }
        },
        onSelected: onSelected,
        fieldViewBuilder: (context, c, f, _) => TextField(controller: c, focusNode: f,
            decoration: InputDecoration(labelText: 'Place of birth', helperText: 'Choose from the list. Place data © GeoNames', errorText: errorText)),
      );
}

class BirthInput {
  DateTime? date;
  TimeOfDay? time;
  Map<String, dynamic>? place;
  bool get complete => date != null && time != null && place != null;
  Map<String, dynamic> toJson([String p = '']) => {
        '${p}birth_date': DateFormat('yyyy-MM-dd').format(date!),
        '${p}birth_time': '${time!.hour.toString().padLeft(2, '0')}:${time!.minute.toString().padLeft(2, '0')}',
        '${p}place_id': place!['id'],
      };
}

class BirthFields extends StatefulWidget {
  const BirthFields({super.key, required this.api, required this.input, this.title});
  final ApiClient api;
  final BirthInput input;
  final String? title;
  @override
  State<BirthFields> createState() => _BirthFieldsState();
}

class _BirthFieldsState extends State<BirthFields> {
  @override
  Widget build(BuildContext context) {
    final i = widget.input;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (widget.title != null) SectionTitle(widget.title!),
      OutlinedButton.icon(
        icon: const Icon(Icons.calendar_today),
        label: Text(i.date == null ? 'Date of birth' : DateFormat('d MMM yyyy').format(i.date!)),
        onPressed: () async {
          final d = await showDatePicker(context: context, firstDate: DateTime(1800), lastDate: DateTime.now(), initialDate: i.date ?? DateTime(1990));
          if (d != null) setState(() => i.date = d);
        },
      ),
      const SizedBox(height: 8),
      OutlinedButton.icon(
        icon: const Icon(Icons.schedule),
        label: Text(i.time == null ? 'Time of birth' : i.time!.format(context)),
        onPressed: () async {
          final t = await showTimePicker(context: context, initialTime: i.time ?? const TimeOfDay(hour: 6, minute: 0));
          if (t != null) setState(() => i.time = t);
        },
      ),
      const SizedBox(height: 8),
      PlaceField(api: widget.api, onSelected: (p) => setState(() => i.place = p)),
    ]);
  }
}

class KundliScreen extends StatefulWidget {
  const KundliScreen({super.key, required this.state, this.matching = false});
  final AppState state;
  final bool matching;
  @override
  State<KundliScreen> createState() => _KundliScreenState();
}

class _KundliScreenState extends State<KundliScreen> {
  final _a = BirthInput(), _b = BirthInput();
  Map<String, dynamic>? _result;
  String? _error;
  bool _busy = false;

  Future<void> _calc() async {
    if (!_a.complete || (widget.matching && !_b.complete)) {
      setState(() => _error = 'Please fill date, time and place.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final body = widget.matching ? {..._a.toJson('a_'), ..._b.toJson('b_')} : _a.toJson();
      final r = await widget.state.api.post('/tools/${widget.matching ? 'kundli-matching' : 'kundli'}', body);
      setState(() => _result = r['data'] as Map<String, dynamic>);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = _result;
    return Scaffold(
      appBar: AppBar(title: Text(widget.matching ? 'Kundli Matching' : 'Free Kundli')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(16), children: [
          BirthFields(api: widget.state.api, input: _a, title: widget.matching ? 'Boy / groom' : null),
          if (widget.matching) BirthFields(api: widget.state.api, input: _b, title: 'Girl / bride'),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _calc, child: Text(_busy ? 'Calculating…' : 'Calculate')),
          if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
          if (r != null && !widget.matching) ..._kundli(context, r['chart'] as Map),
          if (r != null && widget.matching) ..._match(context, r),
        ]),
      ),
    );
  }

  List<Widget> _kundli(BuildContext context, Map c) => [
        const SectionTitle('Summary'),
        Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Lagna: ${c['lagna']['sign_name']} ${c['lagna']['degree']}'),
          Text('Moon sign: ${c['moon_sign']['name']}'),
          Text('Nakshatra: ${c['nakshatra']['name']}, pada ${c['nakshatra']['pada']}'),
        ]))),
        const SectionTitle('Planets'),
        for (final e in (c['planets'] as Map).entries.where((e) => e.value['classical'] == true))
          ListTile(dense: true, title: Text('${e.value['name']}${e.value['retro'] == true && !['rahu', 'ketu'].contains(e.key) ? ' (R)' : ''}'),
              subtitle: Text('${e.value['sign_name']} ${e.value['degree']} · house ${e.value['house']} · ${e.value['nakshatra']}')),
        const SectionTitle('Vimshottari Mahadasha'),
        for (final p in c['dasha']['periods'] as List) ListTile(dense: true, title: Text('${p['lord']}'.toUpperCase()), trailing: Text('${p['start']} – ${p['end']}')),
        Text('Method: ${c['method']['ephemeris']}, ${c['method']['ayanamsha']}', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
      ];

  List<Widget> _match(BuildContext context, Map r) {
    final m = r['match'] as Map;
    return [
      const SectionTitle('Guna Milan'),
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${m['total']} / 36', style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w600)),
        Text('Traditionally ${m['band']}.'),
        if ((m['doshas'] as List).isNotEmpty) Text('Doshas: ${(m['doshas'] as List).join(', ')}'),
      ]))),
      for (final e in (m['kootas'] as Map).entries) ListTile(dense: true, title: Text(e.key.replaceAll('_', ' ')), subtitle: Text('${e.value['detail']}'), trailing: Text('${e.value['score']} / ${e.value['max']}')),
      const Text('Guna milan is one traditional input; exceptions and the full charts should be reviewed by an astrologer.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
    ];
  }
}

class PanchangScreen extends StatefulWidget {
  const PanchangScreen({super.key, required this.state});
  final AppState state;
  @override
  State<PanchangScreen> createState() => _PanchangScreenState();
}

class _PanchangScreenState extends State<PanchangScreen> {
  DateTime _date = DateTime.now();
  Map<String, dynamic> _place = {'id': 1260086, 'label': 'Patna, Bihar, India'};

  String _t(String? iso) => iso == null ? '—' : DateFormat('h:mm a').format(DateTime.parse(iso).toLocal());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Panchang')),
      body: SafeArea(
        child: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Row(children: [
              Expanded(child: OutlinedButton(onPressed: () async {
                final d = await showDatePicker(context: context, firstDate: DateTime(1800), lastDate: DateTime(2399, 12, 31), initialDate: _date);
                if (d != null) setState(() => _date = d);
              }, child: Text(DateFormat('d MMM yyyy').format(_date)))),
            ]),
          ),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: PlaceField(api: widget.state.api, initial: '${_place['label']}', onSelected: (p) => setState(() => _place = p))),
          Expanded(
            child: AsyncView<Map<String, dynamic>>(
              key: ValueKey('${_place['id']}-${DateFormat('yyyy-MM-dd').format(_date)}'),
              load: () async => (await widget.state.api.get('/panchang', query: {'date': DateFormat('yyyy-MM-dd').format(_date), 'place_id': _place['id']}))['data'] as Map<String, dynamic>,
              builder: (context, p, _) => ListView(padding: const EdgeInsets.all(16), children: [
                if (p['polar'] == true) Text('${p['polar_note']}'),
                Text('${p['vara']}', style: Theme.of(context).textTheme.titleLarge),
                Text('Times shown in the place\'s local time (UTC${p['place']['utc_offset']}).', style: const TextStyle(color: ArjColors.ink600, fontSize: 12)),
                const SectionTitle('Sun & Moon'),
                Wrap(spacing: 16, runSpacing: 8, children: [
                  Text('Sunrise ${_t(p['sunrise'])}'), Text('Sunset ${_t(p['sunset'])}'), Text('Moonrise ${_t(p['moonrise'])}'), Text('Moonset ${_t(p['moonset'])}'),
                ]),
                const SectionTitle('Panchang'),
                for (final k in ['tithi', 'nakshatra', 'yoga', 'karana'])
                  ListTile(contentPadding: EdgeInsets.zero, title: Text(k[0].toUpperCase() + k.substring(1)),
                      subtitle: Text([for (final a in p['angas'][k] as List) '${a['name']}${a['ends'] != null ? ' until ${_t(a['ends'])}' : ''}'].join('\n'))),
                if ((p['intervals'] as Map).isNotEmpty) ...[
                  const SectionTitle('Timings'),
                  for (final e in {'abhijit': 'Abhijit muhurta', 'rahu_kaal': 'Rahu Kaal', 'yamaganda': 'Yamaganda', 'gulika': 'Gulika'}.entries)
                    ListTile(dense: true, contentPadding: EdgeInsets.zero, title: Text(e.value), trailing: Text('${_t(p['intervals'][e.key]['start'])} – ${_t(p['intervals'][e.key]['end'])}')),
                ],
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}
