<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::logout();
header('Location: ' . base_url('admin/login.php'));
exit;
