import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../models/slots.dart';
import '../payments/checkout.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'routing.dart';
import 'sign_in_screen.dart';

/// Returns true when signed in, prompting the sign-in screen first if needed.
Future<bool> ensureSignedIn(BuildContext context, AppState state, {String? reason}) async {
  if (state.signedIn) return true;
  final go = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: const Text('Please sign in'),
      content: Text(reason ?? 'Sign in to continue. It only takes a one-time code.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Not now')),
        FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Sign in')),
      ],
    ),
  );
  if (go != true || !context.mounted) return false;
  await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => SignInScreen(state: state)));
  return state.signedIn;
}

/// "Use wallet balance" switch that shows the current balance.
class WalletSwitch extends StatefulWidget {
  const WalletSwitch({super.key, required this.api, required this.value, required this.onChanged});
  final ApiClient api;
  final bool value;
  final ValueChanged<bool> onChanged;
  @override
  State<WalletSwitch> createState() => _WalletSwitchState();
}

class _WalletSwitchState extends State<WalletSwitch> {
  int? _balance;

  @override
  void initState() {
    super.initState();
    widget.api.get('/wallet').then((r) {
      if (mounted) setState(() => _balance = (r['balance_minor'] as num?)?.toInt());
    }).catchError((_) {});
  }

  @override
  Widget build(BuildContext context) => SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: const Text('Use wallet balance'),
        subtitle: Text(_balance == null ? 'Applied first; the rest is paid online.' : 'Available: ${formatPaise(_balance)}'),
        value: widget.value,
        onChanged: _balance == 0 ? null : widget.onChanged,
      );
}

/// Optional coupon code with Apply. Checks it via POST /coupons/check with [target]
/// (`{'variant': ref}` or `{'product': ref, 'quantity': n}`) and reports the quote
/// (`{code, description, list_minor, discount_minor, total_minor}`) or null when removed / invalid.
class CouponField extends StatefulWidget {
  const CouponField({super.key, required this.api, required this.target, required this.onChanged});
  final ApiClient api;
  final Map<String, dynamic> target;
  final ValueChanged<Map<String, dynamic>?> onChanged;
  @override
  State<CouponField> createState() => _CouponFieldState();
}

class _CouponFieldState extends State<CouponField> {
  final _code = TextEditingController();
  Map<String, dynamic>? _quote;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _apply() async {
    final code = _code.text.trim();
    if (code.isEmpty) return setState(() => _error = 'Enter a coupon code.');
    setState(() { _busy = true; _error = null; });
    try {
      final q = unwrap(await widget.api.post('/coupons/check', {'code': code, ...widget.target}));
      if (!mounted) return;
      setState(() => _quote = q);
      widget.onChanged(q);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() { _quote = null; _error = e.fieldErrors['coupon'] ?? e.fieldErrors['code'] ?? e.message; });
      widget.onChanged(null);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _remove() {
    setState(() { _quote = null; _error = null; _code.clear(); });
    widget.onChanged(null);
  }

  @override
  Widget build(BuildContext context) {
    final q = _quote;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(
          child: TextField(
            controller: _code,
            enabled: q == null && !_busy,
            textCapitalization: TextCapitalization.characters,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _apply(),
            decoration: InputDecoration(labelText: 'Coupon code', helperText: q == null ? 'Optional' : null, errorText: _error, errorMaxLines: 2),
          ),
        ),
        const SizedBox(width: 8),
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: q == null
              ? OutlinedButton(onPressed: _busy ? null : _apply, child: Text(_busy ? 'Checking…' : 'Apply'))
              : TextButton(onPressed: _remove, child: const Text('Remove')),
        ),
      ]),
      if (q != null) ...[
        const SizedBox(height: 8),
        Text('${q['code']} applied: you save ${formatPaise((q['discount_minor'] as num?)?.toInt() ?? 0)}',
            style: const TextStyle(color: ArjColors.success700, fontWeight: FontWeight.w600)),
        if (q['description'] != null) Text('${q['description']}', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
        Text('New total ${formatPaise((q['total_minor'] as num?)?.toInt())}'),
      ],
    ]);
  }
}

/// Confirm-and-pay screen for priced, non-slot variants (reports, puja, chadhava, palmistry).
class ServiceBuyScreen extends StatefulWidget {
  const ServiceBuyScreen({super.key, required this.state, required this.title, required this.variant});
  final AppState state;
  final String title;
  final Map variant;
  @override
  State<ServiceBuyScreen> createState() => _ServiceBuyScreenState();
}

class _ServiceBuyScreenState extends State<ServiceBuyScreen> {
  final _attempt = PurchaseAttempt();
  bool _useWallet = true, _busy = false;
  String? _error;
  Map<String, dynamic>? _coupon;

