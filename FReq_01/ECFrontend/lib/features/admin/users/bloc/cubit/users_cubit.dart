import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/features/admin/roles/model/role_model.dart';
import 'package:e_commerce/features/admin/users/bloc/state/users_state.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';

class UsersCubit extends Cubit<UsersState> {
  UsersCubit() : super(UsersInitial());

  static const int _perPage = 15;

  String search = '';
  String? role;
  bool? isActive;

  int _requestId = 0;

  Future<void> load() async {
    final id = ++_requestId;
    final current = state;
    if (current is UsersSuccess) {
      emit(current.copyWith(refreshing: true));
    } else {
      emit(UsersLoading());
    }
    try {
      final page = await _fetch(1);
      if (id != _requestId) return;
      emit(UsersSuccess(users: page.items, meta: page.meta));
    } catch (e) {
      if (id != _requestId) return;
      if (current is UsersSuccess) {
        emit(current.copyWith(refreshing: false));
      } else {
        emit(UsersError(ApiException.from(e).displayMessage));
      }
    }
  }

  Future<void> loadMore() async {
    final current = state;
    if (current is! UsersSuccess) return;
    if (!current.meta.hasMore || current.loadingMore || current.refreshing) {
      return;
    }
    final id = _requestId;
    emit(current.copyWith(loadingMore: true));
    try {
      final page = await _fetch(current.meta.currentPage + 1);
      if (id != _requestId) return;
      emit(current.copyWith(
        users: [...current.users, ...page.items],
        meta: page.meta,
        loadingMore: false,
      ));
    } catch (_) {
      if (id != _requestId) return;
      emit(current.copyWith(loadingMore: false));
    }
  }

  Future<void> setSearch(String value) {
    search = value.trim();
    return load();
  }

  Future<void> setRole(String? value) {
    role = value;
    return load();
  }

  Future<void> setActive(bool? value) {
    isActive = value;
    return load();
  }

  Future<ActionResult<UserModel>> create({
    required String name,
    required String email,
    required String password,
    required bool isActive,
    String? role,
  }) {
    return _run(
      () => Api.post(
        ApiConstants.users,
        data: {
          'name': name.trim(),
          'email': email.trim(),
          'password': password,
          'is_active': isActive,
          if (role != null) 'role': role,
        },
      ),
    );
  }

  Future<ActionResult<UserModel>> update(
    int id, {
    required String name,
    required String email,
    String? password,
    required bool isActive,
  }) {
    return _run(
      () => Api.put(
        ApiConstants.user(id),
        data: {
          'name': name.trim(),
          'email': email.trim(),
          if (password != null && password.isNotEmpty) 'password': password,
          'is_active': isActive,
        },
      ),
    );
  }

  Future<ActionResult<UserModel>> show(int id) {
    return _run(() => Api.get(ApiConstants.user(id)));
  }

  Future<ActionResult<UserModel>> unlock(int id) {
    return _run(() => Api.post(ApiConstants.unlockUser(id)));
  }

  Future<ActionResult<UserModel>> assignRole(int userId, int roleId) {
    return _run(
      () => Api.post(ApiConstants.userRoles(userId), data: {'role_id': roleId}),
    );
  }

  Future<ActionResult<UserModel>> removeRole(int userId, int roleId) {
    return _run(() => Api.delete(ApiConstants.userRole(userId, roleId)));
  }

  Future<ActionResult<void>> delete(int id) async {
    try {
      await Api.delete(ApiConstants.user(id));
      return ActionResult<void>.success();
    } catch (e) {
      return ActionResult<void>.failure(ApiException.from(e).displayMessage);
    }
  }

  Future<ActionResult<List<RoleModel>>> availableRoles() async {
    try {
      final response = await Api.get(ApiConstants.roles);
      return ActionResult<List<RoleModel>>.success(
        response.list.map(RoleModel.fromJson).toList(),
      );
    } catch (e) {
      return ActionResult<List<RoleModel>>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }

  Future<ActionResult<UserModel>> _run(
    Future<ApiResponse> Function() request,
  ) async {
    try {
      final response = await request();
      return ActionResult<UserModel>.success(UserModel.fromJson(response.map));
    } catch (e) {
      return ActionResult<UserModel>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }

  Future<_UserPage> _fetch(int page) async {
    final response = await Api.get(
      ApiConstants.users,
      query: {
        'search': search,
        'role': role,
        'is_active': isActive == null ? null : (isActive! ? 1 : 0),
        'per_page': _perPage,
        'page': page,
      },
    );
    return _UserPage(
      items: response.list.map(UserModel.fromJson).toList(),
      meta: response.meta ?? const PageMeta(),
    );
  }
}

class _UserPage {
  final List<UserModel> items;
  final PageMeta meta;

  _UserPage({required this.items, required this.meta});
}
