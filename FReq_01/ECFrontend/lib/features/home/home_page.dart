import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/cart/bloc/cubit/cart_cubit.dart';
import 'package:e_commerce/features/cart/bloc/state/cart_state.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/home/bloc/state/home_state.dart';
import 'package:e_commerce/features/home/model/product_model.dart';
import 'package:e_commerce/features/home/product_details/page/product_details_page.dart';
import 'package:e_commerce/features/home/widget/hero_card.dart';
import 'package:e_commerce/features/home/widget/product_card.dart';
import 'package:e_commerce/features/main_navigation/nav_controller.dart';
import 'package:e_commerce/core/theme/app_input.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final ScrollController _scroll = ScrollController();
  final TextEditingController _search = TextEditingController();
  final PageController _hero = PageController(viewportFraction: 0.86);
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    context.read<HomeCubit>().load();
    _scroll.addListener(() {
      if (_scroll.hasClients &&
          _scroll.position.pixels >= _scroll.position.maxScrollExtent - 300) {
        context.read<HomeCubit>().loadMore();
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _scroll.dispose();
    _search.dispose();
    _hero.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) context.read<HomeCubit>().setSearch(value);
    });
  }

  void _openProduct(ProductModel product) {
    Get.to(() => ProductDetailsPage(product: product));
  }

  void _openFilter() {
    final cubit = context.read<HomeCubit>();
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColor.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) {
        bool inStock = cubit.inStockOnly;
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Filter',
                    style:
                        TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('In stock only'),
                    value: inStock,
                    onChanged: (value) {
                      setSheetState(() => inStock = value);
                      cubit.setInStockOnly(value);
                    },
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
              child: Row(
                children: [
                  IconButton(
                    onPressed: _openFilter,
                    icon: const Icon(Icons.tune_rounded),
                  ),
                  const Expanded(
                    child: Text(
                      'ECOMMERCE',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        letterSpacing: 3,
                        fontSize: 15,
                      ),
                    ),
                  ),
                  BlocBuilder<CartCubit, CartState>(
                    builder: (context, state) {
                      final count = context.read<CartCubit>().count;
                      return IconButton(
                        onPressed: () =>
                            NavController.index.value = NavController.cart,
                        icon: Badge(
                          isLabelVisible: count > 0,
                          label: Text('$count'),
                          backgroundColor: AppColor.accent,
                          child: const Icon(Icons.shopping_bag_outlined),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 6),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: AppColor.accent.withValues(alpha: 0.12),
                      blurRadius: 18,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: TextField(
                  controller: _search,
                  onChanged: _onSearchChanged,
                  textInputAction: TextInputAction.search,
                  decoration: AppInput.decoration(
                    hint: 'Try search...',
                    borderless: true,
                    suffix: const Icon(
                      Icons.search_rounded,
                      color: AppColor.accent,
                    ),
                  ),
                ),
              ),
            ),
            Expanded(
              child: BlocBuilder<HomeCubit, HomeState>(
                builder: (context, state) {
                  if (state is HomeLoading || state is HomeInitial) {
                    return const LoadingView();
                  }
                  if (state is HomeError) {
                    return ErrorView(
                      message: state.message,
                      onRetry: () => context.read<HomeCubit>().load(),
                    );
                  }
                  return _buildContent(state as HomeSuccess);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(HomeSuccess state) {
    final cubit = context.read<HomeCubit>();
    final showHero = cubit.category == null &&
        cubit.search.isEmpty &&
        !cubit.inStockOnly &&
        state.products.isNotEmpty;
    final heroItems = state.products.take(5).toList();

    return RefreshIndicator(
      color: AppColor.accent,
      onRefresh: cubit.refreshProducts,
      child: CustomScrollView(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          if (state.refreshing)
            const SliverToBoxAdapter(
              child: LinearProgressIndicator(
                minHeight: 2,
                color: AppColor.accent,
                backgroundColor: AppColor.accentLight,
              ),
            ),
          if (showHero) ...[
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.fromLTRB(20, 14, 20, 10),
                child: Text(
                  'New In',
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: SizedBox(
                height: 360,
                child: PageView.builder(
                  controller: _hero,
                  padEnds: false,
                  itemCount: heroItems.length,
                  itemBuilder: (context, index) => Padding(
                    padding: EdgeInsets.only(
                      left: index == 0 ? 20 : 6,
                      right: 6,
                    ),
                    child: HeroCard(
                      product: heroItems[index],
                      onTap: () => _openProduct(heroItems[index]),
                    ),
                  ),
                ),
              ),
            ),
          ],
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 6),
              child: Text(
                cubit.category == null
                    ? 'Collection'
                    : capitalize(cubit.category!),
                style:
                    const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: SizedBox(
              height: 52,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding:
                    const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                children: [
                  _chip('See all', cubit.category == null,
                      () => cubit.setCategory(null)),
                  for (final category in state.categories)
                    _chip(
                      capitalize(category),
                      cubit.category == category,
                      () => cubit.setCategory(category),
                    ),
                ],
              ),
            ),
          ),
          if (state.products.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: EmptyView(
                icon: Icons.search_off_rounded,
                title: 'No products found',
                subtitle: 'Try a different search or category.',
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              sliver: SliverGrid.builder(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 18,
                  crossAxisSpacing: 14,
                  childAspectRatio: 0.72,
                ),
                itemCount: state.products.length,
                itemBuilder: (context, index) {
                  final product = state.products[index];
                  return ProductCard(
                    product: product,
                    onTap: () => _openProduct(product),
                  );
                },
              ),
            ),
          if (state.loadingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: 24),
                child: LoadingView(),
              ),
            ),
        ],
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        showCheckmark: false,
        onSelected: (_) => onTap(),
        labelStyle: TextStyle(
          fontWeight: FontWeight.w700,
          color: selected ? AppColor.white : AppColor.textPrimary,
        ),
      ),
    );
  }
}
