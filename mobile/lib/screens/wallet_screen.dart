import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../payments/checkout.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../util/validators.dart';
import '../widgets/common.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key, required this.state});
  final AppState state;
  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  final _attempt = PurchaseAttempt();
  int _v = 0;
  Object? _busyId;

  Future<void> _recharge(Map p) async {
    setState(() => _busyId = p['id']);
    try {
      final r = await widget.state.api.post('/checkout/recharge', {'package': p['id'], 'key': _attempt.keyFor('recharge|${p['id']}')});
      if (!mounted) return;
      final order = await runCheckout(context, widget.state.api, r);
      if (!mounted) return;
      if (!isPendingPayment(order)) _attempt.reset();
      if (isPaidLike(order)) showSnack(context, 'Wallet recharged.');
      setState(() => _v++);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Wallet')),
        body: SafeArea(
          child: AsyncView<Map<String, dynamic>>(
            key: ValueKey(_v),
            load: () => widget.state.api.get('/wallet'),
            builder: (context, w, _) {
              final packages = ((w['packages'] as List?) ?? const []).whereType<Map>().toList();
              final tx = ((w['transactions'] as List?) ?? const []).whereType<Map>().toList();
              return ListView(padding: const EdgeInsets.all(16), children: [
                Card(
                  color: ArjColors.ink900,
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Text('Wallet balance', style: TextStyle(color: ArjColors.gold200)),
                      const SizedBox(height: 4),
                      Text(formatPaise((w['balance_minor'] as num?)?.toInt() ?? 0),
                          style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 4),
                      const Text('Used automatically at checkout when you choose "Use wallet balance".', style: TextStyle(color: Colors.white70, fontSize: 12)),
                    ]),
                  ),
                ),
                if (packages.isNotEmpty) ...[
                  const SectionTitle('Recharge'),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    childAspectRatio: 1.8,
                    children: [
                      for (final p in packages)
                        Card(
                          margin: EdgeInsets.zero,
                          clipBehavior: Clip.antiAlias,
                          child: InkWell(
                            onTap: _busyId != null ? null : () => _recharge(p),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                                if (_busyId == p['id'])
                                  const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, semanticsLabel: 'Working'))
                                else
                                  Text(formatPaise((p['amount_minor'] as num?)?.toInt() ?? 0), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600)),
                                if (((p['bonus_minor'] as num?) ?? 0) > 0)
                                  Text('+ ${formatPaise((p['bonus_minor'] as num).toInt())} bonus', style: const TextStyle(color: ArjColors.success700, fontWeight: FontWeight.w600)),
                              ]),
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
                const SectionTitle('Recent activity'),
                if (tx.isEmpty) const Text('No wallet activity yet.', style: TextStyle(color: ArjColors.ink600)),
                for (final t in tx)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(t['direction'] == 'credit' ? Icons.south_west : Icons.north_east,
                        color: t['direction'] == 'credit' ? ArjColors.success700 : ArjColors.ink600, semanticLabel: t['direction'] == 'credit' ? 'Credit' : 'Debit'),
                    title: Text('${t['memo'] ?? humanize(t['kind'])}'),
                    subtitle: Text(formatWhen(t['at'])),
                    trailing: Text('${t['direction'] == 'credit' ? '+' : '−'} ${formatPaise((t['amount_minor'] as num?)?.toInt() ?? 0)}',
                        style: TextStyle(fontWeight: FontWeight.w600, color: t['direction'] == 'credit' ? ArjColors.success700 : ArjColors.ink900)),
                  ),
              ]);
            },
          ),
        ),
      );
}
