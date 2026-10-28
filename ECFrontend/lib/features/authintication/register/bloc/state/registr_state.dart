import 'package:e_commerce/features/authintication/login/model/login_model.dart';

abstract class RegisterState {}

class RegisterInitial extends RegisterState {}

class RegisterLoading extends RegisterState {}

class RegisterSuccess extends RegisterState {
  final LoginModel session;

  RegisterSuccess(this.session);
}

class RegisterError extends RegisterState {
  final String message;

  RegisterError(this.message);
}
