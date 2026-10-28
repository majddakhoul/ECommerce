<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Order Received</title>
</head>
<body style="margin:0;padding:0;background-color:#f6f7fb;font-family:Arial,sans-serif;">
    <div style="max-width:600px;margin:40px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">
        <div style="background:#554CA1;padding:20px;text-align:center;color:#ffffff;">
            <h1 style="margin:0;font-size:22px;">New Order Received</h1>
        </div>
        <div style="padding:25px;color:#333;">
            <p style="font-size:16px;">Hello Admin,</p>
            <p style="font-size:15px;line-height:1.6;">A new order has just been placed and needs your attention.</p>
            <div style="padding:15px;border:1px solid #eee;border-radius:10px;background:#fafafa;">
                <p style="margin:5px 0;"><strong>Order ID:</strong> #{{ $order->id }}</p>
                <p style="margin:5px 0;"><strong>Customer:</strong> {{ $customer->name }} ({{ $customer->email }})</p>
                <p style="margin:5px 0;"><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
                <p style="margin:5px 0;"><strong>Total:</strong> ${{ number_format($order->total_price, 2) }}</p>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;font-size:14px;">
                <tr style="background:#EDE4FB;text-align:left;">
                    <th style="padding:8px;">Product</th>
                    <th style="padding:8px;">Qty</th>
                    <th style="padding:8px;">Price</th>
                </tr>
                @foreach ($order->items as $item)
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px;">{{ $item->product_name }}</td>
                        <td style="padding:8px;">{{ $item->quantity }}</td>
                        <td style="padding:8px;">${{ number_format($item->price, 2) }}</td>
                    </tr>
                @endforeach
            </table>
            </div>
            <p style="margin-top:25px;font-size:14px;color:#555;">Please review the order and update its status.</p>
        </div>
        <div style="background:#f1f5f9;text-align:center;padding:15px;font-size:12px;color:#888;">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
