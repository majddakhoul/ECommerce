import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';
import 'package:e_commerce/features/orders/widget/status_chip.dart';

class OrderCard extends StatelessWidget {
  final OrderModel order;
  final bool showUser;
  final VoidCallback onTap;

  const OrderCard({
    super.key,
    required this.order,
    required this.onTap,
    this.showUser = false,
  });

  @override
  Widget build(BuildContext context) {
    final names = order.items.map((e) => e.productName).take(2).join(', ');
    final more = order.items.length > 2 ? ' +${order.items.length - 2}' : '';

    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColor.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColor.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Order #${order.id}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
                const Spacer(),
                StatusChip(status: order.status),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              formatDate(order.createdAt),
              style: const TextStyle(
                color: AppColor.textSecondary,
                fontSize: 12,
              ),
            ),
            if (showUser && order.user != null) ...[
              const SizedBox(height: 6),
              Text(
                '${order.user!.name} · ${order.user!.email}',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ],
            const SizedBox(height: 10),
            Text(
              '$names$more',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: AppColor.textSecondary),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Text(
                  '${order.itemsCount} item(s)',
                  style: const TextStyle(color: AppColor.textSecondary),
                ),
                const Spacer(),
                Text(
                  money(order.totalPrice),
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    color: AppColor.accent,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
