<?php
declare(strict_types=1);
require_once __DIR__.'/includes/cart.php';
$store = require __DIR__.'/config/storefront.php';
$bottles = $tanks = [];
$customer = null;
$catalogueError = false;
try {
    $products = db()->query('SELECT id,stock,image,name,type,size_litres,price FROM products WHERE is_active=1 ORDER BY size_litres')->fetchAll();
    foreach ($products as $p) {
        if ($p['type']==='tank') $tanks[]=$p;
        else $bottles[]=$p;
    }
    if (isset($_SESSION['uid'])) {
        $record=query('SELECT name,password_hash FROM users WHERE id=?',[$_SESSION['uid']])->fetch();
        if ($record && hash_equals($_SESSION['stamp'],hash('sha256',$record['password_hash']))) $customer=$record['name'];
        else fresh_session();
    }
} catch (PDOException $e) { $catalogueError=true; error_log('ARYAAQUA homepage: '.$e->getCode()); }
function money($amount): string { return '₹'.rtrim(rtrim(number_format((float)$amount,2,'.',','),'0'),'.'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ARYAAQUA | Packaged Water &amp; Tank Supply</title>
<meta name="description" content="Explore ARYAAQUA packaged drinking water and compare bulk tank capacities for your home, workplace or event.">
<meta property="og:title" content="ARYAAQUA | Pure Water. Delivered with Trust."><meta property="og:description" content="Bottled water and bulk tank supply for everyday needs."><meta property="og:type" content="website"><meta property="og:image" content="assets/img/spring.svg"><link rel="stylesheet" href="assets/css/home.css"><meta name="csrf" content="<?=h($_SESSION['csrf'])?>"><link rel="stylesheet" href="assets/css/shop.css"><script defer src="assets/js/shop.js?v=6"></script></head>
<body id="home" data-api="api/cart.php"><a class="skip" href="#main">Skip to content</a>
<header><a href="#home" class="logo"><span class="drop"></span>ARYAAQUA</a><nav aria-label="Main"><a href="#home">Home</a><a href="#products">Products</a><a href="#tanks">Tank supply</a><a href="#about">About</a><a href="#contact">Contact</a></nav><div class="tools"><button id="cart">Cart (0)</button>
<?php if ($customer !== null): ?><details class="account"><summary><?=h($customer)?></summary><div><a href="pages/account.php?view=profile">My account</a><form method="post" action="pages/account.php?view=logout"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button>Log out</button></form></div></details>
<?php else: ?><a href="pages/account.php?view=login">Log in</a><a class="register" href="pages/account.php?view=register">Register</a><?php endif; ?></div></header>
<main id="main"><section class="hero"><div class="copy"><h1>Pure Water.<br><em>Delivered</em><br>with Trust.</h1><p>Packaged bottles for the table. Bulk water for the whole venue. Find the right supply for your day.</p><div class="actions"><a class="primary" href="#products">Order Bottles</a><a class="secondary" href="#tanks">Book Water Tank</a></div></div>
<div class="scene" role="img" aria-label="ARYAAQUA bottle standing in aqua water with ripples and a fading reflection"><div class="halo"></div><div class="water"></div><svg class="waves" viewBox="0 0 640 100" preserveAspectRatio="none" aria-hidden="true"><path d="M-160 45Q-80 15 0 45T160 45T320 45T480 45T640 45T800 45V100H-160Z"/><path d="M-160 65Q-80 90 0 65T160 65T320 65T480 65T640 65T800 65V100H-160Z"/></svg><div class="rings"></div><div class="bottle"><img src="assets/img/spring.svg" alt=""><img class="mirror" src="assets/img/spring.svg" alt=""></div><i></i><i></i><i></i></div></section>
<div class="strip"><?php foreach (($store['service_claims'] ?: ['Packaged water','Bulk tank supply','Residential delivery','Commercial supply']) as $s): ?><span><?=h($s)?></span><?php endforeach; ?></div>
<section id="products"><h2>The bottle collection</h2><p>Choose a pack. Save 5% on 50 bottles or 10% on 100 bottles of the same size.</p><div class="grid"><?php foreach ($bottles as $p): ?><article><div class="product-img"><img loading="lazy" src="<?=h($p['image'] ?: 'assets/img/spring.svg')?>" alt="<?=h($p['name'])?>"></div><h3><?=h($p['name'])?></h3><p><?=money($p['price'])?> / bottle</p><?php require __DIR__.'/includes/bottle_form.php'; ?></article><?php endforeach; ?></div><?php if (!$bottles): ?><p><?= $catalogueError?'Catalogue temporarily unavailable.':'No bottle sizes available.' ?></p><?php endif; ?></section>
<section class="tank" id="tanks"><div><h2>Bulk water,<br><em>by the tank.</em></h2><p>Choose a capacity to see the catalogue price for one tank.</p></div><div><?php if ($tanks): ?><?php require __DIR__.'/includes/tank_form.php'; ?><?php else: ?><p>Tank supply currently unavailable.</p><?php endif; ?></div></section>
<section id="about"><h2>How it works</h2><div class="steps"><div><h3>Choose</h3><p>Match your bottle size or tank capacity to your gathering.</p></div><div><h3>Pay</h3><p>Review quantities and delivery charges at checkout.</p></div><div><h3>Delivered</h3><p>Receive your water at the delivery address on your order.</p></div></div></section>
<?php if ($store['testimonials']): ?><section><h2>Customer stories</h2><?php foreach ($store['testimonials'] as $t): ?><blockquote><?=h($t['quote'])?> <cite><?=h($t['name'])?></cite></blockquote><?php endforeach; ?></section><?php endif; ?>
<section class="faq"><h2>Questions about supply</h2><details><summary>Which bottle sizes can I choose?</summary><p>The bottle collection above lists every active size with its current price per unit.</p></details><details><summary>How do I compare tank prices?</summary><p>Choose a capacity and number of tanks to see the estimated line total.</p></details><details><summary>Can I place an order here yet?</summary><p>Add bottles or tanks to your cart, then proceed to checkout and select Cash on Delivery.</p></details></section>
</main><footer id="contact"><div><span class="logo">ARYAAQUA</span><p>Pure Water. Delivered with Trust.</p></div><div><h3>Contact</h3><?php if (!$store['email'] && !$store['phone'] && !$store['address']): ?><p>Supplier contact details will be posted here.</p><?php endif; ?>
<?php if ($store['email']): ?><a href="mailto:<?=h($store['email'])?>"><?=h($store['email'])?></a><?php endif; ?>
<?php if ($store['phone']): ?><a href="tel:<?=h($store['phone'])?>"><?=h($store['phone'])?></a><?php endif; ?><p><?=h($store['address'])?></p>
<?php foreach (array_merge(['View map'=>$store['map_url']],$store['social_links']) as $label=>$url): if (str_starts_with($url,'https://')): ?><a href="<?=h($url)?>"><?=h($label)?></a><?php endif; endforeach; ?></div></footer>
<dialog id="bag" aria-labelledby="cartTitle"><h2 id="cartTitle">Your cart</h2><?php require __DIR__.'/includes/cart_panel.php'; ?><p><a href="pages/cart.php">View full cart</a></p><form method="dialog"><button class="primary">Continue browsing</button></form></dialog><p id="feedback" role="status"></p></body></html>