  Future<void> _buy() async {
    setState(() { _busy = true; _error = null; });
    try {
      final ref = '${widget.variant['ref']}';
      final coupon = _coupon?['code'] as String?;
      final r = await widget.state.api.post('/checkout/service', {
        'variant': ref,
        'use_wallet': _useWallet,
        'coupon': ?coupon,
        'key': _attempt.keyFor('$ref|$_useWallet|${coupon ?? ''}'),
      });
      if (!mounted) return;
      final order = await runCheckout(context, widget.state.api, r);
      if (!mounted) return;
      if (!isPendingPayment(order)) _attempt.reset();
      if (isPaidLike(order)) await afterPurchase(context, widget.state, order!, replace: true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = widget.variant;
    return Scaffold(
      appBar: AppBar(title: const Text('Confirm and pay')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          Text(widget.title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
          const SizedBox(height: 4),
          Text('${v['name']}', style: const TextStyle(color: ArjColors.ink600)),
          const SizedBox(height: 16),
          // One child, so the coupon field below keeps its state when the total row appears.
          Column(children: [
            _PriceRow(label: 'Price', minor: (v['price_minor'] as num?)?.toInt()),
            if (_coupon != null) _PriceRow(label: 'To pay after coupon', minor: (_coupon!['total_minor'] as num?)?.toInt()),
          ]),
          const SizedBox(height: 16),
          CouponField(key: const ValueKey('coupon'), api: widget.state.api, target: {'variant': '${v['ref']}'}, onChanged: (q) => setState(() => _coupon = q)),
          const Divider(height: 32),
          WalletSwitch(api: widget.state.api, value: _useWallet, onChanged: (x) => setState(() => _useWallet = x)),
          if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _buy, child: Text(_busy ? 'Please wait…' : 'Pay securely')),
          const SizedBox(height: 8),
          const Text('Payments are processed by Razorpay. After payment we will ask for any details we need.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
        ]),
      ),
    );
  }
}

class _PriceRow extends StatelessWidget {
  const _PriceRow({required this.label, required this.minor});
  final String label;
  final int? minor;
  @override
  Widget build(BuildContext context) => Row(children: [
        Expanded(child: Text(label)),
        Text(formatPaise(minor), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 18)),
      ]);
}

/// Pick a team astrologer for a team-consultation offering. Pops with the practitioner map.
class PractitionerPickerScreen extends StatelessWidget {
  const PractitionerPickerScreen({super.key, required this.state, required this.offeringSlug, required this.title});
  final AppState state;
  final String offeringSlug;
  final String title;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Choose an astrologer')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/practitioners', query: {'offering': offeringSlug})),
            isEmpty: (l) => l.isEmpty,
            emptyText: 'No astrologers are available for $title right now.\nPlease check back soon, or send us a request and our team will arrange it.',
            builder: (context, list, _) => ListView(padding: const EdgeInsets.all(16), children: [
              for (final p in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: () => Navigator.of(context).pop(p),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        CircleAvatar(
                          radius: 28,
                          backgroundColor: ArjColors.ivory200,
                          foregroundImage: p['photo_url'] != null ? NetworkImage('${p['photo_url']}') : null,
                          child: Text(initialOf(p['display_name']), semanticsLabel: ''),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('${p['display_name']}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                            Text([
                              if (p['experience_years'] != null) '${p['experience_years']} yrs experience',
                              if ((p['languages'] as List?)?.isNotEmpty ?? false) (p['languages'] as List).join(', '),
                            ].join(' · '), style: const TextStyle(color: ArjColors.ink600)),
                            if ((p['specialties'] as List?)?.isNotEmpty ?? false) ...[
                              const SizedBox(height: 6),
                              Wrap(spacing: 6, runSpacing: 6, children: [for (final s in p['specialties'] as List) Chip(label: Text('$s'), visualDensity: VisualDensity.compact)]),
                            ],
                            if (p['bio'] != null) ...[const SizedBox(height: 6), Text('${p['bio']}', maxLines: 3, overflow: TextOverflow.ellipsis)],
                          ]),
                        ),
                        const Icon(Icons.chevron_right, semanticLabel: 'Choose'),
                      ]),
                    ),
                  ),
                ),
            ]),
          ),
        ),
      );
}

/// Slot picker. Books a new consultation, or reschedules [rescheduleRef] when given.
class SlotBookingScreen extends StatefulWidget {
  const SlotBookingScreen({super.key, required this.state, required this.variantRef, required this.title, this.variantName, this.priceMinor, this.practitioner, this.rescheduleRef});
  final AppState state;
  final String variantRef;
  final String title;
  final String? variantName;
  final int? priceMinor;
  final Map? practitioner;
  final String? rescheduleRef;
  bool get rescheduling => rescheduleRef != null;
  @override
  State<SlotBookingScreen> createState() => _SlotBookingScreenState();
}

class _SlotBookingScreenState extends State<SlotBookingScreen> {
  final _attempt = PurchaseAttempt();
  final _topic = TextEditingController();
  Slot? _slot;
  String? _profile;
  bool _useWallet = true, _busy = false;
  String? _error;
  Map<String, dynamic>? _coupon;

