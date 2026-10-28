import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../state/app_state.dart';

// The backend contract stores classic FCM registration tokens (POST /push-tokens) and sends with
// them, so we deliberately keep getToken()/onTokenRefresh. firebase_messaging 17 deprecates them in
// favour of FID-based register()/onRegistered, which the backend does not support yet.
// ignore_for_file: deprecated_member_use

const _optionsKey = 'arj_firebase_options';

/// Builds FirebaseOptions from GET /meta `push.firebase`. Null when push is not configured.
FirebaseOptions? firebaseOptionsFromMeta(Map? meta) {
  final f = (meta?['push'] as Map?)?['firebase'];
  if (f is! Map) return null;
  final apiKey = f['api_key'], appId = f['app_id'], sender = f['messaging_sender_id'], project = f['project_id'];
  if ([apiKey, appId, sender, project].any((v) => v == null || '$v'.isEmpty)) return null;
  return FirebaseOptions(apiKey: '$apiKey', appId: '$appId', messagingSenderId: '$sender', projectId: '$project');
}

/// Runs in a background isolate when a data message arrives while the app is not in the foreground.
/// Notification messages are shown by the system; nothing else is needed here, but Firebase must be
/// initialised with the options cached at the last successful start.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    if (Firebase.apps.isNotEmpty) return;
    final raw = await const FlutterSecureStorage().read(key: _optionsKey);
    if (raw == null) return;
    final m = (jsonDecode(raw) as Map).cast<String, dynamic>();
    await Firebase.initializeApp(options: FirebaseOptions(apiKey: m['apiKey'], appId: m['appId'], messagingSenderId: m['messagingSenderId'], projectId: m['projectId']));
  } catch (_) {/* never crash in the background */}
}

/// FCM wiring. Push is optional: every step is guarded so a bad config can never crash the app.
class PushService {
  PushService(this.state, this.navigatorKey, {required this.targetBuilder, this.messengerKey});
  final AppState state;
  final GlobalKey<NavigatorState> navigatorKey;
  final GlobalKey<ScaffoldMessengerState>? messengerKey;

  /// Builds the screen for a push target (`data.kind` / `data.ref`).
  final Widget Function(String? kind, String? ref) targetBuilder;

  bool _ready = false;
  String? _registered;
  bool _wasSignedIn = false;
  final _subs = <StreamSubscription<dynamic>>[];

  Future<void> start() async {
    try {
      final meta = state.meta ?? await state.loadMeta();
      final options = firebaseOptionsFromMeta(meta);
      if (options == null) return;
      if (Firebase.apps.isEmpty) await Firebase.initializeApp(options: options);
      await const FlutterSecureStorage().write(
        key: _optionsKey,
        value: jsonEncode({'apiKey': options.apiKey, 'appId': options.appId, 'messagingSenderId': options.messagingSenderId, 'projectId': options.projectId}),
      );
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
      _ready = true;

      _subs.add(FirebaseMessaging.onMessage.listen(_onForeground));
      _subs.add(FirebaseMessaging.onMessageOpenedApp.listen(_open));
      _subs.add(FirebaseMessaging.instance.onTokenRefresh.listen((t) => _register(t)));
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) {
        // Wait for the first frame so the navigator exists.
        WidgetsBinding.instance.addPostFrameCallback((_) => _open(initial));
      }

      state.addListener(_onState);
      state.beforeSignOut = unregister;
      _wasSignedIn = state.signedIn;
      if (state.signedIn) await _enable();
    } catch (e) {
      debugPrint('Push disabled: $e');
    }
  }

  void _onState() {
    final now = state.signedIn;
    if (now && !_wasSignedIn) unawaited(_enable());
    if (!now) _registered = null;
    _wasSignedIn = now;
  }

  Future<void> _enable() async {
    if (!_ready) return;
    try {
      final settings = await FirebaseMessaging.instance.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _register(token);
    } catch (e) {
      debugPrint('Push registration failed: $e');
    }
  }

  Future<void> _register(String token) async {
    if (!state.signedIn || token == _registered) return;
    try {
      await state.api.post('/push-tokens', {'token': token, 'platform': 'android'});
      _registered = token;
    } catch (_) {/* retried on next start / refresh */}
  }

  /// Called before sign-out while the bearer token is still valid.
  Future<void> unregister() async {
    if (!_ready) return;
    try {
      final token = _registered ?? await FirebaseMessaging.instance.getToken();
      if (token != null) await state.api.delete('/push-tokens', {'token': token});
    } catch (_) {/* best effort */}
    _registered = null;
  }

  void _onForeground(RemoteMessage m) {
    unawaited(state.refreshUnread());
    final title = m.notification?.title ?? m.data['title'] as String?;
    final body = m.notification?.body ?? m.data['body'] as String?;
    final text = [title, body].whereType<String>().where((s) => s.isNotEmpty).join(' — ');
    if (text.isEmpty) return;
    messengerKey?.currentState?.showSnackBar(SnackBar(
      content: Text(text),
      action: SnackBarAction(label: 'View', onPressed: () => _open(m)),
      duration: const Duration(seconds: 6),
    ));
  }

  void _open(RemoteMessage m) {
    final nav = navigatorKey.currentState;
    if (nav == null || !state.signedIn) return;
    final kind = m.data['kind'] as String?, ref = m.data['ref'] as String?;
    nav.push(MaterialPageRoute<void>(builder: (_) => targetBuilder(kind, ref)));
    unawaited(state.refreshUnread());
  }

  void dispose() {
    for (final s in _subs) {
      s.cancel();
    }
    state.removeListener(_onState);
  }
}
