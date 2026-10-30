import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'activity_screens.dart';
import 'purchase_screens.dart';

const reviewConsentText =
    'I agree that Astro Rajnath Jha may publish this review with the name and city above on its website and app. I can delete it at any time.';
const reviewThanks = 'Thank you! Your review will appear once our team has checked it.';

/// Shown name suggestion, as on the server: "Asha D." from "Asha Devi".
String suggestedReviewName(Object? fullName) {
  final parts = '${fullName ?? ''}'.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
  if (parts.isEmpty) return '';
  return parts.length > 1 ? '${parts.first} ${parts.last[0].toUpperCase()}.' : parts.first;
}

/// "March 2025" from a `Y-m-d` date; empty when missing.
String _monthYear(Object? ymd) {
  final d = ymd == null ? null : DateTime.tryParse('$ymd');
  return d == null ? '' : DateFormat('MMMM yyyy').format(d);
}

String _n(num v) => v == v.roundToDouble() ? '${v.toInt()}' : v.toStringAsFixed(1);

String? _text(Object? v) {
  final s = v == null ? '' : '$v'.trim();
  return s.isEmpty ? null : s;
}

/// Five read-only stars (half stars for averages), read out as "Rated 4.5 out of 5".
class StarRow extends StatelessWidget {
  const StarRow(this.rating, {super.key, this.size = 18});
  final num rating;
  final double size;
  @override
  Widget build(BuildContext context) => Semantics(
        container: true,
        label: 'Rated ${_n(rating)} out of 5',
        excludeSemantics: true,
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          for (var i = 1; i <= 5; i++)
            Icon(rating >= i ? Icons.star : (rating >= i - 0.5 ? Icons.star_half : Icons.star_border), size: size, color: ArjColors.gold500),
        ]),
      );
}

/// "Reply from our team" box.
Widget _replyBox(Object? reply) => Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: ArjColors.gold50, borderRadius: BorderRadius.circular(12), border: Border.all(color: ArjColors.gold200)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Reply from our team', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
        const SizedBox(height: 4),
        Text('$reply', style: const TextStyle(height: 1.4)),
      ]),
    );

/// One published review (GET /reviews item). [compact] shortens the body and hides the reply (home tab).
class ReviewCard extends StatelessWidget {
  const ReviewCard(this.review, {super.key, this.compact = false});
  final Map review;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final r = review;
    final title = _text(r['title']), reply = _text(r['reply']);
    final who = [_text(r['name']), _text(r['city'])].whereType<String>().join(', ');
    final about = [_text(r['subject']), _text(_monthYear(r['date']))].whereType<String>().join(' · ');
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Wrap(spacing: 8, runSpacing: 4, crossAxisAlignment: WrapCrossAlignment.center, children: [
            StarRow((r['rating'] as num?) ?? 0),
            if (r['verified'] == true) const ToneChip('Verified purchase', ArjColors.success700),
          ]),
          if (title != null) ...[const SizedBox(height: 8), Text(title, style: const TextStyle(fontWeight: FontWeight.w600))],
          const SizedBox(height: 4),
          Text('${r['body'] ?? ''}', maxLines: compact ? 4 : null, overflow: compact ? TextOverflow.ellipsis : null, style: const TextStyle(height: 1.5)),
          if (reply != null && !compact) _replyBox(reply),
          if (who.isNotEmpty) ...[const SizedBox(height: 8), Text(who, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))],
          if (about.isNotEmpty) Text(about, style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
        ]),
      ),
    );
  }
}

// ───────────────────────────── Public reviews (GET /reviews) ─────────────────────────────

class ReviewsScreen extends StatefulWidget {
  const ReviewsScreen({super.key, required this.state, this.service});
  final AppState state;

  /// Offering slug to show only that service's reviews.
  final String? service;
  @override
  State<ReviewsScreen> createState() => _ReviewsScreenState();
}

