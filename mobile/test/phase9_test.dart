import 'package:app/api/api_client.dart';
import 'package:app/models/slots.dart';
import 'package:app/payments/checkout.dart';
import 'package:app/screens/services_screens.dart';
import 'package:app/state/app_state.dart';
import 'package:app/util/validators.dart';
import 'package:app/widgets/common.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

class _Storage extends FlutterSecureStorage {
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => 't';
}

/// Fake API: routes are 'METHOD /path' → response body, or a function of the request.
ApiClient _api(Map<String, Object Function(RequestOptions)> routes, List<String> calls) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
    final key = '${o.method} ${o.path}';
    calls.add(key);
    final r = routes[key];
    if (r == null) return h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    h.resolve(Response(requestOptions: o, statusCode: 200, data: r(o)));
  }));
  return ApiClient(dio: dio, storage: _Storage());
}

Map<String, dynamic> _order(String status) => {'ref': 'R1', 'status': status, 'label': 'Kundli report', 'payable_minor': 210000};

void main() {
  group('money', () {
    test('formats paise as rupees with Indian grouping', () {
      expect(formatPaise(210000), '₹2,100');
      expect(formatPaise(0), '₹0');
      expect(formatPaise(99900), '₹999');
      expect(formatPaise(10000000), '₹1,00,000');
      expect(formatPaise(1234567800), '₹1,23,45,678');
      expect(formatPaise(150), '₹1.50');
      expect(formatPaise(null), 'Price on request');
    });
  });

  group('slots', () {
    test('parses, sorts by date and keeps the raw start', () {
      final days = parseSlots({
        'data': {
          '2026-11-04': [{'start': '2026-11-04T05:00Z', 'label': '10:30 AM'}],
          '2026-11-03': [
            {'start': '2026-11-03T04:30Z', 'label': '10:00 AM'},
            {'start': '2026-11-03T05:30Z', 'label': '11:00 AM'},
          ],
        },
      });
      expect(days.map((d) => d.date), ['2026-11-03', '2026-11-04']);
      expect(days.first.slots.map((s) => s.label), ['10:00 AM', '11:00 AM']);
      expect(days.first.slots.first.start, '2026-11-03T04:30Z');
      expect(days.first.slots.first.startUtc, DateTime.utc(2026, 11, 3, 4, 30));
      expect(days.first.title, 'Tue, 3 Nov');
    });

    test('tolerates empty and malformed payloads', () {
      expect(parseSlots({'data': []}), isEmpty);
      expect(parseSlots({'data': {}}), isEmpty);
      expect(parseSlots(null), isEmpty);
      final d = parseSlots({'data': {'2026-11-03': [], '2026-11-05': [{'label': 'no start'}, {'start': '2026-11-05T04:30Z'}]}});
      expect(d.length, 1);
      expect(d.single.slots.single.label, '2026-11-05T04:30Z');
    });

    test('slot-based variants need a slot mode and a duration', () {
      expect(isSlotVariant({'mode': 'video', 'duration_min': 30}), isTrue);
      expect(isSlotVariant({'mode': 'chat', 'duration_min': 15}), isTrue);
      expect(isSlotVariant({'mode': 'video', 'duration_min': null}), isFalse);
      expect(isSlotVariant({'mode': 'report', 'duration_min': 30}), isFalse);
    });
  });

  group('checkout decisions', () {
    test('null razorpay means done, otherwise pay', () {
      expect(checkoutActionFor({'order': _order('paid'), 'razorpay': null}), CheckoutAction.done);
      expect(checkoutActionFor({'order': _order('pending_payment'), 'razorpay': {'key': 'k'}}), CheckoutAction.pay);
    });

    test('razorpay options carry key, amount, order id, name, description and prefill', () {
      final o = razorpayOptions({'key': 'rzp_test', 'order_id': 'order_1', 'amount': 210000, 'currency': 'INR', 'name': 'ARJ', 'description': 'Kundli', 'prefill': {'email': 'a@x.com', 'contact': null}});
      expect(o['key'], 'rzp_test');
      expect(o['order_id'], 'order_1');
      expect(o['amount'], 210000);
      expect(o['name'], 'ARJ');
      expect(o['description'], 'Kundli');
      expect(o['prefill'], {'email': 'a@x.com'});
    });

    test('idempotency key is stable for the same attempt and renewed for a new one', () {
      final a = PurchaseAttempt();
      final k1 = a.keyFor('v1|true');
      expect(a.keyFor('v1|true'), k1);
      expect(RegExp(r'^[0-9a-f-]{36}$').hasMatch(k1), isTrue);
      final k2 = a.keyFor('v2|true');
      expect(k2, isNot(k1));
      a.reset();
      expect(a.keyFor('v2|true'), isNot(k2));
    });

    testWidgets('runCheckout returns the order immediately when razorpay is null', (tester) async {
      final calls = <String>[];
      final api = _api({}, calls);
      Map? result;
      await tester.pumpWidget(MaterialApp(home: Builder(builder: (context) => TextButton(onPressed: () async => result = await runCheckout(context, api, {'order': _order('paid'), 'razorpay': null}), child: const Text('go')))));
      await tester.tap(find.text('go'));
      await tester.pump();
      expect(result?['status'], 'paid');
      expect(calls, isEmpty);
      expect(find.byType(CheckoutFlowScreen), findsNothing);
    });

    testWidgets('successful payment is verified, polled until paid, and shows the result', (tester) async {
      final calls = <String>[];
      var polls = 0;
      Map? verifyBody;
      final api = _api({
        'POST /orders/R1/verify': (o) {
          verifyBody = o.data as Map;
          return {'data': _order('pending_payment')};
        },
        'GET /orders/R1': (_) => {'data': _order(++polls >= 2 ? 'paid' : 'pending_payment')},
      }, calls);
      Map? result;
      await tester.pumpWidget(MaterialApp(
        home: Builder(builder: (context) => TextButton(
              onPressed: () async => result = await Navigator.of(context).push<Map>(MaterialPageRoute(
                builder: (_) => CheckoutFlowScreen(
                  api: api,
                  order: _order('pending_payment'),
                  razorpay: const {'key': 'k', 'order_id': 'order_1', 'amount': 210000},
                  sheet: (_) async => const PaymentOutcome.success(orderId: 'order_1', paymentId: 'pay_1', signature: 'sig'),
                  pollEvery: const Duration(milliseconds: 10),
                ),
              )),
              child: const Text('go'),
            )),
      ));
      await tester.tap(find.text('go'));
      for (var i = 0; i < 10; i++) {
        await tester.pump(const Duration(milliseconds: 20));
      }
      expect(verifyBody, {'razorpay_order_id': 'order_1', 'razorpay_payment_id': 'pay_1', 'razorpay_signature': 'sig'});
      expect(find.text('Payment received'), findsOneWidget);
      await tester.tap(find.text('Continue'));
      await tester.pumpAndSettle();
      expect(result?['status'], 'paid');
      expect(calls.where((c) => c == 'GET /orders/R1').length, 2);
    });

    testWidgets('cancelled payment offers retry and cancel', (tester) async {
      final calls = <String>[];
      final api = _api({'POST /orders/R1/cancel': (_) => {'data': _order('cancelled')}}, calls);
      await tester.pumpWidget(MaterialApp(
        home: CheckoutFlowScreen(
          api: api,
          order: _order('pending_payment'),
          razorpay: const {'key': 'k', 'order_id': 'order_1', 'amount': 210000},
          sheet: (_) async => const PaymentOutcome.failed(cancelled: true),
        ),
      ));
      await tester.pump();
      await tester.pump();
      expect(find.text('Payment was cancelled.'), findsOneWidget);
      expect(find.text('Try again'), findsOneWidget);
      await tester.tap(find.text('Cancel order'));
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 10));
      }
      expect(find.text('Order cancelled'), findsOneWidget);
      expect(calls, ['POST /orders/R1/cancel']);
    });
  });

  testWidgets('service detail offers Book for slot variants, Buy for others, and keeps enquiry for unpriced', (tester) async {
    final api = _api({
      'GET /catalogue/offerings/kundli': (_) => {
            'data': {
              'title': 'Kundli consultation', 'summary': 's', 'includes': [], 'disclaimer': null,
              'variants': [
                {'ref': 'V1', 'name': 'Video 30 min', 'mode': 'video', 'duration_min': 30, 'participants': 1, 'urgent': false, 'price_minor': 210000},
                {'ref': 'V2', 'name': 'Written report', 'mode': 'report', 'duration_min': null, 'participants': 1, 'urgent': false, 'price_minor': 110000},
                {'ref': 'V3', 'name': 'Home visit', 'mode': 'onsite', 'duration_min': null, 'participants': 1, 'urgent': false, 'price_minor': null},
              ],
            },
          },
    }, []);
    await tester.pumpWidget(MaterialApp(home: ServiceDetailScreen(state: AppState(api), slug: 'kundli')));
    await tester.pumpAndSettle();
    expect(find.text('Book'), findsOneWidget);
    expect(find.text('Buy'), findsOneWidget);
    expect(find.textContaining('₹2,100'), findsOneWidget);
    expect(find.text('Request this service'), findsOneWidget);
  });

  group('validators', () {
    test('Indian mobile and pincode', () {
      expect(isValidIndianMobile('98765 43210'), isTrue);
      expect(isValidIndianMobile('+919876543210'), isTrue);
      expect(normalizeIndianMobile('+91 98765-43210'), '9876543210');
      expect(isValidIndianMobile('12345'), isFalse);
      expect(isValidIndianMobile('5876543210'), isFalse);
      expect(isValidPincode('800001'), isTrue);
      expect(isValidPincode('80001'), isFalse);
      expect(isValidPincode('080001'), isFalse);
    });

    test('site origin is derived from API_BASE', () {
      expect(siteOriginFor('https://astrorajnathjha.com/api/v1'), 'https://astrorajnathjha.com');
      expect(siteOriginFor('https://staging.example.com/api/v1/'), 'https://staging.example.com');
    });
  });
}
