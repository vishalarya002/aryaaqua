<?php
declare(strict_types=1);
require_once __DIR__.'/cart.php';

function customer_id(): int {
    $id=(int)($_SESSION['uid'] ?? 0);
    if ($id) {
        $hash=query('SELECT password_hash FROM users WHERE id=?',[$id])->fetchColumn();
        if ($hash && hash_equals(hash('sha256',$hash),$_SESSION['stamp'] ?? '')) return $id;
        fresh_session();
    }
    $_SESSION['after_login']=basename($_SERVER['SCRIPT_NAME'])==='checkout.php'?'checkout.php':'orders.php';
    go('login');
}
function check_order_csrf(): void {
    if (!hash_equals($_SESSION['csrf'],field('csrf'))) throw new DomainException('Session changed. Refresh and try again.');
}
function order_products(array $cart,bool $lock=false): array {
    $ids=array_values(array_unique(array_column($cart,'id')));
    if (!$ids) return [];
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $sql="SELECT id,type,name,price,stock FROM products WHERE is_active=1 AND id IN ($marks) ORDER BY id";
    return array_column(query($sql.($lock?' FOR UPDATE':''),$ids)->fetchAll(),null,'id');
}
function address_text(array $a): string {
    return implode(', ',array_filter([$a['line1'],$a['line2'],$a['city'],$a['state'],$a['pincode']],fn($v)=>$v!==null && $v!==''));
}
function checkout_address(int $uid): array {
    if (field('address_id')!=='new') {
        $a=query('SELECT * FROM addresses WHERE user_id=? AND id=?',[$uid,field('address_id')])->fetch();
        if (!$a) throw new DomainException('Select a saved address or enter a new one.');
        return $a;
    }
    $a=[];
    foreach (['line1'=>200,'line2'=>200,'city'=>100,'state'=>100,'pincode'=>6] as $key=>$max) {
        $a[$key]=trim(field($key));
        if (!preg_match('/^.{0,'.$max.'}$/uD',$a[$key]) || ($key!=='line2' && $a[$key]==='')) throw new DomainException('Check the address fields and their lengths.');
    }
    if (!preg_match('/^[1-9][0-9]{5}$/D',$a['pincode'])) throw new DomainException('Enter a six-digit Indian PIN code.');
    query('INSERT INTO addresses (user_id,line1,line2,city,state,pincode) VALUES (?,?,?,?,?,?)',array_merge([$uid],array_values($a)));
    $a['id']=(int)db()->lastInsertId();
    return $a;
}
function amount(int $paise): string { return number_format($paise/100,2,'.',''); }
function quote_signature(array $quote): string { return hash('sha256',serialize($quote)); }
function submit_order(int $uid,array $cart,string $code,string $number): int {
    db()->beginTransaction();
    try {
        $products=order_products($cart,true);
        $couponId=$code===''?null:query('SELECT id FROM coupons WHERE code=? FOR UPDATE',[$code])->fetchColumn();
        $q=cart_quote($cart,$products,$code);
        if (!$cart || !hash_equals(quote_signature($q),field('review'))) throw new DomainException('Your cart changed. Review the latest total and submit again.');
        if ($code!=='' && !$q['couponValid']) throw new DomainException('Please remove or update the coupon in your cart.');
        foreach ($q['lines'] as $line) {
            if (!$line['available']) throw new DomainException('Stock changed. Please update your cart.');
            if ($line['date'] && $line['date'].' '.substr($line['slot'],0,5)<=date('Y-m-d H:i')) throw new DomainException('A tank slot has passed. Remove the tank and select a new slot.');
        }
        if (field('payment')!=='cod') throw new DomainException('Select Cash on Delivery. Online payments are unavailable.');
        $a=checkout_address($uid);
        $schedules=array_values(array_filter($q['lines'],fn($line)=>$line['date']!==''));
        $first=$schedules[0] ?? [];
        query("INSERT INTO orders (user_id,order_no,subtotal,discount,tax,delivery_fee,total,payment_method,address_id,address_snapshot,coupon_id,delivery_date,delivery_slot) VALUES (?,?,?,?,?,?,?,'cod',?,?,?,?,?)",
            [$uid,$number,amount($q['subtotal']),amount($q['discount']+$q['coupon']),amount($q['tax']),amount($q['delivery']),amount($q['total']),$a['id'],address_text($a),$couponId,$first['date'] ?? null,$first['slot'] ?? null]);
        $id=(int)db()->lastInsertId();
        foreach ($q['lines'] as $line) {
            $p=$products[$line['id']];
            query('INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price,line_total,delivery_date,delivery_slot,delivery_address) VALUES (?,?,?,?,?,?,?,?,?)',
                [$id,$p['id'],$p['name'],$line['units'],$p['price'],amount($line['total']),$line['date'] ?: null,$line['slot'] ?: null,$line['address'] ?: null]);
            query('UPDATE products SET stock=stock-? WHERE id=?',[$line['units'],$p['id']]);
        }
        db()->commit();
        return $id;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
function order_page_start(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> | ARYAAQUA</title><link rel="stylesheet" href="../assets/css/home.css"><link rel="stylesheet" href="../assets/css/orders.css"></head><body><header><a class="logo" href="../index.php">ARYAAQUA</a><nav><a href="cart.php">Cart</a><a href="orders.php">My orders</a><a href="account.php?view=profile">My account</a></nav></header><main class="order-page"><h1><?=h($title)?></h1>
<?php }
