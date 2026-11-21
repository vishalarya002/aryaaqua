# ARYAAQUA | XAMPP installation
## Install on XAMPP
1. Start Apache and MySQL in XAMPP Control Panel. Use PHP 8 with pdo_mysql enabled.
2. Extract the aryaaqua folder into C:\xampp\htdocs so that index.php sits directly in C:\xampp\htdocs\aryaaqua.
3. Open http://localhost/phpmyadmin. Create an empty database named aryaaqua_db with utf8mb4_unicode_ci collation.
4. Select that database, choose Import, select database.sql and click Go. The script is for a fresh installation only.
5. In config/db.php set the port, MySQL username and password to match your local installation if the defaults differ. Environment variables shown there also work.
6. Visit http://localhost/aryaaqua/. The storefront displays four seeded bottle sizes and ten tank capacities.

## Local demo administrator
Email: admin@aryaaqua.test  
Temporary password: PhTrdTUiN4RhFRHZw8js
   
Open http://localhost/aryaaqua/admin/ and sign in using this local administrator. Keep this local setup guide private. Replace the demo administrator before any public deployment.

## Verification
- In phpMyAdmin, verify eight tables and one admin user. The stored password should be a bcrypt hash.
- Run SELECT type,COUNT(*) FROM products GROUP BY type; expect bottle = 4 and tank = 10.
- Inspect coupons for WELCOME5 and BULK100. Prices and coupon amounts are sample INR values.
- Future acceptance checks: register a customer, log in, add bottles, book a tank, submit checkout, then update the order status as admin and verify the customer sees it.

## Later enhancements
Payment gateway integration, SMS or WhatsApp order alerts, and a delivery tracking map.
