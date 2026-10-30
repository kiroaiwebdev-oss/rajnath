import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import '../widgets/tool_display.dart';
import 'activity_screens.dart';
import 'astro_screens.dart';
import 'content_screens.dart';
import 'info_screens.dart';

const toolKindLabels = {'astronomical': 'Astronomical', 'traditional': 'Traditional', 'symbolic': 'Symbolic', 'entertainment': 'Just for fun'};

/// Input types the generic [ToolScreen] can build a form for (Tools::rules on the server).
const supportedToolInputs = {'birth', 'couple', 'name', 'dob', 'dob_month', 'name_dob', 'vehicle', 'two_names'};

/// Kundli and matching keep their dedicated screen; every other calculator uses the generic form.
Widget screenForTool(AppState state, Map tool) => switch (tool['slug']) {
      'kundli' => KundliScreen(state: state),
      'kundli-matching' => KundliScreen(state: state, matching: true),
      _ => ToolScreen(state: state, tool: tool.cast<String, dynamic>()),
    };

/// Tools tab: daily guidance screens, then every calculator from GET /tools.
class ToolsTab extends StatefulWidget {
  const ToolsTab({super.key, required this.state});
  final AppState state;
  @override
  State<ToolsTab> createState() => _ToolsTabState();
}

class _ToolsTabState extends State<ToolsTab> {
  late Future<List<Map<String, dynamic>>> _tools = _load();

  /// The list sits below the fold, so the FutureBuilder may not be listening yet: mark errors handled here.
  Future<List<Map<String, dynamic>>> _load() {
    final f = widget.state.api.get('/tools').then(unwrapList);
    f.ignore();
    return f;
  }

  Widget _card(String title, String subtitle, Widget Function() page) => Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(title: Text(title), subtitle: Text(subtitle), trailing: const Icon(Icons.chevron_right), onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page()))),
      );

  @override
  Widget build(BuildContext context) {
    final s = widget.state;
    return RefreshIndicator(
      onRefresh: () async {
        final f = _load();
        setState(() => _tools = f);
        await f.catchError((_) => <Map<String, dynamic>>[]);
      },
      child: ListView(padding: const EdgeInsets.all(16), children: [
        const SectionTitle('Daily guidance'),
        _card('Panchang', 'Tithi, nakshatra, Rahu Kaal, Choghadiya', () => PanchangScreen(state: s)),
        _card('Rashifal', 'Daily to yearly horoscope by sign', () => HoroscopeScreen(state: s)),
        _card('Grah Gochar', 'Planetary transits and their effects', () => GocharScreen(state: s)),
        _card('Muhurat', 'Auspicious dates for samskaras', () => MuhuratScreen(state: s)),
        _card('Festivals', 'Festival calendar by year and city', () => FestivalsScreen(state: s)),
        _card('Vrat calendar', 'Monthly fasts, festivals and tithis', () => VratCalendarScreen(state: s)),
        _card('Rashi guide', 'The 12 signs, their lords and current transits', () => RashiListScreen(state: s)),
        _card('Navagraha', 'The nine planets and where they are now', () => NavagrahaScreen(state: s)),
        const SectionTitle('Calculators'),
        FutureBuilder<List<Map<String, dynamic>>>(
          future: _tools,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator(semanticsLabel: 'Loading calculators')));
            }
            if (snap.hasError) {
              final e = snap.error;
              return ErrorPanel(
                message: e is ApiException ? e.message : 'Could not load the calculators.',
                offline: e is ApiException && e.isOffline,
                onRetry: () => setState(() => _tools = _load()),
              );
            }
            return Column(children: [
              for (final t in snap.data!)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text('${t['title']}'),
                    subtitle: Text('${t['description'] ?? ''}'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => screenForTool(s, t))),
                  ),
                ),
            ]);
          },
        ),
      ]),
    );
  }
}

