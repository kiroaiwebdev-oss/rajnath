import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../models/slots.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';
import 'purchase_screens.dart';
import 'store_screens.dart';

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key, required this.state});
  final AppState state;
  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  String _q = '';
  String? _family, _mode;
  final _key = GlobalKey();

  Future<Map<String, dynamic>> _load() async {
    final fams = await widget.state.api.get('/catalogue/families');
    final list = await widget.state.api.get('/catalogue/offerings', query: {if (_q.isNotEmpty) 'q': _q, if (_family != null) 'family': _family, if (_mode != null) 'mode': _mode});
    return {'families': fams['data'], 'modes': (fams['meta'] as Map?)?['modes'] ?? const [], 'items': list['data']};
  }

  @override
  Widget build(BuildContext context) {
    return AsyncView<Map<String, dynamic>>(
      key: ValueKey('$_q|$_family|$_mode|$_key'),
      load: _load,
      builder: (context, d, reload) {
        final items = (d['items'] as List).cast<Map>();
        final fams = (d['families'] as List).cast<Map>();
        final modes = (d['modes'] as List).whereType<Map>().toList();
        return ListView(padding: const EdgeInsets.all(16), children: [
          Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: ListTile(
              leading: const Icon(Icons.diamond_outlined, color: ArjColors.gold700),
              title: const Text('Gemstone store'),
              subtitle: const Text('Certified natural gemstones'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => StoreScreen(state: widget.state))),
            ),
          ),
          TextField(decoration: const InputDecoration(labelText: 'Search services', prefixIcon: Icon(Icons.search)), textInputAction: TextInputAction.search,
              onSubmitted: (v) => setState(() => _q = v.trim())),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            ChoiceChip(label: const Text('All'), selected: _family == null, onSelected: (_) => setState(() => _family = null)),
            for (final f in fams) ChoiceChip(label: Text('${f['name']}'), selected: _family == f['slug'], onSelected: (_) => setState(() => _family = '${f['slug']}')),
          ]),
          if (modes.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text('How would you like it?', style: TextStyle(color: ArjColors.ink600)),
            const SizedBox(height: 6),
            Wrap(spacing: 8, runSpacing: 8, children: [
              ChoiceChip(label: const Text('Any mode'), selected: _mode == null, onSelected: (_) => setState(() => _mode = null)),
              for (final m in modes) ChoiceChip(label: Text('${m['label']}'), selected: _mode == m['key'], onSelected: (_) => setState(() => _mode = '${m['key']}')),
            ]),
          ],
          const SizedBox(height: 12),
          if (items.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No services match.', textAlign: TextAlign.center)),
          for (final o in items)
            Card(
              margin: const EdgeInsets.only(bottom: 12),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ServiceDetailScreen(state: widget.state, slug: '${o['slug']}'))),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  if (o['image_url'] != null)
                    AspectRatio(aspectRatio: 16 / 9, child: Image.network('${o['image_url']}', fit: BoxFit.cover, alignment: Alignment.topCenter, semanticLabel: '',
                        errorBuilder: (_, _, _) => Container(color: ArjColors.ivory100))),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${o['title']}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 4),
                      Text('${o['summary']}', style: const TextStyle(color: ArjColors.ink600)),
                      const SizedBox(height: 8),
                      Text(o['from_price_minor'] == null ? 'Price on request' : 'From ${formatPaise((o['from_price_minor'] as num).toInt())}', style: const TextStyle(fontWeight: FontWeight.w600)),
                    ]),
                  ),
                ]),
              ),
            ),
        ]);
      },
    );
  }
}

