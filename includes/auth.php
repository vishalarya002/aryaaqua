<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

session_name('aryaaqua_session');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'path' => '/aryaaqua/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] >= 1800) {
    fresh_session();
}
$_SESSION['seen'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function fresh_session(): void {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function field(string $key): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
function query(string $sql, array $params): PDOStatement {
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}
function go(string $view): void {
    header('Location: account.php?view=' . $view, true, 303);
    exit;
}
function valid_password(string $password): bool {
    return strlen($password) >= 12 && strlen($password) <= 72 && !str_contains($password, "\0");
}
// Serialize attempts for each mailbox so concurrent requests share one allowance.
function take_attempt(string $key, int $limit): bool {
    $key = hash('sha256', $key);
    db()->beginTransaction();
    try {
        query('INSERT INTO login_attempts (throttle_key,window_started_at) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE throttle_key=VALUES(throttle_key)', [$key]);
        $row = query('SELECT failures, window_started_at <= NOW() - INTERVAL 15 MINUTE AS expired FROM login_attempts WHERE throttle_key=? FOR UPDATE', [$key])->fetch();
        if ($row['expired']) {
            query('UPDATE login_attempts SET failures=1,window_started_at=NOW() WHERE throttle_key=?', [$key]);
            $allowed = true;
        } else {
            $allowed = $row['failures'] < $limit;
            if ($allowed) query('UPDATE login_attempts SET failures=failures+1 WHERE throttle_key=?', [$key]);
        }
        db()->commit();
        return $allowed;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function send_reset(string $email): void {
    if (!take_attempt('reset:' . $email, 3)) return;
    db()->beginTransaction();
    try {
        $id = query('SELECT id FROM users WHERE email=? FOR UPDATE', [$email])->fetchColumn();
        if (!$id) { db()->commit(); return; }
        $token = bin2hex(random_bytes(32));
        query('DELETE FROM password_resets WHERE user_id=?', [$id]);
        query('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,NOW()+INTERVAL 20 MINUTE)', [$id,hash('sha256',$token)]);
        $url = 'http://localhost/aryaaqua/pages/account.php?view=reset&token=' . $token;
        // Local XAMPP development mailbox, one file per customer outside Apache's document root.
        $directory = dirname(realpath($_SERVER['DOCUMENT_ROOT'])) . '/aryaaqua-mail';
        if (!is_dir($directory) && !mkdir($directory,0700) && !is_dir($directory)) throw new RuntimeException('Mailbox unavailable');
        if (file_put_contents($directory . '/reset-' . $id . '.txt', $url . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('Delivery failed');
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
function change_password(string $token, string $password): bool {
    $digest = hash('sha256',$token);
    $id = query('SELECT user_id FROM password_resets WHERE token_hash=?', [$digest])->fetchColumn();
    if (!$id) return false;
    db()->beginTransaction();
    try {
        query('SELECT id FROM users WHERE id=? FOR UPDATE', [$id])->fetchColumn();
        $valid = query('SELECT id FROM password_resets WHERE user_id=? AND token_hash=? AND expires_at>NOW() FOR UPDATE', [$id,$digest])->fetchColumn();
        if ($valid) {
            query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password,PASSWORD_DEFAULT),$id]);
            query('DELETE FROM password_resets WHERE user_id=?', [$id]);
        }
        db()->commit();
        return (bool)$valid;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
