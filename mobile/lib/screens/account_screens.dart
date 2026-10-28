import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';
import 'astro_screens.dart';
import 'palm_screens.dart';
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
      const SectionTitle('Settings'),
      _tile(context, Icons.people_outline, 'Birth profiles', () => BirthProfilesScreen(state: state)),
      _tile(context, Icons.devices_outlined, 'Signed-in devices', () => DevicesScreen(state: state)),
      _tile(context, Icons.tune, 'Notification settings', () => NotificationPrefsScreen(state: state)),
      const SizedBox(height: 16),
      OutlinedButton(onPressed: () => state.signOut(), child: const Text('Sign out')),
      const SizedBox(height: 24),
      const Text('Data export and account deletion: email acharyarajnathjha@gmail.com from your registered address (self-service coming soon).', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
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

class AddBirthProfileScreen extends StatefulWidget {
  const AddBirthProfileScreen({super.key, required this.state});
  final AppState state;
  @override
  State<AddBirthProfileScreen> createState() => _AddBirthProfileScreenState();
}

class _AddBirthProfileScreenState extends State<AddBirthProfileScreen> {
  final _name = TextEditingController();
  final _input = BirthInput();
  String _label = 'self';
  bool _busy = false;
  String? _error;

  Future<void> _save() async {
    if (!_input.complete || _name.text.trim().isEmpty) {
      setState(() => _error = 'Please fill name, date, time and place.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final r = await widget.state.api.post('/birth-profiles', {'label': _label, 'name': _name.text.trim(), 'time_accuracy': 'exact', ..._input.toJson()});
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
        appBar: AppBar(title: const Text('Add birth profile')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(16), children: [
            TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Full name')),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _label, decoration: const InputDecoration(labelText: 'Relation'),
                items: const {'self': 'Myself', 'spouse': 'Spouse / partner', 'child': 'Child', 'parent': 'Parent', 'sibling': 'Sibling', 'other': 'Other'}
                    .entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
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
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Devices')),
        body: SafeArea(
          child: AsyncView<List>(
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
