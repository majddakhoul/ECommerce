import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/confirm_dialog.dart';
import 'package:e_commerce/core/widgets/product_image.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/admin/products/bloc/cubit/product_admin_cubit.dart';
import 'package:e_commerce/features/admin/products/page/product_form_page.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/home/bloc/state/home_state.dart';
import 'package:e_commerce/features/home/model/product_model.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';
import 'package:e_commerce/core/theme/app_input.dart';

class AdminProductsPage extends StatelessWidget {
  const AdminProductsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider<AdminProductsCubit>(
          create: (_) => AdminProductsCubit()..load(),
        ),
        BlocProvider<ProductAdminCubit>(create: (_) => ProductAdminCubit()),
      ],
      child: const _AdminProductsView(),
    );
  }
}

class _AdminProductsView extends StatefulWidget {
  const _AdminProductsView();

  @override
  State<_AdminProductsView> createState() => _AdminProductsViewState();
}

class _AdminProductsViewState extends State<_AdminProductsView> {
  final ScrollController _scroll = ScrollController();
  final TextEditingController _search = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients &&
          _scroll.position.pixels >= _scroll.position.maxScrollExtent - 300) {
        context.read<AdminProductsCubit>().loadMore();
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _scroll.dispose();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) context.read<AdminProductsCubit>().setSearch(value);
    });
  }

  void _refreshAll() {
    context.read<AdminProductsCubit>().refreshProducts();
    context.read<HomeCubit>().refreshProducts();
  }

  Future<void> _openForm([ProductModel? product]) async {
    await Get.to(() => ProductFormPage(product: product));
    if (mounted) _refreshAll();
  }

  Future<void> _delete(ProductModel product) async {
    final confirmed = await confirmDialog(
      context,
      title: 'Delete product',
      message: 'Delete "${product.name}" and its image?',
      confirmLabel: 'Delete',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    final result = await context.read<ProductAdminCubit>().delete(product.id);
    if (!mounted) return;
    if (result.ok) {
      AppSnack.show(context, 'Product deleted.');
      _refreshAll();
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('PRODUCTS'),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(),
        backgroundColor: AppColor.black,
        foregroundColor: AppColor.white,
        child: const Icon(Icons.add_rounded),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 6, 20, 8),
            child: TextField(
              controller: _search,
              onChanged: _onSearch,
              decoration: AppInput.decoration(
                hint: 'Search products',
                prefix: const Icon(Icons.search_rounded),
              ),
            ),
          ),
          Expanded(
            child: BlocBuilder<AdminProductsCubit, HomeState>(
              builder: (context, state) {
                if (state is HomeInitial || state is HomeLoading) {
                  return const LoadingView();
                }
                if (state is HomeError) {
                  return ErrorView(
                    message: state.message,
                    onRetry: () => context.read<AdminProductsCubit>().load(),
                  );
                }
                final success = state as HomeSuccess;
                if (success.products.isEmpty) {
                  return const EmptyView(
                    icon: Icons.inventory_2_outlined,
                    title: 'No products found',
                  );
                }
                return RefreshIndicator(
                  color: AppColor.accent,
                  onRefresh: context.read<AdminProductsCubit>().refreshProducts,
                  child: ListView.separated(
                    controller: _scroll,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(20, 4, 20, 90),
                    itemCount:
                        success.products.length + (success.loadingMore ? 1 : 0),
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      if (index >= success.products.length) {
                        return const Padding(
                          padding: EdgeInsets.all(12),
                          child: LoadingView(),
                        );
                      }
                      final product = success.products[index];
                      return _ProductRow(
                        product: product,
                        onEdit: () => _openForm(product),
                        onDelete: () => _delete(product),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _ProductRow extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  const _ProductRow({
    required this.product,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColor.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColor.border),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: SizedBox(
              height: 64,
              width: 64,
              child: ProductImage(url: product.image, name: product.name),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(
                  '${money(product.price)}  ·  cost ${money(product.cost)}',
                  style: const TextStyle(
                    color: AppColor.textSecondary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 6,
                  children: [
                    RoleBadge(
                      label: 'stock ${product.stock}',
                      color: product.inStock
                          ? AppColor.success
                          : AppColor.danger,
                    ),
                    if (product.category != null &&
                        product.category!.isNotEmpty)
                      RoleBadge(label: product.category!),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onEdit,
            icon: const Icon(Icons.edit_outlined),
          ),
          IconButton(
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline_rounded,
                color: AppColor.danger),
          ),
        ],
      ),
    );
  }
}
