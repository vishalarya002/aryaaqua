# ARYAAQUA — project guide
Pure Water. Delivered with Trust.

## Pehle apna setup choose karo
**Agar phases pehle se chal rahe hain:** Ye ZIP complete source ki copy hai. Existing installation ko chalate rehne ke liye dobara import ya install karna zaroori nahi hai. Nayi copy compare karni ho to Downloads mein alag extract karo. Existing project update karte waqt pehle htdocs/aryaaqua ki copy aur phpMyAdmin se aryaaqua_db ka SQL export rakh lo. Apne config/db.php, config/storefront.php aur custom images preserve karo. Is ZIP ka database.sql fresh install ke liye hai. Tumhare current users, passwords, orders aur stock tumhare MySQL database mein hain.

**Fresh XAMPP install:**
1. ZIP extract karo. Andar ka aryaaqua folder C:\xampp\htdocs mein copy karo. Sahi path C:\xampp\htdocs\aryaaqua\index.php hai.
2. XAMPP Control Panel mein Apache aur MySQL start karo. PHP 8 aur pdo_mysql chahiye.
3. http://localhost/phpmyadmin kholo. Fresh installation par Import se aryaaqua/database.sql select karke Go dabao. Script database aur tables create karti hai.
4. config/db.php mein host, port, DB name, username aur password ko apne local MySQL setup se match karo.
5. http://localhost/aryaaqua/ par customer storefront khulega. http://localhost/aryaaqua/admin/ par admin login khulega. Fresh seed ke admin credentials SETUP.md mein hain. Existing account ka password reset kiya hai to updated password use karo.

## Folder aur file ka kaam
| Path | Kaam |
|---|---|
| index.php | Home page, bottle catalogue, tank selector, account menu aur cart drawer ka entry point. |
| database.sql | Tables, sample bottle/tank products, coupons aur local admin seed. |
| .htaccess | Apache directory/file access rules aur custom error pages. |
| SETUP.md | XAMPP installation aur local demo admin credentials. |
| PROJECT-GUIDE.md | Ye Hinglish guide aur testing checklist. |
| config/db.php | PDO se MySQL connection. Port ya DB credentials yahan set karo. |
| config/constants.php | Brand, currency aur timezone. |
| config/storefront.php | Contact, map, social links, customer quotes aur verified service claims. |
| includes/auth.php | Sessions, CSRF token, login limits aur password reset helpers. |
| includes/cart.php | Cart validation, stock checks, pack pricing, bulk tiers aur coupon calculations. |
| includes/bottle_form.php, tank_form.php | Home page par bottle aur tank order forms. |
| includes/cart_panel.php | Drawer aur full cart mein reuse hone wala cart layout. |
| includes/orders.php | Customer checks, checkout address, stock update aur order save karne ki transaction. |
| api/cart.php | Browser se cart add, update, remove aur coupon requests leta hai aur JSON return karta hai. |
| pages/account.php | Register, login, logout, profile aur password reset. |
| pages/cart.php, checkout.php | Full cart aur address/payment review. |
| pages/orders.php, receipt.php | Order history, saved addresses, reorder, invoice print ya HTML download. |
| admin/access.php | Har admin request ke liye login aur admin role check. |
| admin/index.php | Dashboard, charts, orders, customers, products aur coupons ke tabs. |
| admin/actions.php | Product/coupon save, order status aur cancellation par stock return. |
| admin/editor.php, export.php | Product/coupon editor aur orders ka CSV download. |
| assets/css/ | home.css storefront ke liye, account.css login screens ke liye, shop.css cart ke liye, orders.css checkout/invoice ke liye, admin.css admin panel ke liye. |
| assets/js/shop.js | Active shopping UI: quantity, estimate, tank fill, API call aur cart drawer. |
| assets/js/home.js | Earlier catalogue preview script. Current home page shop.js load karta hai. |
| assets/img/spring.svg | Default bottle graphic. Custom product images isi folder mein rakho, phir admin editor se choose karo. |
| errors/404.html, 500.html | Missing page aur server error ki screens. |

## Data ka flow
**Bottle:** Quantity aur pack select → shop.js cart API ko request bhejta hai → PHP price, discount aur stock check karta hai → cart session mein save hota hai. Same bottle size ki 50+ units par 5% aur 100+ par 10% bulk discount hai.
**Tank:** Capacity, quantity, future delivery slot aur address select karo. Ye details cart se order item aur invoice tak jaati hain.
**Checkout:** Login → address → Cash on Delivery → order number. Order save hote waqt stock kam hota hai.
**Admin:** Placed → Confirmed → Out for delivery → Delivered. COD collect hone par payment paid mark karo. Allowed stage par cancel karne se stock wapas aata hai.

## Database ki 8 tables
| Table | Kya store hota hai |
|---|---|
| users | Customer/admin profile aur password hash |
| addresses | Customer ke saved delivery addresses |
| products | Bottle/tank capacity, price, stock, image aur active flag |
| orders | Order number, customer, delivery address snapshot, totals aur status |
| order_items | Har order ke products, quantity, price aur tank delivery details |
| coupons | Code, discount, minimum amount, expiry aur active flag |
| password_resets | One-time reset token ka hash aur expiry |
| login_attempts | Login/reset attempts ki limits |

## Quick QA
1. Customer register/login karo. Empty cart se ek 1 L bottle add karo. Seed price par total ₹20 hona chahiye.
2. Ek tank add karke checkout, future date, slot aur address verify karo. COD order place karo aur AQ order number note karo.
3. My orders mein invoice kholo, amount aur tank details match karo. Reorder se cart populate hona chahiye.
4. InPrivate window mein admin login karo. Wahi AQ order Confirmed karo, phir customer window refresh karke status match karo.
5. CSV export mein order number, total aur payment status check karo. Customer login se admin URL kholne par access deny hona chahiye.

## Demo settings aur pending work
Prices sample hain. GST aur delivery ₹0 configured hain. UPI/Card gateway placeholders hain; local checkout COD use karta hai. Local password reset link C:\xampp\aryaaqua-mail mein milta hai (default install). Public launch se pehle real rates, tax, delivery terms, verified business details aur production credentials configure karo. Future additions: real payment gateway, email/SMS/WhatsApp alerts aur delivery tracking map. Windows XAMPP par Phase 6 admin acceptance aur cross-browser QA pending hain.
