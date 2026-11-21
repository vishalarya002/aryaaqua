<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
const CART_SLOTS = ['09:00–12:00','12:00–15:00','15:00–18:00'];
function cart_int(string $field, int $min, int $max): int {
    $v=filter_var(field($field),FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
    if ($v===false) throw new DomainException('Enter a valid quantity or selection.');
    return $v;
}
function cart_change(array &$cart, string $action, array $products): void {
    $key=field('key');
    if ($action==='remove') { unset($cart[$key]); return; }
    if ($action==='update') {
        if (!isset($cart[$key])) throw new DomainException('This item is no longer in your cart.');
        $cart[$key]['qty']=cart_int('qty',1,9999);
    } elseif ($action==='add') {
        $id=cart_int('id',1,PHP_INT_MAX);
        $p=$products[$id] ?? null;
        if (!$p) throw new DomainException('Product unavailable.');
        $pack=1; $date=$slot=$address='';
        if ($p['type']==='bottle') {
            $pack=cart_int('pack',1,24);
            if (!in_array($pack,[1,12,24],true)) throw new DomainException('Choose a listed pack.');
        } else {
            $date=field('date'); $slot=field('slot'); $address=trim(field('address'));
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$d || $d->format('Y-m-d')!==$date || $date<date('Y-m-d') || !in_array($slot,CART_SLOTS,true) || ($date===date('Y-m-d') && substr($slot,0,5)<=date('H:i'))) throw new DomainException('Choose a future delivery slot.');
            if (strlen($address)<8 || strlen($address)>500) throw new DomainException('Enter a delivery address (8–500 bytes).');
        }
        $key=hash('sha256',serialize([$id,$pack,$date,$slot,$address]));
        $qty=cart_int('qty',1,9999)+($cart[$key]['qty'] ?? 0);
        $cart[$key]=compact('id','pack','date','slot','address','qty');
    } else throw new DomainException('Unknown cart action.');
    $id=$cart[$key]['id']; $units=0;
    foreach ($cart as $row) if ($row['id']===$id) $units+=$row['qty']*$row['pack'];
    if ($units>($products[$id]['stock'] ?? 0)) throw new DomainException('Requested quantity exceeds available stock.');
}
function cart_quote(array $cart,array $products,string $code): array {
    $lines=[]; $unitsByProduct=[]; $count=$subtotal=$discount=$coupon=0; $couponValid=false;
    foreach ($cart as $row) {
        $id=$row['id']; $unitsByProduct[$id]=($unitsByProduct[$id] ?? 0)+$row['pack']*$row['qty'];
    }
    foreach ($cart as $key=>$row) {
        $p=$products[$row['id']] ?? null;
        $units=$row['pack']*$row['qty']; $count+=$units;
        $amount=(int)round((float)($p['price'] ?? 0)*100)*$units;
        $n=$unitsByProduct[$row['id']];
        $tier=($p['type'] ?? '')==='bottle'?($n>=100?10:($n>=50?5:0)):0;
        $saving=(int)round($amount*$tier/100);
        $subtotal+=$amount; $discount+=$saving;
        $lines[]=$row+['key'=>$key,'name'=>$p['name'] ?? 'Unavailable item','units'=>$units,'total'=>$amount-$saving,'tier'=>$tier,'available'=>$p && $n<=$p['stock']];
    }
    $net=$subtotal-$discount;
    if ($code!=='' && $count) {
        $c=query('SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at>NOW())',[$code])->fetch();
        if ($c && $net>=round($c['min_amount']*100)) {
            $couponValid=true;
            $coupon=min($net,(int)round($c['discount_type']==='percent'?$net*$c['value']/100:$c['value']*100));
        }
    }
    // Local demo pricing in paise. Configure business rates before taking orders.
    $tax=0; $delivery=0; $total=$net-$coupon+$tax+$delivery;
    return compact('lines','count','subtotal','discount','coupon','couponValid','code','tax','delivery','total');
}
