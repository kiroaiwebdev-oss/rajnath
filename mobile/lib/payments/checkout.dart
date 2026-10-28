import 'dart:async';

import 'package:flutter/material.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:uuid/uuid.dart';

import '../api/api_client.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

const _uuid = Uuid();

/// Idempotency key for one purchase attempt. The same key is returned while the purchase
/// details ([fingerprint]) stay the same, so retries after a network error or a dismissed
/// payment sheet reuse the same order. Call [reset] once an order is settled.
class PurchaseAttempt {
  String? _fingerprint;
  String _key = _uuid.v4();

  String keyFor(String fingerprint) {
    if (_fingerprint != null && _fingerprint != fingerprint) _key = _uuid.v4();
    _fingerprint = fingerprint;
    return _key;
  }

  void reset() {
    _fingerprint = null;
    _key = _uuid.v4();
  }
}

bool isPendingPayment(Map? order) => order?['status'] == 'pending_payment';
bool isPaidLike(Map? order) => const {'paid', 'refunded', 'partially_refunded'}.contains(order?['status']);

/// What [runCheckout] has to do for a Checkout response.
enum CheckoutAction { done, pay }

CheckoutAction checkoutActionFor(Map checkout) => checkout['razorpay'] == null ? CheckoutAction.done : CheckoutAction.pay;

/// Razorpay Checkout options built from the API's `razorpay` block.
Map<String, dynamic> razorpayOptions(Map rp) {
  final prefill = (rp['prefill'] as Map?) ?? const {};
  return {
    'key': rp['key'],
    'amount': rp['amount'],
    'currency': rp['currency'] ?? 'INR',
    'order_id': rp['order_id'],
    'name': rp['name'] ?? 'Astro Rajnath Jha',
    'description': rp['description'] ?? '',
    'prefill': {
      if (prefill['email'] != null) 'email': prefill['email'],
      if (prefill['contact'] != null) 'contact': prefill['contact'],
    },
    'theme': {'color': '#141A3C'},
  };
}

/// Outcome reported by the payment sheet.
class PaymentOutcome {
  const PaymentOutcome.success({required this.orderId, required this.paymentId, required this.signature})
      : kind = PaymentOutcomeKind.success,
        message = null,
        cancelled = false;
  const PaymentOutcome.failed({this.message, this.cancelled = false})
      : kind = PaymentOutcomeKind.failed,
        orderId = null,
        paymentId = null,
        signature = null;
  const PaymentOutcome.externalWallet()
      : kind = PaymentOutcomeKind.external,
        orderId = null,
        paymentId = null,
        signature = null,
        message = null,
        cancelled = false;

  final PaymentOutcomeKind kind;
  final String? orderId, paymentId, signature, message;
  final bool cancelled;
}

enum PaymentOutcomeKind { success, failed, external }

typedef PaymentSheet = Future<PaymentOutcome> Function(Map<String, dynamic> options);

/// Opens the native Razorpay sheet and resolves with the first event.
Future<PaymentOutcome> openRazorpay(Map<String, dynamic> options) {
  final rp = Razorpay();
  final done = Completer<PaymentOutcome>();
  void finish(PaymentOutcome o) {
    if (!done.isCompleted) done.complete(o);
  }

  rp.on(Razorpay.EVENT_PAYMENT_SUCCESS, (PaymentSuccessResponse r) =>
      finish(PaymentOutcome.success(orderId: r.orderId, paymentId: r.paymentId, signature: r.signature)));
  rp.on(Razorpay.EVENT_PAYMENT_ERROR, (PaymentFailureResponse r) =>
      finish(PaymentOutcome.failed(message: r.message, cancelled: r.code == Razorpay.PAYMENT_CANCELLED)));
  rp.on(Razorpay.EVENT_EXTERNAL_WALLET, (ExternalWalletResponse _) => finish(const PaymentOutcome.externalWallet()));
  try {
    rp.open(options);
  } catch (e) {
    finish(const PaymentOutcome.failed(message: 'Could not open the payment page.'));
  }
  return done.future.whenComplete(rp.clear);
}

/// Completes a Checkout response from any `/checkout/*` endpoint.
///
/// * `razorpay == null` → the order is already settled (wallet / nothing due); it is returned immediately.
/// * Otherwise opens Razorpay, verifies the payment, polls the order (every 3 s, up to 30 s) and shows
///   a result screen. Returns the latest order, or null if it could not be determined.
Future<Map?> runCheckout(BuildContext context, ApiClient api, Map checkoutResponse, {PaymentSheet? sheet}) async {
  final order = checkoutResponse['order'] as Map?;
  if (checkoutActionFor(checkoutResponse) == CheckoutAction.done) return order;
  return Navigator.of(context).push<Map>(MaterialPageRoute(
    fullscreenDialog: true,
    builder: (_) => CheckoutFlowScreen(api: api, order: order ?? const {}, razorpay: checkoutResponse['razorpay'] as Map, sheet: sheet ?? openRazorpay),
  ));
}

enum _Phase { paying, confirming, paid, pending, failed, cancelled }

class CheckoutFlowScreen extends StatefulWidget {
  const CheckoutFlowScreen({super.key, required this.api, required this.order, required this.razorpay, required this.sheet,
      this.pollEvery = const Duration(seconds: 3), this.pollFor = const Duration(seconds: 30)});
  final ApiClient api;
  final Map order;
  final Map razorpay;
  final PaymentSheet sheet;
  final Duration pollEvery, pollFor;