class _ReviewsScreenState extends State<ReviewsScreen> {
  Map? _summary;
  List<Map<String, dynamic>>? _items;
  int? _rating;
  int _page = 1, _lastPage = 1, _seq = 0;
  bool _loadingMore = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool more = false}) async {
    final seq = ++_seq;
    final page = more ? _page + 1 : 1;
    setState(() {
      _error = null;
      _loadingMore = more;
    });
    try {
      final r = await widget.state.api.get('/reviews', query: {'service': ?widget.service, 'rating': ?_rating, 'page': page});
      if (!mounted || seq != _seq) return;
      final meta = (r['meta'] as Map?) ?? const {};
      setState(() {
        _summary = meta['summary'] as Map?;
        _page = (meta['page'] as num?)?.toInt() ?? page;
        _lastPage = (meta['last_page'] as num?)?.toInt() ?? _page;
        _items = [if (more) ...?_items, ...unwrapList(r)];
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted || seq != _seq) return;
      setState(() => _loadingMore = false);
      if (more || _items != null) {
        showSnack(context, e is ApiException ? e.message : 'Something went wrong.');
      } else {
        setState(() => _error = e);
      }
    }
  }

  void _filter(int? stars) {
    setState(() {
      _rating = _rating == stars ? null : stars;
      _items = null;
    });
    _load();
  }

  Future<void> _write() async {
    if (!await ensureSignedIn(context, widget.state, reason: 'Sign in to review a consultation or product you have bought.') || !mounted) return;
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => MyReviewsScreen(state: widget.state)));
  }

  Widget _summaryCard(Map s) {
    final count = (s['count'] as num?)?.toInt() ?? 0;
    final avg = s['average'] as num?;
    final stars = (s['stars'] as Map?) ?? const {};
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
          Column(children: [
            Text(avg == null ? '–' : avg.toStringAsFixed(1), style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w600)),
            StarRow(avg ?? 0),
            const SizedBox(height: 4),
            Text('$count review${count == 1 ? '' : 's'}', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
          ]),
          const SizedBox(width: 16),
          Expanded(
            child: Column(children: [
              for (var i = 5; i >= 1; i--) _bar(i, (stars['$i'] as num?)?.toInt() ?? 0, count),
            ]),
          ),
        ]),
      ),
    );
  }

  Widget _bar(int stars, int n, int count) {
    final selected = _rating == stars;
    return Semantics(
      button: true,
      selected: selected,
      label: '$stars star${stars == 1 ? '' : 's'}, $n review${n == 1 ? '' : 's'}${selected ? ', showing only these. Tap again to show all' : ''}',
      onTap: () => _filter(stars),
      excludeSemantics: true,
      child: InkWell(
        key: ValueKey('rating-bar-$stars'),
        borderRadius: BorderRadius.circular(8),
        onTap: () => _filter(stars),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 5),
          decoration: BoxDecoration(color: selected ? ArjColors.gold50 : null, borderRadius: BorderRadius.circular(8), border: selected ? Border.all(color: ArjColors.gold200) : null),
          child: Row(children: [
            SizedBox(width: 28, child: Text('$stars ★', style: TextStyle(fontWeight: selected ? FontWeight.w600 : FontWeight.normal))),
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(99),
                child: LinearProgressIndicator(value: count == 0 ? 0 : n / count, minHeight: 8, backgroundColor: ArjColors.ivory200, color: ArjColors.gold500),
              ),
            ),
            SizedBox(width: 36, child: Text('$n', textAlign: TextAlign.end)),
          ]),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final items = _items, s = _summary;
    final Widget body;
    if (items == null && s == null) {
      body = _error != null
          ? ErrorPanel(message: _error is ApiException ? '$_error' : 'Something went wrong.', offline: _error is ApiException && (_error as ApiException).isOffline, onRetry: _load)
          : const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator(semanticsLabel: 'Loading')));
    } else {
      body = RefreshIndicator(
        onRefresh: _load,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          if (s != null && ((s['count'] as num?) ?? 0) > 0) _summaryCard(s),
          const SizedBox(height: 12),
          FilledButton.icon(onPressed: _write, icon: const Icon(Icons.rate_review_outlined), label: const Text('Write a review')),
          if (_rating != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Row(children: [
                Expanded(child: Text('Showing $_rating-star reviews', style: const TextStyle(fontWeight: FontWeight.w600))),
                TextButton(onPressed: () => _filter(null), child: const Text('Show all')),
              ]),
            ),
          const SizedBox(height: 12),
          if (items == null)
            _error != null
                ? ErrorPanel(message: _error is ApiException ? '$_error' : 'Something went wrong.', offline: _error is ApiException && (_error as ApiException).isOffline, onRetry: _load)
                : const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator(semanticsLabel: 'Loading')))
          else if (items.isEmpty)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Text(_rating == null ? 'No reviews yet. Be the first to share your experience.' : 'No $_rating-star reviews yet.', textAlign: TextAlign.center),
            )
          else ...[
            for (final r in items) ReviewCard(r),
            if (_page < _lastPage)
              OutlinedButton(onPressed: _loadingMore ? null : () => _load(more: true), child: Text(_loadingMore ? 'Loading…' : 'Load more')),
          ],
        ]),
      );
    }
    return Scaffold(appBar: AppBar(title: const Text('Client reviews')), body: SafeArea(child: body));
  }
}

