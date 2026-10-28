import 'package:e_commerce/features/authintication/login/model/login_model.dart';

abstract class LoginState {}

class LoginInitial extends LoginState {}

class LoginLoading extends LoginState {}

class LoginSuccess extends LoginState {
  final LoginModel session;

  LoginSuccess(this.session);
}

class LoginError extends LoginState {
  final String message;

  LoginError(this.message);
}
