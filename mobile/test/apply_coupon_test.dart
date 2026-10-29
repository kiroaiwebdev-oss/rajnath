import 'package:app/api/api_client.dart';
import 'package:app/screens/activity_screens.dart';
import 'package:app/screens/apply_screens.dart';
import 'package:app/screens/purchase_screens.dart';
import 'package:app/screens/store_screens.dart';
import 'package:app/state/app_state.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

class _Storage extends FlutterSecureStorage {
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => 't';
}

/// A route result that makes the fake API answer with an HTTP error status.
class _Status {
  const _Status(this.code, this.body);
  final int code;
  final Object body;
}

/// Fake API: routes are 'METHOD /path' → a function of the request returning the body
/// (or a [_Status] for an error response). Every request is recorded in [calls].
ApiClient _api(Map<String, Object Function(RequestOptions)> routes, List<RequestOptions> calls) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
    calls.add(o);
    final r = routes['${o.method} ${o.path}'];
    if (r == null) return h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    final out = r(o);
    if (out is _Status) {
      return h.reject(DioException(requestOptions: o, type: DioExceptionType.badResponse, response: Response(requestOptions: o, statusCode: out.code, data: out.body)));
    }
    h.resolve(Response(requestOptions: o, statusCode: 200, data: out));
  }));
  return ApiClient(dio: dio, storage: _Storage());
}

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(1200, 4000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

AppState _signedIn(ApiClient api) => AppState(api)..user = {'name': 'Asha Devi', 'email': 'asha@example.com', 'phone': '9876543210'};

void main() {
  group('astrologer application', () {
    testWidgets('validates, shows a 422 field error, submits, then offers Withdraw', (tester) async {
      _tall(tester);
      final calls = <RequestOptions>[];
      final apps = <Map<String, dynamic>>[
        {'ref': 'OLD1', 'status': 'rejected', 'name': 'Asha Devi', 'email': 'old@example.com', 'experience_years': 2, 'languages': 'Hindi', 'specialties': 'Kundli', 'decision_note': 'Please gain more experience.', 'created_at': '2025-01-10T05:00:00Z'},
      ];
      var posts = 0;
      final api = _api({
        'GET /practitioner-applications': (_) => {'data': apps},
        'POST /practitioner-applications': (o) {
          final body = (o.data as Map).cast<String, dynamic>();
          if (++posts == 1) return const _Status(422, {'message': 'Invalid', 'errors': {'email': ['This email belongs to another account.']}});
          final a = {'ref': 'NEW1', 'status': 'submitted', 'name': body['name'], 'email': body['email'], 'experience_years': body['experience_years'], 'languages': body['languages'], 'specialties': body['specialties'], 'decision_note': null, 'created_at': '2026-11-03T05:00:00Z'};
          apps.insert(0, a);
          return {'data': a};
        },
        'POST /practitioner-applications/NEW1/withdraw': (_) {
          apps[0] = {...apps[0], 'status': 'withdrawn'};
          return {'data': apps[0]};
        },
      }, calls);
      await tester.pumpWidget(MaterialApp(home: AstrologerApplicationScreen(state: _signedIn(api))));
      await tester.pumpAndSettle();

      expect(find.text('Earlier applications'), findsOneWidget);
      expect(find.textContaining('Please gain more experience.'), findsOneWidget);
      expect(find.widgetWithText(TextField, 'Full name'), findsOneWidget);

      Future<void> type(String label, String text) => tester.enterText(find.widgetWithText(TextField, label), text);
      await type('Email for your astrologer account', 'ASHA@example.com');
      await type('Years of experience', '12');
      await type('Languages', 'Hindi, Maithili');
      await type('Specialties', 'Kundli, Vastu');
      await type('About your practice', 'Too short.');
      await type('Mobile number', '12345');
      await tester.tap(find.text('Send application'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Use a different email for your astrologer account'), findsOneWidget);
      expect(find.text('Tell us a little more (at least 60 characters).'), findsOneWidget);
      expect(find.text('Enter a 10-digit Indian mobile number.'), findsOneWidget);
      expect(find.text('Please confirm that your details are true.'), findsOneWidget);
      expect(calls.where((c) => c.method == 'POST'), isEmpty);

      await type('Email for your astrologer account', 'taken@example.com');
      await type('Mobile number', '+91 98765 11111');
      await type('About your practice', 'I have practised Parashari jyotish for twelve years, mostly kundli and vastu consultations in Darbhanga.');
      await tester.tap(find.text('I confirm these details are true'));
      await tester.tap(find.text('Send application'));
      await tester.pumpAndSettle();
      expect(find.text('This email belongs to another account.'), findsOneWidget);
      expect(find.text('Withdraw application'), findsNothing);

      await type('Email for your astrologer account', 'pandit.asha@example.com');
      await tester.tap(find.text('Send application'));
      await tester.pumpAndSettle();
      final body = calls.lastWhere((c) => c.method == 'POST').data as Map;
      expect(body['email'], 'pandit.asha@example.com');
      expect(body['phone'], '9876511111');
      expect(body['experience_years'], 12);
      expect(body['languages'], 'Hindi, Maithili');
      expect(body['consent'], true);
      expect(body['name'], 'Asha Devi');
      expect(find.text('Application sent. Our team will review it and email you.'), findsOneWidget);
      expect(find.text('Withdraw application'), findsOneWidget);
      expect(find.text('Send application'), findsNothing);

      await tester.tap(find.text('Withdraw application'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Withdraw'));
      await tester.pumpAndSettle();
      expect(calls.where((c) => c.path == '/practitioner-applications/NEW1/withdraw').length, 1);
      expect(find.text('Send application'), findsOneWidget);
      expect(find.text('Withdrawn'), findsOneWidget);
    });

    testWidgets('signed-out users are asked to sign in first', (tester) async {
      final api = _api({}, []);
      final state = AppState(api);
      await tester.pumpWidget(MaterialApp(home: Builder(builder: (context) => TextButton(onPressed: () => openAstrologerApplication(context, state), child: const Text('go')))));
      await tester.tap(find.text('go'));
      await tester.pumpAndSettle();
      expect(find.text('Please sign in'), findsOneWidget);
      await tester.tap(find.text('Not now'));
      await tester.pumpAndSettle();
      expect(find.byType(AstrologerApplicationScreen), findsNothing);
    });
  });

  group('coupons', () {
    testWidgets('apply shows the discount and checkout sends the coupon', (tester) async {
      _tall(tester);
      final calls = <RequestOptions>[];
      final api = _api({
        'GET /wallet': (_) => {'balance_minor': 0},
        'POST /coupons/check': (o) {
          final b = o.data as Map;
          if (b['code'] == 'OLD') return const _Status(422, {'errors': {'coupon': ['This coupon has expired.']}});
          return {'data': {'code': 'DIWALI10', 'description': '10% off consultations', 'list_minor': 110000, 'discount_minor': 11000, 'total_minor': 99000}};
        },
        'POST /checkout/service': (_) => {'order': {'ref': 'R1', 'status': 'paid', 'label': 'Written report', 'payable_minor': 99000}, 'razorpay': null},
      }, calls);
      await tester.pumpWidget(MaterialApp(home: ServiceBuyScreen(state: _signedIn(api), title: 'Kundli report', variant: const {'ref': 'V1', 'name': 'Written report', 'price_minor': 110000})));
      await tester.pumpAndSettle();

      final field = find.widgetWithText(TextField, 'Coupon code');
      await tester.enterText(field, 'OLD');
      await tester.tap(find.text('Apply'));
      await tester.pumpAndSettle();
      expect(find.text('This coupon has expired.'), findsOneWidget);

      await tester.enterText(field, 'diwali10');
      await tester.tap(find.text('Apply'));
      await tester.pumpAndSettle();
      final check = calls.lastWhere((c) => c.path == '/coupons/check').data as Map;
      expect(check, {'code': 'diwali10', 'variant': 'V1'});
      expect(find.text('This coupon has expired.'), findsNothing);
      expect(find.text('DIWALI10 applied: you save ₹110'), findsOneWidget);
      expect(find.text('New total ₹990'), findsOneWidget);
      expect(find.text('10% off consultations'), findsOneWidget);
      expect(find.text('Remove'), findsOneWidget);

      await tester.tap(find.text('Pay securely'));
      await tester.pumpAndSettle();
      final body = calls.lastWhere((c) => c.path == '/checkout/service').data as Map;
      expect(body['coupon'], 'DIWALI10');
      expect(body['variant'], 'V1');
    });

    testWidgets('removing the coupon leaves it out of the checkout body', (tester) async {
      _tall(tester);
      final calls = <RequestOptions>[];
      final api = _api({
        'GET /wallet': (_) => {'balance_minor': 0},
        'POST /coupons/check': (_) => {'data': {'code': 'X', 'description': null, 'list_minor': 110000, 'discount_minor': 1000, 'total_minor': 109000}},
        'POST /checkout/service': (_) => {'order': {'ref': 'R1', 'status': 'paid', 'label': 'Written report', 'payable_minor': 110000}, 'razorpay': null},
      }, calls);
      await tester.pumpWidget(MaterialApp(home: ServiceBuyScreen(state: _signedIn(api), title: 'Kundli report', variant: const {'ref': 'V1', 'name': 'Written report', 'price_minor': 110000})));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'Coupon code'), 'X');
      await tester.tap(find.text('Apply'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Remove'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Pay securely'));
      await tester.pumpAndSettle();
      final body = calls.lastWhere((c) => c.path == '/checkout/service').data as Map;
      expect(body.containsKey('coupon'), isFalse);
    });

    testWidgets('gem checkout checks the coupon for the quantity and sends it; changing quantity clears it', (tester) async {
      _tall(tester);
      final calls = <RequestOptions>[];
      final api = _api({
        'POST /coupons/check': (o) {
          final q = ((o.data as Map)['quantity'] as int);
          return {'data': {'code': 'GEM5', 'description': null, 'list_minor': 500000 * q, 'discount_minor': 25000 * q, 'total_minor': 475000 * q}};
        },
        'POST /checkout/product': (_) => {'order': {'ref': 'R2', 'status': 'paid', 'label': 'Ruby', 'payable_minor': 950000}, 'razorpay': null},
      }, calls);
      await tester.pumpWidget(MaterialApp(home: ProductBuyScreen(state: _signedIn(api), product: const {'ref': 'P1', 'name': 'Ruby', 'price_minor': 500000, 'max_per_order': 3})));
      await tester.pumpAndSettle();

      Future<void> apply() async {
        await tester.enterText(find.widgetWithText(TextField, 'Coupon code'), 'GEM5');
        await tester.tap(find.text('Apply'));
        await tester.pumpAndSettle();
      }

      await apply();
      expect(find.text('₹4,750'), findsOneWidget);
      await tester.tap(find.byTooltip('Increase quantity'));
      await tester.pumpAndSettle();
      expect(find.text('GEM5 applied: you save ₹250'), findsNothing);
      expect(find.text('₹10,000'), findsOneWidget);
      await apply();
      expect((calls.last.data as Map)['quantity'], 2);
      expect(find.text('GEM5 applied: you save ₹500'), findsOneWidget);

      Future<void> type(String label, String text) => tester.enterText(find.widgetWithText(TextField, label), text);
      await type('House / flat, street', '12 Boring Road');
      await type('City', 'Patna');
      await type('State', 'Bihar');
      await type('Pincode', '800001');
      await tester.tap(find.text('Pay securely'));
      await tester.pumpAndSettle();
      final body = calls.lastWhere((c) => c.path == '/checkout/product').data as Map;
      expect(body['coupon'], 'GEM5');
      expect(body['quantity'], 2);
    });

    testWidgets('order detail shows coupon discount, GST and invoice numbers', (tester) async {
      _tall(tester);
      final api = _api({
        'GET /orders/R1': (_) => {
              'data': {
                'ref': 'R1', 'status': 'paid', 'label': 'Written report', 'kind': 'service', 'created_at': '2026-11-03T05:00:00Z', 'paid_at': '2026-11-03T05:01:00Z',
                'list_minor': 110000, 'discount_minor': 11000, 'coupon': 'DIWALI10', 'subtotal_minor': 99000, 'payable_minor': 99000, 'tax_minor': 15102,
                'invoices': [
                  {'ref': 'I1', 'type': 'invoice', 'number': 'ARJ/26-27/0042', 'total_minor': 99000, 'tax_minor': 15102, 'pdf_path': '/invoices/I1.pdf'},
                  {'ref': 'I2', 'type': 'credit_note', 'number': 'ARJ-CN/26-27/0003', 'total_minor': 10000, 'tax_minor': 1525, 'pdf_path': '/invoices/I2.pdf'},
                ],
              },
            },
      }, []);
      await tester.pumpWidget(MaterialApp(home: OrderDetailScreen(state: _signedIn(api), ref: 'R1')));
      await tester.pumpAndSettle();
      expect(find.text('Coupon DIWALI10'), findsOneWidget);
      expect(find.text('− ₹110'), findsOneWidget);
      expect(find.text('₹1,100'), findsOneWidget);
      expect(find.text('Includes GST ₹151.02'), findsOneWidget);
      expect(find.text('Invoice ARJ/26-27/0042'), findsOneWidget);
      expect(find.text('Credit note ARJ-CN/26-27/0003'), findsOneWidget);
    });
  });
}
