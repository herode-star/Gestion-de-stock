<?php
require_once __DIR__.'/core/bootstrap.php';require_auth();log_activity('backup','backup',null,'Sovgad JSON telechaje');
$tables=['produit','fournisseur','sales','sale_items','stock_movements','audit_log','app_settings'];$data=['created_at'=>date(DATE_ATOM),'business'=>setting('business_name'),'version'=>2,'tables'=>[]];
db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
db()->beginTransaction();
foreach($tables as $table){$data['tables'][$table]=db()->query('SELECT * FROM '.$table)->fetchAll();}
db()->commit();
$filename='sovgad-stock-'.date('Y-m-d-His').'.json';header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
