import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Renders a calculator result's `display` block from POST /tools/{slug}:
/// `{sections: [{title?, highlight?, text?, table?: {head, rows}, rows?: [[label, value]], items?}], note?}`.
/// Every part is optional, so new tools show up without app changes.
class ToolDisplayView extends StatelessWidget {
  const ToolDisplayView(this.display, {super.key});
  final Map display;

  static String _s(Object? v) => v == null ? '' : '$v';

  @override
  Widget build(BuildContext context) {
    final sections = ((display['sections'] as List?) ?? const []).whereType<Map>().toList();
    final note = display['note'];
    final theme = Theme.of(context);
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      for (final s in sections)
        Card(
          margin: const EdgeInsets.only(top: 12),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (_s(s['title']).isNotEmpty)
                Padding(padding: const EdgeInsets.only(bottom: 6), child: Text(_s(s['title']), style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600))),
              if (_s(s['highlight']).isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: SelectableText(_s(s['highlight']), style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600, color: ArjColors.gold700)),
                ),
              if (_s(s['text']).isNotEmpty) Padding(padding: const EdgeInsets.only(bottom: 6), child: SelectableText(_s(s['text']), style: const TextStyle(height: 1.5))),
              if (s['table'] is Map) _table(s['table'] as Map),
              for (final r in ((s['rows'] as List?) ?? const []).whereType<List>())
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(flex: 2, child: Text(r.isNotEmpty ? _s(r[0]) : '', style: const TextStyle(color: ArjColors.ink600))),
                    const SizedBox(width: 12),
                    Expanded(flex: 3, child: Text(r.length > 1 ? _s(r[1]) : '', style: const TextStyle(fontWeight: FontWeight.w600))),
                  ]),
                ),
              for (final i in (s['items'] as List?) ?? const [])
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 3),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Text('•  ', style: TextStyle(color: ArjColors.gold700)),
                    Expanded(child: Text(_s(i), style: const TextStyle(height: 1.4))),
                  ]),
                ),
            ]),
          ),
        ),
      if (_s(note).isNotEmpty) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_s(note), style: const TextStyle(fontSize: 12, color: ArjColors.ink600))),
    ]);
  }

  Widget _table(Map t) {
    final head = ((t['head'] as List?) ?? const []).map(_s).toList();
    final rows = ((t['rows'] as List?) ?? const []).whereType<List>().map((r) => r.map(_s).toList()).toList();
    final cols = [head.length, for (final r in rows) r.length].fold<int>(0, (a, b) => a > b ? a : b);
    if (cols == 0) return const SizedBox.shrink();
    List<String> pad(List<String> r) => [...r, for (var i = r.length; i < cols; i++) ''];
    Widget cell(String v, {bool bold = false}) => Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          child: Text(v, style: TextStyle(fontWeight: bold ? FontWeight.w600 : FontWeight.normal)),
        );
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Table(
          defaultColumnWidth: const IntrinsicColumnWidth(),
          border: TableBorder.all(color: ArjColors.ink200, width: 0.5),
          children: [
            if (head.isNotEmpty) TableRow(decoration: const BoxDecoration(color: ArjColors.gold50), children: [for (final h in pad(head)) cell(h, bold: true)]),
            for (final r in rows) TableRow(children: [for (final v in pad(r)) cell(v)]),
          ],
        ),
      ),
    );
  }
}
