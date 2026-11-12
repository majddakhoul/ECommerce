import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';
import 'package:e_commerce/features/authintication/login/model/login_model.dart';
import 'package:e_commerce/features/authintication/register/bloc/state/registr_state.dart';

class RegisterCubit extends Cubit<RegisterState> {
  RegisterCubit() : super(RegisterInitial());

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String confirmation,
  }) async {
    emit(RegisterLoading());
    try {
      final response = await Api.post(
        ApiConstants.register,
        data: {
          'name': name.trim(),
          'email': email.trim(),
          'password': password,
          'password_confirmation': confirmation,
        },
      );
      final session = LoginModel.fromJson(response.map);
      await SecureStorageService().saveToken(session.accessToken);
      emit(RegisterSuccess(session));
    } catch (e) {
      emit(RegisterError(ApiException.from(e).displayMessage));
    }
  }
}
