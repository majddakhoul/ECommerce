import 'package:e_commerce/core/utils/json_utils.dart';

class CartItemModel {
  final int productId;
  final String name;
  final double price;
  final int quantity;
  final double subtotal;
  final int availableStock;

  const CartItemModel({
    required this.productId,
    required this.name,
    required this.price,
    required this.quantity,
    required this.subtotal,
    required this.availableStock,
  });

  factory CartItemModel.fromJson(Map<String, dynamic> json) {
    return CartItemModel(
      productId: toInt(json['product_id']),
      name: json['name']?.toString() ?? '',
      price: toDouble(json['price']),
      quantity: toInt(json['quantity']),
      subtotal: toDouble(json['subtotal']),
      availableStock: toInt(json['available_stock']),
    );
  }
}

class CartModel {
  final List<CartItemModel> items;
  final int totalQuantity;
  final double totalPrice;

  const CartModel({
    this.items = const [],
    this.totalQuantity = 0,
    this.totalPrice = 0,
  });

  bool get isEmpty => items.isEmpty;

  factory CartModel.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    return CartModel(
      items: raw is List
          ? raw
              .whereType<Map>()
              .map((e) => CartItemModel.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : <CartItemModel>[],
      totalQuantity: toInt(json['total_quantity']),
      totalPrice: toDouble(json['total_price']),
    );
  }
}