  @override
  State<CheckoutFlowScreen> createState() => _CheckoutFlowScreenState();
}

class _CheckoutFlowScreenState extends State<CheckoutFlowScreen> {
  late Map _order = widget.order;
  _Phase _phase = _Phase.paying;
  String? _message;
  bool _busy = false;

  String get _ref => '${_order['ref']}';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _pay());
  }

  Future<void> _pay() async {
    setState(() { _phase = _Phase.paying; _message = null; });
    final outcome = await widget.sheet(razorpayOptions(widget.razorpay));
    if (!mounted) return;
    if (outcome.kind == PaymentOutcomeKind.failed) {
      setState(() { _phase = _Phase.failed; _message = outcome.cancelled ? 'Payment was cancelled.' : (outcome.message?.isNotEmpty == true ? outcome.message : 'The payment did not go through.'); });
      return;
    }
    setState(() => _phase = _Phase.confirming);
    if (outcome.kind == PaymentOutcomeKind.success) {
      try {
        final r = await widget.api.post('/orders/$_ref/verify', {
          'razorpay_order_id': outcome.orderId ?? widget.razorpay['order_id'],
          'razorpay_payment_id': outcome.paymentId,
          'razorpay_signature': outcome.signature,
        });
        if (r['data'] is Map) _order = r['data'] as Map;
      } on ApiException catch (_) {
        // The webhook may still confirm it; fall through to polling.
      }
    }
    await _poll();
  }

  Future<void> _poll() async {
    final deadline = DateTime.now().add(widget.pollFor);
    while (mounted && isPendingPayment(_order) && DateTime.now().isBefore(deadline)) {
      await Future<void>.delayed(widget.pollEvery);
      if (!mounted) return;
      try {
        final r = await widget.api.get('/orders/$_ref');
        if (r['data'] is Map) _order = r['data'] as Map;
      } on ApiException catch (_) {/* keep polling */}
    }
    if (!mounted) return;
    setState(() {
      if (isPendingPayment(_order)) {
        _phase = _Phase.pending;
      } else if (_order['status'] == 'cancelled') {
        _phase = _Phase.cancelled;
      } else {
        _phase = _Phase.paid;
      }
    });
  }

  Future<void> _cancelOrder() async {
    setState(() => _busy = true);
    try {
      final r = await widget.api.post('/orders/$_ref/cancel', {});
      if (r['data'] is Map) _order = r['data'] as Map;
      if (mounted) setState(() => _phase = _Phase.cancelled);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _close() => Navigator.of(context).pop(_order);

  @override
  Widget build(BuildContext context) {
    final working = _phase == _Phase.paying || _phase == _Phase.confirming;
    final amount = (_order['payable_minor'] as num?)?.toInt();
    final (IconData icon, Color color, String title, String body) = switch (_phase) {
      _Phase.paying => (Icons.payment, ArjColors.ink700, 'Opening secure payment…', 'Complete the payment in the Razorpay window.'),
      _Phase.confirming => (Icons.hourglass_top, ArjColors.ink700, 'Confirming your payment…', 'This usually takes a few seconds. Please keep the app open.'),
      _Phase.paid => (Icons.check_circle, ArjColors.success700, 'Payment received', 'Thank you. Your order is confirmed.'),
      _Phase.pending => (Icons.schedule, ArjColors.gold700, 'Still confirming', "Your bank hasn't confirmed the payment yet. We'll notify you as soon as it does — no need to pay again."),
      _Phase.failed => (Icons.error_outline, ArjColors.danger700, 'Payment not completed', _message ?? 'The payment did not go through.'),
      _Phase.cancelled => (Icons.cancel_outlined, ArjColors.ink600, 'Order cancelled', 'No money was taken for this order.'),
    };
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !working) _close();
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Payment'), automaticallyImplyLeading: false),
        body: SafeArea(
          child: Center(
            child: ListView(shrinkWrap: true, padding: const EdgeInsets.all(24), children: [
              if (working)
                const Center(child: SizedBox(width: 48, height: 48, child: CircularProgressIndicator(semanticsLabel: 'Working')))
              else
                Icon(icon, size: 56, color: color, semanticLabel: title),
              const SizedBox(height: 16),
              Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Text(body, textAlign: TextAlign.center, style: const TextStyle(color: ArjColors.ink600)),
              if (_order['label'] != null) ...[
                const SizedBox(height: 16),
                Text('${_order['label']}${amount != null ? ' · ${formatPaise(amount)}' : ''}', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600)),
              ],
              const SizedBox(height: 24),
              if (_phase == _Phase.failed) ...[
                FilledButton(onPressed: _busy ? null : _pay, child: const Text('Try again')),
                const SizedBox(height: 8),
                OutlinedButton(onPressed: _busy ? null : _cancelOrder, child: Text(_busy ? 'Cancelling…' : 'Cancel order')),
                TextButton(onPressed: _busy ? null : _close, child: const Text('Decide later')),
              ] else if (!working)
                FilledButton(onPressed: _close, child: Text(_phase == _Phase.paid ? 'Continue' : 'Done')),
            ]),
          ),
        ),
      ),
    );
  }
}
