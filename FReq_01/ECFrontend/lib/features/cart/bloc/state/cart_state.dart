import 'package:e_commerce/features/cart/model/cart_model.dart';

abstract class CartState {}

class CartInitial extends CartState {}

class CartLoading extends CartState {}

class CartLoaded extends CartState {
  final CartModel cart;
  final bool busy;

  CartLoaded(this.cart, {this.busy = false});
}

class CartError extends CartState {
  final String message;

  CartError(this.message);
}