// ───────────────────────────── My reviews (GET /me/reviews) ─────────────────────────────

Color _reviewStatusColor(Object? s) => switch (s) {
      'approved' => ArjColors.success700,
      'pending' => ArjColors.gold700,
      'rejected' => ArjColors.danger700,
      _ => ArjColors.ink600,
    };

class MyReviewsScreen extends StatefulWidget {
  const MyReviewsScreen({super.key, required this.state});
  final AppState state;
  @override
  State<MyReviewsScreen> createState() => _MyReviewsScreenState();
}

class _MyReviewsScreenState extends State<MyReviewsScreen> {
  int _v = 0;

  Future<void> _open(Widget page) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
    if (mounted) setState(() => _v++);
  }

  Future<void> _delete(Map r) async {
    if (!await confirm(context, 'Delete this review?', 'It will be removed from the website and the app. This cannot be undone.', 'Delete review') || !mounted) return;
    try {
      await widget.state.api.delete('/me/reviews/${r['ref']}');
      if (!mounted) return;
      showSnack(context, 'Review deleted.');
      setState(() => _v++);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    }
  }

  Widget _own(Map r) {
    final title = _text(r['title']), reply = _text(r['reply']), reason = _text(r['reject_reason']), subject = _text(r['subject']);
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Wrap(spacing: 8, runSpacing: 4, crossAxisAlignment: WrapCrossAlignment.center, children: [
            StarRow((r['rating'] as num?) ?? 0),
            ToneChip('${r['status_label'] ?? humanize(r['status'])}', _reviewStatusColor(r['status'])),
          ]),
          if (subject != null) ...[const SizedBox(height: 6), Text(subject, style: const TextStyle(fontSize: 12, color: ArjColors.ink600))],
          if (title != null) ...[const SizedBox(height: 8), Text(title, style: const TextStyle(fontWeight: FontWeight.w600))],
          const SizedBox(height: 4),
          Text('${r['body'] ?? ''}', style: const TextStyle(height: 1.5)),
          if (r['status'] == 'rejected' && reason != null)
            Container(
              margin: const EdgeInsets.only(top: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: ArjColors.danger700.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: ArjColors.danger700.withValues(alpha: 0.3)),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Why it was not published', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
                const SizedBox(height: 4),
                Text(reason),
                const SizedBox(height: 4),
                const Text('You can edit it and send it again.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
              ]),
            ),
          if (reply != null) _replyBox(reply),
          Row(mainAxisAlignment: MainAxisAlignment.end, children: [
            if (r['editable'] == true)
              TextButton.icon(onPressed: () => _open(WriteReviewScreen(state: widget.state, review: r)), icon: const Icon(Icons.edit_outlined), label: const Text('Edit')),
            TextButton.icon(
              onPressed: () => _delete(r),
              icon: const Icon(Icons.delete_outline),
              label: const Text('Delete'),
              style: TextButton.styleFrom(foregroundColor: ArjColors.danger700),
            ),
          ]),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('My reviews')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            key: ValueKey(_v),
            load: () => widget.state.api.get('/me/reviews'),
            builder: (context, r, _) {
              final meta = (r['meta'] as Map?) ?? const {};
              final orders = ((meta['reviewable_orders'] as List?) ?? const []).whereType<Map>().toList();
              final suggested = _text(meta['suggested_name']);
              final reviews = unwrapList(r);
              return ListView(padding: const EdgeInsets.all(16), children: [
                if (orders.isNotEmpty) ...[
                  const SectionTitle('Waiting for your review'),
                  const Text('Tell others how it went. Reviews appear after our team has checked them.', style: TextStyle(color: ArjColors.ink600)),
                  const SizedBox(height: 8),
                  for (final o in orders)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: const Icon(Icons.rate_review_outlined),
                        title: Text('${o['label'] ?? 'Your order'}'),
                        subtitle: o['paid_at'] == null ? null : Text('Paid ${formatAtOffset(o['paid_at'], pattern: 'd MMM yyyy')}'),
                        trailing: const Icon(Icons.chevron_right, semanticLabel: 'Write a review'),
                        onTap: () => _open(WriteReviewScreen(state: widget.state, orderRef: '${o['ref']}', orderLabel: _text(o['label']), suggestedName: suggested)),
                      ),
                    ),
                ],
                const SectionTitle('Your reviews'),
                if (reviews.isEmpty)
                  Text(
                    orders.isEmpty ? 'You have not written any reviews yet. After you pay for a consultation or a product, you can review it here.' : 'You have not written any reviews yet.',
                    style: const TextStyle(color: ArjColors.ink600),
                  ),
                for (final rv in reviews) _own(rv),
              ]);
            },
          ),
        ),
      );
}

