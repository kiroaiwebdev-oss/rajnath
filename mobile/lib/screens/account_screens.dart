import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';
import 'apply_screens.dart';
import 'astro_screens.dart';
import 'info_screens.dart';
import 'palm_screens.dart';
import 'support_screens.dart';
import 'wallet_screen.dart';
import 'sign_in_screen.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) {
    if (!state.signedIn) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.person_outline, size: 48, color: ArjColors.ink600),
            const SizedBox(height: 12),
            const Text('Sign in to save birth profiles and track your requests.', textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SignInScreen(state: state))), child: const Text('Sign in or create account')),
          ]),
        ),
      );
    }
    final u = state.user!;
    return ListView(padding: const EdgeInsets.all(16), children: [
      Text('Namaste, ${u['name']}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
      const SizedBox(height: 4),
      Text([u['email'], u['phone']].whereType<String>().join(' · '), style: const TextStyle(color: ArjColors.ink600)),
      const SizedBox(height: 16),
      const SectionTitle('My activity'),
      _tile(context, Icons.receipt_long_outlined, 'Orders', () => OrdersScreen(state: state)),
      _tile(context, Icons.event_outlined, 'Bookings', () => BookingsScreen(state: state)),
      _tile(context, Icons.description_outlined, 'Reports', () => ReportsScreen(state: state)),
      _tile(context, Icons.local_fire_department_outlined, 'Puja', () => PujaListScreen(state: state)),
      _tile(context, Icons.back_hand_outlined, 'Palmistry', () => PalmListScreen(state: state)),
      _tile(context, Icons.account_balance_wallet_outlined, 'Wallet', () => WalletScreen(state: state)),
      _tile(context, Icons.notifications_outlined, 'Notifications', () => NotificationsScreen(state: state),
          badge: state.unread > 0 ? '${state.unread}' : null),
      _tile(context, Icons.support_agent_outlined, 'Help & support', () => SupportScreen(state: state)),
      const SectionTitle('Settings'),
      _tile(context, Icons.badge_outlined, 'Profile, email and mobile', () => ProfileScreen(state: state)),
      _tile(context, Icons.people_outline, 'Birth profiles', () => BirthProfilesScreen(state: state)),
      _tile(context, Icons.devices_outlined, 'Signed-in devices', () => DevicesScreen(state: state)),
      _tile(context, Icons.tune, 'Notification settings', () => NotificationPrefsScreen(state: state)),
      _tile(context, Icons.translate, 'Language', () => LanguageScreen(state: state)),
      _tile(context, Icons.privacy_tip_outlined, 'Privacy and your data', () => PrivacyScreen(state: state)),
      const SectionTitle('Work with us'),
      Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(
          leading: const Icon(Icons.workspace_premium_outlined),
          title: const Text('Apply as astrologer'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => openAstrologerApplication(context, state),
        ),
      ),
      const SizedBox(height: 16),
      OutlinedButton(onPressed: () => state.signOut(), child: const Text('Sign out')),
      const SizedBox(height: 8),
      TextButton(onPressed: () => signOutOtherDevices(context, state), child: const Text('Sign out all other devices')),
      const SizedBox(height: 24),
    ]);
  }

  Widget _tile(BuildContext context, IconData icon, String title, Widget Function() page, {String? badge}) => Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(
          leading: Icon(icon),
          title: Text(title),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            if (badge != null) Badge(label: Text(badge, semanticsLabel: '$badge unread')),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right),
          ]),
          onTap: () async {
            await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page()));
            await state.refreshUnread();
          },
        ),
      );
}

class BirthProfilesScreen extends StatefulWidget {
  const BirthProfilesScreen({super.key, required this.state});
  final AppState state;
  @override
  State<BirthProfilesScreen> createState() => _BirthProfilesScreenState();
}

