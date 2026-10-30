import 'package:app/api/api_client.dart';
import 'package:app/main.dart';
import 'package:app/screens/showcase_screens.dart';
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

/// Fake API: 'METHOD /path' → body builder. Every request is recorded in [calls].
ApiClient _api(Map<String, Object Function(RequestOptions)> routes, List<RequestOptions> calls) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
    calls.add(o);
    final r = routes['${o.method} ${o.path}'];
    if (r == null) return h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    h.resolve(Response(requestOptions: o, statusCode: 200, data: r(o)));
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

Map<String, dynamic> _e(String title, {String? detail, Object? year, String? group, String? image, String? link}) => {
      'title': title,
      'detail': detail,
      'year': year,
      'group': group,
      'group_label': switch (group) { 'international' => 'International', 'national' => 'National', _ => null },
      'image_url': image,
      'link_url': link,
      'featured': false,
      'line': [title, if (detail != null) detail].join(' — ') + (year != null ? ' ($year)' : ''),
    };

final _profile = {
  'name': 'Acharya Dr. Rajnath Jha',
  'job': 'Vedic astrologer; Director, Jyotirveda Vigyan Sansthan, Patna',
  'headline': 'Ph.D. in Astrology · Patna, Bihar',
  'summary': 'Short summary.',
  'biography': ['First paragraph of the biography.', 'Second paragraph about Mahavir Mandir.'],
  'languages': ['Hindi', 'Maithili'],
  'stats': {'years': '30+', 'clients': '1,00,000+', 'awards': '0', 'languages': '2'},
  'home': {'eyebrow': 'Jyotish · Vastu', 'subtitle': 'Guidance from Patna since 1995.'},
  'photos': {'about': 'https://test/images/about.jpg', 'home': 'https://test/images/home.jpg'},
  'sections': [
    {
      'key': 'award',
      'label': 'Awards and honours',
      'entries': [
        _e('Hind Gaurav Award', detail: 'Indore', year: 2024, group: 'national', image: 'https://test/uploads/hind.jpg'),
        _e('Global Jyotish Samman', detail: 'Kathmandu', year: 2019, group: 'international'),
        _e('Mithila Ratna', year: 2015),
        _e('Bihar Jyotish Shiromani', detail: 'Patna', year: 2012, group: 'national'),
      ],
    },
    {'key': 'education', 'label': 'Education', 'entries': [_e('Ph.D. in Astrology', detail: 'K.S.D.S.U., Darbhanga', year: 2014)]},
    {'key': 'position', 'label': 'Positions', 'entries': [_e('Director', detail: 'Jyotirveda Vigyan Sansthan')]},
    {'key': 'expertise', 'label': 'Areas of expertise', 'entries': [_e('Vastu Shastra'), _e('Numerology')]},
    {'key': 'research', 'label': 'Research papers', 'entries': [_e('Earthquakes and Jyotish', link: 'https://example.com/paper')]},
    {'key': 'book', 'label': 'Books', 'entries': <Map>[]},
    {'key': 'media', 'label': 'Media and columns', 'entries': [_e('Kaal Dhamal', detail: 'Radio Mirchi 98.3 FM')]},
  ],
  'web': {'about': 'https://test/about', 'awards': 'https://test/awards', 'gallery': 'https://test/gallery'},
};

Map<String, dynamic> _album(String slug, String title, String category, String label) => {
      'slug': slug,
      'title': title,
      'description': 'About $title.',
      'category': category,
      'category_label': label,
      'event_date': '2024-11-01',
      'location': 'Patna',
      'cover_url': 'https://test/uploads/$slug.jpg',
      'photo_count': 2,
      'video_count': 1,
      'web_url': 'https://test/gallery/$slug',
    };

