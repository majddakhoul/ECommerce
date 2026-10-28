import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/orders/bloc/cubit/orders_cubit.dart';
import 'package:e_commerce/features/orders/bloc/state/orders_state.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';
import 'package:e_commerce/features/orders/page/order_details_page.dart';
import 'package:e_commerce/features/orders/widget/order_card.dart';

class OrdersListView extends StatefulWidget {
  final bool showUser;

  const OrdersListView({super.key, this.showUser = false});

  @override
  State<OrdersListView> createState() => _OrdersListViewState();
}

class _OrdersListViewState extends State<OrdersListView> {
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    final cubit = context.read<OrdersCubit>();
    if (cubit.state is OrdersInitial) cubit.load();
    _scroll.addListener(() {
      if (_scroll.hasClients &&
          _scroll.position.pixels >= _scroll.position.maxScrollExtent - 300) {
        context.read<OrdersCubit>().loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _open(OrderModel order) async {
    final cubit = context.read<OrdersCubit>();
    await Get.to(() => OrderDetailsPage(orderId: order.id));
    cubit.load();
  }

  @override
  Widget build(BuildContext context) {
    final cubit = context.read<OrdersCubit>();

    return Column(
      children: [
        BlocBuilder<OrdersCubit, OrdersState>(
          builder: (context, state) {
            return SizedBox(
              height: 52,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding:
                    const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                children: [
                  _chip('All', cubit.status == null,
                      () => cubit.setStatus(null)),
                  for (final status in OrderStatus.all)
                    _chip(
                      capitalize(status),
                      cubit.status == status,
                      () => cubit.setStatus(status),
                    ),
                ],
              ),
            );
          },
        ),
        Expanded(
          child: BlocBuilder<OrdersCubit, OrdersState>(
            builder: (context, state) {
              if (state is OrdersInitial || state is OrdersLoading) {
                return const LoadingView();
              }
              if (state is OrdersError) {
                return ErrorView(
                  message: state.message,
                  onRetry: () => context.read<OrdersCubit>().load(),
                );
              }
              final success = state as OrdersSuccess;

              if (success.orders.isEmpty) {
                return RefreshIndicator(
                  color: AppColor.accent,
                  onRefresh: context.read<OrdersCubit>().load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      SizedBox(
                        height: MediaQuery.of(context).size.height * 0.5,
                        child: const EmptyView(
                          icon: Icons.receipt_long_outlined,
                          title: 'No orders yet',
                          subtitle: 'Orders will show up here.',
                        ),
                      ),
                    ],
                  ),
                );
              }

              return RefreshIndicator(
                color: AppColor.accent,
                onRefresh: context.read<OrdersCubit>().load,
                child: ListView.separated(
                  controller: _scroll,
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                  itemCount: success.orders.length + (success.loadingMore ? 1 : 0),
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    if (index >= success.orders.length) {
                      return const Padding(
                        padding: EdgeInsets.all(12),
                        child: LoadingView(),
                      );
                    }
                    final order = success.orders[index];
                    return OrderCard(
                      order: order,
                      showUser: widget.showUser,
                      onTap: () => _open(order),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
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