class _BirthProfilesScreenState extends State<BirthProfilesScreen> {
  int _v = 0;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Birth profiles')),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () async {
            final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => AddBirthProfileScreen(state: widget.state)));
            if (saved == true) setState(() => _v++);
          },
          icon: const Icon(Icons.add),
          label: const Text('Add'),
        ),
        body: SafeArea(
          child: AsyncView<List>(
            key: ValueKey(_v),
            load: () async => (await widget.state.api.get('/birth-profiles'))['data'] as List,
            isEmpty: (l) => l.isEmpty,
            emptyText: 'No birth profiles yet. Tap Add to save yours.',
            builder: (context, list, reload) => ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 96), children: [
              for (final p in list)
                Card(
                  child: ListTile(
                    title: Text('${p['name']}${p['is_primary'] == true ? '  ★' : ''}'),
                    subtitle: Text('${p['birth_date']}${p['birth_time'] != null ? ' ${p['birth_time']}' : ' (time unknown)'}\n${p['place']['label']}'),
                    isThreeLine: true,
                    onTap: () async {
                      final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => AddBirthProfileScreen(state: widget.state, profile: p as Map)));
                      if (saved == true) await reload();
                    },
                    trailing: IconButton(
                      tooltip: 'Delete',
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () async {
                        final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(title: const Text('Delete profile?'), actions: [
                              TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
                              TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
                            ]));
                        if (ok == true) {
                          await widget.state.api.delete('/birth-profiles/${p['ref']}');
                          await reload();
                        }
                      },
                    ),
                  ),
                ),
            ]),
          ),
        ),
      );
}

/// Adds a birth profile, or edits [profile] (PUT /birth-profiles/{ref}) when given.
class AddBirthProfileScreen extends StatefulWidget {
  const AddBirthProfileScreen({super.key, required this.state, this.profile});
  final AppState state;
  final Map? profile;
  @override
  State<AddBirthProfileScreen> createState() => _AddBirthProfileScreenState();
}

class _AddBirthProfileScreenState extends State<AddBirthProfileScreen> {
  late final _name = TextEditingController(text: widget.profile == null ? '' : '${widget.profile!['name'] ?? ''}');
  late final _input = widget.profile == null ? BirthInput() : BirthInput.fromProfile(widget.profile!);
  late String _label = _labels.containsKey(widget.profile?['label']) ? '${widget.profile!['label']}' : 'self';
  bool _busy = false;
  String? _error;

  static const _labels = {'self': 'Myself', 'spouse': 'Spouse / partner', 'child': 'Child', 'parent': 'Parent', 'sibling': 'Sibling', 'other': 'Other'};

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_input.complete || _name.text.trim().isEmpty) {
      setState(() => _error = 'Please fill name, date, time and place.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final p = widget.profile;
      final body = {
        'label': _label, 'name': _name.text.trim(), 'time_accuracy': 'exact', ..._input.toJson(),
        if (p != null && p['gender'] != null) 'gender': p['gender'],
        if (p != null && p['is_primary'] != null) 'is_primary': p['is_primary'],
      };
      final r = p == null ? await widget.state.api.post('/birth-profiles', body) : await widget.state.api.put('/birth-profiles/${p['ref']}', body);
      if (!mounted) return;
      if (r['notice'] != null) showSnack(context, '${r['notice']}');
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.profile == null ? 'Add birth profile' : 'Edit birth profile')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(16), children: [
            TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Full name')),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _label, decoration: const InputDecoration(labelText: 'Relation'),
                items: _labels.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
                onChanged: (v) => setState(() => _label = v ?? 'self')),
            const SizedBox(height: 12),
            BirthFields(api: widget.state.api, input: _input),
            if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
            const SizedBox(height: 16),
            FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving…' : 'Save')),
          ]),
        ),
      );
}

class DevicesScreen extends StatefulWidget {
  const DevicesScreen({super.key, required this.state});
  final AppState state;
  @override
  State<DevicesScreen> createState() => _DevicesScreenState();
}

class _DevicesScreenState extends State<DevicesScreen> {
  int _v = 0;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Devices'), actions: [
          IconButton(tooltip: 'Sign out all other devices', onPressed: () => signOutOtherDevices(context, widget.state).then((done) {
            if (done && mounted) setState(() => _v++);
          }), icon: const Icon(Icons.logout)),
        ]),
        body: SafeArea(
          child: AsyncView<List>(
            key: ValueKey(_v),
            load: () async => (await widget.state.api.get('/me/devices'))['data'] as List,
            builder: (context, list, reload) => ListView(padding: const EdgeInsets.all(16), children: [
              for (final d in list)
                Card(
                  child: ListTile(
                    title: Text('${d['name']}${d['current'] == true ? ' (this device)' : ''}'),
                    subtitle: Text(d['last_used_at'] != null ? 'Last used ${DateFormat('d MMM yyyy, h:mm a').format(DateTime.parse(d['last_used_at']).toLocal())}' : 'Not used yet'),
                    trailing: d['current'] == true
                        ? null
                        : TextButton(onPressed: () async {
                            await widget.state.api.delete('/me/devices/${d['id']}');
                            await reload();
                          }, child: const Text('Sign out')),
                  ),
                ),
            ]),
          ),
        ),
      );
}

