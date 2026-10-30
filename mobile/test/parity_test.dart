import 'package:app/api/api_client.dart';
import 'package:app/screens/account_screens.dart';
import 'package:app/screens/activity_screens.dart';
import 'package:app/screens/astro_screens.dart';
import 'package:app/screens/services_screens.dart';
import 'package:app/screens/support_screens.dart';
import 'package:app/screens/tool_screens.dart';
import 'package:app/state/app_state.dart';
import 'package:app/widgets/tool_display.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

/// In-memory secure storage.
class _Storage extends FlutterSecureStorage {
  _Storage([Map<String, String>? data]) : data = data ?? {};
  final Map<String, String> data;
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => data[key];
  @override
  Future<void> write({required String key, required String? value, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async =>
      value == null ? data.remove(key) : data[key] = value;
  @override
  Future<void> delete({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => data.remove(key);
}

/// Fake API: 'METHOD /path' → body builder. Every request is recorded in [calls].
/// With [afterClient] the fake runs after ApiClient's own interceptor, so request headers are visible.
ApiClient _api(Map<String, Object Function(RequestOptions)> routes, List<RequestOptions> calls, [_Storage? storage, bool afterClient = false]) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  final fake = InterceptorsWrapper(onRequest: (o, h) {
    calls.add(o);
    final r = routes['${o.method} ${o.path}'];
    if (r == null) return h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    h.resolve(Response(requestOptions: o, statusCode: 200, data: r(o)));
  });
  if (!afterClient) dio.interceptors.add(fake);
  final api = ApiClient(dio: dio, storage: storage ?? _Storage({'arj_token': 't'}));
  if (afterClient) dio.interceptors.add(fake);
  return api;
}

AppState _signedIn(ApiClient api) => AppState(api)..user = {'name': 'Asha Devi', 'email': 'asha@example.com', 'phone': '+919876543210'};

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(1200, 6000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

Future<void> _open(WidgetTester tester, Widget screen) async {
  await tester.pumpWidget(MaterialApp(home: screen));
  await tester.pumpAndSettle();
}

const _display = {
  'sections': [
    {'title': 'Birth details', 'text': 'Asha · 01 Jan 1990', 'rows': [['Lagna (Ascendant)', 'Mesha 12°'], ['Nakshatra', 'Rohini, pada 2']]},
    {'highlight': '7 / 36 gunas', 'items': ['First point', 'Second point']},
    {'title': 'Lo Shu grid', 'table': {'head': [], 'rows': [['4', '9', '2'], ['3', '55', '—']]}},
    {'title': 'Planets', 'table': {'head': ['Planet', 'Sign'], 'rows': [['Sun', 'Dhanu']]}},
  ],
  'note': 'Method: Swiss Ephemeris.',
};

void main() {
  testWidgets('ToolDisplayView renders title, highlight, text, rows, items, tables and note', (tester) async {
    _tall(tester);
    await _open(tester, const Scaffold(body: SingleChildScrollView(child: ToolDisplayView(_display))));
    for (final t in ['Birth details', 'Asha · 01 Jan 1990', 'Lagna (Ascendant)', 'Mesha 12°', '7 / 36 gunas', 'First point', 'Second point', 'Lo Shu grid', '55', 'Planet', 'Dhanu', 'Method: Swiss Ephemeris.']) {
      expect(find.text(t), findsOneWidget, reason: t);
    }
    expect(find.byType(Table), findsNWidgets(2));
  });

  testWidgets('ToolDisplayView tolerates an empty or odd display', (tester) async {
    await _open(tester, const Scaffold(body: ToolDisplayView({'sections': [{'table': {'head': [], 'rows': []}}, 'junk'], 'note': null})));
    expect(find.byType(Card), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('lunarMonthText shows amanta and purnimanta when they differ', () {
    expect(lunarMonthText({'amanta': 'Kartika', 'purnimanta': 'Margashirsha'}), 'Lunar month: Kartika (amanta) · Margashirsha (purnimanta)');
    expect(lunarMonthText({'amanta': 'Kartika', 'purnimanta': 'Kartika'}), 'Lunar month: Kartika');
    expect(lunarMonthText('Kartika', 'Margashirsha'), 'Kartika (amanta) · Margashirsha (purnimanta)');
    expect(lunarMonthText('Kartika'), 'Kartika');
    expect(lunarMonthText(null), isNull);
  });

  testWidgets('Tools tab lists calculators from GET /tools', (tester) async {
    _tall(tester);
    final api = _api({
      'GET /tools': (_) => {
            'data': [
              {'slug': 'kundli', 'title': 'Free Kundli', 'input': 'birth', 'kind': 'traditional', 'description': 'Birth chart'},
              {'slug': 'flames', 'title': 'FLAMES', 'input': 'two_names', 'kind': 'entertainment', 'description': 'The classic name game.'},
            ],
          },
    }, []);
    await _open(tester, Scaffold(body: ToolsTab(state: AppState(api))));
    expect(find.text('Free Kundli'), findsOneWidget);
    expect(find.text('FLAMES'), findsOneWidget);
    expect(find.text('Vrat calendar'), findsOneWidget);
    await tester.tap(find.text('FLAMES'));
    await tester.pumpAndSettle();
    expect(find.byType(ToolScreen), findsOneWidget);
  });

  testWidgets('ToolScreen builds a two-names form, validates, posts and renders the display', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({'POST /tools/flames': (_) => {'data': {'tool': 'flames', 'display': {'sections': [{'highlight': 'Friends', 'text': 'Just a game.'}], 'note': null}}}}, calls);
    await _open(tester, ToolScreen(state: AppState(api), tool: const {'slug': 'flames', 'title': 'FLAMES', 'input': 'two_names', 'kind': 'entertainment', 'description': 'Just for fun.'}));
    expect(find.text('Just for fun'), findsOneWidget);

    await tester.tap(find.text('Calculate'));
    await tester.pumpAndSettle();
    expect(find.text('Please enter your name.'), findsOneWidget);
    expect(calls, isEmpty);

    await tester.enterText(find.widgetWithText(TextField, 'Your name'), 'Ravi');
    await tester.enterText(find.widgetWithText(TextField, "Other person's name"), 'Sita');
    await tester.tap(find.text('Calculate'));
    await tester.pumpAndSettle();
    expect(calls.single.data, {'name': 'Ravi', 'partner': 'Sita'});
    expect(find.text('Friends'), findsOneWidget);
    expect(find.text('Just a game.'), findsOneWidget);
  });

  testWidgets('ToolScreen dob form asks for the date of birth first', (tester) async {
    final calls = <RequestOptions>[];
    await _open(tester, ToolScreen(state: AppState(_api({}, calls)), tool: const {'slug': 'personal-year', 'title': 'Personal Year', 'input': 'dob', 'kind': 'symbolic'}));
    expect(find.text('Date of birth'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'Year (for personal year)'), findsOneWidget);
    await tester.tap(find.text('Calculate'));
    await tester.pumpAndSettle();
    expect(find.text('Please choose the date of birth.'), findsOneWidget);
    expect(calls, isEmpty);
  });

  testWidgets('Services mode chips come from meta.modes and filter offerings', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /catalogue/families': (_) => {
            'data': [{'code': 'K', 'slug': 'kundli', 'name': 'Kundli', 'summary': ''}],
            'meta': {'modes': [{'key': 'video', 'label': 'Video call'}, {'key': 'written', 'label': 'Written answer'}]},
          },
      'GET /catalogue/offerings': (_) => {'data': []},
    }, calls);
    await _open(tester, Scaffold(body: ServicesScreen(state: AppState(api))));
    expect(find.text('Any mode'), findsOneWidget);
    await tester.tap(find.text('Video call'));
    await tester.pumpAndSettle();
    expect(calls.last.path, '/catalogue/offerings');
    expect(calls.last.queryParameters['mode'], 'video');
  });

  testWidgets('Support ticket shows the thread, sends a reply and can be closed', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    Map<String, dynamic> ticket(String status, List<Map<String, dynamic>> msgs) => {
          'data': {'ref': 'T1', 'subject': 'Report delayed', 'category_label': 'Report', 'status': status, 'status_label': status == 'closed' ? 'Closed' : 'Open', 'messages': msgs},
        };
    final first = {'body': 'Where is my report?', 'from': 'you', 'name': null, 'at': '2026-11-03T05:00:00Z'};
    final team = {'body': 'It is nearly ready.', 'from': 'team', 'name': 'Priya', 'at': '2026-11-03T06:00:00Z'};
    final api = _api({
      'GET /support/T1': (_) => ticket('open', [first, team]),
      'POST /support/T1/reply': (o) => ticket('open', [first, team, {'body': '${(o.data as Map)['body']}', 'from': 'you', 'at': '2026-11-03T07:00:00Z'}]),
      'POST /support/T1/close': (_) => ticket('closed', [first, team]),
    }, calls);
    await _open(tester, TicketScreen(state: _signedIn(api), ref: 'T1'));
    expect(find.text('Where is my report?'), findsOneWidget);
    expect(find.text('Priya'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'Write a reply'), 'Thank you');
    await tester.tap(find.byTooltip('Send reply'));
    await tester.pumpAndSettle();
    expect(calls.last.data, {'body': 'Thank you'});
    expect(find.text('Thank you'), findsOneWidget);

    await tester.tap(find.widgetWithText(TextButton, 'Close'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Close request'));
    await tester.pumpAndSettle();
    expect(calls.last.path, '/support/T1/close');
    expect(find.textContaining('This request is closed'), findsOneWidget);
  });

  testWidgets('Privacy export sends a code first, then the challenge and code', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /privacy': (_) => {'data': {'blockers': [], 'kept': 'Order amounts are kept for tax law.'}},
      'POST /privacy/code': (_) => {'data': {'challenge': '01JCHALLENGE00000000000000', 'sent_to': 'a***@example.com'}},
      'POST /privacy/export': (_) => {'data': {'account': {'name': 'Asha Devi'}}},
    }, calls);
    await _open(tester, PrivacyScreen(state: _signedIn(api)));
    expect(find.text('Order amounts are kept for tax law.'), findsOneWidget);

    await tester.tap(find.text('Download my data'));
    await tester.pumpAndSettle();
    expect(calls.last.path, '/privacy/code');
    expect(find.text('We sent a confirmation code to a***@example.com.'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'Code'), '123456');
    await tester.tap(find.widgetWithText(FilledButton, 'Download'));
    await tester.pumpAndSettle();
    expect(calls.last.path, '/privacy/export');
    expect(calls.last.data, {'challenge': '01JCHALLENGE00000000000000', 'code': '123456'});
    expect(find.byType(PrivacyExportScreen), findsOneWidget);
    expect(find.textContaining('"name": "Asha Devi"'), findsOneWidget);
  });

