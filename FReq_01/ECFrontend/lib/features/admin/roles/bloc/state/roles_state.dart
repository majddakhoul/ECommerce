import 'package:e_commerce/features/admin/roles/model/role_model.dart';

abstract class RolesState {}

class RolesInitial extends RolesState {}

class RolesLoading extends RolesState {}

class RolesSuccess extends RolesState {
  final List<RoleModel> roles;
  final bool busy;

  RolesSuccess(this.roles, {this.busy = false});
}

class RolesError extends RolesState {
  final String message;

  RolesError(this.message);
}
