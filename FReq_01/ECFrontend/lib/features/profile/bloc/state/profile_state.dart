import 'package:e_commerce/features/authintication/register/model/user_model.dart';

abstract class ProfileState {}

class ProfileInitial extends ProfileState {}

class ProfileSaving extends ProfileState {}

class ProfileSaved extends ProfileState {
  final UserModel user;
  final String message;

  ProfileSaved(this.user, this.message);
}

class ProfileError extends ProfileState {
  final String message;

  ProfileError(this.message);
}