  testWidgets('Privacy delete is disabled while there are blockers', (tester) async {
    final api = _api({'GET /privacy': (_) => {'data': {'blockers': ['You have an unpaid order. Pay or cancel it first.'], 'kept': null}}}, []);
    await _open(tester, PrivacyScreen(state: _signedIn(api)));
    expect(find.text('You have an unpaid order. Pay or cancel it first.'), findsOneWidget);
    final button = tester.widget<OutlinedButton>(find.ancestor(of: find.text('Delete my account'), matching: find.byWidgetPredicate((w) => w is OutlinedButton)));
    expect(button.onPressed, isNull);
  });

  testWidgets('Report detail offers Download PDF when the report is ready', (tester) async {
    final api = _api({
      'GET /reports/R1': (_) => {'data': {'ref': 'R1', 'title': 'Career report', 'status': 'completed', 'sections': [{'title': 'Career', 'body': 'Good.'}], 'pdf_url': 'https://test/files/report/R1?signature=x'}},
    }, []);
    await _open(tester, ReportDetailScreen(state: _signedIn(api), ref: 'R1'));
    expect(find.text('Download PDF'), findsOneWidget);
  });

  test('language choice is saved, sent as Accept-Language and saved to the account', () async {
    final calls = <RequestOptions>[];
    final storage = _Storage({'arj_token': 't'});
    final api = _api({
      'GET /me': (_) => {'data': {'name': 'Asha', 'locale': 'en'}},
      'GET /notifications': (_) => {'data': [], 'unread': 0},
      'PATCH /me': (o) => {'data': {'name': 'Asha', 'locale': (o.data as Map)['locale']}},
    }, calls, storage, true);
    final state = AppState(api);
    await state.bootstrap();
    expect(state.locale, 'en');
    expect(state.enabledLocales, ['en', 'hi']);
    expect(calls.first.headers['Accept-Language'], isNull);
    expect(calls.first.headers['Authorization'], 'Bearer t');

    await state.setLocale('hi');
    expect(storage.data['arj_locale'], 'hi');
    final patch = calls.lastWhere((c) => c.method == 'PATCH');
    expect(patch.data, {'locale': 'hi'});
    expect(patch.headers['Accept-Language'], 'hi');
    expect(state.user!['locale'], 'hi');

    final restored = AppState(_api({}, [], storage));
    await restored.bootstrap();
    expect(restored.locale, 'hi');
    expect(restored.api.locale, 'hi');
  });

  testWidgets('Birth profile edit is prefilled and saved with PUT', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({'PUT /birth-profiles/P1': (o) => {'data': o.data, 'notice': null}}, calls);
    const profile = {'ref': 'P1', 'label': 'spouse', 'name': 'Ravi', 'birth_date': '1988-05-04', 'birth_time': '06:30', 'place': {'id': 1260086, 'label': 'Patna, Bihar, India'}};
    await tester.pumpWidget(MaterialApp(home: Builder(builder: (context) => TextButton(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AddBirthProfileScreen(state: _signedIn(api), profile: profile))),
          child: const Text('open'),
        ))));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('Edit birth profile'), findsOneWidget);
    expect(find.text('4 May 1988'), findsOneWidget);
    expect(find.text('Patna, Bihar, India'), findsOneWidget);
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(calls.single.method, 'PUT');
    expect(calls.single.data, containsPair('birth_time', '06:30'));
    expect(calls.single.data, containsPair('place_id', 1260086));
    expect(calls.single.data, containsPair('label', 'spouse'));
  });
}
