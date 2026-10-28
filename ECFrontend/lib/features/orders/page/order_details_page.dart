import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/confirm_dialog.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/orders/bloc/cubit/order_details_cubit.dart';
import 'package:e_commerce/features/orders/bloc/state/order_details_state.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';
import 'package:e_commerce/features/orders/widget/status_chip.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class OrderDetailsPage extends StatelessWidget {
  final int orderId;

  const OrderDetailsPage({super.key, required this.orderId});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => OrderDetailsCubit(orderId)..load(),
      child: _OrderDetailsView(orderId: orderId),
    );
  }
}

class _OrderDetailsView extends StatelessWidget {
  final int orderId;

  const _OrderDetailsView({required this.orderId});

  Future<void> _cancel(BuildContext context) async {
    final confirmed = await confirmDialog(
      context,
      title: 'Cancel order',
      message: 'The stock of this order will be restored. Continue?',
      confirmLabel: 'Cancel order',
      danger: true,
    );
    if (!confirmed || !context.mounted) return;
    final homeCubit = context.read<HomeCubit>();
    final result = await context.read<OrderDetailsCubit>().cancel();
    if (!context.mounted) return;
    if (result.ok) {
      homeCubit.refreshProducts();
      AppSnack.show(context, 'Order cancelled.');
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  Future<void> _delete(BuildContext context) async {
    final confirmed = await confirmDialog(
      context,
      title: 'Delete order',
      message: 'This permanently deletes the order. Continue?',
      confirmLabel: 'Delete',
      danger: true,
    );
    if (!confirmed || !context.mounted) return;
    final homeCubit = context.read<HomeCubit>();
    final result = await context.read<OrderDetailsCubit>().delete();
    if (!context.mounted) return;
    if (result.ok) {
      homeCubit.refreshProducts();
      Get.back();
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  Future<void> _changeStatus(BuildContext context, OrderModel order) async {
    final next = OrderStatus.next(order.status)
        .where((s) => s != OrderStatus.cancelled)
        .toList();
    final cubit = context.read<OrderDetailsCubit>();

    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: AppColor.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Update status',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 12),
              for (final status in next)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(
                    radius: 8,
                    backgroundColor: statusColor(status),
                  ),
                  title: Text(
                    capitalize(status),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  onTap: () => Navigator.of(sheetContext).pop(status),
                ),
            ],
          ),
        );
      },
    );

    if (selected == null || !context.mounted) return;
    final result = await cubit.updateStatus(selected);
    if (!context.mounted) return;
    if (result.ok) {
      AppSnack.show(context, 'Status updated to ${capitalize(selected)}.');
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAdmin = context.read<AuthCubit>().isAdmin;

    return Scaffold(
      appBar: appTopBar('ORDER #$orderId'),
      body: BlocBuilder<OrderDetailsCubit, OrderDetailsState>(
        builder: (context, state) {
          if (state is OrderDetailsLoading) return const LoadingView();
          if (state is OrderDetailsError) {
            return ErrorView(
              message: state.message,
              onRetry: () => context.read<OrderDetailsCubit>().load(),
            );
          }

          final loaded = state as OrderDetailsLoaded;
          final order = loaded.order;
          final canCancel = OrderStatus.canCancel(order.status);
          final canChange = isAdmin &&
              OrderStatus.next(order.status)
                  .any((s) => s != OrderStatus.cancelled);

          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
            children: [
              if (loaded.busy)
                const Padding(
                  padding: EdgeInsets.only(bottom: 12),
                  child: LinearProgressIndicator(
                    minHeight: 2,
                    color: AppColor.accent,
                    backgroundColor: AppColor.accentLight,
                  ),
                ),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColor.pinkSoft,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        StatusChip(status: order.status),
                        const Spacer(),
                        Text(
                          money(order.totalPrice),
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _info('Placed', formatDate(order.createdAt)),
                    if (order.deliveredAt != null)
                      _info('Delivered', formatDate(order.deliveredAt)),
                    if (order.user != null) ...[
                      _info('Customer', order.user!.name),
                      _info('Email', order.user!.email),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Items',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              for (final item in order.items)
                Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColor.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColor.border),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.productName,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${item.quantity} x ${money(item.price)}',
                              style: const TextStyle(
                                color: AppColor.textSecondary,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        money(item.subtotal),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 14),
              if (canChange) ...[
                CustomButton(
                  label: 'Update status',
                  style: CustomButtonStyle.purple,
                  onPressed: loaded.busy
                      ? null
                      : () => _changeStatus(context, order),
                ),
                const SizedBox(height: 10),
              ],
              if (canCancel) ...[
                CustomButton(
                  label: 'Cancel order',
                  style: CustomButtonStyle.outline,
                  onPressed: loaded.busy ? null : () => _cancel(context),
                ),
                const SizedBox(height: 10),
              ],
              if (isAdmin)
                CustomButton(
                  label: 'Delete order',
                  style: CustomButtonStyle.danger,
                  onPressed: loaded.busy ? null : () => _delete(context),
                ),
            ],
          );
        },
      ),
    );
  }

  Widget _info(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(
        children: [
          SizedBox(
            width: 90,
            child: Text(
              label,
              style: const TextStyle(color: AppColor.textSecondary),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
