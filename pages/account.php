<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$titles = ['login'=>'Log in','register'=>'Create an account','forgot'=>'Forgot password','reset'=>'Set a new password','profile'=>'Your account','logout'=>'Log out'];
$view = $_GET['view'] ?? 'login';
if (!is_string($view) || !isset($titles[$view])) { http_response_code(404); exit('Page not found'); }
$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : field('token');
$email = strtolower(trim(field('email')));
$password = field('password');
$user = null;
try {
    if (isset($_SESSION['uid'])) {
        $user = query('SELECT id,name,email,role,password_hash FROM users WHERE id=?', [$_SESSION['uid']])->fetch();
        if (!$user || !hash_equals($_SESSION['stamp'], hash('sha256',$user['password_hash']))) {
            fresh_session();
            $user = null;
        }
    }
    if ($view === 'profile' && !$user) go('login');
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $user && ($view === 'login' || ($view === 'profile' && $user['role'] === 'admin'))) {
        header('Location: '.($user['role'] === 'admin' ? '../admin/' : 'orders.php'), true, 303);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'],field('csrf'))) throw new DomainException('This form expired. Refresh the page and try again.');
        if ($view === 'logout') { fresh_session(); go('login'); }
        if (in_array($view,['register','forgot','login'],true) && (strlen($email)>191 || !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new DomainException('Enter a valid email address.');
        if (in_array($view,['register','reset'],true) && (!valid_password($password) || $password !== field('confirm'))) throw new DomainException('Use 12–72 bytes for your password and enter the same password twice.');
        if ($view === 'register') {
            $name = trim(field('name'));
            $phone = trim(field('phone'));
            if (!preg_match('/^.{2,120}$/usD',$name) || !preg_match('/^[0-9]{10}$/D',$phone)) throw new DomainException('Enter your name and a 10-digit phone number.');
            try {
                query('INSERT INTO users (name,email,phone,password_hash) VALUES (?,?,?,?)', [$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) != 1062) throw $e;
                throw new DomainException('This email is already registered. Log in or reset your password.');
            }
            $_SESSION['notice']='Account created. Log in to continue.'; go('login');
        }
        if ($view === 'login') {
            if (!take_attempt('login:'.$email,5)) throw new DomainException('Too many login attempts. Try again in 15 minutes.');
            $account = query('SELECT id,role,password_hash FROM users WHERE email=?', [$email])->fetch();
            $ok = password_verify($password,$account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (!$account || !$ok) throw new DomainException('Email or password is incorrect.');
            query('DELETE FROM login_attempts WHERE throttle_key=?', [hash('sha256','login:'.$email)]);
            $shopping = array_intersect_key($_SESSION, array_flip(['cart','coupon','after_login']));
            fresh_session();
            $_SESSION += $shopping;
            $_SESSION['uid']=$account['id'];
            $_SESSION['seen']=time();
            $_SESSION['stamp']=hash('sha256',$account['password_hash']);
            $next=$_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            $destination = $account['role'] === 'admin' ? '../admin/' : 'orders.php';
            if ($account['role'] === 'customer' && $next === 'checkout.php') {
                $destination = 'checkout.php';
            }
            header('Location: '.$destination, true, 303);
            exit;
        }
        if ($view === 'forgot') {
            send_reset($email);
            $_SESSION['notice']='If the email is registered, a reset link is available in the local development mailbox.';
            go('forgot');
        }
        if ($view === 'reset') {
            if (!preg_match('/^[a-f0-9]{64}$/D',$token) || !change_password($token,$password)) throw new DomainException('This reset link has expired or was already used. Request a new one.');
            fresh_session(); $_SESSION['notice']='Password updated. Log in with your new password.'; go('login');
        }
    }
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (Throwable $e) { http_response_code(503); $error='The request could not be completed. Please try again.'; error_log('ARYAAQUA account error: '.$e->getCode()); }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($titles[$view])?> | ARYAAQUA</title><link rel="stylesheet" href="../assets/css/account.css"></head>
<body><main><a class="brand" href="../index.php">ARYAAQUA</a><h1><?=h($titles[$view])?></h1>
<?php if ($error): ?><p class="error" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?=h($notice)?></p><?php endif; ?>
<?php if ($view==='profile' && $user): ?><p>Welcome, <?=h($user['name'])?>.</p><p><a href="orders.php">My orders and saved addresses</a></p><?php if ($user['role']==='admin'): ?><p><a href="../admin/">Open admin dashboard</a></p><?php endif; ?><p><?=h($user['email'])?> · <?=h(ucfirst($user['role']))?></p><?php endif; ?>
<form method="post" action="?view=<?= $view==='profile'?'logout':h($view) ?>">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
<?php if ($view==='register'): ?><label>Full name<input name="name" autocomplete="name" required minlength="2" maxlength="120" value="<?=h(field('name'))?>"></label>
<label>Mobile number<input name="phone" type="tel" autocomplete="tel-national" inputmode="numeric" required pattern="[0-9]{10}" maxlength="10" placeholder="10 digits" value="<?=h(field('phone'))?>"></label><?php endif; ?>
<?php if (in_array($view,['login','register','forgot'],true)): ?><label>Email address<input type="email" name="email" required autocomplete="email" maxlength="191" value="<?=h($email)?>"></label><?php endif; ?>
<?php if (in_array($view,['register','reset','login'],true)): ?><label>Password<input required type="password" name="password" <?= $view==='login'?'autocomplete="current-password"':'minlength="12" maxlength="72" autocomplete="new-password"' ?>></label><?php endif; ?>
<?php if (in_array($view,['register','reset'],true)): ?><small>Use at least 12 characters (maximum 72 bytes).</small><label>Confirm password<input type="password" name="confirm" required autocomplete="new-password"></label><?php endif; ?>
<?php if ($view==='reset'): ?><input type="hidden" name="token" value="<?=h($token)?>"><?php endif; ?>
<button><?=h(['profile'=>'Log out','forgot'=>'Request reset link'][$view] ?? $titles[$view])?></button></form>
<nav><?php foreach (['login','register','forgot'] as $link): if ($view!== 'profile' && $link!==$view): ?><a href="?view=<?=$link?>"><?=h($titles[$link])?></a> <?php endif; endforeach; ?></nav></main>
<script>
const form=document.querySelector('form');
if(form.elements.confirm) form.addEventListener('input',()=>{form.elements.confirm.setCustomValidity(form.elements.confirm.value===form.elements.password.value?'':'Passwords must match.');});
</script></body></html>
