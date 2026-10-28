import 'package:e_commerce/features/orders/model/order_model.dart';

abstract class OrderDetailsState {}

class OrderDetailsLoading extends OrderDetailsState {}

class OrderDetailsLoaded extends OrderDetailsState {
  final OrderModel order;
  final bool busy;

  OrderDetailsLoaded(this.order, {this.busy = false});
}

class OrderDetailsError extends OrderDetailsState {
  final String message;

  OrderDetailsError(this.message);
}
