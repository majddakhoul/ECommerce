<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Update</title>
</head>
<body style="margin:0;padding:0;background-color:#f6f7fb;font-family:Arial,sans-serif;">
    <div style="max-width:600px;margin:40px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">
        <div style="background:#554CA1;padding:20px;text-align:center;color:#ffffff;">
            <h1 style="margin:0;font-size:22px;">Order Update</h1>
        </div>
        <div style="padding:25px;color:#333;">
            <p style="font-size:16px;">Hello <strong><?php echo e($user->name); ?></strong>,</p>
            <p style="font-size:15px;line-height:1.6;"><?php echo e($statusMessage); ?></p>
            <div style="padding:15px;border:1px solid #eee;border-radius:10px;background:#fafafa;">
                <p style="margin:5px 0;"><strong>Order ID:</strong> #<?php echo e($order->id); ?></p>
                <p style="margin:5px 0;"><strong>Previous status:</strong> <?php echo e(ucfirst($previousStatus)); ?></p>
                <p style="margin:5px 0;"><strong>Current status:</strong> <?php echo e(ucfirst($order->status)); ?></p>
                <p style="margin:5px 0;"><strong>Total:</strong> $<?php echo e(number_format($order->total_price, 2)); ?></p>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;font-size:14px;">
                <tr style="background:#EDE4FB;text-align:left;">
                    <th style="padding:8px;">Product</th>
                    <th style="padding:8px;">Qty</th>
                    <th style="padding:8px;">Price</th>
                </tr>
                <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px;"><?php echo e($item->product_name); ?></td>
                        <td style="padding:8px;"><?php echo e($item->quantity); ?></td>
                        <td style="padding:8px;">$<?php echo e(number_format($item->price, 2)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>
            </div>
            <p style="margin-top:25px;font-size:14px;color:#555;">If you have any questions, feel free to contact support.</p>
        </div>
        <div style="background:#f1f5f9;text-align:center;padding:15px;font-size:12px;color:#888;">
            &copy; <?php echo e(date('Y')); ?> <?php echo e(config('app.name')); ?>. All rights reserved.
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\Users\hp\Desktop\جامعة\ECommerce\ECommerce\FReq_02_03\resources\views/emails/order_status_updated.blade.php ENDPATH**/ ?>