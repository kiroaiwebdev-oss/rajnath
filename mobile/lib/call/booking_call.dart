/// Call fields of a `GET /bookings` item and the decision of which call button to show.
/// See docs/api/app-v1-phase9.md, "In-app call (LiveKit)".
library;

class BookingCall {
  const BookingCall({required this.status, this.provider, this.callOpen = false, this.meetingLink});

  factory BookingCall.fromJson(Map b) {
    final p = b['meeting_provider'];
    final link = b['meeting_link'];
    return BookingCall(
      status: '${b['status'] ?? ''}',
      provider: p is String && p.isNotEmpty ? p : null,
      callOpen: b['call_open'] == true,
      meetingLink: link is String && _isHttps(link) ? link : null,
    );
  }

  final String status;

  /// `livekit` | `zoom` | `google_meet` | `manual` | null
  final String? provider;
  final bool callOpen;

  /// https link for zoom / google_meet / manual; always null for livekit.
  final String? meetingLink;

  static bool _isHttps(String s) {
    final u = Uri.tryParse(s.trim());
    return u != null && u.scheme == 'https' && u.host.isNotEmpty;
  }
}

enum CallButtonKind {
  /// No call UI (booking is not confirmed).
  hidden,

  /// In-app LiveKit call ("Join call").
  inApp,

  /// External https link (Zoom / Google Meet / manual).
  external,

  /// Confirmed, but no link yet.
  pending,
}

class CallButtonSpec {
  const CallButtonSpec(this.kind, {this.label, this.enabled = false, this.hint, this.link});
  final CallButtonKind kind;
  final String? label;
  final bool enabled;
  final String? hint;
  final String? link;
}

const callOpensHint = 'The Join call button becomes active 15 minutes before the start.';
const callLinkPendingHint = 'Your call link will appear here before the session.';

CallButtonSpec callButtonFor(BookingCall c) {
  if (c.status != 'confirmed') return const CallButtonSpec(CallButtonKind.hidden);
  if (c.provider == 'livekit') {
    return CallButtonSpec(CallButtonKind.inApp, label: 'Join call', enabled: c.callOpen, hint: c.callOpen ? null : callOpensHint);
  }
  final link = c.meetingLink;
  if (link != null) {
    final label = switch (c.provider) {
      'zoom' => 'Join on Zoom',
      'google_meet' => 'Join on Google Meet',
      _ => 'Open call link',
    };
    return CallButtonSpec(CallButtonKind.external, label: label, enabled: true, link: link);
  }
  return const CallButtonSpec(CallButtonKind.pending, hint: callLinkPendingHint);
}

/// Parsed `POST /bookings/{ref}/call-token` response.
class CallToken {
  const CallToken({required this.url, required this.token, required this.room, required this.audioOnly, required this.identity});

  factory CallToken.fromJson(Map<String, dynamic> r) {
    final d = r['data'] is Map ? (r['data'] as Map).cast<String, dynamic>() : r;
    final url = '${d['url'] ?? ''}', token = '${d['token'] ?? ''}';
    if (url.isEmpty || token.isEmpty) throw const FormatException('Missing call url or token');
    return CallToken(url: url, token: token, room: '${d['room'] ?? ''}', audioOnly: d['audio_only'] == true, identity: '${d['identity'] ?? ''}');
  }

  final String url;
  final String token;
  final String room;
  final bool audioOnly;
  final String identity;
}

/// Whether an error from enabling the mic/camera looks like a denied permission.
bool looksLikePermissionDenied(Object e) {
  final s = e.toString().toLowerCase();
  return s.contains('notallowed') || s.contains('permission') || s.contains('denied');
}
