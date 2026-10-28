import 'package:intl/intl.dart';

/// One bookable start time from GET /slots. [start] is sent back to the API unchanged.
class Slot {
  const Slot({required this.start, required this.label});
  final String start;
  final String label;

  DateTime? get startUtc => DateTime.tryParse(start)?.toUtc();

  factory Slot.fromJson(Map json) => Slot(start: '${json['start']}', label: '${json['label'] ?? json['start']}');
}

/// Slots for one IST calendar date (`yyyy-MM-dd`).
class SlotDay {
  const SlotDay({required this.date, required this.slots});
  final String date;
  final List<Slot> slots;

  /// e.g. "Tue, 3 Nov". Falls back to the raw key if it is not a date.
  String get title {
    final d = DateTime.tryParse(date);
    return d == null ? date : DateFormat('EEE, d MMM').format(d);
  }
}

/// Parses `{ "data": { "2026-11-03": [ {start, label} ] } }` (or just the inner map) into days sorted by date.
/// Days without slots are dropped. Tolerates an empty list (`[]`) for "no slots".
List<SlotDay> parseSlots(Object? json) {
  Object? data = json;
  if (data is Map && data.containsKey('data')) data = data['data'];
  if (data is! Map) return const [];
  final days = <SlotDay>[];
  for (final e in data.entries) {
    final raw = e.value;
    if (raw is! List) continue;
    final slots = raw.whereType<Map>().where((m) => m['start'] != null).map(Slot.fromJson).toList();
    if (slots.isNotEmpty) days.add(SlotDay(date: '${e.key}', slots: slots));
  }
  days.sort((a, b) => a.date.compareTo(b.date));
  return days;
}

/// Modes that are booked against a time slot (when the variant also has a duration).
const slotModes = {'audio', 'video', 'in_person', 'chat'};

bool isSlotVariant(Map v) => slotModes.contains(v['mode']) && v['duration_min'] != null;

/// Team-consultation offerings need an astrologer chosen before the slot.
const teamOfferingSlugs = {'elite-astrologer-consultation', 'premium-astrologer-consultation', 'team-astrologer-consultation'};
