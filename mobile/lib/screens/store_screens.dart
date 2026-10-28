import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api/api_client.dart';
import '../payments/checkout.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';
import 'purchase_screens.dart';
import 'routing.dart';

class StoreScreen extends StatelessWidget {
  const StoreScreen({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Gemstone store')),
        body: SafeArea(
          child: AsyncView<List<Map<String, dynamic>>>(
            load: () async => unwrapList(await state.api.get('/store/products', query: {'category': 'gemstone'})),
            isEmpty: (l) => l.isEmpty,
            emptyText: 'No gemstones are listed right now. Please check back soon.',
            builder: (context, list, _) => ListView(padding: const EdgeInsets.all(16), children: [
              const Text('Certified natural gemstones, selected and energised under Acharya ji\'s guidance. Wear a gemstone only after a consultation.',
                  style: TextStyle(color: ArjColors.ink600)),
              const SizedBox(height: 12),
              for (final p in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductDetailScreen(state: state, slug: '${p['slug']}'))),
                    child: Row(children: [
                      SizedBox(width: 104, height: 104, child: _ProductImage(url: p['image_url'] as String?)),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('${p['name']}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                            if (p['weight'] != null) Text('${p['weight']}', style: const TextStyle(color: ArjColors.ink600)),
                            const SizedBox(height: 4),
                            Text(formatPaise((p['price_minor'] as num?)?.toInt()), style: const TextStyle(fontWeight: FontWeight.w600)),
                            if (p['in_stock'] == false) const Text('Out of stock', style: TextStyle(color: ArjColors.danger700)),
                          ]),
                        ),
                      ),
                      const Padding(padding: EdgeInsets.only(right: 8), child: Icon(Icons.chevron_right, semanticLabel: 'Open')),
                    ]),
                  ),
                ),
            ]),
          ),
        ),
      );
}

class _ProductImage extends StatelessWidget {
  const _ProductImage({required this.url});
  final String? url;
  @override
  Widget build(BuildContext context) => url == null
      ? Container(color: ArjColors.ivory100, child: const Icon(Icons.diamond_outlined, color: ArjColors.gold700, size: 36))
      : Image.network(url!, fit: BoxFit.cover, semanticLabel: '', errorBuilder: (_, _, _) => Container(color: ArjColors.ivory100));
}

class ProductDetailScreen extends StatelessWidget {
  const ProductDetailScreen({super.key, required this.state, required this.slug});
  final AppState state;
  final String slug;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Gemstone')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            load: () async => unwrap(await state.api.get('/store/products/$slug')),
            builder: (context, p, _) => ListView(padding: const EdgeInsets.all(16), children: [
              ClipRRect(borderRadius: BorderRadius.circular(20), child: AspectRatio(aspectRatio: 4 / 3, child: _ProductImage(url: p['image_url'] as String?))),
              const SizedBox(height: 16),
              Text('${p['name']}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
              if (p['summary'] != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text('${p['summary']}', style: const TextStyle(color: ArjColors.ink600))),
              const SizedBox(height: 12),
              Text(formatPaise((p['price_minor'] as num?)?.toInt()), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Column(children: [
                    if (p['weight'] != null) _spec(Icons.scale_outlined, 'Weight', '${p['weight']}'),
                    if (p['origin'] != null) _spec(Icons.public, 'Origin', '${p['origin']}'),
                    if (p['certification'] != null) _spec(Icons.verified_outlined, 'Certification', '${p['certification']}'),
                  ]),
                ),
              ),
              if (p['description'] != null) ...[const SizedBox(height: 16), Text('${p['description']}', style: const TextStyle(height: 1.5))],
              const SizedBox(height: 20),
              FilledButton(
                onPressed: p['in_stock'] == true && p['price_minor'] != null
                    ? () async {
                        if (!await ensureSignedIn(context, state, reason: 'Sign in to buy and track your order.') || !context.mounted) return;
                        Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductBuyScreen(state: state, product: p)));
                      }
                    : null,
                child: Text(p['in_stock'] == true ? 'Buy now' : 'Out of stock'),
              ),
              const SizedBox(height: 8),
              const Text('Gemstones are natural; colour and inclusions vary slightly. Shipped insured within India.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: ArjColors.ink600)),
            ]),
          ),
        ),
      );

  Widget _spec(IconData icon, String k, String v) => ListTile(
        contentPadding: EdgeInsets.zero,
        dense: true,
        leading: Icon(icon, color: ArjColors.gold700),
        title: Text(k, style: const TextStyle(color: ArjColors.ink600)),
        subtitle: Text(v, style: const TextStyle(color: ArjColors.ink900, fontSize: 15)),
      );
}

class ProductBuyScreen extends StatefulWidget {
  const ProductBuyScreen({super.key, required this.state, required this.product});
  final AppState state;
  final Map product;
  @override
  State<ProductBuyScreen> createState() => _ProductBuyScreenState();
}