/// Date-only picker button (numerology tools).
class _DateButton extends StatelessWidget {
  const _DateButton({required this.label, required this.value, required this.onChanged, this.error});
  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final String? error;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        OutlinedButton.icon(
          icon: const Icon(Icons.calendar_today),
          label: Text(value == null ? label : DateFormat('d MMM yyyy').format(value!)),
          onPressed: () async {
            final d = await showDatePicker(context: context, firstDate: DateTime(1800), lastDate: DateTime.now(), initialDate: value ?? DateTime(1990));
            if (d != null) onChanged(d);
          },
        ),
        FormError(error),
      ]);
}

/// Generic calculator: builds the form from the tool's `input` type and renders the `display` sections.
class ToolScreen extends StatefulWidget {
  const ToolScreen({super.key, required this.state, required this.tool});
  final AppState state;
  final Map<String, dynamic> tool;
  @override
  State<ToolScreen> createState() => _ToolScreenState();
}

class _ToolScreenState extends State<ToolScreen> {
  final _a = BirthInput(), _b = BirthInput();
  final _name = TextEditingController(), _partner = TextEditingController(), _plate = TextEditingController();
  final _aName = TextEditingController(), _bName = TextEditingController();
  late final _year = TextEditingController(text: '${DateTime.now().year}');
  DateTime? _dob;
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic>? _display;
  Map<String, String> _errors = {};
  bool _busy = false;

  String get _input => '${widget.tool['input']}';
  String get _slug => '${widget.tool['slug']}';

  @override
  void dispose() {
    for (final c in [_name, _partner, _plate, _aName, _bName, _year]) {
      c.dispose();
    }
    super.dispose();
  }

