import 'package:e_commerce/core/utils/json_utils.dart';

class UserModel {
  final int id;
  final String name;
  final String email;
  final bool isActive;
  final bool isLocked;
  final List<String> roles;
  final DateTime? lastLoginAt;
  final DateTime? createdAt;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.isActive = true,
    this.isLocked = false,
    this.roles = const [],
    this.lastLoginAt,
    this.createdAt,
  });

  bool get isAdmin => roles.contains('admin');

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final rawRoles = json['roles'];
    return UserModel(
      id: toInt(json['id']),
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      isActive: json['is_active'] == null ? true : toBool(json['is_active']),
      isLocked: toBool(json['is_locked']),
      roles: rawRoles is List ? rawRoles.map((e) => e.toString()).toList() : [],
      lastLoginAt: toDate(json['last_login_at']),
      createdAt: toDate(json['created_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'is_active': isActive,
      'is_locked': isLocked,
      'roles': roles,
    };
  }
}
