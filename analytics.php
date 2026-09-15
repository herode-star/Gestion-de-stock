<?php
require_once __DIR__.'/core/layout.php';
require_auth();
$days=(int)($_GET['days']??30);if(!in_array($days,[7,30,90,365],true))$days=30;
$snapshot=business_snapshot($days);$insights=local_business_insights($snapshot);$pdo=db();
$previous=$pdo->query("SELECT COALESCE(SUM(total),0) revenue,COUNT(*) sales_count FROM sales WHERE created_at >= DATE_SUB(NOW(),INTERVAL ".($days*2)." DAY) AND created_at < DATE_SUB(NOW(),INTERVAL {$days} DAY)")->fetch();
$revenueChange=percent_change((float)$snapshot['summary']['revenue'],(float)$previous['revenue']);
$daily=$pdo->query("SELECT DATE(created_at) day,SUM(total) total FROM sales WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL {$days} DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();
$maxDaily=1.0;foreach($daily as $row)$maxDaily=max($maxDaily,(float)$row['total']);
$answer=null;if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$question=post('question');if($question!==''){$answer=ask_openai_about_business($question,$snapshot);log_activity('ai_question','analytics',null,substr($question,0,180));}}
page_header('AI & Estatistik','analytics');
?>
<div class="page-actions"><div><strong>Sant Entèlijans Biznis</strong><p class="muted" style="margin:4px 0 0">Konprann stock, lavant ak sa pou w fè apre.</p></div><form method="get" style="max-width:220px"><select name="days" onchange="this.form.submit()"><option value="7" <?=$days===7?'selected':''?>>7 dènye jou</option><option value="30" <?=$days===30?'selected':''?>>30 dènye jou</option><option value="90" <?=$days===90?'selected':''?>>90 dènye jou</option><option value="365" <?=$days===365?'selected':''?>>1 ane</option></select></form></div>
<div class="stats">
 <div class="card stat"><small>Chif lavant</small><strong><?=e(money($snapshot['summary']['revenue']))?></strong><em><?=$revenueChange===null?'Nouvo peryòd':(($revenueChange>=0?'+':'').number_format($revenueChange,1).'% vs peryòd anvan')?></em></div>
 <div class="card stat"><small>Pwofi brit estime</small><strong><?=e(money($snapshot['summary']['gross_profit']))?></strong><em>sou pri acha ki antre yo</em></div>
 <div class="card stat"><small>Kantite lavant</small><strong><?=(int)$snapshot['summary']['sales_count']?></strong><em><?=e(money($snapshot['summary']['average_sale']))?> mwayèn</em></div>
 <div class="card stat"><small>Lajan nan stock</small><strong><?=e(money($snapshot['inventory']['cost_value']))?></strong><em><?=(int)$snapshot['inventory']['units']?> inite · <?=e(money($snapshot['inventory']['retail_value']))?> potansyèl</em></div>
</div>
<div class="grid-2 analytics-grid">
 <div class="card"><h2>Tandans lavant</h2><?php if(!$daily):?><div class="empty">Poko gen ase lavant pou trase tandans lan.</div><?php else:?><div class="bar-chart"><?php foreach($daily as $row):$height=max(5,((float)$row['total']/$maxDaily)*100);?><div class="bar-column" title="<?=e(date('d/m',strtotime($row['day'])).' · '.money($row['total']))?>"><div class="bar-value" style="height:<?=$height?>%"></div><small><?=e(date('d/m',strtotime($row['day'])))?></small></div><?php endforeach;?></div><?php endif;?></div>
 <div class="card ai-panel"><div class="ai-heading"><span class="ai-orb">✦</span><div><h2>Poze AI a yon kestyon</h2><small>Li analize estatistik yo, pa modpas ni done prive.</small></div></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><textarea name="question" required placeholder="Egzanp: Ki pwodwi mwen dwe achte ankò semèn sa a?"><?=e(post('question'))?></textarea><button class="primary">Analize biznis mwen</button></form><?php if($answer):?><div class="ai-answer <?=$answer['ok']?'':'warning-answer'?>"><?=nl2br(e($answer['message']))?></div><?php endif;?></div>
</div>
<div class="card" style="margin-top:20px"><h2>Rekòmandasyon otomatik</h2><div class="insight-grid"><?php foreach($insights as $item):?><article class="insight <?=$item[0]?>"><span><?=['danger'=>'!','warning'=>'△','success'=>'✓','info'=>'i'][$item[0]]?></span><div><strong><?=e($item[1])?></strong><p><?=e($item[2])?></p></div></article><?php endforeach;?></div></div>
<div class="card" style="margin-top:20px"><div class="page-actions"><h2>Pwodwi ki pi vann</h2><a class="button small" href="products.php">Jere pwodwi</a></div><div class="table-wrap"><table><thead><tr><th>Pwodwi</th><th>Referans</th><th>Vann</th><th>Revni</th><th>Stock aktyèl</th><th>Sijesyon</th></tr></thead><tbody><?php foreach($snapshot['top_products'] as $p):?><tr><td><strong><?=e(trim($p['marque'].' '.$p['model']))?></strong></td><td><?=e($p['referance'])?></td><td><?=(int)$p['sold_units']?></td><td><?=e(money($p['revenue']))?></td><td><span class="badge <?=(int)$p['quantite']===0?'out':((int)$p['quantite']<=(int)setting('low_stock_limit','5')?'low':'')?>"><?=(int)$p['quantite']?></span></td><td><?php if((int)$p['quantite']===0):?>Achte ankò kounye a<?php elseif((int)$p['sold_units']>0&&(int)$p['quantite']<=(int)$p['sold_units']):?>Stock pou mwens pase yon peryòd<?php elseif((int)$p['sold_units']===0):?>Siveye: pa vann nan peryòd la<?php else:?>Nivo a nòmal<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
<?php page_footer();?>