  String _ymd(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  /// Request body, or null after setting [_errors] when something required is missing.
  Map<String, dynamic>? _body() {
    final e = <String, String>{};
    final name = _name.text.trim(), partner = _partner.text.trim(), plate = _plate.text.trim();
    bool needs(bool ok, String key, String msg) {
      if (!ok) e[key] = msg;
      return ok;
    }

    Map<String, dynamic>? body;
    switch (_input) {
      case 'birth':
        if (needs(_a.complete, '_', 'Please fill date, time and place of birth.')) body = {..._a.toJson(), if (name.isNotEmpty) 'name': name};
      case 'couple':
        if (needs(_a.complete && _b.complete, '_', 'Please fill date, time and place for both people.')) {
          body = {..._a.toJson('a_'), ..._b.toJson('b_'), if (_aName.text.trim().isNotEmpty) 'a_name': _aName.text.trim(), if (_bName.text.trim().isNotEmpty) 'b_name': _bName.text.trim()};
        }
      case 'name':
        if (needs(name.isNotEmpty, 'name', 'Please enter a name.')) body = {'name': name};
      case 'dob':
        final y = _year.text.trim();
        final ok = needs(_dob != null, 'birth_date', 'Please choose the date of birth.') &
            needs(y.isEmpty || (int.tryParse(y) != null && int.parse(y) >= 1900 && int.parse(y) <= 2200), 'year', 'Enter a year between 1900 and 2200.');
        if (ok) body = {'birth_date': _ymd(_dob!), if (y.isNotEmpty) 'year': int.parse(y)};
      case 'dob_month':
        if (needs(_dob != null, 'birth_date', 'Please choose the date of birth.')) body = {'birth_date': _ymd(_dob!), 'month': DateFormat('yyyy-MM').format(_month)};
      case 'name_dob':
        final ok = needs(name.isNotEmpty, 'name', 'Please enter a name.') & needs(_dob != null, 'birth_date', 'Please choose the date of birth.');
        if (ok) body = {'name': name, 'birth_date': _ymd(_dob!)};
      case 'vehicle':
        final ok = needs(_dob != null, 'birth_date', 'Please choose the date of birth.') & needs(RegExp(r'\d').hasMatch(plate), 'plate', 'Enter the registration number.');
        if (ok) body = {'birth_date': _ymd(_dob!), 'plate': plate};
      case 'two_names':
        final ok = needs(name.isNotEmpty, 'name', 'Please enter your name.') & needs(partner.isNotEmpty, 'partner', "Please enter the other person's name.");
        if (ok) body = {'name': name, 'partner': partner};
    }
    setState(() => _errors = e);
    return e.isEmpty ? body : null;
  }

  Future<void> _calc() async {
    final body = _body();
    if (body == null) return;
    setState(() { _busy = true; _display = null; });
    try {
      final r = unwrap(await widget.state.api.post('/tools/$_slug', body));
      setState(() => _display = (r['display'] as Map?)?.cast<String, dynamic>() ?? const {'sections': []});
    } on ApiException catch (e) {
      const shown = {'name', 'partner', 'plate', 'year', 'birth_date', 'a_name', 'b_name'};
      setState(() => _errors = {...e.fieldErrors, if (!e.fieldErrors.keys.any(shown.contains)) '_': e.message});
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _text(TextEditingController c, String key, String label, {TextCapitalization caps = TextCapitalization.words, TextInputType? type}) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextField(controller: c, textCapitalization: caps, keyboardType: type, decoration: InputDecoration(labelText: label, errorText: _errors[key])),
      );

  Widget _dobField() => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: _DateButton(label: 'Date of birth', value: _dob, error: _errors['birth_date'], onChanged: (d) => setState(() => _dob = d)),
      );

  List<Widget> _form() => switch (_input) {
        'birth' => [_text(_name, 'name', 'Name (optional)'), BirthFields(api: widget.state.api, input: _a)],
        'couple' => [
            const SectionTitle('First person'),
            _text(_aName, 'a_name', 'Name (optional)'),
            BirthFields(api: widget.state.api, input: _a),
            const SectionTitle('Second person'),
            _text(_bName, 'b_name', 'Name (optional)'),
            BirthFields(api: widget.state.api, input: _b),
          ],
        'name' => [_text(_name, 'name', 'Full name')],
        'dob' => [_dobField(), _text(_year, 'year', 'Year (for personal year)', type: TextInputType.number)],
        'dob_month' => [
            _dobField(),
            PeriodStepper(
              label: DateFormat('MMMM yyyy').format(_month),
              unit: 'month',
              onPrevious: () => setState(() => _month = DateTime(_month.year, _month.month - 1)),
              onNext: () => setState(() => _month = DateTime(_month.year, _month.month + 1)),
            ),
          ],
        'name_dob' => [_text(_name, 'name', 'Full name'), _dobField()],
        'vehicle' => [_dobField(), _text(_plate, 'plate', 'Vehicle registration number', caps: TextCapitalization.characters)],
        'two_names' => [_text(_name, 'name', 'Your name'), _text(_partner, 'partner', "Other person's name")],
        _ => const [],
      };

  @override
  Widget build(BuildContext context) {
    final t = widget.tool;
    final supported = supportedToolInputs.contains(_input);
    return Scaffold(
      appBar: AppBar(title: Text('${t['title']}')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(16), children: [
          if (t['description'] != null) Text('${t['description']}', style: const TextStyle(color: ArjColors.ink600)),
          if (toolKindLabels[t['kind']] != null) ...[
            const SizedBox(height: 8),
            Row(children: [ToneChip(toolKindLabels[t['kind']]!, t['kind'] == 'entertainment' ? ArjColors.gold700 : ArjColors.ink600)]),
          ],
          const SizedBox(height: 16),
          if (!supported) ...[
            const Text('This calculator is available on the website.'),
            const SizedBox(height: 12),
            OutlinedButton.icon(onPressed: () => openExternal(context, '$siteOrigin/tools/$_slug'), icon: const Icon(Icons.open_in_new), label: const Text('Open on the website')),
          ] else ...[
            ..._form(),
            FormError(_errors['_']),
            const SizedBox(height: 16),
            FilledButton(onPressed: _busy ? null : _calc, child: Text(_busy ? 'Calculating…' : 'Calculate')),
          ],
          if (_display != null) ToolDisplayView(_display!),
        ]),
      ),
    );
  }
}
