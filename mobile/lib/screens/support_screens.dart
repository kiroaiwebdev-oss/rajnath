import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'sign_in_screen.dart';

/// Support needs an account: shows the sign-in prompt until signed in, then the tickets.
class SupportGate extends StatelessWidget {
  const SupportGate({super.key, required this.state});
  final AppState state;
  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: state,
        builder: (context, _) => state.signedIn
            ? SupportScreen(state: state)
            : Scaffold(
                appBar: AppBar(title: const Text('Help & support')),
                body: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      const Text('Sign in to send us a support request and follow the replies.', textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SignInScreen(state: state))), child: const Text('Sign in')),
                    ]),
                  ),
                ),
              ),
      );
}

/// GET /support: the customer's tickets, newest activity first.
class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key, required this.state});
  final AppState state;
  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  int _v = 0;
  List<Map> _categories = const [];

  Future<List<Map<String, dynamic>>> _load() async {
    final r = await widget.state.api.get('/support');
    _categories = (((r['meta'] as Map?)?['categories'] as List?) ?? const []).whereType<Map>().toList();
    return unwrapList(r);
  }

  Future<void> _open(Widget page) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
    if (mounted) setState(() => _v++);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Help & support')),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _open(NewTicketScreen(state: widget.state, categories: _categories)),
          icon: const Icon(Icons.add),
          label: const Text('New request'),
        ),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            key: ValueKey(_v),
            load: _load,
            isEmpty: (l) => l.isEmpty,
            emptyText: 'No support requests yet. Tap New request to write to us.',
            builder: (context, list, _) => ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 96), children: [
              for (final t in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text('${t['subject']}'),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
                        ToneChip('${t['status_label'] ?? humanize(t['status'])}', _statusColor(t['status'])),
                        Text('${t['category_label'] ?? ''} · ${formatWhen(t['updated_at'], pattern: 'd MMM yyyy')}'),
                      ]),
                    ),
                    trailing: const Icon(Icons.chevron_right, semanticLabel: 'Open'),
                    onTap: () => _open(TicketScreen(state: widget.state, ref: '${t['ref']}')),
                  ),
                ),
            ]),
          ),
        ),
      );
}

Color _statusColor(Object? s) => switch (s) {
      'waiting_customer' => ArjColors.gold700,
      'resolved' => ArjColors.success700,
      'closed' => ArjColors.ink600,
      _ => ArjColors.ink700,
    };

class NewTicketScreen extends StatefulWidget {
  const NewTicketScreen({super.key, required this.state, this.categories = const []});
  final AppState state;
  final List<Map> categories;
  @override
  State<NewTicketScreen> createState() => _NewTicketScreenState();
}

class _NewTicketScreenState extends State<NewTicketScreen> {
  /// Fallback when the list has not loaded (Tickets::CATEGORIES).
  static const _defaults = [
    {'key': 'booking', 'label': 'Booking or consultation'}, {'key': 'payment', 'label': 'Payment or refund'}, {'key': 'report', 'label': 'Report'},
    {'key': 'puja', 'label': 'Puja / Chadhava'}, {'key': 'order', 'label': 'Gem order / delivery'}, {'key': 'account', 'label': 'Account or privacy'},
    {'key': 'other', 'label': 'Something else'},
  ];
  final _subject = TextEditingController(), _body = TextEditingController();
  String? _category;
  bool _busy = false;
  Map<String, String> _errors = {};

  List<Map> get _cats => widget.categories.isEmpty ? _defaults : widget.categories;

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final e = <String, String>{
      if (_category == null) 'category': 'Choose what this is about.',
      if (_subject.text.trim().isEmpty) 'subject': 'Add a short subject.',
      if (_body.text.trim().isEmpty) 'body': 'Tell us what happened.',
    };
    if (e.isNotEmpty) return setState(() => _errors = e);
    setState(() { _busy = true; _errors = {}; });
    try {
      final r = unwrap(await widget.state.api.post('/support', {'category': _category, 'subject': _subject.text.trim(), 'body': _body.text.trim()}));
      if (!mounted) return;
      showSnack(context, 'Thank you. Our team will reply here.');
      await Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => TicketScreen(state: widget.state, ref: '${r['ref']}')));
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('New support request')),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(16), children: [
            DropdownButtonFormField<String>(
              initialValue: _category,
              decoration: InputDecoration(labelText: 'What is this about?', errorText: _errors['category']),
              items: [for (final c in _cats) DropdownMenuItem(value: '${c['key']}', child: Text('${c['label']}'))],
              onChanged: (v) => setState(() => _category = v),
            ),
            const SizedBox(height: 12),
            TextField(controller: _subject, maxLength: 160, decoration: InputDecoration(labelText: 'Subject', errorText: _errors['subject'])),
            TextField(controller: _body, maxLines: 6, maxLength: 5000, decoration: InputDecoration(labelText: 'Message', errorText: _errors['body'])),
            FormError(_errors['_']),
            const SizedBox(height: 12),
            FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Sending…' : 'Send')),
          ]),
        ),
      );
}

