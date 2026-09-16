<?php
require_once __DIR__ . '/core/layout.php';
require_auth();
$pdo = db();

function save_product_image(string $existing = ''): string
{
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) return $existing;
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Foto a pa t ka telechaje. Eseye ankò.');
    if ((int)$_FILES['image']['size'] > 5 * 1024 * 1024) throw new RuntimeException('Foto a twò gwo. Chwazi yon foto ki pi piti pase 5 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($_FILES['image']['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Foto a dwe JPG, PNG oswa WebP.');
    $directory = __DIR__.'/produit';
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) throw new RuntimeException('Dosye foto a pa disponib.');
    $filename = 'upload-'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $directory.'/'.$filename)) throw new RuntimeException('Sèvè a pa t ka sove foto a.');
    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int) post('id', '0');
        if (!preg_match('/^\d{1,9}$/', post('quantity')) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', post('price')) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', post('cost_price', '0'))) {
            flash('error', 'Mete yon kantite antye ak pri valab (maksimòm 2 chif apre pwen an).');
            redirect($id ? 'products.php?edit='.$id : 'products.php?new=1');
        }
        $values = [post('category'), post('model'), post('brand'), post('reference'), (float) post('price'), max(0, (float) post('cost_price')), max(0, (int) post('quantity')), post('description'), post('supplier_id') !== '' ? (int) post('supplier_id') : null];
        if ($values[1] === '' || $values[3] === '' || $values[4] < 0) {
            flash('error', 'Non, referans ak pri pwodwi a obligatwa.');
            redirect($id?'products.php?edit='.$id:'products.php?new=1');
        }
        $existingImage = '';
        $image = '';
        try {
        $pdo->beginTransaction();
        if ($id) {
            $imageQuery=$pdo->prepare('SELECT img,quantite FROM produit WHERE id=? FOR UPDATE');
            $imageQuery->execute([$id]);
            $previous=$imageQuery->fetch();
            if (!$previous) throw new RuntimeException('Pwodwi a pa egziste ankò.');
            if ((string)$previous['quantite'] !== post('original_quantity')) throw new RuntimeException('Stock la chanje depi ou ouvri paj la. Rafrechi epi verifye kantite a.');
            $existingImage=(string)$previous['img'];
        }
        $image = save_product_image($existingImage);
        if ($id) {
            $old = $pdo->prepare('SELECT quantite FROM produit WHERE id=?'); $old->execute([$id]); $before = (int) $old->fetchColumn();
            $stmt = $pdo->prepare("UPDATE produit SET categorie=?,model=?,marque=?,referance=?,prix=?,cost_price=?,quantite=?,description=?,four_id=?,img=? WHERE id=?");
            $stmt->execute(array_merge($values, [$image,$id]));
            $delta = $values[6] - $before;
            if ($delta !== 0) $pdo->prepare("INSERT INTO stock_movements(product_id,movement_type,quantity,note,created_by) VALUES(?, 'adjustment', ?, 'Koreksyon manyèl', ?)")->execute([$id, $delta, current_user()['user_id']]);
            log_activity('update','product',$id,trim($values[2].' '.$values[1]).'; stock '.$before.' → '.$values[6]);
            flash('success', 'Pwodwi a modifye.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO produit(categorie,model,marque,referance,prix,cost_price,img,img_face,img_darrier,etat,color,quantite,date_entre,description,four_id) VALUES(?,?,?,?,?,?,?,'','','Neuf','',?,NOW(),?,?)");
            $stmt->execute(array_merge(array_slice($values,0,6),[$image],array_slice($values,6)));
            $id = (int) $pdo->lastInsertId();
            if ($values[6] > 0) $pdo->prepare("INSERT INTO stock_movements(product_id,movement_type,quantity,note,created_by) VALUES(?, 'in', ?, 'Premye stock', ?)")->execute([$id, $values[6], current_user()['user_id']]);
            log_activity('create','product',$id,trim($values[2].' '.$values[1]).'; stock '.$values[6]);
            flash('success', 'Nouvo pwodwi a ajoute.');
        }
        $pdo->commit();
        if ($image !== $existingImage && strpos($existingImage, 'upload-') === 0 && basename($existingImage) === $existingImage) {
            @unlink(__DIR__.'/produit/'.$existingImage);
        }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($image !== '' && $image !== $existingImage) @unlink(__DIR__.'/produit/'.$image);
            flash('error', $error instanceof PDOException ? 'Pwodwi a pa sove. Verifye chan yo ak founisè a epi eseye ankò.' : $error->getMessage());
            redirect($id ? 'products.php?edit='.$id : 'products.php?new=1');
        }
        redirect('products.php');
    }
    if ($action === 'delete') {
        $id = (int) post('id');
        try {
            $pdo->prepare('DELETE FROM produit WHERE id=?')->execute([$id]);
            log_activity('delete','product',$id,'Pwodwi efase');
            flash('success', 'Pwodwi a efase.');
        } catch (PDOException $e) {
            flash('error', 'Pwodwi sa a gen lavant ki relye avè l; mete kantite li a 0 olye ou efase l.');
        }
        redirect('products.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) { $stmt=$pdo->prepare('SELECT * FROM produit WHERE id=?'); $stmt->execute([(int)$_GET['edit']]); $edit=$stmt->fetch(); }
$showForm = isset($_GET['new']) || $edit;
$q = trim((string)($_GET['q'] ?? ''));
$where = []; $params = [];
if ($q !== '') { $where[]='(model LIKE ? OR marque LIKE ? OR referance LIKE ? OR categorie LIKE ?)'; $needle='%'.$q.'%'; $params=[$needle,$needle,$needle,$needle]; }
if (($_GET['stock'] ?? '') === 'low') { $where[]='quantite <= ?'; $params[]=(int)setting('low_stock_limit','5'); }
$sql='SELECT p.*, CONCAT(COALESCE(f.nom,\'\'),\' \',COALESCE(f.prenom,\'\')) supplier_name FROM produit p LEFT JOIN fournisseur f ON f.fourn_id=p.four_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY p.id DESC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $products=$stmt->fetchAll();
$suppliers=$pdo->query('SELECT fourn_id,nom,prenom FROM fournisseur ORDER BY nom')->fetchAll();
page_header($showForm ? ($edit ? 'Modifye pwodwi' : 'Ajoute pwodwi') : 'Pwodwi ak stock', 'products');
?>
<?php if ($showForm): ?>
<div class="card"><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="original_quantity" value="<?= e($edit['quantite']??'') ?>"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<div class="field"><label>Non pwodwi *</label><input name="model" required autofocus value="<?= e($edit['model']??'') ?>" placeholder="Egzanp: iPhone 15"></div>
<div class="field"><label>Mak</label><input name="brand" value="<?= e($edit['marque']??'') ?>" placeholder="Egzanp: Apple"></div>
<div class="field"><label>Referans / SKU *</label><input name="reference" required value="<?= e($edit['referance']??'') ?>" placeholder="Egzanp: IP15-128-BLK"></div>
<div class="field"><label>Kategori</label><input name="category" value="<?= e($edit['categorie']??'') ?>" placeholder="Telefòn, rad, manje..."></div>
<div class="field"><label>Pri vant *</label><input type="number" min="0" step="0.01" name="price" required value="<?= e($edit['prix']??'') ?>"></div>
<div class="field"><label>Pri acha</label><input type="number" min="0" step="0.01" name="cost_price" value="<?= e($edit['cost_price']??'0') ?>"><small>Sa pèmèt sistèm nan kalkile pwofi.</small></div>
<div class="field"><label>Kantite nan stock</label><input type="number" min="0" name="quantity" required value="<?= e($edit['quantite']??'0') ?>"></div>
<div class="field"><label>Founisè</label><select name="supplier_id"><option value="">— Pa chwazi —</option><?php foreach($suppliers as $s): ?><option value="<?= (int)$s['fourn_id'] ?>" <?= (string)($edit['four_id']??'')===(string)$s['fourn_id']?'selected':'' ?>><?= e($s['nom'].' '.$s['prenom']) ?></option><?php endforeach; ?></select></div>
<div class="field full image-upload"><label>Foto pwodwi a</label><div class="image-upload-box"><div class="image-preview-wrap"><?php if(!empty($edit['img'])):?><img id="image-preview" src="produit/<?=e(rawurlencode($edit['img']))?>" alt="Aperçu foto"><?php else:?><div id="image-placeholder">▧<small>Aperçu foto</small></div><img id="image-preview" alt="Aperçu foto" hidden><?php endif;?></div><div><input id="product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG oswa WebP · maksimòm 5 MB</small></div></div></div>
<div class="field full"><label>Nòt / deskripsyon</label><textarea name="description"><?= e($edit['description']??'') ?></textarea></div>
<div class="form-footer"><a class="button" href="products.php">Anile</a><button class="primary" type="submit">Sove pwodwi a</button></div></form></div>
<?php else: ?>
<div class="page-actions"><form><input type="search" name="q" value="<?= e($q) ?>" placeholder="Chèche non, mak oswa referans"><button type="submit">Chèche</button></form><a class="button primary" href="products.php?new=1">+ Ajoute pwodwi</a></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Pwodwi</th><th>Referans</th><th>Kategori</th><th>Pri</th><th>Stock</th><th>Founisè</th><th>Aksyon</th></tr></thead><tbody>
<?php foreach($products as $p): $image=$p['img']?('produit/'.rawurlencode($p['img'])):''; ?><tr><td><div class="product-cell"><?php if($image): ?><img class="product-image" src="<?= e($image) ?>" alt=""><?php else: ?><span class="product-image"></span><?php endif; ?><div><strong><?= e(trim($p['marque'].' '.$p['model'])) ?></strong></div></div></td><td><?= e($p['referance']) ?></td><td><?= e($p['categorie']) ?></td><td><?= e(money($p['prix'])) ?></td><td><span class="badge <?= (int)$p['quantite']===0?'out':((int)$p['quantite']<=(int)setting('low_stock_limit','5')?'low':'') ?>"><?= (int)$p['quantite'] ?></span></td><td><?= e(trim($p['supplier_name'])) ?: '—' ?></td><td><div class="actions"><a class="button small" href="products.php?edit=<?= (int)$p['id'] ?>">Modifye</a><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="button small danger" data-confirm="Ou sèten ou vle efase pwodwi sa a?">Efase</button></form></div></td></tr><?php endforeach; ?>
<?php if(!$products): ?><tr><td colspan="7" class="empty">Pa gen pwodwi ki koresponn.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endif; page_footer(); ?>
