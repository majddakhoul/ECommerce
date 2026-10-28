import 'package:e_commerce/core/utils/json_utils.dart';

class ProductModel {
  final int id;
  final String name;
  final String? description;
  final double price;
  final double cost;
  final int stock;
  final String? category;
  final String? image;
  final DateTime? createdAt;

  const ProductModel({
    required this.id,
    required this.name,
    this.description,
    required this.price,
    this.cost = 0,
    this.stock = 0,
    this.category,
    this.image,
    this.createdAt,
  });

  bool get inStock => stock > 0;

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: toInt(json['id']),
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      price: toDouble(json['price']),
      cost: toDouble(json['cost']),
      stock: toInt(json['stock']),
      category: json['category']?.toString(),
      image: json['image']?.toString(),
      createdAt: toDate(json['created_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'description': description,
      'price': price,
      'cost': cost,
      'stock': stock,
      'category': category,
      'image': image,
    };
  }
}