class ServiceDetailScreen extends StatelessWidget {
  const ServiceDetailScreen({super.key, required this.state, required this.slug});
  final AppState state;
  final String slug;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Service')),
      body: SafeArea(
        child: AsyncView<Map<String, dynamic>>(
          load: () async => (await state.api.get('/catalogue/offerings/$slug'))['data'] as Map<String, dynamic>,
          builder: (context, o, _) => ListView(padding: const EdgeInsets.all(16), children: [
            Text('${o['title']}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            Text('${o['summary']}', style: const TextStyle(color: ArjColors.ink600)),
            if (o['body'] != null) ...[const SizedBox(height: 16), Text('${o['body']}')],
            if ((o['includes'] as List).isNotEmpty) ...[
              const SectionTitle("What's included"),
              for (final i in o['includes'] as List) ListTile(dense: true, contentPadding: EdgeInsets.zero, leading: const Icon(Icons.check, color: ArjColors.gold700), title: Text('$i')),
            ],
            const SectionTitle('Options & prices'),
            for (final v in (o['variants'] as List).cast<Map>())
              ListTile(contentPadding: EdgeInsets.zero, title: Text('${v['name']}'),
                  subtitle: Text([if (v['duration_min'] != null) '${v['duration_min']} min', if ((v['participants'] ?? 1) > 1) '${v['participants']} people', if (v['urgent'] == true) 'Priority', formatPaise((v['price_minor'] as num?)?.toInt())].join(' · ')),
                  trailing: v['price_minor'] == null
                      ? null
                      : FilledButton.tonal(
                          onPressed: () => _buy(context, o, v),
                          child: Text(isSlotVariant(v) ? 'Book' : 'Buy', semanticsLabel: '${isSlotVariant(v) ? 'Book' : 'Buy'} ${v['name']}'),
                        )),
            if (o['disclaimer'] != null)
              Container(margin: const EdgeInsets.only(top: 16), padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: ArjColors.gold50, borderRadius: BorderRadius.circular(12), border: Border.all(color: ArjColors.gold200)),
                  child: Text('Please note: ${o['disclaimer']}')),
            const SizedBox(height: 20),
            if ((o['variants'] as List).isEmpty || (o['variants'] as List).any((v) => v['price_minor'] == null)) ...[
              FilledButton(onPressed: () => _request(context, o), child: const Text('Request this service')),
              const SizedBox(height: 8),
              const Text('Our team will call you to confirm the time and fee.', textAlign: TextAlign.center, style: TextStyle(color: ArjColors.ink600)),
            ] else
              TextButton(onPressed: () => _request(context, o), child: const Text('Have a question first? Send us a request')),
          ]),
        ),
      ),
    );
  }
}

extension on ServiceDetailScreen {
  void _request(BuildContext context, Map o) =>
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => RequestScreen(state: state, slug: slug, title: '${o['title']}')));

  /// Priced variant: slot booking (optionally via a team astrologer) or direct checkout.
  Future<void> _buy(BuildContext context, Map o, Map v) async {
    if (!await ensureSignedIn(context, state, reason: 'Sign in to book and pay securely.') || !context.mounted) return;
    final nav = Navigator.of(context);
    final title = '${o['title']}';
    if (!isSlotVariant(v)) {
      await nav.push(MaterialPageRoute(builder: (_) => ServiceBuyScreen(state: state, title: title, variant: v)));
      return;
    }
    Map? practitioner;
    if (teamOfferingSlugs.contains(slug)) {
      practitioner = await nav.push<Map>(MaterialPageRoute(builder: (_) => PractitionerPickerScreen(state: state, offeringSlug: slug, title: title)));
      if (practitioner == null) return;
    }
    await nav.push(MaterialPageRoute(
      builder: (_) => SlotBookingScreen(
        state: state, variantRef: '${v['ref']}', title: title, variantName: '${v['name']}', priceMinor: (v['price_minor'] as num?)?.toInt(), practitioner: practitioner),
    ));
  }
}

class RequestScreen extends StatefulWidget {
  const RequestScreen({super.key, required this.state, this.slug, required this.title});
  final AppState state;
  final String? slug;
  final String title;
  @override
  State<RequestScreen> createState() => _RequestScreenState();
}

