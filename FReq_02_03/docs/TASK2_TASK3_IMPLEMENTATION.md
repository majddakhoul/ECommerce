# Task 2 + Task 3 Implementation

## Task 2

تمت إضافة `inventory-service/` كخدمة Go مستقلة بعقد `inventory-service/proto/inventory.proto` وكود gRPC مولد للـ Go والـ PHP. قاعدة المستودع مستقلة (`inventory-db`) وتحتوي منتجات المخزون والحجوزات وعناصرها. الحجز ذري ومتعدد المنتجات، مع row locks وترتيب ثابت للـ IDs وidempotency/release compensation.

تتكامل Laravel عبر `App\Inventory\GrpcInventoryClient`. إنشاء/تحديث المنتج يزامن الخدمة، checkout يستعمل `Reserve`، والإلغاء/الحذف يستعمل `Release`. في الاختبارات يمكن وضع `INVENTORY_ENABLED=false` لاستعمال Null client.

## Task 3

- Redis cache لقوائم المنتجات والفئات مع versioned invalidation بعد أي write أو تغيير مخزون.
- `ETag` middleware على GET products/products/categories مع استجابة `304` للطلبات الشرطية.
- اتصال `mysql_read` قابل للتوجيه إلى read replica عبر `DB_READ_*`; القراءة من replica للقوائم والفئات فقط، بينما القراءة اللازمة للمخزون والكتابة من primary.
- فهارس مركبة للمنتجات والطلبات.
- سيناريو k6 في `performance/k6/products.js` وشرح EXPLAIN والقياس في `performance/README.md`.

## Commands

```bash
# local tests without external services
INVENTORY_ENABLED=false PRODUCT_CACHE_ENABLED=false php artisan test

# complete Docker stack
cp .env.example .env
docker compose up --build

# compare cache off/on
k6 run -e BASE_URL=http://localhost:8000 -e TOKEN=<JWT> performance/k6/products.js
```

سجّل p50/p95/p99 وعدد الطلبات في الثانية في التشغيلين، ثم استخدم `EXPLAIN` الموثق في `performance/README.md` للتحقق من استخدام الفهارس.
