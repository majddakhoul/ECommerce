# Performance & Caching

شغّل `php artisan migrate --seed` ثم نفّذ `EXPLAIN` على استعلامات المنتجات والطلبات بعد تطبيق migration `2026_11_09_010000_add_performance_indexes.php`.

مثال MySQL:

```sql
EXPLAIN SELECT id, name, price, stock, category FROM products WHERE category = 'electronics' AND stock > 0 ORDER BY id LIMIT 15;
EXPLAIN SELECT id, user_id, status FROM orders WHERE user_id = 2 AND status = 'pending' ORDER BY id DESC LIMIT 15;
```

اختبار الحمل:

```powershell
k6 run -e BASE_URL=http://localhost:8000 -e TOKEN=<JWT> performance/k6/products.js
```

للمقارنة قبل/بعد، نفّذ الاختبار مع `PRODUCT_CACHE_ENABLED=false` ثم `true`، وسجّل p50/p95/p99 وعدد الطلبات في الثانية. بعد تعديل منتج أو مخزونه يُرفع إصدار مفتاح cache، فلا تُعاد بيانات قديمة. `ETag` يعيد `304 Not Modified` عندما يرسل العميل نفس البصمة.

يستخدم `DB_READ_CONNECTION=mysql_read` للقراءات القابلة للتوزيع، بينما تبقى عمليات الكتابة وقراءة المخزون الأساسية على الاتصال الرئيسي. في Docker يمكن توجيهه إلى replica حقيقية عبر `DB_READ_HOST` دون تغيير الكود.
