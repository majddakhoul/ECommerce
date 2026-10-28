import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/features/cart/model/cart_model.dart';

class CartItemTile extends StatelessWidget {
  final CartItemModel item;
  final bool busy;
  final ValueChanged<int> onQuantityChanged;
  final VoidCallback onRemove;

  const CartItemTile({
    super.key,
    required this.item,
    required this.busy,
    required this.onQuantityChanged,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    final overStock = item.quantity > item.availableStock;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColor.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColor.border),
      ),
      child: Row(
        children: [
          Container(
            height: 72,
            width: 72,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColor.pink,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Text(
              item.name.isEmpty ? '?' : item.name.substring(0, 1).toUpperCase(),
              style: const TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w800,
                color: AppColor.accent,
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                        ),
                      ),
                    ),
                    GestureDetector(
                      onTap: busy ? null : onRemove,
                      child: const Icon(
                        Icons.delete_outline_rounded,
                        size: 20,
                        color: AppColor.danger,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  money(item.price),
                  style: const TextStyle(color: AppColor.textSecondary),
                ),
                if (overStock)
                  Text(
                    'Only ${item.availableStock} available',
                    style: const TextStyle(
                      color: AppColor.danger,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _StepButton(
                      icon: Icons.remove_rounded,
                      onTap: busy
                          ? null
                          : () => onQuantityChanged(item.quantity - 1),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      child: Text(
                        '${item.quantity}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                    ),
                    _StepButton(
                      icon: Icons.add_rounded,
                      onTap: busy
                          ? null
                          : () => onQuantityChanged(item.quantity + 1),
                    ),
                    const Spacer(),
                    Text(
                      money(item.subtotal),
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColor.accent,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _StepButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 30,
        width: 30,
        decoration: BoxDecoration(
          color: onTap == null ? AppColor.surface : AppColor.accentLight,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(
          icon,
          size: 18,
          color: onTap == null ? AppColor.textSecondary : AppColor.accent,
        ),
      ),
    );
  }
}
