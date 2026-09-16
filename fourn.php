<?php
// Compatibility route: all changes use the authenticated current application.
require_once __DIR__ . '/core/bootstrap.php';
require_auth();
redirect('suppliers.php');