/// One ticket's thread with reply and close.
class TicketScreen extends StatefulWidget {
  const TicketScreen({super.key, required this.state, required this.ref});
  final AppState state;
  final String ref;
  @override
  State<TicketScreen> createState() => _TicketScreenState();
}

class _TicketScreenState extends State<TicketScreen> {
  final _reply = TextEditingController();
  Map<String, dynamic>? _ticket;
  Object? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final t = unwrap(await widget.state.api.get('/support/${widget.ref}'));
      if (mounted) setState(() => _ticket = t);
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _act(String path, Map<String, dynamic> body, String done) async {
    setState(() => _busy = true);
    try {
      final t = unwrap(await widget.state.api.post(path, body));
      if (!mounted) return;
      setState(() => _ticket = t);
      _reply.clear();
      showSnack(context, done);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _close() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(title: const Text('Close this request?'), content: const Text('You can open a new one any time.'), actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Keep open')),
        FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Close request')),
      ]),
    );
    if (ok == true) await _act('/support/${widget.ref}/close', {}, 'Request closed.');
  }

  @override
  Widget build(BuildContext context) {
    final t = _ticket;
    final closed = t?['status'] == 'closed';
    return Scaffold(
      appBar: AppBar(title: const Text('Support request'), actions: [
        if (t != null && !closed) TextButton(onPressed: _busy ? null : _close, child: const Text('Close')),
      ]),
      body: SafeArea(
        child: t == null
            ? (_error != null
                ? ErrorPanel(message: _error is ApiException ? '$_error' : 'Something went wrong.', offline: _error is ApiException && (_error as ApiException).isOffline, onRetry: _load)
                : const Center(child: CircularProgressIndicator(semanticsLabel: 'Loading')))
            : Column(children: [
                Expanded(
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    Text('${t['subject']}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 6),
                    Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
                      ToneChip('${t['status_label'] ?? humanize(t['status'])}', _statusColor(t['status'])),
                      Text('${t['category_label'] ?? ''}', style: const TextStyle(color: ArjColors.ink600)),
                    ]),
                    const SizedBox(height: 12),
                    for (final m in ((t['messages'] as List?) ?? const []).whereType<Map>())
                      Align(
                        alignment: m['from'] == 'you' ? Alignment.centerRight : Alignment.centerLeft,
                        child: Container(
                          constraints: const BoxConstraints(maxWidth: 520),
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: m['from'] == 'you' ? ArjColors.ivory100 : ArjColors.gold50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: m['from'] == 'you' ? ArjColors.ivory200 : ArjColors.gold200),
                          ),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(m['from'] == 'you' ? 'You' : '${m['name'] ?? 'Our team'}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
                            const SizedBox(height: 4),
                            SelectableText('${m['body'] ?? ''}'),
                            const SizedBox(height: 4),
                            Text(formatWhen(m['at']), style: const TextStyle(fontSize: 11, color: ArjColors.ink600)),
                          ]),
                        ),
                      ),
                  ]),
                ),
                if (!closed)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 8, 8),
                    child: Row(children: [
                      Expanded(child: TextField(controller: _reply, minLines: 1, maxLines: 4, maxLength: 5000, decoration: const InputDecoration(labelText: 'Write a reply', counterText: ''))),
                      IconButton(
                        tooltip: 'Send reply',
                        onPressed: _busy
                            ? null
                            : () {
                                if (_reply.text.trim().isEmpty) return;
                                _act('/support/${widget.ref}/reply', {'body': _reply.text.trim()}, 'Reply sent.');
                              },
                        icon: const Icon(Icons.send),
                      ),
                    ]),
                  )
                else
                  const Padding(padding: EdgeInsets.all(16), child: Text('This request is closed. Open a new one if you need more help.', style: TextStyle(color: ArjColors.ink600))),
              ]),
      ),
    );
  }
}
