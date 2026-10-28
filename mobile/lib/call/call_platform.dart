import 'package:flutter/services.dart';

/// Native helpers in MainActivity ("arj/call" channel). Failures are ignored: both are best-effort.
class CallPlatform {
  static const _ch = MethodChannel('arj/call');

  static Future<void> keepScreenOn(bool on) async {
    try {
      await _ch.invokeMethod<void>('keepScreenOn', on);
    } catch (_) {}
  }

  /// Android 12+: BLUETOOTH_CONNECT lets call audio use Bluetooth headsets. Not required for the call.
  static Future<bool> requestBluetooth() async {
    try {
      return await _ch.invokeMethod<bool>('requestBluetooth') ?? false;
    } catch (_) {
      return false;
    }
  }
}
