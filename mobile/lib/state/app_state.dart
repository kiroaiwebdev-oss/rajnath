import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api/api_client.dart';

/// App-wide session state. Signed-in user is loaded from /me using the stored token.
class AppState extends ChangeNotifier {
  AppState(this.api) {
    api.onUnauthorized = () {
      if (user != null) signOutLocal();
    };
  }

  final ApiClient api;
  Map<String, dynamic>? user;
  bool ready = false;

  /// Public GET /meta (push config, assistant flag). Null until loaded.
  Map<String, dynamic>? meta;

  /// Unread in-app notifications (badge on the Account tab).
  int unread = 0;

  /// Runs before the bearer token is revoked on sign-out (e.g. DELETE /push-tokens).
  Future<void> Function()? beforeSignOut;

  bool get assistantEnabled => (meta?['assistant'] as Map?)?['enabled'] != false;

  bool get signedIn => user != null;

  /// Display names for the languages the server knows (config/locales.php).
  static const localeNames = {
    'en': 'English', 'hi': 'हिन्दी', 'bn': 'বাংলা', 'gu': 'ગુજરાતી', 'kn': 'ಕನ್ನಡ',
    'ml': 'മലയാളം', 'mr': 'मराठी', 'pa': 'ਪੰਜਾਬੀ', 'ta': 'தமிழ்', 'te': 'తెలుగు',
  };

  /// App language. Null until chosen (the server then uses the account's saved language, else English).
  String? locale;

  /// Languages offered in the app: `meta.locales` when the server reports them, else English and Hindi.
  List<String> get enabledLocales {
    final l = meta?['locales'];
    final list = l is List ? l.map((e) => e is Map ? '${e['code'] ?? e['key']}' : '$e').where(localeNames.containsKey).toList() : const <String>[];
    return list.isEmpty ? const ['en', 'hi'] : list;
  }

  Future<void> bootstrap() async {
    final saved = await api.savedLocale();
    if (saved != null && localeNames.containsKey(saved)) api.locale = locale = saved;
    if (await api.token() != null) {
      try {
        user = (await api.get('/me'))['data'] as Map<String, dynamic>;
        if (locale == null && localeNames.containsKey(user?['locale'])) api.locale = locale = '${user!['locale']}';
      } on ApiException catch (e) {
        if (e.status == 401) await api.clearToken();
      }
    }
    ready = true;
    notifyListeners();
    if (signedIn) unawaited(refreshUnread());
  }

  Future<Map<String, dynamic>?> loadMeta() async {
    try {
      meta = await api.get('/meta');
      notifyListeners();
    } catch (_) {/* offline: features fall back to defaults */}
    return meta;
  }

  Future<void> refreshUnread() async {
    if (!signedIn) return;
    try {
      final r = await api.get('/notifications');
      setUnread((r['unread'] as num?)?.toInt() ?? 0);
    } catch (_) {/* badge is best-effort */}
  }

  void setUnread(int n) {
    if (n == unread) return;
    unread = n;
    notifyListeners();
  }

  Future<void> completeSignIn(String token, Map<String, dynamic> u) async {
    await api.saveToken(token);
    user = u;
    notifyListeners();
    unawaited(refreshUnread());
  }

  /// Switches the app language: saved on the device, sent as Accept-Language, and saved to the account when signed in.
  Future<void> setLocale(String l) async {
    api.locale = locale = l;
    notifyListeners();
    await api.saveLocale(l);
    if (signedIn) {
      final r = await api.patch('/me', {'locale': l});
      if (r['data'] is Map) setUser((r['data'] as Map).cast<String, dynamic>());
    }
  }

  void setUser(Map<String, dynamic> u) {
    user = u;
    notifyListeners();
  }

  Future<void> refreshUser() async {
    user = (await api.get('/me'))['data'] as Map<String, dynamic>;
    notifyListeners();
  }

  Future<void> signOut() async {
    try {
      await beforeSignOut?.call();
    } catch (_) {/* never block sign-out */}
    try {
      await api.post('/auth/logout', {});
    } catch (_) {/* token may already be revoked */}
    await signOutLocal();
  }

  Future<void> signOutLocal() async {
    await api.clearToken();
    user = null;
    unread = 0;
    notifyListeners();
  }
}
