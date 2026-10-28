import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

/// Website support page; uses the origin of API_BASE so staging builds point at staging.
Uri get supportUrl => Uri.parse('$siteOrigin/account/support');

class _Msg {
  const _Msg(this.text, {required this.mine});
  final String text;
  final bool mine;
}

class AssistantScreen extends StatefulWidget {
  const AssistantScreen({super.key, required this.state});
  final AppState state;
  @override
  State<AssistantScreen> createState() => _AssistantScreenState();
}

class _AssistantScreenState extends State<AssistantScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _messages = <_Msg>[
    const _Msg('Namaste! I can help with our services, bookings, reports and orders. How can I help you today?', mine: false),
  ];
  String? _conversation;
  String? _disclaimer;
  String? _notice;
  bool _handoff = false, _busy = false, _blocked = false;

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    if (text.isEmpty || _busy || _blocked) return;
    setState(() {
      _messages.add(_Msg(text, mine: true));
      _input.clear();
      _busy = true;
      _notice = null;
    });
    _toEnd();
    try {
      final r = await widget.state.api.post('/assistant/chat', {'message': text, if (_conversation != null) 'conversation': _conversation});
      setState(() {
        _conversation = r['conversation'] as String? ?? _conversation;
        _disclaimer = r['disclaimer'] as String? ?? _disclaimer;
        _handoff = r['handoff'] == true || _handoff;
        _messages.add(_Msg('${r['reply'] ?? ''}', mine: false));
      });
    } on ApiException catch (e) {
      setState(() {
        if (e.status == 429) {
          _blocked = true;
          _handoff = true;
          _notice = "You've reached today's limit for the assistant. Please try again tomorrow, or talk to our team.";
        } else if (e.status == 503 || e.code == 'assistant_unavailable') {
          _blocked = true;
          _handoff = true;
          _notice = "The assistant isn't available right now. Our team is happy to help instead.";
        } else {
          _notice = e.message;
        }
      });
    } finally {
      if (mounted) setState(() => _busy = false);
      _toEnd();
    }
  }

  void _toEnd() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      });

  Future<void> _openSupport() async {
    if (!await launchUrl(supportUrl, mode: LaunchMode.externalApplication) && mounted) showSnack(context, 'Could not open Support.');
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Ask our assistant')),
        body: SafeArea(
          child: Column(children: [
            Expanded(
              child: ListView.builder(
                controller: _scroll,
                padding: const EdgeInsets.all(16),
                itemCount: _messages.length + (_busy ? 1 : 0),
                itemBuilder: (context, i) {
                  if (i == _messages.length) {
                    return const Align(alignment: Alignment.centerLeft, child: Padding(padding: EdgeInsets.all(8), child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, semanticsLabel: 'Assistant is typing'))));
                  }
                  final m = _messages[i];
                  return Align(
                    alignment: m.mine ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.8),
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: m.mine ? ArjColors.ink900 : Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: m.mine ? null : Border.all(color: ArjColors.ink200),
                      ),
                      child: Semantics(label: m.mine ? 'You said' : 'Assistant said', child: SelectableText(m.text, style: TextStyle(color: m.mine ? Colors.white : ArjColors.ink900))),
                    ),
                  );
                },
              ),
            ),
            if (_notice != null)
              Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Text(_notice!, textAlign: TextAlign.center, style: const TextStyle(color: ArjColors.danger700))),
            if (_handoff)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: OutlinedButton.icon(onPressed: _openSupport, icon: const Icon(Icons.support_agent), label: const Text('Talk to our team')),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 8, 4),
              child: Row(children: [
                Expanded(
                  child: TextField(
                    controller: _input,
                    enabled: !_blocked,
                    minLines: 1,
                    maxLines: 4,
                    maxLength: 1000,
                    textInputAction: TextInputAction.send,
                    onSubmitted: (_) => _send(),
                    decoration: const InputDecoration(hintText: 'Type your question', counterText: ''),
                  ),
                ),
                IconButton(tooltip: 'Send', onPressed: _busy || _blocked ? null : _send, icon: const Icon(Icons.send)),
              ]),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(_disclaimer ?? 'Automated answers can be wrong. For personal guidance, book a consultation.', textAlign: TextAlign.center, style: const TextStyle(fontSize: 11, color: ArjColors.ink600)),
            ),
          ]),
        ),
      );
}
