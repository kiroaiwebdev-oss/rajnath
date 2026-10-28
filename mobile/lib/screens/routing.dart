import 'package:flutter/material.dart';

import '../state/app_state.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';
import 'palm_screens.dart';

/// Screen for an order's `next` step (`{type, ref}`), or null when there is nothing to do.
Widget? screenForNext(AppState state, Map order) {
  final next = order['next'];
  if (next is! Map) return null;
  final ref = next['ref'] == null ? null : '${next['ref']}';
  return switch (next['type']) {
    'palm_upload' when ref != null => PalmDetailScreen(state: state, ref: ref),
    'report_details' when ref != null => ReportDetailScreen(state: state, ref: ref),
    'puja_details' when ref != null => PujaDetailScreen(state: state, ref: ref),
    'booking' => BookingsScreen(state: state, highlight: ref),
    'shipment' => OrderDetailScreen(state: state, ref: '${order['ref']}'),
    _ => null,
  };
}

/// After a paid order: open the next step if there is one, otherwise confirm and go back.
Future<void> afterPurchase(BuildContext context, AppState state, Map order, {bool replace = false}) async {
  final nav = Navigator.of(context);
  final next = screenForNext(state, order);
  if (next == null) {
    showSnack(context, 'Thank you — your order ${order['label'] ?? ''} is confirmed.'.replaceAll('  ', ' '));
    if (replace && nav.canPop()) nav.pop();
    return;
  }
  final route = MaterialPageRoute<void>(builder: (_) => next);
  replace ? await nav.pushReplacement(route) : await nav.push(route);
}

/// Screen for a push / notification target (`kind` + `ref`). Unknown targets open Notifications.
Widget screenForTarget(AppState state, String? kind, String? ref) {
  final r = (ref == null || ref.isEmpty) ? null : ref;
  return switch (kind) {
    'booking' => BookingsScreen(state: state, highlight: r),
    'report' when r != null => ReportDetailScreen(state: state, ref: r),
    'puja' when r != null => PujaDetailScreen(state: state, ref: r),
    'palm' when r != null => PalmDetailScreen(state: state, ref: r),
    'order' when r != null => OrderDetailScreen(state: state, ref: r),
    _ => NotificationsScreen(state: state),
  };
}
