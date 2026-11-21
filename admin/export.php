<?php
declare(strict_types=1);
require_once __DIR__.'/access.php';
$rows=query('SELECT o.order_no,o.created_at,u.name,u.email,o.order_status,o.payment_method,o.payment_status,o.subtotal,o.discount,o.tax,o.delivery_fee,o.total FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC',[]);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="aryaaqua-orders-'.date('Y-m-d').'.csv"');
$out=fopen('php://output','wb');
fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Order number','Placed at','Customer','Email','Order status','Payment method','Payment status','Subtotal INR','Discount INR','GST INR','Delivery INR','Total INR'],',','"','');
while ($row=$rows->fetch(PDO::FETCH_NUM)) {
    foreach ($row as &$cell) {
        $cell=(string)$cell;
        if (preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u',$cell)) $cell="'".$cell;
    }
    unset($cell);
    fputcsv($out,$row,',','"','');
}
fclose($out);
