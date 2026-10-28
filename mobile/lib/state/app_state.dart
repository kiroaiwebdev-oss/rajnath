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

  Future<void> bootstrap() async {
    if (await api.token() != null) {
      try {
        user = (await api.get('/me'))['data'] as Map<String, dynamic>;
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
