import 'package:app/api/api_client.dart';
import 'package:app/main.dart';
import 'package:app/state/app_state.dart';
import 'package:app/theme/app_theme.dart';
import 'package:app/widgets/common.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

/// In-memory secure storage for tests.
class FakeStorage extends FlutterSecureStorage {
  final Map<String, String> data = {};
  @override
  Future<String?> read({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => data[key];
  @override
  Future<void> write({required String key, required String? value, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async =>
      value == null ? data.remove(key) : data[key] = value;
  @override
  Future<void> delete({required String key, AppleOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, AppleOptions? mOptions, WindowsOptions? wOptions}) async => data.remove(key);
}

ApiClient fakeApi(Map<String, Object> routes, FakeStorage s) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test/api/v1'));
  dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
    final key = '${o.method} ${o.path}';
    final r = routes[key];
    if (r is int) {
      h.reject(DioException(requestOptions: o, response: Response(requestOptions: o, statusCode: r, data: {'error': {'code': 'x', 'message': 'Failed $r'}}), type: DioExceptionType.badResponse));
    } else if (r == null) {
      h.reject(DioException(requestOptions: o, type: DioExceptionType.connectionError));
    } else {
      h.resolve(Response(requestOptions: o, statusCode: 200, data: r));
    }
  }));
  return ApiClient(dio: dio, storage: s);
}

void main() {
  test('Indian money formatting', () {
    expect(formatPaise(10000000), '₹1,00,000');
    expect(formatPaise(149950), '₹1,499.50');
    expect(formatPaise(null), 'Price on request');
  });

  test('API errors are mapped: offline, validation, 401 clears session', () async {
    final s = FakeStorage()..data['arj_token'] = 't';
    final api = fakeApi({'GET /me': 401}, s);
    final state = AppState(api);
    await state.bootstrap();
    expect(state.signedIn, false);
    expect(s.data.containsKey('arj_token'), false);
    try {
      await api.get('/nowhere');
      fail('expected error');
    } on ApiException catch (e) {
      expect(e.isOffline, true);
    }
  });

  test('stored token restores the session', () async {
    final s = FakeStorage()..data['arj_token'] = 't';
    final state = AppState(fakeApi({'GET /me': {'data': {'name': 'Asha', 'email': 'a@x.com'}}}, s));
    await state.bootstrap();
    expect(state.signedIn, true);
    expect(state.user!['name'], 'Asha');
  });

  testWidgets('shell renders with brand theme and bottom navigation', (tester) async {
    final state = AppState(fakeApi({}, FakeStorage()));
    await state.bootstrap();
    await tester.pumpWidget(ArjApp(state: state));
    await tester.pump();
    expect(find.text('Astro Rajnath Jha'), findsOneWidget);
    expect(find.text('Book a consultation'), findsOneWidget);
    final ctx = tester.element(find.byType(HomeShell));
    expect(Theme.of(ctx).scaffoldBackgroundColor, ArjColors.ivory50);
    await tester.tap(find.text('Account'));
    await tester.pumpAndSettle();
    expect(find.text('Sign in or create account'), findsOneWidget);
  });

  testWidgets('services tab shows offline error with retry', (tester) async {
    final state = AppState(fakeApi({}, FakeStorage()));
    await state.bootstrap();
    await tester.pumpWidget(ArjApp(state: state));
    await tester.tap(find.text('Services'));
    await tester.pumpAndSettle();
    expect(find.textContaining('No internet connection'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
  });
}
