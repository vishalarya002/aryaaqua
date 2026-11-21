<?php
declare(strict_types=1);
require_once __DIR__.'/actions.php';
$tabs=['dashboard'=>'Dashboard','orders'=>'Orders','products'=>'Products','coupons'=>'Coupons','customers'=>'Customers'];
$tab=$_GET['tab'] ?? 'dashboard';
if (!is_string($tab) || !isset($tabs[$tab])) {http_response_code(404);exit('Page not found');}
$error=''; $notice=$_SESSION['admin_notice'] ?? ''; unset($_SESSION['admin_notice']);
function token_field(): void { ?><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><?php }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!hash_equals($_SESSION['csrf'],field('csrf'))) throw new DomainException('Refresh this page and try again.');
        $id=filter_var(field('id'),FILTER_VALIDATE_INT);
        if ($id===false || $id<0) throw new DomainException('Invalid record.');
        db()->beginTransaction();
        if ($tab==='orders' && field('action')==='collect') {
            $updated=query("UPDATE orders SET payment_status='paid' WHERE id=? AND order_status='Delivered' AND payment_method='cod' AND payment_status='pending'",[$id]);
            if (!$updated->rowCount()) throw new DomainException('Review the current payment status.');
        } elseif ($tab==='orders') change_status($id);
        elseif (in_array($tab,['products','coupons'],true)) manage_catalogue($tab,field('action'),$id);
        else throw new DomainException('Invalid action.');
        db()->commit(); $_SESSION['admin_notice']='Changes saved.';
        header('Location: ?tab='.$tab,true,303); exit;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        $error=$e instanceof DomainException?$e->getMessage():'Could not save. Check for a duplicate code or refresh and retry.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title><?=h($tabs[$tab])?> | ARYAAQUA Admin</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body><header><a href="./">ARYAAQUA <small>Admin</small></a><a href="../index.php">Storefront</a><form method="post" action="../pages/account.php?view=logout"><?php token_field(); ?><button>Log out</button></form></header><nav aria-label="Administration"><?php foreach ($tabs as $key=>$title): ?><a href="?tab=<?=$key?>" <?= $tab===$key?'aria-current="page"':'' ?>><?=$title?></a><?php endforeach; ?></nav><main><h1><?=$tabs[$tab]?></h1>
<?php if ($error): ?><p role="alert"><?=h($error)?></p><?php endif; if ($notice): ?><p role="status"><?=h($notice)?></p><?php endif;
try {
if ($tab==='dashboard'):
$k=query("SELECT COUNT(CASE WHEN created_at>=CURDATE() THEN 1 END) AS today,COALESCE(SUM(CASE WHEN payment_status='paid' AND order_status<>'Cancelled' THEN total ELSE 0 END),0) AS revenue,COUNT(CASE WHEN order_status IN ('Placed','Confirmed','Out for delivery') THEN 1 END) AS pending FROM orders",[])->fetch();
?><div class="metrics"><?php foreach (["Today's orders"=>$k['today'],'Collected revenue (all time)'=>'₹'.number_format((float)$k['revenue'],2),'Pending deliveries'=>$k['pending']] as $label=>$value): ?><article><?=h($label)?><strong><?=h((string)$value)?></strong></article><?php endforeach; ?></div>
<h2>Orders over the last 7 days</h2><?php $daily=query('SELECT DATE(created_at) AS day,COUNT(*) AS n FROM orders WHERE created_at>=CURDATE()-INTERVAL 6 DAY GROUP BY DATE(created_at)',[])->fetchAll(PDO::FETCH_KEY_PAIR);$peak=max([1,...array_values($daily)]);for ($i=6;$i>=0;$i--): $day=date('Y-m-d',strtotime("-$i days"));$n=$daily[$day] ?? 0; ?><div class="bar"><span><?=h(date('D, d M',strtotime($day)))?></span><meter min="0" max="<?=$peak?>" value="<?=$n?>" aria-label="Orders on <?=$day?>"></meter><b><?=$n?></b></div><?php endfor; ?>
<h2>Orders by status</h2><?php $counts=query('SELECT order_status,COUNT(*) FROM orders GROUP BY order_status',[])->fetchAll(PDO::FETCH_KEY_PAIR);foreach (NEXT_STATUS as $status=>$unused): $n=$counts[$status] ?? 0; ?><div class="bar"><span><?=h($status)?></span><meter min="0" max="<?=max([1,...array_values($counts)])?>" value="<?=$n?>" aria-label="<?=h($status)?>"></meter><b><?=$n?></b></div><?php endforeach;
elseif ($tab==='orders'): ?><a class="button" href="export.php">Export orders CSV</a><?php
$rows=query('SELECT o.*,u.name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC',[])->fetchAll();
if (!$rows) echo '<p>No orders yet.</p>';
foreach ($rows as $o): ?><article><h2><?=h($o['order_no'])?></h2><p><?=h($o['name'])?> · <?=h($o['created_at'])?> · ₹<?=h($o['total'])?></p><p><?=h($o['address_snapshot'])?></p><p><strong><?=h($o['order_status'])?></strong> · <?=h(strtoupper($o['payment_method']).' / '.$o['payment_status'])?></p><details><summary>Items and tank delivery slots</summary><?php foreach(query('SELECT * FROM order_items WHERE order_id=?',[$o['id']]) as $item): ?><p><?=h($item['product_name'])?> × <?=(int)$item['quantity']?> <?=h(implode(' · ',array_filter([$item['delivery_date'],$item['delivery_slot'],$item['delivery_address']])))?></p><?php endforeach; ?></details>
<?php if (NEXT_STATUS[$o['order_status']]): ?><form method="post"><?php token_field(); ?><input type="hidden" name="id" value="<?=(int)$o['id']?>"><label>Next status <select name="status"><?php foreach(NEXT_STATUS[$o['order_status']] as $s): ?><option><?=h($s)?></option><?php endforeach; ?></select></label><button>Update status</button></form><?php endif; ?>
<?php if ($o['order_status']==='Delivered' && $o['payment_method']==='cod' && $o['payment_status']==='pending'): ?><form method="post"><?php token_field(); ?><input type="hidden" name="id" value="<?=(int)$o['id']?>"><button name="action" value="collect">Mark COD payment collected</button></form><?php endif; ?></article><?php endforeach;
elseif ($tab==='customers'):
$rows=query("SELECT u.name,u.email,u.phone,u.created_at,COUNT(o.id) AS orders FROM users u LEFT JOIN orders o ON o.user_id=u.id WHERE u.role='customer' GROUP BY u.id,u.name,u.email,u.phone,u.created_at ORDER BY u.id DESC",[])->fetchAll();
if (!$rows) echo '<p>No registered customers yet.</p>';
foreach ($rows as $u): ?><article><h2><?=h($u['name'])?></h2><p><?=h($u['email'].' · '.$u['phone'])?></p><p>Joined <?=h($u['created_at'])?> · <?=(int)$u['orders']?> orders</p></article><?php endforeach;
else:
$rows=query($tab==='products'?'SELECT * FROM products ORDER BY id':'SELECT * FROM coupons ORDER BY id',[])->fetchAll();
foreach ([[],...$rows] as $r): require __DIR__.'/editor.php'; endforeach;
endif;
} catch (PDOException $e) { http_response_code(503); echo '<p role="alert">Administration data is temporarily unavailable.</p>'; }
?></main></body></html>
