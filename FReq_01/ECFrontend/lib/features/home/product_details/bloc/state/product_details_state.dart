import 'package:e_commerce/features/home/model/product_model.dart';

abstract class ProductDetailsState {
  final ProductModel product;

  ProductDetailsState(this.product);
}

class ProductDetailsSuccess extends ProductDetailsState {
  final bool refreshing;

  ProductDetailsSuccess(super.product, {this.refreshing = false});
}

class ProductDetailsError extends ProductDetailsState {
  final String message;

  ProductDetailsError(super.product, this.message);
}