  @override
  void dispose() {
    _topic.dispose();
    super.dispose();
  }

  Future<(List<SlotDay>, List<Map<String, dynamic>>)> _load() async {
    final api = widget.state.api;
    final slots = parseSlots(await api.get('/slots', query: {'variant': widget.variantRef, if (widget.practitioner?['ref'] != null) 'practitioner': widget.practitioner!['ref']}));
    var profiles = <Map<String, dynamic>>[];
    if (!widget.rescheduling) {
      try {
        profiles = unwrapList(await api.get('/birth-profiles'));
      } on ApiException catch (_) {/* optional */}
    }
    return (slots, profiles);
  }

  Future<void> _submit() async {
    final slot = _slot;
    if (slot == null) return setState(() => _error = 'Please choose a time.');
    setState(() { _busy = true; _error = null; });
    final api = widget.state.api;
    try {
      if (widget.rescheduling) {
        await api.post('/bookings/${widget.rescheduleRef}/reschedule', {'start': slot.start});
        if (!mounted) return;
        showSnack(context, 'Rescheduled to ${slot.label}.');
        Navigator.of(context).pop(true);
        return;
      }
      final practitioner = widget.practitioner?['ref'];
      final topic = _topic.text.trim();
      final coupon = _coupon?['code'] as String?;
      final r = await api.post('/checkout/booking', {
        'variant': widget.variantRef,
        'start': slot.start,
        'practitioner': ?practitioner,
        'birth_profile': ?_profile,
        if (topic.isNotEmpty) 'topic': topic,
        'use_wallet': _useWallet,
        'coupon': ?coupon,
        'key': _attempt.keyFor('${widget.variantRef}|${slot.start}|$practitioner|$_useWallet|${coupon ?? ''}'),
      });
      if (!mounted) return;
      final order = await runCheckout(context, api, r);
      if (!mounted) return;
      if (!isPendingPayment(order)) _attempt.reset();
      if (isPaidLike(order)) {
        await afterPurchase(context, widget.state, {...order!, 'next': order['next'] ?? {'type': 'booking', 'ref': (r['booking'] as Map?)?['ref']}}, replace: true);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.fieldErrors['start'] ?? e.fieldErrors['coupon'] ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.rescheduling ? 'Choose a new time' : 'Choose a time')),
        body: SafeArea(
          child: AsyncView<(List<SlotDay>, List<Map<String, dynamic>>)>(
            load: _load,
            isEmpty: (d) => d.$1.isEmpty,
            emptyText: 'No times are open in the next few days. Please check again later.',
            builder: (context, d, _) => ListView(padding: const EdgeInsets.all(16), children: [
              Text(widget.title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
              Text([
                if (widget.variantName != null) widget.variantName!,
                if (widget.practitioner != null) 'with ${widget.practitioner!['display_name']}',
                if (widget.priceMinor != null) formatPaise(widget.priceMinor),
              ].join(' · '), style: const TextStyle(color: ArjColors.ink600)),
              const SizedBox(height: 4),
              const Text('Times are shown in Indian Standard Time (IST).', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
              for (final day in d.$1) ...[
                SectionTitle(day.title),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final s in day.slots)
                    ChoiceChip(
                      label: Text(s.label),
                      tooltip: '${day.title}, ${s.label}',
                      selected: _slot?.start == s.start,
                      onSelected: (_) => setState(() => _slot = s),
                    ),
                ]),
              ],
              if (!widget.rescheduling) ...[
                const SectionTitle('About you (optional)'),
                if (d.$2.isNotEmpty)
                  DropdownButtonFormField<String?>(
                    initialValue: _profile,
                    decoration: const InputDecoration(labelText: 'Birth profile'),
                    items: [
                      const DropdownMenuItem<String?>(value: null, child: Text('None')),
                      for (final p in d.$2) DropdownMenuItem<String?>(value: '${p['ref']}', child: Text('${p['name']}')),
                    ],
                    onChanged: (v) => setState(() => _profile = v),
                  ),
                const SizedBox(height: 12),
                TextField(controller: _topic, maxLines: 2, maxLength: 300, decoration: const InputDecoration(labelText: 'What would you like to discuss?')),
                CouponField(key: const ValueKey('coupon'), api: widget.state.api, target: {'variant': widget.variantRef}, onChanged: (q) => setState(() => _coupon = q)),
                const SizedBox(height: 8),
                WalletSwitch(api: widget.state.api, value: _useWallet, onChanged: (x) => setState(() => _useWallet = x)),
              ],
              if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _busy || _slot == null ? null : _submit,
                child: Text(_busy ? 'Please wait…' : widget.rescheduling ? 'Confirm new time' : _slot == null ? 'Choose a time' : 'Book ${_slot!.label}'),
              ),
            ]),
          ),
        ),
      );
}

String initialOf(Object? name) {
  final s = '${name ?? ''}'.trim();
  return s.isEmpty ? '?' : s.characters.first.toUpperCase();
}
