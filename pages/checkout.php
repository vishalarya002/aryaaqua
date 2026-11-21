<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/orders.php';
$uid=customer_id();
$cart=$_SESSION['cart'] ?? []; $code=$_SESSION['coupon'] ?? '';
$number=$_SESSION['checkout_no'] ??= 'AQ-'.bin2hex(random_bytes(12));
$error=''; $q=null; $addresses=[];
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_order_csrf();
        $token=field('number');
        $existing=query('SELECT id FROM orders WHERE user_id=? AND order_no=?',[$uid,$token])->fetchColumn();
        if ($existing) {
            if ($token===$number) unset($_SESSION['cart'],$_SESSION['coupon'],$_SESSION['checkout_no']);
            header('Location: receipt.php?id='.(int)$existing,true,303); exit;
        }
        if (!hash_equals($number,$token)) throw new DomainException('Checkout expired. Review the cart again.');
        $id=submit_order($uid,$cart,$code,$number);
        unset($_SESSION['cart'],$_SESSION['coupon'],$_SESSION['checkout_no']);
        $_SESSION['confirmed_order']=$id;
        header('Location: receipt.php?id='.$id,true,303); exit;
    }
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (PDOException $e) { $error='Order could not be confirmed. Review My orders before retrying.'; error_log('Checkout: '.$e->getCode()); }
try {
    $q=cart_quote($cart,order_products($cart),$code);
    $addresses=query('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC,id DESC',[$uid])->fetchAll();
} catch (PDOException $e) { $q=null; $error='Checkout temporarily unavailable.'; }
order_page_start('Checkout');
?>
<?php if ($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?>
<?php if (!$cart): ?><p>Your cart is empty. <a href="../index.php#products">Choose products</a></p><?php elseif ($q): ?>
<div class="checkout-grid"><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="number" value="<?=h($number)?>"><input type="hidden" name="review" value="<?=h(quote_signature($q))?>">
<h2>Delivery address</h2><label>Use an address<select name="address_id" id="address-choice"><?php foreach ($addresses as $a): ?><option value="<?=(int)$a['id']?>" <?=field('address_id')===(string)$a['id']?'selected':''?>><?=h(address_text($a))?></option><?php endforeach; ?><option value="new" <?=field('address_id')==='new'?'selected':''?>>Add a new address</option></select></label>
<fieldset id="new-address"><legend>Save a new address</legend><?php foreach (['line1'=>'Street and building','line2'=>'Apartment or landmark (optional)','city'=>'City','state'=>'State','pincode'=>'PIN code'] as $k=>$label): ?><label><?=h($label)?><input name="<?=$k?>" value="<?=h(field($k))?>" maxlength="<?=in_array($k,['line1','line2'])?200:($k==='pincode'?6:100)?>" <?=$k==='pincode'?'pattern="[1-9][0-9]{5}" inputmode="numeric"':''?>></label><?php endforeach; ?></fieldset>
<h2>Payment</h2><label><select required name="payment"><option value="cod">Cash on Delivery</option><option disabled>UPI · gateway offline</option><option disabled>Card · gateway offline</option></select></label><button class="primary">Place order · ₹<?=amount($q['total'])?></button></form>
<aside><h2>Order summary</h2><?php foreach ($q['lines'] as $l): ?><p><?=h($l['name'])?> × <?=$l['units']?> · ₹<?=amount($l['total'])?></p><?php if ($l['date']): ?><small><?=h($l['date'].' · '.$l['slot'].' · '.$l['address'])?></small><?php endif; endforeach; ?>
<dl><?php foreach (['Subtotal'=>'subtotal','Bulk discount'=>'discount','Coupon saving'=>'coupon','GST (demo)'=>'tax','Delivery'=>'delivery','Total'=>'total'] as $label=>$k): ?><dt><?=$label?></dt><dd>₹<?=amount($q[$k])?></dd><?php endforeach; ?></dl><a href="cart.php">Edit cart</a></aside></div>
<script>
const choice=document.querySelector('#address-choice'), fields=document.querySelector('#new-address');
function updateAddress(){fields.disabled=fields.hidden=choice.value!=='new';for(const input of fields.querySelectorAll('input'))input.required=!fields.disabled&&input.name!=='line2';}
choice.addEventListener('change',updateAddress);updateAddress();
</script><?php endif; ?></main></body></html>
