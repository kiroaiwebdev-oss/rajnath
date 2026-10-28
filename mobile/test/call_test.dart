import 'package:app/api/api_client.dart';
import 'package:app/call/booking_call.dart';
import 'package:app/screens/activity_screens.dart';
import 'package:app/screens/call_screen.dart';
import 'package:app/state/app_state.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

class _Storage extends FlutterSecureStorage {
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => 't';
}

/// Fake API: 'METHOD /path' → (status, body).
ApiClient _api(Map<String, (int, Object) Function(RequestOptions)> routes, List<String> calls) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
    final key = '${o.method} ${o.path}';
    calls.add(key);
    final r = routes[key];
    if (r == null) return h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    final (status, body) = r(o);
    final res = Response(requestOptions: o, statusCode: status, data: body);
    if (status >= 400) return h.reject(DioException(requestOptions: o, response: res, type: DioExceptionType.badResponse));
    h.resolve(res);
  }));
  return ApiClient(dio: dio, storage: _Storage());
}

Map<String, dynamic> _booking(String ref, {String status = 'confirmed', String? provider, bool open = false, String? link, String mode = 'video'}) => {
      'ref': ref, 'title': 'Consultation $ref', 'variant': 'V1', 'mode': mode, 'start_utc': '2026-11-03T04:30:00Z', 'end_utc': '2026-11-03T05:00:00Z',
      'status': status, 'meeting_link': link, 'meeting_provider': provider, 'call_open': open, 'practitioner': null, 'practitioner_ref': null,
      'can_cancel': false, 'can_reschedule': false,
    };

CallButtonSpec _spec(Map<String, dynamic> b) => callButtonFor(BookingCall.fromJson(b));

