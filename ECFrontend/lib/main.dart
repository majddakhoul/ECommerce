import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_theme.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/widget/auth_gete.dart';
import 'package:e_commerce/features/cart/bloc/cubit/cart_cubit.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/orders/bloc/cubit/orders_cubit.dart';
import 'package:e_commerce/features/splash_screen/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  runApp(
    MultiBlocProvider(
      providers: [
        BlocProvider<AuthCubit>(create: (_) => AuthCubit()),
        BlocProvider<HomeCubit>(create: (_) => HomeCubit()),
        BlocProvider<CartCubit>(create: (_) => CartCubit()),
        BlocProvider<OrdersCubit>(create: (_) => OrdersCubit()),
      ],
      child: const MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'ECommerce',
      theme: AppTheme.light,
      builder: (context, child) => AuthGate(child: child ?? const SizedBox()),
      home: const SplashPage(),
    );
  }
}
