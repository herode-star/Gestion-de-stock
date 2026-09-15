<?php
require_once __DIR__ . '/core/layout.php';
require_auth();
$limit = max(1, (int) setting('low_stock_limit', '5'));
$stats = [
    'products' => (int) db()->query('SELECT COUNT(*) FROM produit')->fetchColumn(),
    'units' => (int) db()->query('SELECT COALESCE(SUM(quantite),0) FROM produit')->fetchColumn(),
    'low' => (int) db()->query('SELECT COUNT(*) FROM produit WHERE quantite <= ' . $limit)->fetchColumn(),
    'today' => (float) db()->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
];
$low = db()->query('SELECT id, marque, model, referance, quantite FROM produit WHERE quantite <= ' . $limit . ' ORDER BY quantite ASC LIMIT 8')->fetchAll();
$recent = db()->query('SELECT sale_id, customer_name, payment_method, total, created_at FROM sales ORDER BY sale_id DESC LIMIT 8')->fetchAll();
page_header('Tableau de bord', 'dashboard');
?>
<div class="stats">
 <div class="card stat"><small>Kalite pwodwi</small><strong><?= $stats['products'] ?></strong><em>nan katalòg la</em></div>
 <div class="card stat"><small>Inite nan stock</small><strong><?= $stats['units'] ?></strong><em>disponib pou vann</em></div>
 <div class="card stat"><small>Stock ki ba</small><strong><?= $stats['low'] ?></strong><em>bezwen atansyon</em></div>
 <div class="card stat"><small>Lavant jodi a</small><strong><?= e(money($stats['today'])) ?></strong><em><?= e(date('d/m/Y')) ?></em></div>
</div>
<a class="intelligence-banner" href="analytics.php"><span class="ai-orb">✦</span><div><strong>AI ap siveye biznis ou</strong><small>Wè pwofi, tandans lavant, risk stock ak rekòmandasyon pou pwochen aksyon ou.</small></div><b>Ouvri estatistik →</b></a>
<div class="grid-2">
 <div class="card"><div class="page-actions"><h2>Dènye lavant yo</h2><a class="button small" href="sales.php">Wè tout</a></div>
 <?php if (!$recent): ?><div class="empty">Poko gen lavant. Peze “Nouvo vant” pou kòmanse.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Nimewo</th><th>Kliyan</th><th>Peman</th><th>Total</th><th>Dat</th></tr></thead><tbody><?php foreach ($recent as $sale): ?><tr><td>#<?= (int)$sale['sale_id'] ?></td><td><?= e($sale['customer_name']) ?></td><td><?= e($sale['payment_method']) ?></td><td><strong><?= e(money($sale['total'])) ?></strong></td><td><?= e(date('d/m/Y H:i', strtotime($sale['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
 </div>
 <div class="card"><div class="page-actions"><h2>Avètisman stock</h2><a class="button small" href="products.php?stock=low">Jere stock</a></div>
 <?php if (!$low): ?><div class="empty">Tout stock yo anfòm.</div><?php else: ?><div class="low-list"><?php foreach ($low as $product): ?><div class="low-item"><div><strong><?= e(trim($product['marque'].' '.$product['model'])) ?></strong><div class="muted"><?= e($product['referance']) ?></div></div><span class="badge <?= (int)$product['quantite'] === 0 ? 'out' : 'low' ?>"><?= (int)$product['quantite'] ?> rete</span></div><?php endforeach; ?></div><?php endif; ?>
 </div>
</div>
<?php page_footer(); ?>
