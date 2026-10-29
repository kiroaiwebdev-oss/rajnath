import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'activity_screens.dart' show confirm;
import 'purchase_screens.dart';

/// Opens the astrologer application (AN-12), asking the user to sign in first.
Future<void> openAstrologerApplication(BuildContext context, AppState state) async {
  if (!await ensureSignedIn(context, state, reason: 'Sign in to apply as an astrologer.') || !context.mounted) return;
  await Navigator.of(context).push(MaterialPageRoute(builder: (_) => AstrologerApplicationScreen(state: state)));
}

/// Apply to join the team as an astrologer: the open application with Withdraw, or the form; earlier ones below.
class AstrologerApplicationScreen extends StatefulWidget {
  const AstrologerApplicationScreen({super.key, required this.state});
  final AppState state;
  @override
  State<AstrologerApplicationScreen> createState() => _AstrologerApplicationScreenState();
}

class _AstrologerApplicationScreenState extends State<AstrologerApplicationScreen> {
  int _v = 0;
  bool _busy = false;

  Future<void> _withdraw(Map a) async {
    if (!await confirm(context, 'Withdraw your application?', 'Our team will stop reviewing it. You can apply again later.', 'Withdraw')) return;
    setState(() => _busy = true);
    try {
      await widget.state.api.post('/practitioner-applications/${a['ref']}/withdraw', {});
      if (!mounted) return;
      showSnack(context, 'Application withdrawn.');
      setState(() => _v++);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Apply as astrologer')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            key: ValueKey(_v),
            load: () async => unwrapList(await widget.state.api.get('/practitioner-applications')),
            builder: (context, list, _) {
              final open = list.where((a) => a['status'] == 'submitted').firstOrNull;
              final earlier = list.where((a) => !identical(a, open)).toList();
              return ListView(padding: const EdgeInsets.all(16), children: [
                if (open != null)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          Expanded(child: Text('Your application', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600))),
                          StatusChip(open['status']),
                        ]),
                        const SizedBox(height: 8),
                        Text('Sent ${formatWhen(open['created_at'], pattern: 'd MMM yyyy')} as ${open['name']} (${open['email']}).'),
                        const SizedBox(height: 4),
                        const Text('Our team is reviewing it and will email you with the next steps.', style: TextStyle(color: ArjColors.ink600)),
                        const SizedBox(height: 12),
                        OutlinedButton(onPressed: _busy ? null : () => _withdraw(open), child: Text(_busy ? 'Please wait…' : 'Withdraw application')),
                      ]),
                    ),
                  )
                else
                  ApplicationForm(state: widget.state, onSubmitted: () => setState(() => _v++)),
                if (earlier.isNotEmpty) ...[
                  const SectionTitle('Earlier applications'),
                  for (final a in earlier)
                    Card(
                      child: ListTile(
                        title: Text('${a['name']}'),
                        subtitle: Text([
                          'Sent ${formatWhen(a['created_at'], pattern: 'd MMM yyyy')}',
                          if (a['decision_note'] != null && '${a['decision_note']}'.isNotEmpty) '${a['decision_note']}',
                        ].join('\n')),
                        trailing: StatusChip(a['status']),
                      ),
                    ),
                ],
              ]);
            },
          ),
        ),
      );
}

class ApplicationForm extends StatefulWidget {
  const ApplicationForm({super.key, required this.state, required this.onSubmitted});
  final AppState state;
  final VoidCallback onSubmitted;
  @override
  State<ApplicationForm> createState() => _ApplicationFormState();
}

