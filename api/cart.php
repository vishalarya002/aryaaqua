<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/cart.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $products=array_column(db()->query('SELECT id,name,type,price,stock FROM products WHERE is_active=1')->fetchAll(),null,'id');
    $cart=$_SESSION['cart'] ?? []; $code=$_SESSION['coupon'] ?? '';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!hash_equals($_SESSION['csrf'],field('csrf'))) throw new DomainException('Refresh the page before updating the cart.');
        $action=field('action');
        if ($action==='coupon') $code=strtoupper(trim(field('code')));
        else cart_change($cart,$action,$products);
    }
    if (!$cart) $code='';
    $quote=cart_quote($cart,$products,$code);
    if (isset($action) && $action==='coupon' && $code!=='' && !$quote['couponValid']) throw new DomainException('Coupon unavailable or minimum amount not reached.');
    $_SESSION['cart']=$cart; $_SESSION['coupon']=$code;
    echo json_encode($quote,JSON_THROW_ON_ERROR);
} catch (DomainException $e) { http_response_code(422); echo json_encode(['error'=>$e->getMessage()]); }
catch (Throwable $e) { http_response_code(503); echo json_encode(['error'=>'Cart unavailable. Please retry.']); }
