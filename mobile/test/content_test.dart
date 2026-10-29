import 'package:app/api/api_client.dart';
import 'package:app/screens/astro_screens.dart';
import 'package:app/screens/content_screens.dart';
import 'package:app/state/app_state.dart';
import 'package:app/util/validators.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

class _Storage extends FlutterSecureStorage {
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => 't';
}

/// Fake API: routes are 'METHOD /path' → response body, or a function of the request.
/// Every request is recorded in [calls] so tests can inspect query parameters and bodies.
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

/// Tall test surface so long lazy lists are fully built.
void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(1200, 9000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

Future<void> _open(WidgetTester tester, Widget screen) async {
  await tester.pumpWidget(MaterialApp(home: screen));
  await tester.pumpAndSettle();
}

const _types = {
  'data': {
    'kinds': [
      {'key': 'rashi', 'hi': 'राशि', 'en': 'Rashi'},
      {'key': 'tarot', 'hi': 'टैरो', 'en': 'Tarot'},
    ],
    'periods': [
      {'key': 'daily', 'hi': 'दैनिक', 'en': 'Daily'},
      {'key': 'yearly', 'hi': 'वार्षिक', 'en': 'Yearly'},
    ],
    'topics': [
      {'key': 'general', 'hi': 'सामान्य', 'en': 'General'},
      {'key': 'love', 'hi': 'प्रेम', 'en': 'Love'},
    ],
  },
};

void main() {
  test('formatAtOffset keeps the given offset and shows UTC in IST', () {
    expect(formatAtOffset('2026-06-02T03:15:00+05:30'), '2 Jun 2026, 3:15 AM');
    expect(formatAtOffset('2026-06-01T21:45:00Z'), '2 Jun 2026, 3:15 AM');
    expect(formatAtOffset('2026-06-01T21:45:00+00:00', pattern: 'h:mm a'), '9:45 PM');
    expect(formatAtOffset('not a date'), 'not a date');
  });

  testWidgets('Rashifal lists every subject with the published body or Coming soon', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /horoscopes/types': (_) => _types,
      'GET /horoscopes': (o) {
        final q = o.queryParameters;
        if (q['kind'] == 'tarot') return {'data': [], 'meta': {'kind': 'tarot', 'lang': q['lang'], 'subjects': []}};
        final hi = q['lang'] == 'hi';
        return {
          'data': [
            {'subject': 0, 'name': hi ? 'मेष' : 'Mesha (Aries)', 'starts_on': '2026-11-03', 'ends_on': '2026-11-03', 'body': hi ? 'आज का दिन शुभ है।' : 'A good day for new work.'},
          ],
          'meta': {'kind': 'rashi', 'period': 'daily', 'topic': 'general', 'lang': q['lang'], 'subjects': hi ? ['मेष', 'वृषभ', 'मिथुन'] : ['Mesha (Aries)', 'Vrishabha (Taurus)', 'Mithuna (Gemini)']},
        };
      },
    }, calls);
    await _open(tester, HoroscopeScreen(state: AppState(api)));

    expect(find.text('आज का दिन शुभ है।'), findsOneWidget);
    expect(find.text('वृषभ'), findsOneWidget);
    expect(find.text('जल्द आ रहा है'), findsNWidgets(2));
    final first = calls.lastWhere((c) => c.path == '/horoscopes').queryParameters;
    expect(first, {'kind': 'rashi', 'period': 'daily', 'topic': 'general', 'lang': 'hi'});

    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();
    expect(find.text('A good day for new work.'), findsOneWidget);
    expect(find.text('Coming soon'), findsNWidgets(2));

    await tester.tap(find.text('Yearly'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters['year'], DateTime.now().year);
    expect(find.text('${DateTime.now().year + 1}'), findsOneWidget);

    await tester.tap(find.text('Tarot'));
    await tester.pumpAndSettle();
    expect(find.text('Not published yet'), findsOneWidget);
    expect(calls.last.queryParameters.containsKey('topic'), isFalse);
    expect(find.text('Love'), findsNothing);
  });

  testWidgets('Gochar detail shows dated events and favourable / needs-care chips', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /gochar/jupiter': (o) => {
            'data': {
              'planet': 'jupiter', 'name': 'Jupiter', 'hi': 'गुरु',
              'now': {'sign': 2, 'sign_name': 'Mithuna (Gemini)', 'degree': '24°10′', 'nakshatra': 'Punarvasu', 'retro': false},
              'events': [
                {'type': 'ingress', 'at': '2026-06-02T03:15:00+05:30', 'sign': 3, 'sign_name': 'Karka (Cancer)', 'retro': false, 'degree': null},
                {'type': 'retrograde', 'at': '2026-11-11T18:40:00+05:30', 'sign': 3, 'sign_name': 'Karka (Cancer)', 'retro': null, 'degree': '25°02′'},
              ],
              'by_moon_sign': [
                for (var i = 0; i < 12; i++)
                  {'moon_sign': i, 'name': signNames[i], 'hi': 'राशि $i', 'house': (14 - i) % 12 + 1, 'favourable': i.isEven, 'note': i == 0 ? 'Good for study and family.' : null},
              ],
              'facts': {'signifies': 'Wisdom', 'gem': 'Yellow sapphire (Pukhraj)', 'exalt': [3, 5], 'debil': 9, 'nakshatras': ['Punarvasu', 'Vishakha'], 'dasha_years': 16},
            },
          },
    }, calls);
    await _open(tester, GocharDetailScreen(state: AppState(api), planet: 'jupiter', title: 'Jupiter'));

    expect(calls.single.queryParameters, {'year': DateTime.now().year});
    expect(find.text('Enters Karka (Cancer)'), findsOneWidget);
    expect(find.text('2 Jun 2026, 3:15 AM'), findsOneWidget);
    expect(find.text('Turns retrograde in Karka (Cancer) at 25°02′'), findsOneWidget);
    expect(find.text('Favourable'), findsNWidgets(6));
    expect(find.text('Needs care'), findsNWidgets(6));
    expect(find.textContaining('Good for study and family.'), findsOneWidget);
    expect(find.text('Karka (Cancer) 5°'), findsOneWidget);
    expect(find.text('16 years'), findsOneWidget);

    await tester.tap(find.byTooltip('Next year'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'year': DateTime.now().year + 1});
  });

  testWidgets('Gochar for the Moon steps by month', (tester) async {
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /gochar/moon': (_) => {'data': {'planet': 'moon', 'name': 'Moon', 'now': {'sign_name': 'Tula (Libra)'}, 'events': [], 'by_moon_sign': [], 'facts': {}}},
    }, calls);
    await _open(tester, GocharDetailScreen(state: AppState(api), planet: 'moon'));
    final now = DateTime.now();
    String ym(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}';
    expect(calls.single.queryParameters, {'month': ym(now)});
    expect(find.text('No sign changes or retrograde turns in this period.'), findsOneWidget);
    await tester.tap(find.byTooltip('Previous month'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters, {'month': ym(DateTime(now.year, now.month - 1))});
  });

  testWidgets('Muhurat shows dates with time windows and both notes', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final api = _api({
      'GET /muhurat/vivah': (o) => {
            'data': [
              {
                'date': '2026-11-20', 'masa': 'Margashirsha',
                'windows': [
                  {'start': '2026-11-20T07:05:00+05:30', 'end': '2026-11-20T11:40:00+05:30', 'tithi': 'Shukla Dashami', 'nakshatra': 'Uttara Bhadrapada'},
                  {'start': '2026-11-20T22:10:00+05:30', 'end': '2026-11-21T02:30:00+05:30', 'tithi': 'Shukla Ekadashi', 'nakshatra': 'Revati'},
                ],
              },
            ],
            'meta': {'event': 'vivah', 'name': 'Vivah', 'month': '2026-11', 'place': {'id': 1260086, 'name': 'Patna'}, 'note': 'Confirm the final muhurat with Acharya Ji.'},
          },
    }, calls);
    await _open(tester, MuhuratDetailScreen(state: AppState(api), event: 'vivah', title: 'Vivah'));

    expect(calls.single.queryParameters['place_id'], 1260086);
    expect(calls.single.queryParameters['month'], matches(RegExp(r'^\d{4}-\d{2}$')));
    expect(find.text('Fri, 20 Nov 2026'), findsOneWidget);
    expect(find.text('Margashirsha'), findsOneWidget);
    expect(find.text('7:05 AM – 11:40 AM\nShukla Dashami · Uttara Bhadrapada'), findsOneWidget);
    expect(find.text('10:10 PM – 21 Nov, 2:30 AM\nShukla Ekadashi · Revati'), findsOneWidget);
    expect(find.text('Confirm the final muhurat with Acharya Ji.'), findsOneWidget);
    expect(find.text('We do not give dates or times for childbirth; that is a decision for your doctors.'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'City'), findsOneWidget);

    await tester.tap(find.byTooltip('Next month'));
    await tester.pumpAndSettle();
    expect(calls.length, 2);
    expect(calls.last.queryParameters['month'], isNot(calls.first.queryParameters['month']));
  });

  testWidgets('Festivals are grouped by month and filtered by religion', (tester) async {
    _tall(tester);
    final calls = <RequestOptions>[];
    final all = [
      {'date': '2026-03-20', 'key': 'eid_ul_fitr', 'name': 'Eid ul-Fitr', 'religion': 'islamic', 'basis': 'Expected; depends on moon sighting'},
      {'date': '2026-11-08', 'key': 'diwali', 'name': 'Diwali', 'religion': 'hindu', 'basis': 'Kartika Amavasya'},
      {'date': '2026-12-25', 'key': 'christmas', 'name': 'Christmas', 'religion': 'christian', 'basis': null},
    ];
    final api = _api({
      'GET /festivals': (o) => {
            'data': [for (final f in all) if (o.queryParameters['religion'] == null || f['religion'] == o.queryParameters['religion']) f],
            'meta': {
              'religions': {'hindu': 'Hindu', 'islamic': 'Islamic', 'christian': 'Christian', 'sikh': 'Sikh', 'jain': 'Jain', 'buddhist': 'Buddhist'},
              'place': {'id': 1260086, 'name': 'Patna'},
            },
          },
    }, calls);
    await _open(tester, FestivalsScreen(state: AppState(api)));

    expect(calls.single.queryParameters, {'year': DateTime.now().year});
    for (final n in ['Eid ul-Fitr', 'Diwali', 'Christmas', 'March 2026', 'November 2026', 'December 2026']) {
      expect(find.text(n), findsOneWidget, reason: n);
    }

    await tester.tap(find.text('Islamic'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters['religion'], 'islamic');
    expect(find.text('Eid ul-Fitr'), findsOneWidget);
    expect(find.text('Diwali'), findsNothing);
    expect(find.text('Christmas'), findsNothing);

    await tester.tap(find.text('All'));
    await tester.pumpAndSettle();
    expect(find.text('Diwali'), findsOneWidget);

    await tester.tap(find.byTooltip('Previous year'));
    await tester.pumpAndSettle();
    expect(calls.last.queryParameters['year'], DateTime.now().year - 1);
  });

  testWidgets('Panchang shows Choghadiya with quality chips and Hora with planet names', (tester) async {
    _tall(tester);
    const names = ['Udveg', 'Char', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg'];
    const quality = {'Udveg': 'bad', 'Char': 'neutral', 'Labh': 'good', 'Amrit': 'good', 'Kaal': 'bad', 'Shubh': 'good', 'Rog': 'bad'};
    const lords = ['sun', 'venus', 'mercury', 'moon', 'saturn', 'jupiter', 'mars'];
    String at(int h) => DateTime.utc(2026, 11, 3, 0, 30).add(Duration(minutes: 90 * h)).toIso8601String();
    String hr(int h) => DateTime.utc(2026, 11, 3, 0, 30).add(Duration(hours: h)).toIso8601String();
    final api = _api({
      'GET /panchang': (_) => {
            'data': {
              'vara': 'Tuesday',
              'place': {'utc_offset': '+05:30'},
              'sunrise': at(0), 'sunset': at(8), 'moonrise': null, 'moonset': null,
              'angas': {
                'tithi': [{'name': 'Shukla Tritiya', 'ends': at(3)}],
                'nakshatra': [{'name': 'Rohini', 'ends': null}],
                'yoga': [{'name': 'Shiva', 'ends': null}],
                'karana': [{'name': 'Taitila', 'ends': null}],
              },
              'intervals': {
                for (final k in ['abhijit', 'rahu_kaal', 'yamaganda', 'gulika']) k: {'start': at(2), 'end': at(3)},
              },
              'choghadiya': [
                for (var i = 0; i < 16; i++) {'name': names[i % 8], 'quality': quality[names[i % 8]], 'period': i < 8 ? 'day' : 'night', 'start': at(i), 'end': at(i + 1)},
              ],
              'hora': [
                for (var i = 0; i < 24; i++) {'lord': lords[i % 7], 'period': i < 12 ? 'day' : 'night', 'start': hr(i), 'end': hr(i + 1)},
              ],
            },
          },
    }, []);
    await _open(tester, PanchangScreen(state: AppState(api)));

    expect(find.text('Timings'), findsOneWidget);
    expect(find.text('Choghadiya'), findsOneWidget);
    expect(find.text('Hora'), findsOneWidget);
    expect(find.text('Day'), findsNWidgets(2));
    expect(find.text('Night'), findsNWidgets(2));
    expect(find.text('Amrit'), findsNWidgets(2));
    expect(find.text('Good'), findsNWidgets(6));
    expect(find.text('Avoid'), findsNWidgets(8));
    expect(find.text('Neutral'), findsNWidgets(2));
    for (final p in ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn']) {
      expect(find.text(p), findsWidgets, reason: p);
    }
    expect(find.text('Jupiter'), findsNWidgets(3));
    // Choghadiya appears after Timings.
    expect(tester.getTopLeft(find.text('Choghadiya')).dy, greaterThan(tester.getTopLeft(find.text('Timings')).dy));
  });

  testWidgets('Panchang without choghadiya or hora hides those sections', (tester) async {
    final api = _api({
      'GET /panchang': (_) => {
            'data': {
              'vara': 'Tuesday', 'place': {'utc_offset': '+05:30'}, 'sunrise': null, 'sunset': null, 'moonrise': null, 'moonset': null,
              'angas': {'tithi': [], 'nakshatra': [], 'yoga': [], 'karana': []}, 'intervals': {},
            },
          },
    }, []);
    await _open(tester, PanchangScreen(state: AppState(api)));
    expect(find.text('Tuesday'), findsOneWidget);
    expect(find.text('Choghadiya'), findsNothing);
    expect(find.text('Hora'), findsNothing);
  });
}
