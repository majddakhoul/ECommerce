import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/custom_from_field.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/profile/bloc/cubit/profile_cubit.dart';
import 'package:e_commerce/features/profile/bloc/state/profile_state.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class ChangePasswordPage extends StatelessWidget {
  const ChangePasswordPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => ProfileCubit(),
      child: const _ChangePasswordView(),
    );
  }
}

class _ChangePasswordView extends StatefulWidget {
  const _ChangePasswordView();

  @override
  State<_ChangePasswordView> createState() => _ChangePasswordViewState();
}

class _ChangePasswordViewState extends State<_ChangePasswordView> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    context.read<ProfileCubit>().changePassword(
          current: _current.text,
          password: _password.text,
          confirmation: _confirmation.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('CHANGE PASSWORD'),
      body: BlocConsumer<ProfileCubit, ProfileState>(
        listener: (context, state) {
          if (state is ProfileSaved) {
            context.read<AuthCubit>().updateUser(state.user);
            AppSnack.show(context, state.message);
            Get.back();
          } else if (state is ProfileError) {
            AppSnack.error(context, state.message);
          }
        },
        builder: (context, state) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                children: [
                  CustomFormField(
                    controller: _current,
                    label: 'Current password',
                    icon: Icons.lock_outline_rounded,
                    isPassword: true,
                    textInputAction: TextInputAction.next,
                    validator: (v) => Validators.notEmpty(v, 'Current password'),
                  ),
                  const SizedBox(height: 18),
                  CustomFormField(
                    controller: _password,
                    label: 'New password',
                    icon: Icons.lock_reset_rounded,
                    isPassword: true,
                    textInputAction: TextInputAction.next,
                    validator: Validators.password,
                  ),
                  const SizedBox(height: 18),
                  CustomFormField(
                    controller: _confirmation,
                    label: 'Confirm new password',
                    icon: Icons.lock_reset_rounded,
                    isPassword: true,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _submit(),
                    validator: (v) =>
                        v != _password.text ? 'Passwords do not match' : null,
                  ),
                  const SizedBox(height: 28),
                  CustomButton(
                    label: 'Update password',
                    loading: state is ProfileSaving,
                    onPressed: _submit,
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
