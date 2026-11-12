import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/features/orders/bloc/state/order_details_state.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';

class OrderDetailsCubit extends Cubit<OrderDetailsState> {
  final int orderId;

  OrderDetailsCubit(this.orderId) : super(OrderDetailsLoading());

  OrderModel? get order {
    final current = state;
    return current is OrderDetailsLoaded ? current.order : null;
  }

  Future<void> load() async {
    if (state is! OrderDetailsLoaded) emit(OrderDetailsLoading());
    try {
      final response = await Api.get(ApiConstants.order(orderId));
      emit(OrderDetailsLoaded(OrderModel.fromJson(response.map)));
    } catch (e) {
      if (state is OrderDetailsLoaded) return;
      emit(OrderDetailsError(ApiException.from(e).displayMessage));
    }
  }

  Future<ActionResult<OrderModel>> cancel() {
    return _mutate(() => Api.post(ApiConstants.cancelOrder(orderId)));
  }

  Future<ActionResult<OrderModel>> updateStatus(String status) {
    return _mutate(
      () => Api.put(
        ApiConstants.orderStatus(orderId),
        data: {'status': status},
      ),
    );
  }

  Future<ActionResult<void>> delete() async {
    final previous = order;
    if (previous != null) emit(OrderDetailsLoaded(previous, busy: true));
    try {
      await Api.delete(ApiConstants.order(orderId));
      return ActionResult<void>.success();
    } catch (e) {
      if (previous != null) emit(OrderDetailsLoaded(previous));
      return ActionResult<void>.failure(ApiException.from(e).displayMessage);
    }
  }

  Future<ActionResult<OrderModel>> _mutate(
    Future<ApiResponse> Function() request,
  ) async {
    final previous = order;
    if (previous != null) emit(OrderDetailsLoaded(previous, busy: true));
    try {
      final response = await request();
      final updated = OrderModel.fromJson(response.map);
      emit(OrderDetailsLoaded(updated));
      return ActionResult<OrderModel>.success(updated);
    } catch (e) {
      if (previous != null) emit(OrderDetailsLoaded(previous));
      return ActionResult<OrderModel>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }
}