class NotificationPrefsScreen extends StatefulWidget {
  const NotificationPrefsScreen({super.key, required this.state});
  final AppState state;
  @override
  State<NotificationPrefsScreen> createState() => _NotificationPrefsScreenState();
}

class _NotificationPrefsScreenState extends State<NotificationPrefsScreen> {
  late final Map<String, dynamic> _prefs = Map<String, dynamic>.from(widget.state.user?['notification_prefs'] as Map? ?? {});
  static const labels = {'booking_reminders': 'Booking reminders and changes', 'reports_ready': 'Report ready notices', 'payments': 'Payment and refund updates', 'offers': 'Festival guidance and offers'};

  Future<void> _set(String k, bool v) async {
    setState(() => _prefs[k] = v);
    try {
      await widget.state.api.patch('/me', {'notification_prefs': {k: v}});
      await widget.state.refreshUser();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _prefs[k] = !v);
        showSnack(context, e.message);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Notifications')),
        body: SafeArea(child: ListView(children: [for (final e in labels.entries) SwitchListTile(title: Text(e.value), value: _prefs[e.key] == true, onChanged: (v) => _set(e.key, v))])),
      );
}

// ───────────────────────────── Sign out other devices ─────────────────────────────

/// POST /me/sign-out-others after confirming. True when it succeeded.
Future<bool> signOutOtherDevices(BuildContext context, AppState state) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: const Text('Sign out all other devices?'),
      content: const Text('Every other phone and browser signed in to your account will be signed out. This device stays signed in.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Sign out others')),
      ],
    ),
  );
  if (ok != true) return false;
  try {
    await state.api.post('/me/sign-out-others', {});
    if (context.mounted) showSnack(context, 'All other devices have been signed out.');
    return true;
  } on ApiException catch (e) {
    if (context.mounted) showSnack(context, e.message);
    return false;
  }
}

// ───────────────────────────── Profile & contact change ─────────────────────────────

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.state});
  final AppState state;
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final _name = TextEditingController(text: '${widget.state.user?['name'] ?? ''}');
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) return setState(() => _error = 'Please enter your name.');
    setState(() { _busy = true; _error = null; });
    try {
      final r = await widget.state.api.patch('/me', {'name': _name.text.trim()});
      widget.state.setUser(unwrap(r));
      if (mounted) showSnack(context, 'Saved.');
    } on ApiException catch (e) {
      setState(() => _error = e.fieldErrors['name'] ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: SafeArea(
          child: ListenableBuilder(
            listenable: widget.state,
            builder: (context, _) {
              final u = widget.state.user ?? const {};
              Widget contact(IconData icon, String label, Object? value, bool verified) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: Icon(icon),
                      title: Text(value == null || '$value'.isEmpty ? 'Not added' : '$value'),
                      subtitle: Text(value == null ? label : '$label${verified ? ' · verified' : ''}'),
                      trailing: TextButton(
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ContactChangeScreen(state: widget.state, email: icon == Icons.email_outlined))),
                        child: Text(value == null ? 'Add' : 'Change'),
                      ),
                    ),
                  );
              return ListView(padding: const EdgeInsets.all(16), children: [
                TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: InputDecoration(labelText: 'Your name', errorText: _error)),
                const SizedBox(height: 12),
                FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving…' : 'Save name')),
                const SectionTitle('Email and mobile'),
                contact(Icons.email_outlined, 'Email', u['email'], u['email_verified'] == true),
                contact(Icons.phone_outlined, 'Mobile', u['phone'], u['phone_verified'] == true),
                const Text('We send a code to the new email or mobile to confirm it is yours.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
              ]);
            },
          ),
        ),
      );
}

/// Change email or mobile: POST /me/contact sends a code to the new address, POST /me/contact/confirm applies it.
class ContactChangeScreen extends StatefulWidget {
  const ContactChangeScreen({super.key, required this.state, this.email = true});
  final AppState state;
  final bool email;
  @override
  State<ContactChangeScreen> createState() => _ContactChangeScreenState();
}

