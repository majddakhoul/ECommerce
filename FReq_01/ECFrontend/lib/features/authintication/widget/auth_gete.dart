import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/bloc/auth_state/auth_state.dart';
import 'package:e_commerce/features/authintication/login/login_page.dart';
import 'package:e_commerce/features/cart/bloc/cubit/cart_cubit.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/main_navigation/nav_controller.dart';
import 'package:e_commerce/features/orders/bloc/cubit/orders_cubit.dart';

class AuthGate extends StatelessWidget {
  final Widget child;

  const AuthGate({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthCubit, AuthState>(
      listenWhen: (previous, current) =>
          previous is AuthSuccess && current is AuthUnauthenticated,
      listener: (context, state) {
        context.read<CartCubit>().reset();
        context.read<OrdersCubit>().reset();
        context.read<HomeCubit>().resetFilters();
        NavController.index.value = NavController.home;
        Get.offAll(() => const LoginPage());
      },
      child: child,
    );
  }
}
