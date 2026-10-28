import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';

/// Email / mobile OTP sign-in and sign-up, matching the website flow.
class SignInScreen extends StatefulWidget {
  const SignInScreen({super.key, required this.state});
  final AppState state;
  @override
  State<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends State<SignInScreen> {
  final _id = TextEditingController();
  final _code = TextEditingController();
  final _name = TextEditingController();
  Map<String, dynamic>? _challenge;
  bool _terms = false, _busy = false;
  String? _error;
  int _resendIn = 0;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    _id.dispose();
    _code.dispose();
    _name.dispose();
    super.dispose();
  }

  void _startTimer(int s) {
    _timer?.cancel();
    setState(() => _resendIn = s);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      setState(() => _resendIn = _resendIn > 0 ? _resendIn - 1 : 0);
      if (_resendIn == 0) t.cancel();
    });
  }

  Future<void> _run(Future<void> Function() f) async {
    setState(() { _busy = true; _error = null; });
    try {
      await f();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _send() => _run(() async {
        final r = await widget.state.api.post('/auth/otp/start', {'identifier': _id.text.trim()});
        setState(() => _challenge = r);
        _startTimer((r['resend_in'] as num?)?.toInt() ?? 30);
      });

  Future<void> _resend() => _run(() async {
        await widget.state.api.post('/auth/otp/resend', {'challenge': _challenge!['challenge']});
        _startTimer(30);
      });

  Future<void> _verify() => _run(() async {
        final isNew = _challenge!['is_new_account'] == true;
        if (isNew && !_terms) throw ApiException('Please accept the Terms and Privacy Policy.');
        final r = await widget.state.api.post('/auth/otp/verify', {
          'challenge': _challenge!['challenge'], 'code': _code.text.trim(), 'device_name': 'Android app', 'platform': 'android',
          if (isNew) 'name': _name.text.trim(), if (isNew) 'accept_terms': true,
        });
        await widget.state.completeSignIn(r['token'] as String, (r['user'] as Map).cast<String, dynamic>());
        if (mounted) Navigator.of(context).pop(true);
      });

  @override
  Widget build(BuildContext context) {
    final c = _challenge;
    return Scaffold(
      appBar: AppBar(title: Text(c == null ? 'Sign in' : 'Enter code')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          if (c == null) ...[
            const Text('Enter your email or mobile number. We will send you a 6-digit code — no password needed.'),
            const SizedBox(height: 20),
            TextField(controller: _id, keyboardType: TextInputType.emailAddress, autofillHints: const [AutofillHints.username, AutofillHints.telephoneNumber],
                decoration: const InputDecoration(labelText: 'Email or mobile number', hintText: '98xxxxxxxx or you@example.com'), onSubmitted: (_) => _send()),
            const SizedBox(height: 20),
            FilledButton(onPressed: _busy ? null : _send, child: Text(_busy ? 'Sending…' : 'Send code')),
          ] else ...[
            Text('We sent a code to ${c['sent_to']}. It is valid for 10 minutes.'),
            const SizedBox(height: 20),
            TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6, autofillHints: const [AutofillHints.oneTimeCode],
                inputFormatters: [FilteringTextInputFormatter.digitsOnly], decoration: const InputDecoration(labelText: '6-digit code', counterText: '')),
            if (c['is_new_account'] == true) ...[
              const SizedBox(height: 16),
              TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Your name')),
              CheckboxListTile(contentPadding: EdgeInsets.zero, value: _terms, onChanged: (v) => setState(() => _terms = v ?? false),
                  title: const Text('I accept the Terms of use and Privacy policy'), controlAffinity: ListTileControlAffinity.leading),
            ],
            const SizedBox(height: 16),
            FilledButton(onPressed: _busy ? null : _verify, child: Text(c['is_new_account'] == true ? 'Create account' : 'Sign in')),
            const SizedBox(height: 8),
            TextButton(onPressed: _busy || _resendIn > 0 ? null : _resend, child: Text(_resendIn > 0 ? 'Resend code in ${_resendIn}s' : 'Resend code')),
            TextButton(onPressed: () => setState(() { _challenge = null; _code.clear(); }), child: const Text('Use a different email or number')),
          ],
          if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error), semanticsLabel: 'Error: $_error')),
        ]),
      ),
    );
  }
}