class _ContactChangeScreenState extends State<ContactChangeScreen> {
  final _contact = TextEditingController(), _code = TextEditingController();
  String? _challenge, _sentTo;
  bool _busy = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _contact.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_contact.text.trim().isEmpty) return setState(() => _errors = {'contact': widget.email ? 'Enter the new email address.' : 'Enter the new mobile number.'});
    setState(() { _busy = true; _errors = {}; });
    try {
      final d = unwrap(await widget.state.api.post('/me/contact', {'contact': _contact.text.trim()}));
      setState(() { _challenge = '${d['challenge']}'; _sentTo = '${d['sent_to'] ?? _contact.text.trim()}'; });
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm() async {
    if (_code.text.trim().isEmpty) return setState(() => _errors = {'code': 'Enter the code we sent.'});
    setState(() { _busy = true; _errors = {}; });
    try {
      final u = unwrap(await widget.state.api.post('/me/contact/confirm', {'challenge': _challenge, 'code': _code.text.trim()}));
      widget.state.setUser(u);
      if (!mounted) return;
      showSnack(context, widget.email ? 'Email updated.' : 'Mobile number updated.');
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.email ? 'Change email' : 'Change mobile')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(16), children: [
            TextField(
              controller: _contact,
              enabled: _challenge == null,
              keyboardType: widget.email ? TextInputType.emailAddress : TextInputType.phone,
              decoration: InputDecoration(labelText: widget.email ? 'New email address' : 'New mobile number', errorText: _errors['contact']),
            ),
            if (_challenge == null) ...[
              FormError(_errors['_']),
              const SizedBox(height: 12),
              FilledButton(onPressed: _busy ? null : _send, child: Text(_busy ? 'Sending…' : 'Send code')),
            ] else ...[
              const SizedBox(height: 12),
              Text('We sent a code to $_sentTo.'),
              const SizedBox(height: 8),
              TextField(controller: _code, keyboardType: TextInputType.number, autofillHints: const [AutofillHints.oneTimeCode], decoration: InputDecoration(labelText: 'Code', errorText: _errors['code'])),
              FormError(_errors['_']),
              const SizedBox(height: 12),
              FilledButton(onPressed: _busy ? null : _confirm, child: Text(_busy ? 'Checking…' : 'Confirm')),
              TextButton(onPressed: _busy ? null : () => setState(() { _challenge = null; _code.clear(); }), child: const Text('Use a different address')),
            ],
          ]),
        ),
      );
}

// ───────────────────────────── Privacy ─────────────────────────────

/// GET /privacy, then export or delete. Both first send a code to the customer's own email or mobile (POST /privacy/code).
class PrivacyScreen extends StatefulWidget {
  const PrivacyScreen({super.key, required this.state});
  final AppState state;
  @override
  State<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends State<PrivacyScreen> {
  final _code = TextEditingController(), _confirmText = TextEditingController();
  String? _action, _challenge, _sentTo;
  bool _busy = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _code.dispose();
    _confirmText.dispose();
    super.dispose();
  }