// ───────────────────────────── Write / edit (POST /reviews, PUT /me/reviews/{ref}) ─────────────────────────────

class WriteReviewScreen extends StatefulWidget {
  const WriteReviewScreen({super.key, required this.state, this.orderRef, this.orderLabel, this.suggestedName, this.review})
      : assert(orderRef != null || review != null, 'A new review needs the order ref');
  final AppState state;

  /// New review for this paid order.
  final String? orderRef;
  final String? orderLabel;

  /// Prefill for the shown name (GET /me/reviews meta.suggested_name); falls back to the account name.
  final String? suggestedName;

  /// Own review to edit (GET /me/reviews item).
  final Map? review;

  @override
  State<WriteReviewScreen> createState() => _WriteReviewScreenState();
}

class _WriteReviewScreenState extends State<WriteReviewScreen> {
  static const _fields = {'rating', 'title', 'body', 'display_name', 'city', 'consent'};
  late final Map? _r = widget.review;
  late int _rating = (_r?['rating'] as num?)?.toInt() ?? 0;
  late final _title = TextEditingController(text: _text(_r?['title']) ?? '');
  late final _body = TextEditingController(text: _text(_r?['body']) ?? '');
  late final _name = TextEditingController(text: _text(_r?['name']) ?? widget.suggestedName ?? suggestedReviewName(widget.state.user?['name']));
  late final _city = TextEditingController(text: _text(_r?['city']) ?? '');
  bool _consent = false, _busy = false;
  Map<String, String> _errors = {};

  bool get _editing => _r != null;

