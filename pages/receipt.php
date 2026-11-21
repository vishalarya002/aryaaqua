<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/orders.php';
$uid=customer_id();
$o=query('SELECT * FROM orders WHERE user_id=? AND id=?',[$uid,(int)($_GET['id'] ?? 0)])->fetch();
if (!$o) { http_response_code(404); order_page_start('Order unavailable'); echo '</main></body></html>'; exit; }
$items=query('SELECT * FROM order_items WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll();
$download=isset($_GET['download']);
if ($download) header('Content-Disposition: attachment; filename="Invoice-'.preg_replace('/[^a-zA-Z0-9-]/','',$o['order_no']).'.html"');
$confirmed=($_SESSION['confirmed_order'] ?? 0)===(int)$o['id'];
unset($_SESSION['confirmed_order']);
order_page_start($download?'Invoice':($confirmed?'Order placed':'Order details'));
?>
<h2>ARYAAQUA</h2><p><strong><?=h($o['order_no'])?></strong> · <?=h($o['created_at'])?></p><p>Order status <strong><?=h($o['order_status'])?></strong></p><p><?=h(strtoupper($o['payment_method']))?> · Payment <?=h($o['payment_status'])?></p>
<h3>Delivery address</h3><p><?=h($o['address_snapshot'])?></p>
<table><thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Amount after bulk discount</th></tr></thead><tbody><?php foreach ($items as $i): ?><tr><td><?=h($i['product_name'])?><?php if ($i['delivery_date']): ?><br><small><?=h($i['delivery_date'].' · '.$i['delivery_slot'].' · '.$i['delivery_address'])?></small><?php endif; ?></td><td><?=(int)$i['quantity']?></td><td>₹<?=h($i['unit_price'])?></td><td>₹<?=h($i['line_total'])?></td></tr><?php endforeach; ?></tbody></table>
<dl><?php foreach (['Subtotal'=>'subtotal','Discount (bulk and coupon)'=>'discount','GST'=>'tax','Delivery'=>'delivery_fee','Total'=>'total'] as $label=>$key): ?><dt><?=h($label)?></dt><dd>₹<?=h($o[$key])?></dd><?php endforeach; ?></dl>
<?php if (!$download): ?><div class="actions"><button onclick="window.print()">Print / Save PDF</button><a href="?id=<?=(int)$o['id']?>&amp;download=1">Download HTML invoice</a><a href="orders.php">My orders</a></div><?php endif; ?></main></body></html>
