import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';
import 'package:e_commerce/features/authintication/login/bloc/login_state/login_state.dart';
import 'package:e_commerce/features/authintication/login/model/login_model.dart';

class LoginCubit extends Cubit<LoginState> {
  LoginCubit() : super(LoginInitial());

  Future<void> login(String email, String password) async {
    emit(LoginLoading());
    try {
      final response = await Api.post(
        ApiConstants.login,
        data: {'email': email.trim(), 'password': password}
      );
      final session = LoginModel.fromJson(response.map);
      await SecureStorageService().saveToken(session.accessToken);
      emit(LoginSuccess(session));
    } catch (e) {
      emit(LoginError(ApiException.from(e).displayMessage));
    }
  }
}
