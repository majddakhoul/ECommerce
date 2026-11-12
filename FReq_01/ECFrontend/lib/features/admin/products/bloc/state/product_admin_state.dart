import 'package:e_commerce/features/home/model/product_model.dart';

abstract class ProductAdminState {}

class ProductAdminInitial extends ProductAdminState {}

class ProductAdminSaving extends ProductAdminState {}

class ProductAdminSaved extends ProductAdminState {
  final ProductModel product;
  final String message;

  ProductAdminSaved(this.product, this.message);
}

class ProductAdminError extends ProductAdminState {
  final String message;

  ProductAdminError(this.message);
}
