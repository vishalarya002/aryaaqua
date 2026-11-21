<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$admin=null;
if (isset($_SESSION['uid'])) {
    $admin=query('SELECT name,password_hash,role FROM users WHERE id=?',[$_SESSION['uid']])->fetch();
    if (!$admin || !hash_equals($_SESSION['stamp'] ?? '',hash('sha256',$admin['password_hash']))) {
        fresh_session();
        $admin=null;
    }
}
if (!$admin) {
    $_SESSION['after_login']='../admin/';
    header('Location: ../pages/account.php?view=login',true,303);
    exit;
}
if ($admin['role']!=='admin') {
    http_response_code(403);
    exit('<h1>Administrator access required</h1><p><a href="../pages/account.php?view=profile">Return to your account</a></p>');
}
