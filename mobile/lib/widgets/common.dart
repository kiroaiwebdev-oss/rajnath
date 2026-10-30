import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../theme/app_theme.dart';

/// Loads data with loading / error (with retry) / empty states.
class AsyncView<T> extends StatefulWidget {
  const AsyncView({super.key, required this.load, required this.builder, this.isEmpty, this.emptyText = 'Nothing here yet.'});
  final Future<T> Function() load;
  final Widget Function(BuildContext, T, Future<void> Function() reload) builder;
  final bool Function(T)? isEmpty;
  final String emptyText;

  @override
  State<AsyncView<T>> createState() => _AsyncViewState<T>();
}

class _AsyncViewState<T> extends State<AsyncView<T>> {
  late Future<T> _f = widget.load();

  Future<void> _reload() async {
    final f = widget.load();
    setState(() => _f = f);
    await f.catchError((_) => null as T);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<T>(
      future: _f,
      builder: (context, s) {
        if (s.connectionState != ConnectionState.done) {
          return const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator(semanticsLabel: 'Loading')));
        }
        if (s.hasError) {
          final e = s.error;
          return ErrorPanel(message: e is ApiException ? e.message : 'Something went wrong.', onRetry: _reload, offline: e is ApiException && e.isOffline);
        }
        final data = s.data as T;
        if (widget.isEmpty?.call(data) ?? false) {
          return Center(child: Padding(padding: const EdgeInsets.all(32), child: Text(widget.emptyText, textAlign: TextAlign.center)));
        }
        return RefreshIndicator(onRefresh: _reload, child: widget.builder(context, data, _reload));
      },
    );
  }
}

class ErrorPanel extends StatelessWidget {
  const ErrorPanel({super.key, required this.message, required this.onRetry, this.offline = false});
  final String message;
  final VoidCallback onRetry;
  final bool offline;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(offline ? Icons.wifi_off : Icons.error_outline, size: 40, color: ArjColors.danger700),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton(onPressed: onRetry, child: const Text('Try again')),
          ]),
        ),
      );
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 20, 4, 8),
        child: Text(text, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
      );
}

String formatPaise(int? minor) {
  if (minor == null) return 'Price on request';
  final rupees = minor ~/ 100;
  final s = rupees.toString();
  String grouped;
  if (s.length <= 3) {
    grouped = s;
  } else {
    final last3 = s.substring(s.length - 3);
    var rest = s.substring(0, s.length - 3);
    final parts = <String>[];
    while (rest.length > 2) {
      parts.insert(0, rest.substring(rest.length - 2));
      rest = rest.substring(0, rest.length - 2);
    }
    if (rest.isNotEmpty) parts.insert(0, rest);
    grouped = '${parts.join(',')},$last3';
  }
  final paise = minor % 100;
  return '₹$grouped${paise != 0 ? '.${paise.toString().padLeft(2, '0')}' : ''}';
}

void showSnack(BuildContext context, String text) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

/// Small coloured pill for an API status code.
class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});
  final Object? status;

  static Color colorFor(String s) => switch (s) {
        'paid' || 'completed' || 'confirmed' || 'performed' || 'scheduled' || 'accepted' => ArjColors.success700,
        'cancelled' || 'failed' || 'needs_new_photos' || 'no_show' || 'rejected' => ArjColors.danger700,
        'pending_payment' || 'awaiting_details' || 'awaiting_photos' || 'submitted' => ArjColors.gold700,
        _ => ArjColors.ink600,
      };

  @override
  Widget build(BuildContext context) {
    final s = '${status ?? ''}';
    final label = s.replaceAll('_', ' ');
    final c = colorFor(s);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: c.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(99), border: Border.all(color: c.withValues(alpha: 0.4))),
      child: Text(label.isEmpty ? '' : label[0].toUpperCase() + label.substring(1), style: TextStyle(color: c, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

/// Small coloured pill with a fixed label (e.g. "Favourable", "Good").
class ToneChip extends StatelessWidget {
  const ToneChip(this.text, this.color, {super.key});
  final String text;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(99), border: Border.all(color: color.withValues(alpha: 0.4))),
        child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
      );
}

/// Previous / label / next row for stepping through years or months.
class PeriodStepper extends StatelessWidget {
  const PeriodStepper({super.key, required this.label, required this.onPrevious, required this.onNext, this.unit = 'year'});
  final String label;
  final VoidCallback? onPrevious, onNext;
  final String unit;
  @override
  Widget build(BuildContext context) => Row(children: [
        IconButton(tooltip: 'Previous $unit', onPressed: onPrevious, icon: const Icon(Icons.chevron_left)),
        Expanded(child: Text(label, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600))),
        IconButton(tooltip: 'Next $unit', onPressed: onNext, icon: const Icon(Icons.chevron_right)),
      ]);
}

/// Titled body sections (reports, palmistry readings).
class SectionsView extends StatelessWidget {
  const SectionsView(this.sections, {super.key});
  final List sections;
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        for (final s in sections.whereType<Map>()) ...[
          SectionTitle('${s['title'] ?? ''}'),
          SelectableText('${s['body'] ?? ''}', style: const TextStyle(height: 1.5)),
        ],
      ]);
}

/// Network image with a quiet placeholder while loading and when it fails (or [fallback] instead,
/// e.g. a bundled asset). Failed loads always go through errorBuilder, so they never throw.
class NetImage extends StatelessWidget {
  const NetImage(this.url, {super.key, this.fit = BoxFit.cover, this.fallback, this.semanticLabel, this.dark = false});
  final String? url;
  final BoxFit fit;
  final Widget? fallback;
  final String? semanticLabel;

  /// Black placeholder for the full-screen photo viewer.
  final bool dark;

  Widget _placeholder(IconData icon) =>
      fallback ??
      Semantics(
        label: semanticLabel,
        image: semanticLabel != null,
        child: ColoredBox(color: dark ? Colors.black : ArjColors.ivory200, child: Center(child: Icon(icon, color: dark ? Colors.white54 : ArjColors.ink600))),
      );

  @override
  Widget build(BuildContext context) {
    final u = url?.trim() ?? '';
    if (u.isEmpty) return _placeholder(Icons.image_outlined);
    return Image.network(
      u,
      fit: fit,
      semanticLabel: semanticLabel,
      frameBuilder: (_, child, frame, sync) => sync || frame != null ? child : _placeholder(Icons.image_outlined),
      errorBuilder: (_, _, _) => _placeholder(Icons.broken_image_outlined),
    );
  }
}

/// Error line under a form.
class FormError extends StatelessWidget {
  const FormError(this.text, {super.key});
  final String? text;
  @override
  Widget build(BuildContext context) => text == null
      ? const SizedBox.shrink()
      : Padding(padding: const EdgeInsets.only(top: 8), child: Text(text!, style: TextStyle(color: Theme.of(context).colorScheme.error), semanticsLabel: 'Error: $text'));
}