void main() {
  testWidgets('AboutScreen renders the profile from GET /profile', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    await _open(tester, AboutScreen(state: AppState(_api({'GET /profile': (_) => {'data': _profile}}, calls))));
    expect(calls.single.path, '/profile');
    for (final t in [
      'Acharya Dr. Rajnath Jha',
      'Vedic astrologer; Director, Jyotirveda Vigyan Sansthan, Patna',
      'First paragraph of the biography.',
      'Second paragraph about Mahavir Mandir.',
      '30+',
      'Clients guided',
      'Vastu Shastra',
      'Education',
      'Ph.D. in Astrology',
      'K.S.D.S.U., Darbhanga · 2014', // detail · year
      'Research papers',
      'Earthquakes and Jyotish',
      'Media and columns',
      'Hind Gaurav Award — Indore (2024)',
      'All awards',
      'Open the gallery',
      'Read more on the website',
    ]) {
      expect(find.text(t), findsOneWidget, reason: t);
    }
    expect(find.text('Books'), findsNothing); // empty section
    expect(find.text('Awards and honours'), findsOneWidget); // section title; the "0" awards stat is skipped
    expect(tester.takeException(), isNull); // the portrait fails to load in tests and falls back to the asset

    await tester.tap(find.text('All awards'));
    await tester.pumpAndSettle();
    expect(find.byType(AwardsScreen), findsOneWidget);
    expect(calls, hasLength(1)); // the loaded profile is reused
  });

  testWidgets('AwardsScreen groups awards by group_label and opens award photos', (tester) async {
    _tall(tester);
    await _open(tester, AwardsScreen(state: AppState(_api({'GET /profile': (_) => {'data': _profile}}, []))));
    final intl = tester.getTopLeft(find.text('International')).dy;
    final national = tester.getTopLeft(find.text('National')).dy;
    final more = tester.getTopLeft(find.text('More honours')).dy;
    expect(intl < national && national < more, isTrue);
    double y(String t) => tester.getTopLeft(find.text(t).last).dy;
    expect(y('Global Jyotish Samman') > intl && y('Global Jyotish Samman') < national, isTrue);
    expect(y('Bihar Jyotish Shiromani') > national && y('Bihar Jyotish Shiromani') < more, isTrue);
    expect(y('Mithila Ratna') > more, isTrue);
    expect(find.text('2019'), findsOneWidget);
    expect(find.text('Kathmandu'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('award-photo-0')));
    await tester.pumpAndSettle();
    expect(find.byType(PhotoViewerScreen), findsOneWidget);
    expect(find.text('1 / 1'), findsOneWidget);
    expect(find.text('Hind Gaurav Award — Indore (2024)'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('GalleryScreen lists albums and filters by category chip', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final albums = [_album('diwali-puja', 'Diwali puja', 'puja', 'Pujas and anushthans'), _album('patna-seminar', 'Patna seminar', 'events', 'Events and seminars')];
    final api = _api({
      'GET /gallery': (o) => {
            'data': [for (final a in albums) if (o.queryParameters['category'] == null || a['category'] == o.queryParameters['category']) a],
            'meta': {
              'categories': [
                {'key': 'puja', 'label': 'Pujas and anushthans'},
                {'key': 'events', 'label': 'Events and seminars'},
              ],
            },
          },
    }, calls);
    await _open(tester, GalleryScreen(state: AppState(api)));
    expect(calls.single.queryParameters, isEmpty);
    expect(find.text('Diwali puja'), findsOneWidget);
    expect(find.text('Patna seminar'), findsOneWidget);
    expect(find.text('1 Nov 2024 · Patna'), findsNWidgets(2));
    expect(find.text('2 photos · 1 video'), findsNWidgets(2));
    expect(find.widgetWithText(ChoiceChip, 'All'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Events and seminars'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'category': 'events'});
    expect(find.text('Diwali puja'), findsNothing);
    expect(find.text('Patna seminar'), findsOneWidget);
    expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'Events and seminars')).selected, isTrue);

    await tester.tap(find.widgetWithText(ChoiceChip, 'All'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, isEmpty);
    expect(find.text('Diwali puja'), findsOneWidget);

    await tester.tap(find.text('Diwali puja'));
    await tester.pumpAndSettle();
    expect(find.byType(AlbumScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('AlbumScreen shows thumbnails and opens the photo viewer', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /gallery/diwali-puja': (_) => {
            'data': {
              ..._album('diwali-puja', 'Diwali puja', 'puja', 'Pujas and anushthans'),
              'items': [
                {'kind': 'photo', 'url': 'https://test/uploads/1.jpg', 'thumb_url': 'https://test/uploads/1-t.jpg', 'width': 1600, 'height': 1200, 'caption': 'Lighting the lamps'},
                {'kind': 'video', 'video_id': 'abcdefghijk', 'url': 'https://www.youtube.com/watch?v=abcdefghijk', 'thumb_url': 'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg', 'caption': 'Aarti'},
                {'kind': 'photo', 'url': 'https://test/uploads/2.jpg', 'thumb_url': 'https://test/uploads/2-t.jpg', 'width': 1600, 'height': 1200, 'caption': 'Prasad for visitors'},
              ],
            },
          },
    }, calls);
    await _open(tester, AlbumScreen(state: AppState(api), slug: 'diwali-puja'));
    expect(calls.single.path, '/gallery/diwali-puja');
    expect(find.text('Diwali puja'), findsOneWidget);
    expect(find.text('1 Nov 2024 · Patna'), findsOneWidget);
    expect(find.text('About Diwali puja.'), findsOneWidget);
    expect(find.byIcon(Icons.play_arrow), findsOneWidget); // only the video

    await tester.tap(find.byKey(const ValueKey('album-item-2')));
    await tester.pumpAndSettle();
    expect(find.byType(PhotoViewerScreen), findsOneWidget);
    expect(find.byType(InteractiveViewer), findsOneWidget);
    expect(find.text('2 / 2'), findsOneWidget);
    expect(find.text('Prasad for visitors'), findsOneWidget);

    await tester.fling(find.byType(PageView), const Offset(800, 0), 1000);
    await tester.pumpAndSettle();
    expect(find.text('1 / 2'), findsOneWidget);
    expect(find.text('Lighting the lamps'), findsOneWidget);

    await tester.tap(find.byTooltip('Close'));
    await tester.pumpAndSettle();
    expect(find.byType(PhotoViewerScreen), findsNothing);
    expect(find.byType(AlbumScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('home hero uses GET /profile and keeps the built-in text without it', (tester) async {
    _tall(tester);
    final offline = AppState(_api({}, []));
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: HomeTab(state: offline, onOpenServices: () {}))));
    await tester.pumpAndSettle();
    expect(find.text('VEDIC ASTROLOGY · VASTU · REMEDIES'), findsOneWidget);
    expect(find.textContaining('30+ years of practice'), findsOneWidget);
    expect(find.text('Photo gallery'), findsOneWidget);

    final online = AppState(_api({'GET /profile': (_) => {'data': _profile}}, []));
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: HomeTab(key: const ValueKey('online'), state: online, onOpenServices: () {}))));
    await tester.pumpAndSettle();
    expect(find.text('JYOTISH · VASTU'), findsOneWidget);
    expect(find.text('Guidance from Patna since 1995.'), findsOneWidget);
    expect(find.text('1,00,000+'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
