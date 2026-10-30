import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/custom_from_field.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/login/login_page.dart';
import 'package:e_commerce/features/authintication/register/bloc/cubit/register_cubit.dart';
import 'package:e_commerce/features/authintication/register/bloc/state/registr_state.dart';
import 'package:e_commerce/features/authintication/widget/auth_scaffold.dart';
import 'package:e_commerce/features/main_navigation/main_navigation_page.dart';

class RegisterPage extends StatelessWidget {
  const RegisterPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => RegisterCubit(),
      child: const _RegisterView(),
    );
  }
}

class _RegisterView extends StatefulWidget {
  const _RegisterView();

  @override
  State<_RegisterView> createState() => _RegisterViewState();
}

class _RegisterViewState extends State<_RegisterView> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    context.read<RegisterCubit>().register(
          name: _name.text,
          email: _email.text,
          password: _password.text,
          confirmation: _confirmation.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<RegisterCubit, RegisterState>(
      listener: (context, state) {
        if (state is RegisterSuccess) {
          context.read<AuthCubit>().onAuthenticated(state.session);
          Get.offAll(() => const MainNavigation());
        } else if (state is RegisterError) {
          AppSnack.error(context, state.message);
        }
      },
      builder: (context, state) {
        return AuthScaffold(
          title: 'Create account',
          subtitle: 'Join us and start shopping',
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomFormField(
                  controller: _name,
                  label: 'Name',
                  icon: Icons.person_outline_rounded,
                  textInputAction: TextInputAction.next,
                  validator: (v) => Validators.notEmpty(v, 'Name'),
                ),
                const SizedBox(height: 16),
                CustomFormField(
                  controller: _email,
                  label: 'Email',
                  icon: Icons.mail_outline_rounded,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  validator: Validators.email,
                ),
                const SizedBox(height: 16),
                CustomFormField(
                  controller: _password,
                  label: 'Password',
                  icon: Icons.lock_outline_rounded,
                  isPassword: true,
                  textInputAction: TextInputAction.next,
                  validator: Validators.password,
                ),
                const SizedBox(height: 16),
                CustomFormField(
                  controller: _confirmation,
                  label: 'Confirm password',
                  icon: Icons.lock_reset_rounded,
                  isPassword: true,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _submit(),
                  validator: (v) =>
                      v != _password.text ? 'Passwords do not match' : null,
                ),
                const SizedBox(height: 26),
                CustomButton(
                  label: 'Sign up',
                  loading: state is RegisterLoading,
                  onPressed: _submit,
                ),
                const Spacer(),
                const SizedBox(height: 20),
                Center(
                  child: GestureDetector(
                    onTap: () => Get.off(() => const LoginPage()),
                    child: const Text.rich(
                      TextSpan(
                        text: 'Already have an account? ',
                        style: TextStyle(color: AppColor.textSecondary),
                        children: [
                          TextSpan(
                            text: 'Log in',
                            style: TextStyle(
                              color: AppColor.accent,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
