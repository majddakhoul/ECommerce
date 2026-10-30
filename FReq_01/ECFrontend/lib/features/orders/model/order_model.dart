import 'package:e_commerce/core/utils/json_utils.dart';

class OrderStatus {
  OrderStatus._();

  static const String pending = 'pending';
  static const String confirmed = 'confirmed';
  static const String processing = 'processing';
  static const String delivered = 'delivered';
  static const String cancelled = 'cancelled';

  static const List<String> all = [
    pending,
    confirmed,
    processing,
    delivered,
    cancelled,
  ];

  static const Map<String, List<String>> transitions = {
    pending: [confirmed, processing, cancelled],
    confirmed: [processing, delivered, cancelled],
    processing: [delivered, cancelled],
    delivered: [],
    cancelled: [],
  };

  static List<String> next(String status) => transitions[status] ?? const [];

  static bool canCancel(String status) => next(status).contains(cancelled);
}

class OrderItemModel {
  final int id;
  final int? productId;
  final String productName;
  final int quantity;
  final double price;
  final double subtotal;

  const OrderItemModel({
    required this.id,
    this.productId,
    required this.productName,
    required this.quantity,
    required this.price,
    required this.subtotal,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> json) {
    return OrderItemModel(
      id: toInt(json['id']),
      productId: json['product_id'] == null ? null : toInt(json['product_id']),
      productName: json['product_name']?.toString() ?? '',
      quantity: toInt(json['quantity']),
      price: toDouble(json['price']),
      subtotal: toDouble(json['subtotal']),
    );
  }
}

class OrderUserModel {
  final int id;
  final String name;
  final String email;

  const OrderUserModel({
    required this.id,
    required this.name,
    required this.email,
  });

  factory OrderUserModel.fromJson(Map<String, dynamic> json) {
    return OrderUserModel(
      id: toInt(json['id']),
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
    );
  }
}

class OrderModel {
  final int id;
  final int userId;
  final OrderUserModel? user;
  final String status;
  final double totalPrice;
  final List<OrderItemModel> items;
  final DateTime? deliveredAt;
  final DateTime? createdAt;

  const OrderModel({
    required this.id,
    required this.userId,
    this.user,
    required this.status,
    required this.totalPrice,
    this.items = const [],
    this.deliveredAt,
    this.createdAt,
  });

  int get itemsCount => items.fold(0, (sum, item) => sum + item.quantity);

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final rawUser = json['user'];
    return OrderModel(
      id: toInt(json['id']),
      userId: toInt(json['user_id']),
      user: rawUser is Map
          ? OrderUserModel.fromJson(Map<String, dynamic>.from(rawUser))
          : null,
      status: json['status']?.toString() ?? OrderStatus.pending,
      totalPrice: toDouble(json['total_price']),
      items: rawItems is List
          ? rawItems
              .whereType<Map>()
              .map((e) => OrderItemModel.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : <OrderItemModel>[],
      deliveredAt: toDate(json['delivered_at']),
      createdAt: toDate(json['created_at']),
    );
  }
}
