import 'package:app/api/api_client.dart';
import 'package:app/main.dart';
import 'package:app/screens/blog_screens.dart';
import 'package:app/screens/info_screens.dart';
import 'package:app/screens/services_screens.dart';
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


Map<String, dynamic> _post(String slug, String title, {String category = 'jyotish', String label = 'Jyotish'}) => {
      'slug': slug,
      'title': title,
      'excerpt': 'Excerpt for $title.',
      'category': category,
      'category_label': label,
      'cover_url': '',
      'reading_minutes': 5,
      'published_at': '2025-03-14',
      'web_url': 'https://test/blog/$slug',
    };

const _cats = [
  {'key': 'jyotish', 'label': 'Jyotish'},
  {'key': 'vastu', 'label': 'Vastu'},
];

void main() {
  testWidgets('BlogScreen lists articles, filters by category, searches and loads more', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /blog': (o) {
        final q = o.queryParameters;
        if (q['category'] == 'vastu') return {'data': [_post('north-door', 'A north-facing door', category: 'vastu', label: 'Vastu')], 'meta': {'categories': _cats, 'page': 1, 'last_page': 1}};
        if (q['q'] == 'shani') return {'data': [], 'meta': {'categories': _cats, 'page': 1, 'last_page': 1}};
        if (q['page'] == 2) return {'data': [_post('rahu-kaal', 'What is Rahu Kaal')], 'meta': {'categories': _cats, 'page': 2, 'last_page': 2}};
        return {'data': [_post('sade-sati', 'Understanding Sade Sati'), _post('mangal-dosh', 'Mangal dosh explained')], 'meta': {'categories': _cats, 'page': 1, 'last_page': 2}};
      },
    }, calls);
    await _open(tester, BlogScreen(state: AppState(api)));

    expect(calls.single.queryParameters, {'page': 1});
    expect(find.text('Understanding Sade Sati'), findsOneWidget);
    expect(find.text('Mangal dosh explained'), findsOneWidget);
    expect(find.text('5 min read · 14 Mar 2025'), findsNWidgets(2));
    expect(find.widgetWithText(ChoiceChip, 'Vastu'), findsOneWidget);

    await tester.tap(find.text('Load more'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'page': 2});
    expect(find.text('What is Rahu Kaal'), findsOneWidget);
    expect(find.text('Understanding Sade Sati'), findsOneWidget); // appended
    expect(find.text('Load more'), findsNothing);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Vastu'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'category': 'vastu', 'page': 1});
    expect(find.text('A north-facing door'), findsOneWidget);
    expect(find.text('Understanding Sade Sati'), findsNothing);

    await tester.tap(find.widgetWithText(ChoiceChip, 'All'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'shani');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'q': 'shani', 'page': 1});
    expect(find.textContaining('No articles match'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('BlogScreen shows an error with retry', (tester) async {
    await _open(tester, BlogScreen(state: AppState(_api({}, []))));
    expect(find.text('Try again'), findsOneWidget);
  });

  testWidgets('ArticleScreen renders blocks, tags, related services and articles', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /blog/sade-sati': (_) => {
            'data': {
              ..._post('sade-sati', 'Understanding Sade Sati'),
              'blocks': [
                {'type': 'h2', 'text': 'What is Sade Sati?'},
                {'type': 'p', 'text': 'It is the seven and a half year transit of Saturn.'},
                {'type': 'h3', 'text': 'The three phases'},
                {'type': 'li', 'text': 'Rising phase'},
                {'type': 'li', 'text': 'Peak phase'},
                {'type': 'quote', 'text': 'Patience is the remedy.'},
              ],
              'tags': ['shani', 'saturn'],
              'services': ['kundli-consultation'],
              'related': [_post('mangal-dosh', 'Mangal dosh explained')],
            },
          },
      'GET /catalogue/offerings/kundli-consultation': (_) => {
            'data': {'slug': 'kundli-consultation', 'title': 'Kundli consultation', 'summary': 'A full reading.', 'image_url': 'https://test/k.jpg', 'includes': [], 'variants': []},
          },
    }, calls);
    await _open(tester, ArticleScreen(state: AppState(api), slug: 'sade-sati'));

    expect(find.text('Understanding Sade Sati'), findsOneWidget);
    expect(find.text('What is Sade Sati?'), findsOneWidget);
    expect(find.text('It is the seven and a half year transit of Saturn.'), findsOneWidget);
    expect(find.text('The three phases'), findsOneWidget);
    expect(find.text('Rising phase'), findsOneWidget);
    expect(find.text('Peak phase'), findsOneWidget);
    expect(find.text('Patience is the remedy.'), findsOneWidget);
    expect(find.byIcon(Icons.format_quote), findsOneWidget);
    expect(find.text('#shani'), findsOneWidget);
    expect(find.text('Related articles'), findsOneWidget);
    expect(find.text('Mangal dosh explained'), findsOneWidget);
    expect(find.text('Read on the website'), findsOneWidget);

    await tester.tap(find.text('Kundli consultation'));
    await tester.pumpAndSettle();
    expect(calls.last.path, '/catalogue/offerings/kundli-consultation');
    expect(find.byType(ServiceDetailScreen), findsOneWidget);
    expect(find.text('A full reading.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('HomeTab shows the ecosystem tiles, sign chips and latest articles', (tester) async {
    _tall(tester);
    final api = _api({
      'GET /blog': (_) => {'data': [_post('a', 'First article'), _post('b', 'Second article'), _post('c', 'Third article'), _post('d', 'Fourth article')], 'meta': {'categories': _cats, 'page': 1, 'last_page': 1}},
    }, []);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: HomeTab(state: AppState(api), onOpenServices: () {}))));
    await tester.pumpAndSettle();

    expect(find.text('Everything in one place'), findsOneWidget);
    for (final t in ['Free Kundli', 'Kundli matching', 'Online puja', 'Chadhava', 'Gemstones & samagri', 'Panchang', 'Muhurat', 'Grah gochar', 'Festivals', 'Calculators', 'Articles', 'Courses']) {
      expect(find.text(t), findsOneWidget, reason: t);
    }
    expect(find.text('Your sign today'), findsOneWidget);
    expect(find.text('Mesha'), findsOneWidget);
    expect(find.text('Latest articles'), findsOneWidget);
    expect(find.text('First article'), findsOneWidget);
    expect(find.text('Third article'), findsOneWidget);
    expect(find.text('Fourth article'), findsNothing); // only 3

    await tester.tap(find.text('Courses'));
    await tester.pumpAndSettle();
    expect(find.byType(CoursesScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('HomeTab hides the article strip when GET /blog fails', (tester) async {
    _tall(tester);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: HomeTab(state: AppState(_api({}, [])), onOpenServices: () {}))));
    await tester.pumpAndSettle();
    expect(find.text('Everything in one place'), findsOneWidget);
    expect(find.text('Latest articles'), findsNothing);
  });

  testWidgets('CoursesScreen lists the planned courses as coming soon and opens the request form', (tester) async {
    _tall(tester);
    await _open(tester, CoursesScreen(state: AppState(_api({}, []))));
    expect(find.text('Vedic astrology for beginners'), findsOneWidget);
    expect(find.text('Advanced predictive astrology'), findsOneWidget);
    expect(find.text('Coming soon'), findsNWidgets(6));
    await tester.tap(find.text('Tell me when admissions open'));
    await tester.pumpAndSettle();
    expect(find.byType(RequestScreen), findsOneWidget);
    expect(find.text('Courses: tell me when admissions open'), findsOneWidget);
  });

  testWidgets('MoreScreen has Articles and Courses', (tester) async {
    _tall(tester);
    await _open(tester, Scaffold(body: MoreScreen(state: AppState(_api({}, [])))));
    expect(find.text('Articles'), findsOneWidget);
    expect(find.text('Courses'), findsOneWidget);
  });
}
