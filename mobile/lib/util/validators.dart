import 'package:intl/intl.dart';

/// Normalises an Indian mobile number to 10 digits (drops spaces, dashes, +91 / 91 / 0 prefixes).
String normalizeIndianMobile(String input) {
  var d = input.replaceAll(RegExp(r'[\s\-()]'), '');
  if (d.startsWith('+91')) {
    d = d.substring(3);
  } else if (d.length == 12 && d.startsWith('91')) {
    d = d.substring(2);
  } else if (d.length == 11 && d.startsWith('0')) {
    d = d.substring(1);
  }
  return d;
}

bool isValidIndianMobile(String input) => RegExp(r'^[6-9]\d{9}$').hasMatch(normalizeIndianMobile(input));

bool isValidPincode(String input) => RegExp(r'^[1-9]\d{5}$').hasMatch(input.trim());

String? mobileError(String v) => isValidIndianMobile(v) ? null : 'Enter a 10-digit Indian mobile number.';
String? pincodeError(String v) => isValidPincode(v) ? null : 'Enter a 6-digit pincode.';

/// Local, readable date-time for an ISO-8601 UTC string. Empty for null/invalid.
String formatWhen(Object? iso, {String pattern = 'd MMM yyyy, h:mm a'}) {
  final d = iso == null ? null : DateTime.tryParse('$iso');
  return d == null ? '' : DateFormat(pattern).format(d.toLocal());
}

/// "pending_payment" → "Pending payment".
String humanize(Object? code) {
  final s = '${code ?? ''}'.replaceAll('_', ' ').trim();
  return s.isEmpty ? '' : s[0].toUpperCase() + s.substring(1);
}

/// API objects are usually wrapped as `{ "data": {...} }`; accept both shapes.
Map<String, dynamic> unwrap(Map<String, dynamic> r) => r['data'] is Map ? (r['data'] as Map).cast<String, dynamic>() : r;
List<Map<String, dynamic>> unwrapList(Map<String, dynamic> r) => ((r['data'] as List?) ?? const []).whereType<Map>().map((m) => m.cast<String, dynamic>()).toList();
