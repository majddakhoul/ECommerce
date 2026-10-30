import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/features/orders/bloc/state/orders_state.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';

class OrdersCubit extends Cubit<OrdersState> {
  final bool adminScope;

  OrdersCubit({this.adminScope = false}) : super(OrdersInitial());

  static const int _perPage = 10;

  String? status;

  int _requestId = 0;

  void reset() {
    _requestId++;
    status = null;
    emit(OrdersInitial());
  }

  Future<void> load() async {
    final id = ++_requestId;
    final current = state;
    if (current is OrdersSuccess) {
      emit(current.copyWith(refreshing: true));
    } else {
      emit(OrdersLoading());
    }
    try {
      final page = await _fetch(1);
      if (id != _requestId) return;
      emit(OrdersSuccess(orders: page.items, meta: page.meta));
    } catch (e) {
      if (id != _requestId) return;
      if (current is OrdersSuccess) {
        emit(current.copyWith(refreshing: false));
      } else {
        emit(OrdersError(ApiException.from(e).displayMessage));
      }
    }
  }

  Future<void> loadMore() async {
    final current = state;
    if (current is! OrdersSuccess) return;
    if (!current.meta.hasMore || current.loadingMore || current.refreshing) {
      return;
    }
    final id = _requestId;
    emit(current.copyWith(loadingMore: true));
    try {
      final page = await _fetch(current.meta.currentPage + 1);
      if (id != _requestId) return;
      emit(current.copyWith(
        orders: [...current.orders, ...page.items],
        meta: page.meta,
        loadingMore: false,
      ));
    } catch (_) {
      if (id != _requestId) return;
      emit(current.copyWith(loadingMore: false));
    }
  }

  Future<void> setStatus(String? value) {
    status = value;
    return load();
  }

  Future<ActionResult<OrderModel>> place(
    List<({int productId, int quantity})> items,
  ) async {
    try {
      final response = await Api.post(
        ApiConstants.orders,
        data: {
          'items': items
              .map((e) => {'product_id': e.productId, 'quantity': e.quantity})
              .toList(),
        },
      );
      return ActionResult<OrderModel>.success(
        OrderModel.fromJson(response.map),
      );
    } catch (e) {
      return ActionResult<OrderModel>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }

  Future<_OrderPage> _fetch(int page) async {
    final response = await Api.get(
      adminScope ? ApiConstants.orders : ApiConstants.myOrders,
      query: {
        'status': status,
        'per_page': _perPage,
        'page': page,
      },
    );
    return _OrderPage(
      items: response.list.map(OrderModel.fromJson).toList(),
      meta: response.meta ?? const PageMeta(),
    );
  }
}

class _OrderPage {
  final List<OrderModel> items;
  final PageMeta meta;

  _OrderPage({required this.items, required this.meta});
}
