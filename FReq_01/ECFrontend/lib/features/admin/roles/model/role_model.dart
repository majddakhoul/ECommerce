import 'package:e_commerce/core/utils/json_utils.dart';

class RoleModel {
  final int id;
  final String name;
  final int usersCount;

  const RoleModel({
    required this.id,
    required this.name,
    this.usersCount = 0,
  });

  bool get isBuiltIn => name == 'admin' || name == 'user';

  factory RoleModel.fromJson(Map<String, dynamic> json) {
    return RoleModel(
      id: toInt(json['id']),
      name: json['name']?.toString() ?? '',
      usersCount: toInt(json['users_count']),
    );
  }
}
