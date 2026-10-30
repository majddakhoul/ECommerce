import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';

abstract class OrdersState {}

class OrdersInitial extends OrdersState {}

class OrdersLoading extends OrdersState {}

class OrdersSuccess extends OrdersState {
  final List<OrderModel> orders;
  final PageMeta meta;
  final bool refreshing;
  final bool loadingMore;

  OrdersSuccess({
    required this.orders,
    required this.meta,
    this.refreshing = false,
    this.loadingMore = false,
  });

  OrdersSuccess copyWith({
    List<OrderModel>? orders,
    PageMeta? meta,
    bool? refreshing,
    bool? loadingMore,
  }) {
    return OrdersSuccess(
      orders: orders ?? this.orders,
      meta: meta ?? this.meta,
      refreshing: refreshing ?? this.refreshing,
      loadingMore: loadingMore ?? this.loadingMore,
    );
  }
}

class OrdersError extends OrdersState {
  final String message;

  OrdersError(this.message);
}