  Future<void> _start(String action) async {
    setState(() { _busy = true; _errors = {}; _action = action; _code.clear(); _confirmText.clear(); });
    try {
      final d = unwrap(await widget.state.api.post('/privacy/code', {}));
      setState(() { _challenge = '${d['challenge']}'; _sentTo = d['sent_to'] == null ? null : '${d['sent_to']}'; });
    } on ApiException catch (e) {
      setState(() { _action = null; _errors = {'_': e.fieldErrors['code'] ?? e.message}; });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _cancel() => setState(() { _action = null; _challenge = null; _errors = {}; });

  Future<void> _submit() async {
    final code = _code.text.trim();
    final e = <String, String>{
      if (code.isEmpty) 'code': 'Enter the code we sent.',
      if (_action == 'delete' && _confirmText.text.trim() != 'DELETE') 'confirm': 'Type DELETE in capital letters to confirm.',
    };
    if (e.isNotEmpty) return setState(() => _errors = e);
    setState(() { _busy = true; _errors = {}; });
    try {
      if (_action == 'export') {
        final data = (await widget.state.api.post('/privacy/export', {'challenge': _challenge, 'code': code}))['data'];
        if (!mounted) return;
        _cancel();
        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PrivacyExportScreen(data: data)));
      } else {
        await widget.state.api.post('/privacy/delete', {'challenge': _challenge, 'code': code, 'confirm': 'DELETE'});
        if (!mounted) return;
        final messenger = ScaffoldMessenger.of(context);
        final nav = Navigator.of(context);
        await widget.state.signOutLocal();
        nav.popUntil((r) => r.isFirst);
        messenger.showSnackBar(const SnackBar(content: Text('Your account has been deleted.')));
      }
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Privacy and your data')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await widget.state.api.get('/privacy')),
            builder: (context, p, _) {
              final blockers = ((p['blockers'] as List?) ?? const []).map((b) => '$b').toList();
              return ListView(padding: const EdgeInsets.all(16), children: [
                const SectionTitle('Download your data'),
                const Text('Get a copy of your account, birth profiles, orders, bookings, reports and messages.'),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerLeft,
                  child: FilledButton.tonalIcon(onPressed: _busy || _action != null ? null : () => _start('export'), icon: const Icon(Icons.download_outlined), label: const Text('Download my data')),
                ),
                const SectionTitle('Delete your account'),
                if (blockers.isNotEmpty) ...[
                  const Text('Your account cannot be deleted yet:'),
                  for (final b in blockers)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [const Text('•  ', style: TextStyle(color: ArjColors.danger700)), Expanded(child: Text(b))]),
                    ),
                ] else
                  const Text('This removes your name, contact details, birth profiles and history. It cannot be undone.'),
                if (p['kept'] != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${p['kept']}', style: const TextStyle(fontSize: 12, color: ArjColors.ink600))),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerLeft,
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(foregroundColor: ArjColors.danger700),
                    onPressed: _busy || _action != null || blockers.isNotEmpty ? null : () => _start('delete'),
                    icon: const Icon(Icons.delete_forever_outlined),
                    label: const Text('Delete my account'),
                  ),
                ),
                if (_action == null) FormError(_errors['_']),
                if (_action != null && _challenge != null)
                  Card(
                    margin: const EdgeInsets.only(top: 16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Text(_action == 'export' ? 'Confirm the download' : 'Confirm account deletion', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                        const SizedBox(height: 6),
                        Text(_sentTo == null ? 'We sent you a confirmation code.' : 'We sent a confirmation code to $_sentTo.'),
                        const SizedBox(height: 8),
                        TextField(controller: _code, keyboardType: TextInputType.number, autofillHints: const [AutofillHints.oneTimeCode], decoration: InputDecoration(labelText: 'Code', errorText: _errors['code'])),
                        if (_action == 'delete') ...[
                          const SizedBox(height: 8),
                          TextField(controller: _confirmText, textCapitalization: TextCapitalization.characters, decoration: InputDecoration(labelText: 'Type DELETE to confirm', errorText: _errors['confirm'])),
                        ],
                        FormError(_errors['_']),
                        const SizedBox(height: 12),
                        Row(children: [
                          TextButton(onPressed: _busy ? null : _cancel, child: const Text('Cancel')),
                          const Spacer(),
                          FilledButton(
                            style: _action == 'delete' ? FilledButton.styleFrom(backgroundColor: ArjColors.danger700) : null,
                            onPressed: _busy ? null : _submit,
                            child: Text(_busy ? 'Please wait…' : (_action == 'export' ? 'Download' : 'Delete account')),
                          ),
                        ]),
                      ]),
                    ),
                  ),
              ]);
            },
          ),
        ),
      );
}

/// The exported data as formatted JSON, with Copy.
class PrivacyExportScreen extends StatelessWidget {
  const PrivacyExportScreen({super.key, required this.data});
  final Object? data;
  @override
  Widget build(BuildContext context) {
    final text = const JsonEncoder.withIndent('  ').convert(data);
    return Scaffold(
      appBar: AppBar(title: const Text('Your data'), actions: [
        IconButton(
          tooltip: 'Copy',
          icon: const Icon(Icons.copy),
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: text));
            if (context.mounted) showSnack(context, 'Copied. Paste it into a note or email to keep it.');
          },
        ),
      ]),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(16), children: [
          const Text('This is everything we hold about your account. It is not saved on this phone unless you copy it.', style: TextStyle(color: ArjColors.ink600)),
          const SizedBox(height: 12),
          SelectableText(text, style: const TextStyle(fontFamily: 'monospace', fontSize: 12)),
        ]),
      ),
    );
  }
}