void main() {
  group('booking call fields', () {
    test('parses provider, call_open and https link', () {
      final c = BookingCall.fromJson(_booking('A', provider: 'zoom', open: true, link: 'https://zoom.us/j/1'));
      expect(c.provider, 'zoom');
      expect(c.callOpen, isTrue);
      expect(c.meetingLink, 'https://zoom.us/j/1');
      expect(c.status, 'confirmed');
    });

    test('tolerates missing / odd values', () {
      final c = BookingCall.fromJson({'status': 'confirmed'});
      expect(c.provider, isNull);
      expect(c.callOpen, isFalse);
      expect(c.meetingLink, isNull);
      expect(BookingCall.fromJson({'meeting_provider': '', 'call_open': 'yes'}).provider, isNull);
      expect(BookingCall.fromJson({'call_open': 'yes'}).callOpen, isFalse);
      expect(BookingCall.fromJson({'meeting_link': 'http://insecure.example/x'}).meetingLink, isNull);
      expect(BookingCall.fromJson({'meeting_link': 'javascript:alert(1)'}).meetingLink, isNull);
    });

    test('parses the call token (bare or wrapped in data)', () {
      final t = CallToken.fromJson({'url': 'wss://lk.example', 'token': 'jwt', 'room': 'booking-R1', 'audio_only': true, 'identity': 'c-1'});
      expect((t.url, t.token, t.room, t.audioOnly, t.identity), ('wss://lk.example', 'jwt', 'booking-R1', true, 'c-1'));
      expect(CallToken.fromJson({'data': {'url': 'wss://x', 'token': 'j'}}).audioOnly, isFalse);
      expect(() => CallToken.fromJson({'url': '', 'token': 'j'}), throwsFormatException);
    });
  });

  group('call button choice', () {
    test('livekit: Join call, enabled only when call_open', () {
      final open = _spec(_booking('A', provider: 'livekit', open: true));
      expect((open.kind, open.label, open.enabled, open.hint), (CallButtonKind.inApp, 'Join call', true, null));
      final closed = _spec(_booking('A', provider: 'livekit', open: false));
      expect((closed.kind, closed.enabled, closed.hint), (CallButtonKind.inApp, false, callOpensHint));
    });

    test('external providers open their link', () {
      expect(_spec(_booking('A', provider: 'zoom', link: 'https://zoom.us/j/1')).label, 'Join on Zoom');
      expect(_spec(_booking('A', provider: 'google_meet', link: 'https://meet.google.com/abc')).label, 'Join on Google Meet');
      final m = _spec(_booking('A', provider: 'manual', link: 'https://example.com/call'));
      expect((m.kind, m.label, m.enabled, m.link), (CallButtonKind.external, 'Open call link', true, 'https://example.com/call'));
      // call_open does not gate external links
      expect(_spec(_booking('A', provider: 'zoom', open: false, link: 'https://zoom.us/j/1')).enabled, isTrue);
    });

    test('no link yet → pending hint', () {
      for (final p in [null, 'zoom', 'google_meet', 'manual']) {
        final s = _spec(_booking('A', provider: p));
        expect((s.kind, s.hint), (CallButtonKind.pending, callLinkPendingHint), reason: '$p');
      }
    });

    test('livekit ignores any meeting_link', () {
      expect(_spec(_booking('A', provider: 'livekit', open: true, link: 'https://x.example')).kind, CallButtonKind.inApp);
    });

    test('non-confirmed bookings show no call UI', () {
      for (final st in ['pending_payment', 'cancelled', 'completed', 'no_show']) {
        expect(_spec(_booking('A', status: st, provider: 'livekit', open: true)).kind, CallButtonKind.hidden, reason: st);
      }
    });
  });

  testWidgets('BookingsScreen renders the right call button per booking', (tester) async {
    final calls = <String>[];
    final api = _api({
      'GET /bookings': (_) => (200, {
            'data': [
              _booking('LK1', provider: 'livekit', open: true),
              _booking('LK2', provider: 'livekit', open: false, mode: 'audio'),
              _booking('ZM', provider: 'zoom', link: 'https://zoom.us/j/1'),
              _booking('GM', provider: 'google_meet', link: 'https://meet.google.com/abc'),
              _booking('MN', provider: 'manual', link: 'https://example.com/c'),
              _booking('NL', provider: null),
              _booking('CX', status: 'cancelled'),
            ],
          }),
    }, calls);
    await tester.pumpWidget(MaterialApp(home: BookingsScreen(state: AppState(api))));
    await tester.pumpAndSettle();

    ButtonStyleButton btn(String key) => tester.widget<ButtonStyleButton>(find.byKey(ValueKey(key)));
    Future<void> show(String text) => tester.scrollUntilVisible(find.text(text), 200, scrollable: find.byType(Scrollable).first);

    expect(btn('join-call-LK1').onPressed, isNotNull);
    expect(btn('join-call-LK2').onPressed, isNull);
    expect(find.text('Join call'), findsNWidgets(2));
    expect(find.text(callOpensHint), findsOneWidget);
    await show('Join on Zoom');
    expect(btn('join-link-ZM').onPressed, isNotNull);
    await show('Join on Google Meet');
    await show('Open call link');
    await show(callLinkPendingHint);
    expect(find.text(callLinkPendingHint), findsOneWidget);
    await show('Consultation CX');
    expect(find.byKey(const ValueKey('join-call-CX')), findsNothing);
    expect(find.byKey(const ValueKey('join-link-CX')), findsNothing);
    expect(calls, ['GET /bookings']);
  });

  testWidgets('CallScreen shows the 422 call_not_available message', (tester) async {
    final calls = <String>[];
    final api = _api({
      'POST /bookings/R1/call-token': (_) => (422, {'error': {'code': 'call_not_available', 'message': 'The call opens 15 minutes before the start.'}}),
    }, calls);
    await tester.pumpWidget(MaterialApp(home: CallScreen(api: api, bookingRef: 'R1', title: 'Kundli consultation')));
    await tester.pumpAndSettle();
    expect(find.text('The call opens 15 minutes before the start.'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
    expect(calls, ['POST /bookings/R1/call-token']);
  });
}
