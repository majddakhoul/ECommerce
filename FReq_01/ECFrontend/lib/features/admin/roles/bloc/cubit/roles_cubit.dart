import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/features/admin/roles/bloc/state/roles_state.dart';
import 'package:e_commerce/features/admin/roles/model/role_model.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';

class RolesCubit extends Cubit<RolesState> {
  RolesCubit() : super(RolesInitial());

  List<RoleModel> get roles {
    final current = state;
    return current is RolesSuccess ? current.roles : const [];
  }

  Future<void> load() async {
    if (state is! RolesSuccess) emit(RolesLoading());
    try {
      final response = await Api.get(ApiConstants.roles);
      emit(RolesSuccess(response.list.map(RoleModel.fromJson).toList()));
    } catch (e) {
      if (state is RolesSuccess) return;
      emit(RolesError(ApiException.from(e).displayMessage));
    }
  }

  Future<ActionResult<RoleModel>> create(String name) {
    return _mutate(() async {
      final response = await Api.post(ApiConstants.roles, data: {'name': name});
      return RoleModel.fromJson(response.map);
    });
  }

  Future<ActionResult<RoleModel>> rename(int id, String name) {
    return _mutate(() async {
      final response =
          await Api.put(ApiConstants.role(id), data: {'name': name});
      return RoleModel.fromJson(response.map);
    });
  }

  Future<ActionResult<void>> delete(int id) async {
    final result = await _mutate<void>(() async {
      await Api.delete(ApiConstants.role(id));
    });
    return result;
  }

  Future<ActionResult<List<UserModel>>> usersOf(int roleId) async {
    try {
      final response = await Api.get(
        ApiConstants.roleUsers(roleId),
        query: {'per_page': 50},
      );
      return ActionResult<List<UserModel>>.success(
        response.list.map(UserModel.fromJson).toList(),
      );
    } catch (e) {
      return ActionResult<List<UserModel>>.failure(
        ApiException.from(e).displayMessage,
      );
    }
  }

  Future<ActionResult<T>> _mutate<T>(Future<T> Function() action) async {
    final previous = roles;
    emit(RolesSuccess(previous, busy: true));
    try {
      final data = await action();
      final response = await Api.get(ApiConstants.roles);
      emit(RolesSuccess(response.list.map(RoleModel.fromJson).toList()));
      return ActionResult<T>.success(data);
    } catch (e) {
      emit(RolesSuccess(previous));
      return ActionResult<T>.failure(ApiException.from(e).displayMessage);
    }
  }
}