  @override
  void dispose() {
    for (final c in [_title, _body, _name, _city]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    final body = _body.text.trim(), name = _name.text.trim();
    final e = <String, String>{
      if (_rating < 1) 'rating': 'Choose a star rating.',
      if (body.length < 20) 'body': 'Please write at least 20 characters.',
      if (name.isEmpty) 'display_name': 'Add the name to show with your review.',
      if (!_editing && !_consent) 'consent': 'Please agree that we may publish your review.',
    };
    if (e.isNotEmpty) return setState(() => _errors = e);
    setState(() {
      _busy = true;
      _errors = {};
    });
    final data = <String, dynamic>{'rating': _rating, 'title': ?_text(_title.text), 'body': body, 'display_name': name, 'city': ?_text(_city.text)};
    try {
      if (_editing) {
        await widget.state.api.put('/me/reviews/${_r!['ref']}', data);
      } else {
        await widget.state.api.post('/reviews', {'order_ref': widget.orderRef, ...data, 'consent': true});
      }
      if (!mounted) return;
      showSnack(context, reviewThanks);
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : Map.of(e.fieldErrors));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _star(int i) {
    final label = '$i star${i == 1 ? '' : 's'}';
    void pick() => setState(() {
          _rating = i;
          _errors.remove('rating');
        });
    return Semantics(
      button: true,
      selected: _rating == i,
      label: label,
      onTap: pick,
      excludeSemantics: true,
      child: IconButton(
        key: ValueKey('star-$i'),
        onPressed: pick,
        iconSize: 36,
        icon: Icon(i <= _rating ? Icons.star : Icons.star_border, color: ArjColors.gold500),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final subject = widget.orderLabel ?? _text(_r?['subject']);
    final general = _errors.entries.where((e) => !_fields.contains(e.key)).map((e) => e.value).join('\n');
    final err = Theme.of(context).colorScheme.error;
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'Edit your review' : 'Write a review')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(16), children: [
          if (subject != null) ...[Text(subject, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)), const SizedBox(height: 12)],
          const Text('Your rating', style: TextStyle(fontWeight: FontWeight.w600)),
          Row(children: [for (var i = 1; i <= 5; i++) _star(i)]),
          if (_errors['rating'] != null) Text(_errors['rating']!, style: TextStyle(color: err, fontSize: 12)),
          const SizedBox(height: 12),
          TextField(controller: _title, maxLength: 120, decoration: InputDecoration(labelText: 'Title (optional)', errorText: _errors['title'])),
          TextField(
            controller: _body,
            minLines: 4,
            maxLines: 8,
            maxLength: 2000,
            decoration: InputDecoration(labelText: 'Your review', helperText: 'At least 20 characters.', errorText: _errors['body'], alignLabelWithHint: true),
          ),
          TextField(controller: _name, maxLength: 80, decoration: InputDecoration(labelText: 'Name to show', errorText: _errors['display_name'])),
          TextField(controller: _city, maxLength: 80, decoration: InputDecoration(labelText: 'City (optional)', errorText: _errors['city'])),
          if (!_editing)
            CheckboxListTile(
              value: _consent,
              onChanged: (v) => setState(() {
                _consent = v ?? false;
                _errors.remove('consent');
              }),
              controlAffinity: ListTileControlAffinity.leading,
              contentPadding: EdgeInsets.zero,
              title: const Text(reviewConsentText, style: TextStyle(fontSize: 14)),
              subtitle: _errors['consent'] == null ? null : Text(_errors['consent']!, style: TextStyle(color: err)),
            )
          else
            const Text('After you save, our team checks the review again before it appears.', style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
          FormError(general.isEmpty ? null : general),
          const SizedBox(height: 12),
          FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Sending…' : (_editing ? 'Save changes' : 'Send review'))),
        ]),
      ),
    );
  }
}

/// Home tab: up to three published reviews. Hidden while loading, on error and when there are none.
class HomeReviews extends StatefulWidget {
  const HomeReviews({super.key, required this.state});
  final AppState state;
  @override
  State<HomeReviews> createState() => _HomeReviewsState();
}

class _HomeReviewsState extends State<HomeReviews> {
  List<Map<String, dynamic>> _items = const [];
  num? _average;
  int _count = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final r = await widget.state.api.get('/reviews');
      final s = ((r['meta'] as Map?)?['summary'] as Map?) ?? const {};
      if (mounted) {
        setState(() {
          _items = unwrapList(r).take(3).toList();
          _average = s['average'] as num?;
          _count = (s['count'] as num?)?.toInt() ?? 0;
        });
      }
    } catch (_) {/* the section stays hidden */}
  }

  @override
  Widget build(BuildContext context) {
    if (_items.isEmpty) return const SizedBox.shrink();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const SectionTitle('What clients say'),
      if (_average != null && _count > 0)
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Row(children: [
            StarRow(_average!),
            const SizedBox(width: 8),
            Text('${_average!.toStringAsFixed(1)} from $_count review${_count == 1 ? '' : 's'}', style: const TextStyle(color: ArjColors.ink600)),
          ]),
        ),
      for (final r in _items) ReviewCard(r, compact: true),
      Align(
        alignment: Alignment.centerRight,
        child: TextButton(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ReviewsScreen(state: widget.state))),
          child: const Text('All reviews'),
        ),
      ),
    ]);
  }
}
