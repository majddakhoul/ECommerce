import 'package:e_commerce/features/authintication/register/model/user_model.dart';

class LoginModel {
  final UserModel user;
  final String accessToken;
  final String tokenType;
  final int expiresIn;

  const LoginModel({
    required this.user,
    required this.accessToken,
    this.tokenType = 'bearer',
    this.expiresIn = 0,
  });

  factory LoginModel.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    final expires = json['expires_in'];
    return LoginModel(
      user: UserModel.fromJson(
        user is Map ? Map<String, dynamic>.from(user) : <String, dynamic>{},
      ),
      accessToken: json['access_token']?.toString() ?? '',
      tokenType: json['token_type']?.toString() ?? 'bearer',
      expiresIn: expires is num ? expires.toInt() : 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'user': user.toJson(),
      'access_token': accessToken,
      'token_type': tokenType,
      'expires_in': expiresIn,
    };
  }
}
