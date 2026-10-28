import 'dart:async';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/dio_client.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';
import 'package:e_commerce/features/authintication/bloc/auth_state/auth_state.dart';
import 'package:e_commerce/features/authintication/login/model/login_model.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';

class AuthCubit extends Cubit<AuthState> {
  AuthCubit() : super(AuthInitial()) {
    _subscription = DioClient().onUnauthorized.listen((_) {
      if (state is AuthSuccess) emit(AuthUnauthenticated());
    });
  }

  StreamSubscription<void>? _subscription;

  UserModel? get user {
    final current = state;
    return current is AuthSuccess ? current.user : null;
  }

  bool get isAdmin => user?.isAdmin ?? false;

  Future<void> checkSession() async {
    emit(AuthLoading());
    final storage = SecureStorageService();
    final token = await storage.getToken();

    if (token == null || token.isEmpty) {
      emit(AuthUnauthenticated());
      return;
    }

    try {
      final me = await Api.get(ApiConstants.me);
      var current = UserModel.fromJson(me.map);

      try {
        final refreshed = await Api.post(ApiConstants.refresh);
        final session = LoginModel.fromJson(refreshed.map);
        if (session.accessToken.isNotEmpty) {
          await storage.saveToken(session.accessToken);
          current = session.user;
        }
      } catch (_) {}

      emit(AuthSuccess(current));
    } catch (e) {
      final error = ApiException.from(e);
      final status = error.statusCode;
      if (status == 401 || status == 403 || status == 423) {
        await storage.clearToken();
        emit(AuthUnauthenticated());
      } else {
        emit(AuthError(error.displayMessage));
      }
    }
  }

  void onAuthenticated(LoginModel session) {
    emit(AuthSuccess(session.user));
  }

  void updateUser(UserModel user) {
    emit(AuthSuccess(user));
  }

  Future<void> logout() async {
    try {
      await Api.post(ApiConstants.logout);
    } catch (_) {}
    await SecureStorageService().clearToken();
    emit(AuthUnauthenticated());
  }

  @override
  Future<void> close() {
    _subscription?.cancel();
    return super.close();
  }
}
