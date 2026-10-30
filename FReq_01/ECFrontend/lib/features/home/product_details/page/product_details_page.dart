import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/product_image.dart';
import 'package:e_commerce/core/widgets/purple_blob.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/features/cart/bloc/cubit/cart_cubit.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/home/model/product_model.dart';
import 'package:e_commerce/features/home/product_details/bloc/cubit/product_details_cubit.dart';
import 'package:e_commerce/features/home/product_details/bloc/state/product_details_state.dart';
import 'package:e_commerce/features/main_navigation/nav_controller.dart';
import 'package:e_commerce/features/orders/bloc/cubit/orders_cubit.dart';

class ProductDetailsPage extends StatelessWidget {
  final ProductModel product;

  const ProductDetailsPage({super.key, required this.product});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => ProductDetailsCubit(product)..load(),
      child: const _ProductDetailsView(),
    );
  }
}

class _ProductDetailsView extends StatefulWidget {
  const _ProductDetailsView();

  @override
  State<_ProductDetailsView> createState() => _ProductDetailsViewState();
}

class _ProductDetailsViewState extends State<_ProductDetailsView> {
  int _quantity = 1;
  bool _adding = false;
  bool _buying = false;

  void _changeQuantity(int delta, int stock) {
    final next = _quantity + delta;
    if (next < 1) return;
    if (next > stock) {
      AppSnack.error(context, 'Only $stock left in stock.');
      return;
    }
    setState(() => _quantity = next);
  }

  Future<void> _addToCart(ProductModel product) async {
    setState(() => _adding = true);
    final result = await context.read<CartCubit>().add(product.id, _quantity);
    if (!mounted) return;
    setState(() => _adding = false);
    if (result.ok) {
      AppSnack.show(
        context,
        'Added to your cart.',
        actionLabel: 'VIEW CART',
        onAction: () => NavController.goTo(NavController.cart),
      );
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  Future<void> _buyNow(ProductModel product) async {
    setState(() => _buying = true);
    final ordersCubit = context.read<OrdersCubit>();
    final homeCubit = context.read<HomeCubit>();
    final result = await ordersCubit.place(
      [(productId: product.id, quantity: _quantity)],
    );
    if (!mounted) return;
    setState(() => _buying = false);
    if (result.ok) {
      ordersCubit.load();
      homeCubit.refreshProducts();
      AppSnack.show(context, 'Order placed successfully.');
      NavController.goTo(NavController.orders);
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final height = MediaQuery.of(context).size.height;

    return BlocBuilder<ProductDetailsCubit, ProductDetailsState>(
      builder: (context, state) {
        final product = state.product;
        final canBuy = product.inStock;

        return Scaffold(
          backgroundColor: AppColor.white,
          body: Column(
            children: [
              SizedBox(
                height: height * 0.42,
                width: double.infinity,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    ProductImage(url: product.image, name: product.name),
                    const Positioned(
                      top: -50,
                      left: -50,
                      child: PurpleBlob(size: 170),
                    ),
                    const Positioned(
                      bottom: -60,
                      right: -50,
                      child: PurpleBlob(size: 170),
                    ),
                    SafeArea(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: GestureDetector(
                          onTap: () => Get.back(),
                          child: Container(
                            height: 40,
                            width: 40,
                            decoration: BoxDecoration(
                              color: AppColor.white.withValues(alpha: 0.25),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.arrow_back_rounded,
                              color: AppColor.white,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(24, 22, 24, 12),
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            product.name,
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          money(product.price),
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            color: AppColor.accent,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        if (product.category != null &&
                            product.category!.isNotEmpty) ...[
                          RoleBadge(label: capitalize(product.category!)),
                          const SizedBox(width: 8),
                        ],
                        RoleBadge(
                          label: product.inStock
                              ? '${product.stock} in stock'
                              : 'Out of stock',
                          color: product.inStock
                              ? AppColor.success
                              : AppColor.danger,
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Text(
                      (product.description == null ||
                              product.description!.isEmpty)
                          ? 'No description available.'
                          : product.description!,
                      style: const TextStyle(
                        color: AppColor.textSecondary,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Quantity',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        Container(
                          decoration: BoxDecoration(
                            color: AppColor.surface,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              IconButton(
                                onPressed: canBuy
                                    ? () => _changeQuantity(-1, product.stock)
                                    : null,
                                icon: const Icon(Icons.remove_rounded),
                              ),
                              SizedBox(
                                width: 28,
                                child: Text(
                                  '$_quantity',
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 16,
                                  ),
                                ),
                              ),
                              IconButton(
                                onPressed: canBuy
                                    ? () => _changeQuantity(1, product.stock)
                                    : null,
                                icon: const Icon(Icons.add_rounded),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(24, 16, 24, 20),
                decoration: const BoxDecoration(
                  color: AppColor.pinkSoft,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: SafeArea(
                  top: false,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CustomButton(
                        label: 'Add Cart',
                        loading: _adding,
                        onPressed: canBuy && !_buying
                            ? () => _addToCart(product)
                            : null,
                      ),
                      const SizedBox(height: 10),
                      CustomButton(
                        label: 'Buy now',
                        style: CustomButtonStyle.outline,
                        loading: _buying,
                        onPressed: canBuy && !_adding
                            ? () => _buyNow(product)
                            : null,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
