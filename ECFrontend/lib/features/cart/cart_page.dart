import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/confirm_dialog.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/cart/bloc/cubit/cart_cubit.dart';
import 'package:e_commerce/features/cart/bloc/state/cart_state.dart';
import 'package:e_commerce/features/cart/widget/cart_item_tile.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/main_navigation/nav_controller.dart';
import 'package:e_commerce/features/orders/bloc/cubit/orders_cubit.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class CartPage extends StatelessWidget {
  const CartPage({super.key});

  Future<void> _change(BuildContext context, int productId, int quantity) async {
    final cubit = context.read<CartCubit>();
    final result = quantity < 1
        ? await cubit.remove(productId)
        : await cubit.setQuantity(productId, quantity);
    if (!context.mounted) return;
    if (!result.ok) AppSnack.error(context, result.error!);
  }

  Future<void> _remove(BuildContext context, int productId) async {
    final result = await context.read<CartCubit>().remove(productId);
    if (!context.mounted) return;
    if (!result.ok) AppSnack.error(context, result.error!);
  }

  Future<void> _clear(BuildContext context) async {
    final confirmed = await confirmDialog(
      context,
      title: 'Clear cart',
      message: 'Remove all items from your cart?',
      confirmLabel: 'Clear',
      danger: true,
    );
    if (!confirmed || !context.mounted) return;
    final result = await context.read<CartCubit>().clear();
    if (!context.mounted) return;
    if (!result.ok) AppSnack.error(context, result.error!);
  }

  Future<void> _checkout(BuildContext context) async {
    final cartCubit = context.read<CartCubit>();
    final ordersCubit = context.read<OrdersCubit>();
    final homeCubit = context.read<HomeCubit>();
    final result = await cartCubit.checkout();
    if (!context.mounted) return;
    if (result.ok) {
      ordersCubit.load();
      homeCubit.refreshProducts();
      AppSnack.show(context, 'Order placed successfully.');
      NavController.goTo(NavController.orders);
    } else {
      AppSnack.error(context, result.error!);
      cartCubit.load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar(
        'CART',
        actions: [
          BlocBuilder<CartCubit, CartState>(
            builder: (context, state) {
              if (state is! CartLoaded || state.cart.isEmpty) {
                return const SizedBox.shrink();
              }
              return IconButton(
                tooltip: 'Clear cart',
                onPressed: state.busy ? null : () => _clear(context),
                icon: const Icon(Icons.delete_sweep_outlined),
              );
            },
          ),
        ],
      ),
      body: BlocBuilder<CartCubit, CartState>(
        builder: (context, state) {
          if (state is CartInitial || state is CartLoading) {
            return const LoadingView();
          }
          if (state is CartError) {
            return ErrorView(
              message: state.message,
              onRetry: () => context.read<CartCubit>().load(),
            );
          }

          final loaded = state as CartLoaded;
          final cart = loaded.cart;

          if (cart.isEmpty) {
            return RefreshIndicator(
              color: AppColor.accent,
              onRefresh: context.read<CartCubit>().load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  SizedBox(
                    height: MediaQuery.of(context).size.height * 0.6,
                    child: EmptyView(
                      icon: Icons.shopping_bag_outlined,
                      title: 'Your cart is empty',
                      subtitle: 'Browse the collection and add something you like.',
                      actionLabel: 'Start shopping',
                      onAction: () =>
                          NavController.index.value = NavController.home,
                    ),
                  ),
                ],
              ),
            );
          }

          return Column(
            children: [
              if (loaded.busy)
                const LinearProgressIndicator(
                  minHeight: 2,
                  color: AppColor.accent,
                  backgroundColor: AppColor.accentLight,
                ),
              Expanded(
                child: RefreshIndicator(
                  color: AppColor.accent,
                  onRefresh: context.read<CartCubit>().load,
                  child: ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
                    itemCount: cart.items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final item = cart.items[index];
                      return CartItemTile(
                        item: item,
                        busy: loaded.busy,
                        onQuantityChanged: (quantity) =>
                            _change(context, item.productId, quantity),
                        onRemove: () => _remove(context, item.productId),
                      );
                    },
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(24, 18, 24, 20),
                decoration: const BoxDecoration(
                  color: AppColor.pinkSoft,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: SafeArea(
                  top: false,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            '${cart.totalQuantity} item(s)',
                            style: const TextStyle(
                              color: AppColor.textSecondary,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          Text(
                            money(cart.totalPrice),
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      CustomButton(
                        label: 'Checkout',
                        loading: loaded.busy,
                        onPressed: () => _checkout(context),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
