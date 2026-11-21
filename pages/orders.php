<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/orders.php';
$uid=customer_id(); $error=''; $history=$addresses=[];
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_order_csrf();
        $items=query('SELECT i.* FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.user_id=? AND o.id=? ORDER BY i.id',[$uid,field('order_id')])->fetchAll();
        if (!$items) throw new DomainException('Order unavailable.');
        $next=$_SESSION['cart'] ?? [];
        $products=order_products(array_map(fn($i)=>['id'=>$i['product_id']],$items));
        $used=[];
        foreach ($next as $row) $used[$row['id']]=($used[$row['id']] ?? 0)+$row['qty']*$row['pack'];
        foreach ($items as $i) {
            $id=(int)$i['product_id']; $qty=(int)$i['quantity']; $pack=1;
            $used[$id]=($used[$id] ?? 0)+$qty;
            if (!isset($products[$id]) || $products[$id]['stock']<$used[$id]) throw new DomainException('Insufficient stock for '.$i['product_name'].'. Your cart was kept as it was.');
            $date=$slot=$address='';
            if ($products[$id]['type']==='tank') {
                $date=field('date'); $slot=field('slot'); $address=$i['delivery_address'];
                $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$d || $d->format('Y-m-d')!==$date || !in_array($slot,CART_SLOTS,true) || $date.' '.substr($slot,0,5)<=date('Y-m-d H:i') || !$address) throw new DomainException('Select a future delivery date and slot for the tanks.');
            }
            $key=hash('sha256',serialize([$id,$pack,$date,$slot,$address]));
            $qty+=$next[$key]['qty'] ?? 0;
            $next[$key]=compact('id','qty','pack','date','slot','address');
        }
        $_SESSION['cart']=$next;
        header('Location: cart.php',true,303); exit;
    }
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (PDOException $e) { $error='Reorder temporarily unavailable.'; }
try {
    $history=query('SELECT o.*, EXISTS(SELECT 1 FROM order_items i WHERE i.order_id=o.id AND i.delivery_date IS NOT NULL) AS has_tank FROM orders o WHERE o.user_id=? ORDER BY o.id DESC',[$uid])->fetchAll();
    $addresses=query('SELECT * FROM addresses WHERE user_id=? ORDER BY id DESC',[$uid])->fetchAll();
} catch (PDOException $e) { $error='Account information temporarily unavailable.'; }
order_page_start('My orders');
if ($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?>
<?php if (!$history): ?><p>You have no orders yet. <a href="../index.php#products">Browse bottles</a></p><?php endif; ?>
<?php foreach ($history as $o): ?><article class="record"><h2><?=h($o['order_no'])?></h2><p><?=h($o['created_at'])?> · ₹<?=h($o['total'])?> · <strong><?=h($o['order_status'])?></strong></p><p><a href="receipt.php?id=<?=(int)$o['id']?>">Order details and invoice</a></p>
<form method="post"><input name="csrf" type="hidden" value="<?=h($_SESSION['csrf'])?>"><input name="order_id" type="hidden" value="<?=(int)$o['id']?>"><?php if ($o['has_tank']): ?><label>New tank delivery date<input type="date" name="date" min="<?=date('Y-m-d')?>" required></label><label>Delivery slot<select name="slot"><?php foreach (CART_SLOTS as $s): ?><option><?=h($s)?></option><?php endforeach; ?></select></label><?php endif; ?><button>Reorder into cart</button></form></article><?php endforeach; ?>
<section><h2>Saved addresses</h2><?php if (!$addresses): ?><p>Your first checkout address will appear here.</p><?php endif; foreach ($addresses as $a): ?><p><?=h(address_text($a))?></p><?php endforeach; ?></section></main></body></html>
