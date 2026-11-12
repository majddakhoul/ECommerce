import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/custom_from_field.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/login/bloc/login_cubit/login_cubit.dart';
import 'package:e_commerce/features/authintication/login/bloc/login_state/login_state.dart';
import 'package:e_commerce/features/authintication/register/page/register_page.dart';
import 'package:e_commerce/features/authintication/widget/auth_scaffold.dart';
import 'package:e_commerce/features/main_navigation/main_navigation_page.dart';

class LoginPage extends StatelessWidget {
  const LoginPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => LoginCubit(),
      child: const _LoginView(),
    );
  }
}

class _LoginView extends StatefulWidget {
  const _LoginView();

  @override
  State<_LoginView> createState() => _LoginViewState();
}

class _LoginViewState extends State<_LoginView> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    context.read<LoginCubit>().login(_email.text, _password.text);
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<LoginCubit, LoginState>(
      listener: (context, state) {
        if (state is LoginSuccess) {
          context.read<AuthCubit>().onAuthenticated(state.session);
          Get.offAll(() => const MainNavigation());
        } else if (state is LoginError) {
          AppSnack.error(context, state.message);
        }
      },
      builder: (context, state) {
        return AuthScaffold(
          title: 'Welcome back',
          subtitle: 'Log in to continue shopping',
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomFormField(
                  controller: _email,
                  label: 'Email',
                  hint: 'you@example.com',
                  icon: Icons.mail_outline_rounded,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  validator: Validators.email,
                ),
                const SizedBox(height: 18),
                CustomFormField(
                  controller: _password,
                  label: 'Password',
                  icon: Icons.lock_outline_rounded,
                  isPassword: true,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _submit(),
                  validator: (v) => Validators.notEmpty(v, 'Password'),
                ),
                const SizedBox(height: 28),
                CustomButton(
                  label: 'Log in',
                  loading: state is LoginLoading,
                  onPressed: _submit,
                ),
                const Spacer(),
                const SizedBox(height: 20),
                Center(
                  child: GestureDetector(
                    onTap: () => Get.off(() => const RegisterPage()),
                    child: const Text.rich(
                      TextSpan(
                        text: "Don't have an account? ",
                        style: TextStyle(color: AppColor.textSecondary),
                        children: [
                          TextSpan(
                            text: 'Sign up',
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
