import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';

Color statusColor(String status) {
  switch (status) {
    case OrderStatus.confirmed:
      return AppColor.info;
    case OrderStatus.processing:
      return AppColor.accent;
    case OrderStatus.delivered:
      return AppColor.success;
    case OrderStatus.cancelled:
      return AppColor.danger;
    default:
      return AppColor.warning;
  }
}

class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    return RoleBadge(label: capitalize(status), color: statusColor(status));
  }
}
