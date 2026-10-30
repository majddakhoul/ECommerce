import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/features/cart/bloc/state/cart_state.dart';
import 'package:e_commerce/features/cart/model/cart_model.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';

class CartCubit extends Cubit<CartState> {
  CartCubit() : super(CartInitial());

  CartModel get cart {
    final current = state;
    return current is CartLoaded ? current.cart : const CartModel();
  }

  int get count => cart.totalQuantity;

  void reset() => emit(CartInitial());

  Future<void> load() async {
    if (state is! CartLoaded) emit(CartLoading());
    try {
      final response = await Api.get(ApiConstants.cart);
      emit(CartLoaded(CartModel.fromJson(response.map)));
    } catch (e) {
      if (state is CartLoaded) return;
      emit(CartError(ApiException.from(e).displayMessage));
    }
  }

  Future<ActionResult<CartModel>> add(int productId, int quantity) {
    return _mutate(
      () => Api.post(
        ApiConstants.cartItems,
        data: {'product_id': productId, 'quantity': quantity},
      ),
    );
  }

  Future<ActionResult<CartModel>> setQuantity(int productId, int quantity) {
    return _mutate(
      () => Api.put(
        ApiConstants.cartItem(productId),
        data: {'quantity': quantity},
      ),
    );
  }

  Future<ActionResult<CartModel>> remove(int productId) {
    return _mutate(() => Api.delete(ApiConstants.cartItem(productId)));
  }

  Future<ActionResult<void>> clear() async {
    final previous = cart;
    emit(CartLoaded(previous, busy: true));
    try {
      await Api.delete(ApiConstants.cart);
      emit(CartLoaded(const CartModel()));
      return ActionResult<void>.success();
    } catch (e) {
      emit(CartLoaded(previous));
      return ActionResult<void>.failure(ApiException.from(e).displayMessage);
    }
  }

  Future<ActionResult<OrderModel>> checkout() async {
    final previous = cart;
    emit(CartLoaded(previous, busy: true));
    try {
      final response = await Api.post(ApiConstants.cartCheckout);
      emit(CartLoaded(const CartModel()));
      return ActionResult<OrderModel>.success(
        OrderModel.fromJson(response.map),
      );
    } catch (e) {
      emit(CartLoaded(previous));
      return ActionResult<OrderModel>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }

  Future<ActionResult<CartModel>> _mutate(
    Future<ApiResponse> Function() request,
  ) async {
    final previous = cart;
    emit(CartLoaded(previous, busy: true));
    try {
      final response = await request();
      final updated = CartModel.fromJson(response.map);
      emit(CartLoaded(updated));
      return ActionResult<CartModel>.success(updated);
    } catch (e) {
      emit(CartLoaded(previous));
      return ActionResult<CartModel>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }
}
