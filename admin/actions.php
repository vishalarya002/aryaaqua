<?php
declare(strict_types=1);
require_once __DIR__.'/access.php';

const NEXT_STATUS=[
    'Placed'=>['Confirmed','Cancelled'],
    'Confirmed'=>['Out for delivery','Cancelled'],
    'Out for delivery'=>['Delivered','Cancelled'],
    'Delivered'=>[], 'Cancelled'=>[]
];
function checked_decimal(string $key,int $digits,bool $positive=false): string {
    $value=trim(field($key));
    if (!preg_match('/^\d{1,'.$digits.'}(?:\.\d{1,2})?$/D',$value) || ($positive && (float)$value<=0)) throw new DomainException('Check the amount or size entered.');
    return $value;
}
function product_values(): array {
    $name=trim(field('name')); $type=field('type'); $stock=filter_var(field('stock'),FILTER_VALIDATE_INT);
    if (!preg_match('/^.{1,120}$/uD',$name) || !in_array($type,['bottle','tank'],true) || $stock===false || $stock<0 || $stock>4294967295) throw new DomainException('Check the product name, type and stock.');
    $image=trim(field('image'));
    if ($image!=='' && (!preg_match('~^assets/img/[a-zA-Z0-9_-]+\.(png|jpe?g|webp|svg)$~iD',$image) || !is_file(__DIR__.'/../'.$image))) throw new DomainException('Choose an existing image in assets/img.');
    return [$type,$name,checked_decimal('size_litres',5,true),checked_decimal('price',8),$stock,$image ?: null,(int)(field('active')==='1')];
}
function coupon_values(): array {
    $code=strtoupper(trim(field('code'))); $type=field('discount_type'); $v=checked_decimal('value',8,true);
    if (!preg_match('/^[A-Z0-9_-]{1,32}$/D',$code) || !in_array($type,['fixed','percent'],true) || ($type==='percent' && (float)$v>100)) throw new DomainException('Check the coupon code and discount.');
    $expiry=field('expires_at');
    if ($expiry!=='') {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$expiry);
        if (!$d || $d->format('Y-m-d\TH:i')!==$expiry) throw new DomainException('Choose a valid expiry date and time.');
        $expiry=$d->format('Y-m-d H:i:s');
    }
    return [$code,$type,$v,checked_decimal('min_amount',8),$expiry ?: null,(int)(field('active')==='1')];
}
function change_status(int $id): void {
    $old=query('SELECT order_status FROM orders WHERE id=? FOR UPDATE',[$id])->fetchColumn();
    $next=field('status');
    if (!$old || !in_array($next,NEXT_STATUS[$old],true)) throw new DomainException('Order status changed or this update is unavailable. Review the current status.');
    if ($next==='Cancelled') {
        $items=query('SELECT product_id,SUM(quantity) AS units FROM order_items WHERE order_id=? GROUP BY product_id ORDER BY product_id',[$id])->fetchAll();
        foreach ($items as $item) query('UPDATE products SET stock=stock+? WHERE id=?',[$item['units'],$item['product_id']]);
    }
    query('UPDATE orders SET order_status=? WHERE id=?',[$next,$id]);
}
function manage_catalogue(string $tab,string $action,int $id): void {
    $product=$tab==='products';
    $table=$product?'products':'coupons';
    $cols=$product?['type','name','size_litres','price','stock','image','is_active']:['code','discount_type','value','min_amount','expires_at','is_active'];
    if ($id>0) {
        $old=query("SELECT * FROM $table WHERE id=? FOR UPDATE",[$id])->fetch();
        if (!$old || !hash_equals(hash('sha256',serialize($old)),field('version'))) throw new DomainException('This record changed. Review its current values before saving.');
    }
    if ($action==='delete' && $id>0) {
        $used=$product?query('SELECT 1 FROM order_items WHERE product_id=? LIMIT 1',[$id])->fetchColumn():query('SELECT 1 FROM orders WHERE coupon_id=? LIMIT 1',[$id])->fetchColumn();
        if ($used) query("UPDATE $table SET is_active=0 WHERE id=?",[$id]);
        else query("DELETE FROM $table WHERE id=?",[$id]);
        return;
    }
    if ($action!=='save') throw new DomainException('Invalid action.');
    $values=$product?product_values():coupon_values();
    if ($id===0) {
        $keys=implode(',',$cols);$marks=implode(',',array_fill(0,count($cols),'?'));
        query("INSERT INTO $table ($keys) VALUES ($marks)",$values);
    } else {
        $sets=implode('=?,',$cols).'=?';
        query("UPDATE $table SET $sets WHERE id=?",[...$values,$id]);
    }
}
