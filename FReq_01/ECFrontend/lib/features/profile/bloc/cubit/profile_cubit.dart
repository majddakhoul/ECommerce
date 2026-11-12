import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';
import 'package:e_commerce/features/authintication/login/model/login_model.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';
import 'package:e_commerce/features/profile/bloc/state/profile_state.dart';

class ProfileCubit extends Cubit<ProfileState> {
  ProfileCubit() : super(ProfileInitial());

  Future<void> updateProfile({
    required String name,
    required String email,
  }) async {
    emit(ProfileSaving());
    try {
      final response = await Api.put(
        ApiConstants.me,
        data: {'name': name.trim(), 'email': email.trim()},
      );
      emit(ProfileSaved(UserModel.fromJson(response.map), 'Profile updated.'));
    } catch (e) {
      emit(ProfileError(ApiException.from(e).displayMessage));
    }
  }

  Future<void> changePassword({
    required String current,
    required String password,
    required String confirmation,
  }) async {
    emit(ProfileSaving());
    try {
      final response = await Api.put(
        ApiConstants.password,
        data: {
          'current_password': current,
          'password': password,
          'password_confirmation': confirmation,
        },
      );
      final session = LoginModel.fromJson(response.map);
      if (session.accessToken.isNotEmpty) {
        await SecureStorageService().saveToken(session.accessToken);
      }
      emit(ProfileSaved(session.user, 'Password changed.'));
    } catch (e) {
      emit(ProfileError(ApiException.from(e).displayMessage));
    }
  }
}
