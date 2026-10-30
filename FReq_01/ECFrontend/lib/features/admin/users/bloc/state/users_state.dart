import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';

abstract class UsersState {}

class UsersInitial extends UsersState {}

class UsersLoading extends UsersState {}

class UsersSuccess extends UsersState {
  final List<UserModel> users;
  final PageMeta meta;
  final bool refreshing;
  final bool loadingMore;

  UsersSuccess({
    required this.users,
    required this.meta,
    this.refreshing = false,
    this.loadingMore = false,
  });

  UsersSuccess copyWith({
    List<UserModel>? users,
    PageMeta? meta,
    bool? refreshing,
    bool? loadingMore,
  }) {
    return UsersSuccess(
      users: users ?? this.users,
      meta: meta ?? this.meta,
      refreshing: refreshing ?? this.refreshing,
      loadingMore: loadingMore ?? this.loadingMore,
    );
  }
}

class UsersError extends UsersState {
  final String message;

  UsersError(this.message);
}