class _ApplicationFormState extends State<ApplicationForm> {
  late final _name = TextEditingController(text: '${widget.state.user?['name'] ?? ''}');
  late final _phone = TextEditingController(text: '${widget.state.user?['phone'] ?? ''}');
  final _email = TextEditingController(), _city = TextEditingController(), _years = TextEditingController(),
      _languages = TextEditingController(), _specialties = TextEditingController(), _about = TextEditingController();
  bool _consent = false, _busy = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    for (final c in [_name, _phone, _email, _city, _years, _languages, _specialties, _about]) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, String> _validate() {
    final e = <String, String>{};
    if (_name.text.trim().length < 3) e['name'] = 'Enter your full name.';
    final email = _email.text.trim().toLowerCase();
    final own = '${widget.state.user?['email'] ?? ''}'.trim().toLowerCase();
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      e['email'] = 'Enter a valid email address.';
    } else if (own.isNotEmpty && email == own) {
      e['email'] = 'Use a different email for your astrologer account (your customer account already uses this one).';
    }
    final pe = mobileError(_phone.text);
    if (pe != null) e['phone'] = pe;
    final y = int.tryParse(_years.text.trim());
    if (y == null || y < 0 || y > 70) e['experience_years'] = 'Enter your years of experience (0–70).';
    if (_languages.text.trim().isEmpty) e['languages'] = 'Enter the languages you consult in.';
    if (_specialties.text.trim().isEmpty) e['specialties'] = 'Enter your specialties.';
    if (_about.text.trim().length < 60) e['about'] = 'Tell us a little more (at least 60 characters).';
    if (!_consent) e['consent'] = 'Please confirm that your details are true.';
    return e;
  }

  Future<void> _submit() async {
    final errs = _validate();
    if (errs.isNotEmpty) return setState(() => _errors = errs);
    setState(() { _busy = true; _errors = {}; });
    try {
      await widget.state.api.post('/practitioner-applications', {
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'phone': normalizeIndianMobile(_phone.text),
        'city': _city.text.trim().isEmpty ? null : _city.text.trim(),
        'experience_years': int.parse(_years.text.trim()),
        'languages': _languages.text.trim(),
        'specialties': _specialties.text.trim(),
        'about': _about.text.trim(),
        'consent': true,
      });
      if (!mounted) return;
      showSnack(context, 'Application sent. Our team will review it and email you.');
      widget.onSubmitted();
    } on ApiException catch (e) {
      const shown = {'name', 'email', 'phone', 'city', 'experience_years', 'languages', 'specialties', 'about', 'consent'};
      if (mounted) setState(() => _errors = {...e.fieldErrors, if (!e.fieldErrors.keys.any(shown.contains)) '_': e.message});
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _field(TextEditingController c, String label, String key,
          {String? helper, TextInputType? type, List<TextInputFormatter>? fmt, int maxLines = 1, int? maxLength, TextCapitalization cap = TextCapitalization.sentences}) =>
      Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextField(
          controller: c,
          keyboardType: type,
          inputFormatters: fmt,
          maxLines: maxLines,
          maxLength: maxLength,
          textCapitalization: cap,
          decoration: InputDecoration(labelText: label, helperText: helper, helperMaxLines: 2, errorText: _errors[key], errorMaxLines: 3),
        ),
      );

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('Join our team of astrologers', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 4),
        const Text('Tell us about your practice. Our team reviews every application and replies by email.', style: TextStyle(color: ArjColors.ink600)),
        const SizedBox(height: 16),
        _field(_name, 'Full name', 'name', cap: TextCapitalization.words),
        _field(_email, 'Email for your astrologer account', 'email', type: TextInputType.emailAddress, cap: TextCapitalization.none,
            helper: 'Use a different email from your customer account.'),
        _field(_phone, 'Mobile number', 'phone', type: TextInputType.phone, fmt: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]'))]),
        _field(_city, 'City (optional)', 'city', cap: TextCapitalization.words),
        _field(_years, 'Years of experience', 'experience_years', type: TextInputType.number, fmt: [FilteringTextInputFormatter.digitsOnly]),
        _field(_languages, 'Languages', 'languages', helper: 'e.g. Hindi, English, Maithili', maxLength: 160),
        _field(_specialties, 'Specialties', 'specialties', helper: 'e.g. Kundli, Vastu, Muhurat', maxLength: 255),
        _field(_about, 'About your practice', 'about', helper: 'At least 60 characters.', maxLines: 5, maxLength: 3000),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: _consent,
          controlAffinity: ListTileControlAffinity.leading,
          onChanged: (v) => setState(() => _consent = v ?? false),
          title: const Text('I confirm these details are true'),
          subtitle: _errors['consent'] != null ? Text(_errors['consent']!, style: TextStyle(color: Theme.of(context).colorScheme.error)) : null,
        ),
        FormError(_errors['_']),
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Sending…' : 'Send application')),
      ]);
}