class _RequestScreenState extends State<RequestScreen> {
  late final _name = TextEditingController(text: '${widget.state.user?['name'] ?? ''}');
  late final _phone = TextEditingController(text: '${widget.state.user?['phone'] ?? ''}');
  final _msg = TextEditingController();
  bool _consent = false, _busy = false;
  String? _mode, _language;
  DateTime? _date;
  Map<String, String> _errors = {};

  /// Same modes and languages the server accepts (OfferingVariant::MODES, EnquiryApiController).
  static const modes = {'video': 'Video call', 'audio': 'Audio call', 'chat': 'Chat', 'in_person': 'In person (Patna office)', 'written': 'Written answer', 'report': 'Report', 'onsite': 'On-site visit', 'puja': 'Puja'};
  static const languages = ['Hindi', 'Maithili', 'Sanskrit', 'English'];

  Future<void> _submit() async {
    setState(() { _busy = true; _errors = {}; });
    try {
      final r = await widget.state.api.post('/enquiries', {
        'offering': widget.slug, 'name': _name.text.trim(), 'phone': _phone.text.trim(), 'message': _msg.text.trim(), 'consent': _consent,
        'mode': ?_mode, 'language': ?_language, if (_date != null) 'preferred_date': DateFormat('yyyy-MM-dd').format(_date!),
      });
      if (!mounted) return;
      await showDialog<void>(context: context, builder: (_) => AlertDialog(title: const Text('Request received'),
          content: Text('Thank you. Our team will call you to confirm. Reference ${('${r['data']['ref']}').substring(18)}.'), actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('OK'))]));
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Send request')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(20), children: [
            Text(widget.title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
            TextField(controller: _name, decoration: InputDecoration(labelText: 'Your name', errorText: _errors['name'])),
            const SizedBox(height: 12),
            TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: InputDecoration(labelText: 'Mobile number', errorText: _errors['phone'])),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _mode,
              decoration: InputDecoration(labelText: 'Preferred mode (optional)', errorText: _errors['mode']),
              items: [for (final e in modes.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
              onChanged: (v) => setState(() => _mode = v),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _language,
              decoration: InputDecoration(labelText: 'Language (optional)', errorText: _errors['language']),
              items: [for (final l in languages) DropdownMenuItem(value: l, child: Text(l))],
              onChanged: (v) => setState(() => _language = v),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.event_outlined),
              title: Text(_date == null ? 'Preferred date (optional)' : DateFormat('EEE, d MMM yyyy').format(_date!)),
              subtitle: _errors['preferred_date'] != null ? Text(_errors['preferred_date']!, style: TextStyle(color: Theme.of(context).colorScheme.error)) : null,
              trailing: _date == null ? null : IconButton(tooltip: 'Clear date', onPressed: () => setState(() => _date = null), icon: const Icon(Icons.clear)),
              onTap: () async {
                final now = DateTime.now();
                final d = await showDatePicker(context: context, firstDate: DateTime(now.year, now.month, now.day), lastDate: now.add(const Duration(days: 365)), initialDate: _date ?? now);
                if (d != null) setState(() => _date = d);
              },
            ),
            TextField(controller: _msg, maxLines: 3, decoration: const InputDecoration(labelText: 'Your question (optional)')),
            CheckboxListTile(contentPadding: EdgeInsets.zero, value: _consent, controlAffinity: ListTileControlAffinity.leading, onChanged: (v) => setState(() => _consent = v ?? false),
                title: const Text('I agree to be contacted about this request'), subtitle: _errors['consent'] != null ? Text(_errors['consent']!, style: TextStyle(color: Theme.of(context).colorScheme.error)) : null),
            if (_errors['_'] != null) Text(_errors['_']!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            const SizedBox(height: 12),
            FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Sending…' : 'Send request')),
          ]),
        ),
      );
}
