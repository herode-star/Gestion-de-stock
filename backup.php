<?php
require_once __DIR__.'/core/bootstrap.php';require_auth();
$tables=['produit','fournisseur','sales','sale_items','stock_movements','app_settings'];$data=['created_at'=>date(DATE_ATOM),'business'=>setting('business_name'),'version'=>1,'tables'=>[]];
foreach($tables as $table){$data['tables'][$table]=db()->query('SELECT * FROM '.$table)->fetchAll();}
$filename='sovgad-stock-'.date('Y-m-d-His').'.json';header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
