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
  }

  Future<void> completeSignIn(String token, Map<String, dynamic> u) async {
    await api.saveToken(token);
    user = u;
    notifyListeners();
  }

  Future<void> refreshUser() async {
    user = (await api.get('/me'))['data'] as Map<String, dynamic>;
    notifyListeners();
  }

  Future<void> signOut() async {
    try {
      await api.post('/auth/logout', {});
    } catch (_) {/* token may already be revoked */}
    await signOutLocal();
  }

  Future<void> signOutLocal() async {
    await api.clearToken();
    user = null;
    notifyListeners();
  }
}