class _ProductBuyScreenState extends State<ProductBuyScreen> {
  final _attempt = PurchaseAttempt();
  late final _name = TextEditingController(text: '${widget.state.user?['name'] ?? ''}');
  late final _phone = TextEditingController(text: '${widget.state.user?['phone'] ?? ''}');
  final _line1 = TextEditingController(), _line2 = TextEditingController(), _city = TextEditingController(), _stateName = TextEditingController(), _pincode = TextEditingController();
  int _qty = 1;
  bool _busy = false;
  Map<String, String> _errors = {};

  int get _max => ((widget.product['max_per_order'] as num?)?.toInt() ?? 1).clamp(1, 99);
  int get _price => (widget.product['price_minor'] as num?)?.toInt() ?? 0;

  @override
  void dispose() {
    for (final c in [_name, _phone, _line1, _line2, _city, _stateName, _pincode]) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, String> _validate() {
    final e = <String, String>{};
    if (_name.text.trim().isEmpty) e['address.name'] = 'Enter the recipient\'s name.';
    final pe = mobileError(_phone.text);
    if (pe != null) e['address.phone'] = pe;
    if (_line1.text.trim().isEmpty) e['address.line1'] = 'Enter the address.';
    if (_city.text.trim().isEmpty) e['address.city'] = 'Enter the city.';
    if (_stateName.text.trim().isEmpty) e['address.state'] = 'Enter the state.';
    final pin = pincodeError(_pincode.text);
    if (pin != null) e['address.pincode'] = pin;
    return e;
  }

  Future<void> _buy() async {
    final errs = _validate();
    if (errs.isNotEmpty) return setState(() => _errors = errs);
    setState(() { _busy = true; _errors = {}; });
    final address = {
      'name': _name.text.trim(),
      'phone': normalizeIndianMobile(_phone.text),
      'line1': _line1.text.trim(),
      if (_line2.text.trim().isNotEmpty) 'line2': _line2.text.trim(),
      'city': _city.text.trim(),
      'state': _stateName.text.trim(),
      'pincode': _pincode.text.trim(),
    };
    try {
      final ref = '${widget.product['ref']}';
      final r = await widget.state.api.post('/checkout/product', {'product': ref, 'quantity': _qty, 'address': address, 'key': _attempt.keyFor('$ref|$_qty|${address.values.join('|')}')});
      if (!mounted) return;
      final order = await runCheckout(context, widget.state.api, r);
      if (!mounted) return;
      if (!isPendingPayment(order)) _attempt.reset();
      if (isPaidLike(order)) await afterPurchase(context, widget.state, order!, replace: true);
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors.isEmpty ? {'_': e.message} : e.fieldErrors);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _field(TextEditingController c, String label, String key, {TextInputType? type, List<TextInputFormatter>? fmt, int? maxLength, TextCapitalization cap = TextCapitalization.words}) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextField(
          controller: c,
          keyboardType: type,
          inputFormatters: fmt,
          maxLength: maxLength,
          textCapitalization: cap,
          decoration: InputDecoration(labelText: label, errorText: _errors[key], counterText: ''),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final p = widget.product;
    return Scaffold(
      appBar: AppBar(title: const Text('Delivery details')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Text('${p['name']}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Row(children: [
            const Expanded(child: Text('Quantity')),
            IconButton(tooltip: 'Decrease quantity', onPressed: _qty > 1 ? () => setState(() => _qty--) : null, icon: const Icon(Icons.remove_circle_outline)),
            Semantics(label: 'Quantity $_qty', child: ExcludeSemantics(child: Text('$_qty', style: Theme.of(context).textTheme.titleMedium))),
            IconButton(tooltip: 'Increase quantity', onPressed: _qty < _max ? () => setState(() => _qty++) : null, icon: const Icon(Icons.add_circle_outline)),
          ]),
          if (_max > 1) Text('Up to $_max per order.', style: const TextStyle(fontSize: 12, color: ArjColors.ink600)),
          FormError(_errors['quantity'] ?? _errors['product']),
          const Divider(height: 32),
          _field(_name, 'Full name', 'address.name'),
          _field(_phone, 'Mobile number', 'address.phone', type: TextInputType.phone, maxLength: 14, fmt: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]'))]),
          _field(_line1, 'House / flat, street', 'address.line1', cap: TextCapitalization.sentences),
          _field(_line2, 'Area, landmark (optional)', 'address.line2', cap: TextCapitalization.sentences),
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: _field(_city, 'City', 'address.city')),
            const SizedBox(width: 12),
            Expanded(child: _field(_stateName, 'State', 'address.state')),
          ]),
          _field(_pincode, 'Pincode', 'address.pincode', type: TextInputType.number, maxLength: 6, fmt: [FilteringTextInputFormatter.digitsOnly]),
          const Divider(height: 24),
          Row(children: [const Expanded(child: Text('Total')), Text(formatPaise(_price * _qty), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600))]),
          FormError(_errors['_']),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _buy, child: Text(_busy ? 'Please wait…' : 'Pay securely')),
        ]),
      ),
    );
  }
}
