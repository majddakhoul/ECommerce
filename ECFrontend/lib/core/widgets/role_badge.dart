import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

class RoleBadge extends StatelessWidget {
  final String label;
  final Color? color;
  final VoidCallback? onDeleted;

  const RoleBadge({super.key, required this.label, this.color, this.onDeleted});

  @override
  Widget build(BuildContext context) {
    final tone = color ?? AppColor.accent;
    return Container(
      padding: const EdgeInsets.only(left: 12, right: 12, top: 5, bottom: 5),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: TextStyle(
              color: tone,
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
          if (onDeleted != null) ...[
            const SizedBox(width: 6),
            GestureDetector(
              onTap: onDeleted,
              child: Icon(Icons.close_rounded, size: 15, color: tone),
            ),
          ],
        ],
      ),
    );
  }
}
