import 'package:app/api/api_client.dart';
import 'package:app/screens/info_screens.dart';
import 'package:app/screens/review_screens.dart';
import 'package:app/state/app_state.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

/// In-memory secure storage.
class _Storage extends FlutterSecureStorage {
  final Map<String, String> data = {};
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => data[key];
  @override
  Future<void> write({required String key, required String? value, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async =>
      value == null ? data.remove(key) : data[key] = value;
  @override
  Future<void> delete({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => data.remove(key);
}

/// A non-2xx answer from the fake API.
class _Fail {
  const _Fail(this.status, this.data);
  final int status;
  final Object data;
}

/// Fake API: 'METHOD /path' → body builder (or [_Fail]). Every request is recorded in [calls].
ApiClient _api(Map<String, Object Function(RequestOptions)> routes, List<RequestOptions> calls) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
    calls.add(o);
    final r = routes['${o.method} ${o.path}'];
    if (r == null) return h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    final body = r(o);
    if (body is _Fail) {
      return h.reject(DioException(requestOptions: o, type: DioExceptionType.badResponse, response: Response(requestOptions: o, statusCode: body.status, data: body.data)));
    }
    h.resolve(Response(requestOptions: o, statusCode: 200, data: body));
  }));
  return ApiClient(dio: dio, storage: _Storage());
}

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(1200, 6000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

Future<void> _open(WidgetTester tester, Widget screen) async {
  await tester.pumpWidget(MaterialApp(home: screen));
  await tester.pumpAndSettle();
}

Map<String, dynamic> _review(String ref, int rating, {String? title, String body = 'Very clear and practical guidance, thank you.', String name = 'Asha D.', String? city = 'Patna', String? reply, bool verified = true}) => {
      'ref': ref,
      'rating': rating,
      'title': title,
      'body': body,
      'name': name,
      'city': city,
      'subject': 'Kundli consultation',
      'verified': verified,
      'reply': reply,
      'date': '2025-03-14',
    };

Map<String, dynamic> _page(List<Map<String, dynamic>> items, {int page = 1, int last = 1}) => {
      'data': items,
      'meta': {
        'summary': {
          'count': 10,
          'average': 4.6,
          'stars': {'5': 7, '4': 2, '3': 1, '2': 0, '1': 0},
        },
        'page': page,
        'last_page': last,
        'total': 10,
      },
    };

void main() {
  testWidgets('ReviewsScreen shows the summary and cards, filters by star bar and loads more', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /reviews': (o) {
        final q = o.queryParameters;
        if (q['rating'] == 4) return _page([_review('R4', 4, body: 'Good advice about my career, will come again.', name: 'Vikas', city: null)]);
        if (q['page'] == 2) return _page([_review('R3', 3, body: 'Helpful but the call started a bit late.', name: 'Meena K.')], page: 2, last: 2);
        return _page([_review('R1', 5, title: 'Life changing', reply: 'Thank you, Asha ji.'), _review('R2', 5, name: 'Ravi S.', city: 'Delhi', verified: false)], last: 2);
      },
    }, calls);
    await _open(tester, ReviewsScreen(state: AppState(api)));

    expect(calls.single.queryParameters, {'page': 1});
    expect(find.text('4.6'), findsOneWidget);
    expect(find.text('10 reviews'), findsOneWidget);
    expect(find.text('7'), findsOneWidget); // 5-star bar count
    expect(find.text('Life changing'), findsOneWidget);
    expect(find.text('Reply from our team'), findsOneWidget);
    expect(find.text('Thank you, Asha ji.'), findsOneWidget);
    expect(find.text('Verified purchase'), findsOneWidget); // only R1
    expect(find.text('Asha D., Patna'), findsOneWidget);
    expect(find.text('Ravi S., Delhi'), findsOneWidget);
    expect(find.text('Kundli consultation · March 2025'), findsNWidgets(2));
    expect(find.bySemanticsLabel('Rated 5 out of 5'), findsNWidgets(2));

    await tester.tap(find.text('Load more'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'page': 2});
    expect(find.text('Meena K., Patna'), findsOneWidget);
    expect(find.text('Asha D., Patna'), findsOneWidget); // appended, not replaced
    expect(find.text('Load more'), findsNothing); // last page

    await tester.tap(find.byKey(const ValueKey('rating-bar-4')));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'rating': 4, 'page': 1});
    expect(find.text('Showing 4-star reviews'), findsOneWidget);
    expect(find.text('Vikas'), findsOneWidget);
    expect(find.text('Asha D., Patna'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('rating-bar-4'))); // tap again clears
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'page': 1});
    expect(find.text('Asha D., Patna'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('ReviewsScreen passes the service slug', (tester) async {
    final calls = <RequestOptions>[];
    await _open(tester, ReviewsScreen(state: AppState(_api({'GET /reviews': (_) => _page([])}, calls)), service: 'kundli-consultation'));
    expect(calls.single.queryParameters, {'service': 'kundli-consultation', 'page': 1});
    expect(find.textContaining('No reviews yet'), findsOneWidget);
  });

  testWidgets('WriteReviewScreen validates, posts the exact body and shows server field errors', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    var fail = true;
    final api = _api({
      'POST /reviews': (_) => fail
          ? const _Fail(422, {'message': 'Invalid', 'errors': {'order': ['You have already reviewed this order.'], 'body': ['Please remove the phone number.']}})
          : {'data': {'ref': 'NEW', 'status': 'pending'}},
    }, calls);
    final state = AppState(api)..user = {'name': 'Asha Devi Kumari'};
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (c) => Scaffold(
          body: TextButton(
            onPressed: () => Navigator.of(c).push(MaterialPageRoute(builder: (_) => WriteReviewScreen(state: state, orderRef: '01HZORDER00000000000000000', orderLabel: 'Kundli consultation'))),
            child: const Text('go'),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    expect(find.text('Kundli consultation'), findsOneWidget);
    expect(find.text('Asha K.'), findsOneWidget); // suggested name from the account
    expect(find.text(reviewConsentText), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'Your review'), 'Too short');
    await tester.tap(find.text('Send review'));
    await tester.pumpAndSettle();
    expect(find.text('Choose a star rating.'), findsOneWidget);
    expect(find.text('Please write at least 20 characters.'), findsOneWidget);
    expect(find.text('Please agree that we may publish your review.'), findsOneWidget);
    expect(calls, isEmpty);

    final four = find.bySemanticsLabel('4 stars');
    expect(four, findsOneWidget);
    await tester.tap(four);
    await tester.pumpAndSettle();
    expect(tester.getSemantics(four), containsSemantics(label: '4 stars', isButton: true, isSelected: true));
    expect(tester.getSemantics(find.bySemanticsLabel('3 stars')), containsSemantics(isSelected: false));
    expect(find.text('Choose a star rating.'), findsNothing);

    await tester.enterText(find.widgetWithText(TextField, 'Title (optional)'), '  Clear advice ');
    await tester.enterText(find.widgetWithText(TextField, 'Your review'), 'Acharya Ji explained my chart patiently and clearly.');
    await tester.enterText(find.widgetWithText(TextField, 'City (optional)'), 'Patna');
    await tester.tap(find.byType(Checkbox));
    await tester.tap(find.text('Send review'));
    await tester.pumpAndSettle();

    expect(calls.single.method, 'POST');
    expect(calls.single.data, {
      'order_ref': '01HZORDER00000000000000000',
      'rating': 4,
      'title': 'Clear advice',
      'body': 'Acharya Ji explained my chart patiently and clearly.',
      'display_name': 'Asha K.',
      'city': 'Patna',
      'consent': true,
    });
    expect(find.text('You have already reviewed this order.'), findsOneWidget); // general error
    expect(find.text('Please remove the phone number.'), findsOneWidget); // under the body field
    expect(find.byType(WriteReviewScreen), findsOneWidget);

    fail = false;
    await tester.tap(find.text('Send review'));
    await tester.pumpAndSettle();
    expect(find.byType(WriteReviewScreen), findsNothing);
    expect(find.text(reviewThanks), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('WriteReviewScreen edits with PUT and no consent box', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({'PUT /me/reviews/R9': (_) => {'data': {'ref': 'R9', 'status': 'pending'}}}, calls);
    await _open(tester, WriteReviewScreen(state: AppState(api), review: {..._review('R9', 2, city: null), 'status': 'rejected', 'editable': true}));
    expect(find.text('Edit your review'), findsOneWidget);
    expect(find.byType(Checkbox), findsNothing);
    await tester.tap(find.bySemanticsLabel('5 stars'));
    await tester.tap(find.text('Save changes'));
    await tester.pumpAndSettle();
    expect(calls.single.data, {'rating': 5, 'body': 'Very clear and practical guidance, thank you.', 'display_name': 'Asha D.'});
  });

  testWidgets('MyReviewsScreen lists orders to review, shows reject_reason and deletes after confirm', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    var reviews = [
      {..._review('R1', 3, title: 'Okay call'), 'status': 'rejected', 'status_label': 'Not published', 'reject_reason': 'Please remove the phone number.', 'editable': true},
      {..._review('R2', 5, title: 'Wonderful'), 'status': 'approved', 'status_label': 'Published', 'reject_reason': null, 'editable': false},
    ];
    final api = _api({
      'GET /me/reviews': (_) => {
            'data': reviews,
            'meta': {
              'suggested_name': 'Asha D.',
              'reviewable_orders': [
                {'ref': '01HZORDER00000000000000000', 'label': 'Blue sapphire (Neelam)', 'paid_at': '2025-02-01 10:00:00'},
              ],
            },
          },
      'DELETE /me/reviews/R1': (_) {
        reviews = [reviews[1]];
        return {'data': {'status': 'deleted'}};
      },
    }, calls);
    await _open(tester, MyReviewsScreen(state: AppState(api)));

    expect(find.text('Waiting for your review'), findsOneWidget);
    expect(find.text('Blue sapphire (Neelam)'), findsOneWidget);
    expect(find.text('Paid 1 Feb 2025'), findsOneWidget);
    expect(find.text('Not published'), findsOneWidget);
    expect(find.text('Published'), findsOneWidget);
    expect(find.text('Please remove the phone number.'), findsOneWidget);
    expect(find.text('Edit'), findsOneWidget); // only the editable one
    expect(find.text('Delete'), findsNWidgets(2));

    await tester.tap(find.text('Delete').first);
    await tester.pumpAndSettle();
    expect(find.text('Delete this review?'), findsOneWidget);
    await tester.tap(find.text('Keep it'));
    await tester.pumpAndSettle();
    expect(calls.where((c) => c.method == 'DELETE'), isEmpty);

    await tester.tap(find.text('Delete').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Delete review'));
    await tester.pumpAndSettle();
    expect(calls.where((c) => c.method == 'DELETE').single.path, '/me/reviews/R1');
    expect(find.text('Okay call'), findsNothing);
    expect(find.text('Wonderful'), findsOneWidget);
    expect(find.text('Review deleted.'), findsOneWidget);

    await tester.tap(find.text('Blue sapphire (Neelam)'));
    await tester.pumpAndSettle();
    expect(find.byType(WriteReviewScreen), findsOneWidget);
    expect(find.text('Asha D.'), findsOneWidget); // suggested_name prefill
    expect(tester.takeException(), isNull);
  });

  testWidgets('MoreScreen lists legal pages from GET /site and falls back without it', (tester) async {
    _tall(tester);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: MoreScreen(state: AppState(_api({}, []))))));
    await tester.pumpAndSettle();
    expect(find.text('Terms of service'), findsOneWidget);
    expect(find.text('Privacy policy'), findsOneWidget);
    expect(find.text('Client reviews'), findsOneWidget);

    final calls = <RequestOptions>[];
    final api = _api({
      'GET /site': (_) => {
            'data': {
              'name': 'Astro Rajnath Jha',
              'web': {'reviews': 'https://test/reviews'},
              'legal': [
                {'kind': 'privacy', 'title': 'Privacy notice', 'url': 'https://test/privacy'},
                {'kind': 'terms', 'title': 'Terms of use', 'url': 'https://test/terms'},
                {'kind': 'refunds', 'title': 'Cancellation and refund policy', 'url': 'https://test/refund-policy'},
              ],
            },
          },
    }, calls);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: MoreScreen(key: const ValueKey('online'), state: AppState(api)))));
    await tester.pumpAndSettle();
    expect(calls.single.path, '/site');
    for (final t in ['Privacy notice', 'Terms of use', 'Cancellation and refund policy']) {
      expect(find.text(t), findsOneWidget, reason: t);
    }
    expect(find.text('Terms of service'), findsNothing);
    expect(find.text('Shipping and delivery policy'), findsNothing); // not published
    expect(tester.takeException(), isNull);
  });
}
