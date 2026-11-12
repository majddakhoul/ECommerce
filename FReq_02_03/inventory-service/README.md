# Inventory Service (Task 2)

خدمة مستقلة بلغة Go تتواصل مع Laravel عبر gRPC فقط. تمتلك قاعدة MySQL مستقلة ولا تقرأ جدول `products` من قاعدة Laravel.

## Guarantees

- `Reserve` ينفذ transaction واحدة بمستوى `SERIALIZABLE`، يقفل الصفوف بـ `SELECT ... FOR UPDATE`، يرتب المنتجات حسب ID لمنع deadlocks، ويتحقق من كل الكميات قبل أي decrement.
- `reservation_id` هو idempotency key؛ تكرار نفس الطلب يعيد نجاحًا دون خصم إضافي.
- `Release` idempotent؛ لا يعيد المخزون أكثر من مرة.
- Laravel يستعمل timeout بالميكروثانية ويحوّل `Unavailable/Deadline/FailedPrecondition` إلى خطأ API مناسب، ويعوض reservation إذا فشل إنشاء الطلب محليًا.

## Run

```bash
go mod tidy
go run ./cmd/server
```

في Docker تُنشأ الجداول تلقائيًا ويستخدم `inventory-db`، بينما يستعمل Laravel أمر `php artisan inventory:sync` لمزامنة المنتجات الموجودة قبل استقبال الطلبات.
