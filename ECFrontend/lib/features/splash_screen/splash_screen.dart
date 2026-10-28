import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/purple_blob.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/bloc/auth_state/auth_state.dart';
import 'package:e_commerce/features/authintication/login/login_page.dart';
import 'package:e_commerce/features/main_navigation/main_navigation_page.dart';
import 'package:e_commerce/features/onboarding/onboarding.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  String? _error;

  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    if (_error != null) setState(() => _error = null);

    final auth = context.read<AuthCubit>();
    await Future.wait<void>([
      Future<void>.delayed(const Duration(milliseconds: 1500)),
      auth.checkSession(),
    ]);
    if (!mounted) return;

    final state = auth.state;
    if (state is AuthSuccess) {
      Get.offAll(() => const MainNavigation());
      return;
    }
    if (state is AuthError) {
      setState(() => _error = state.message);
      return;
    }

    final seen = await SecureStorageService().isOnboardingSeen();
    if (!mounted) return;
    Get.offAll(() => seen ? const LoginPage() : const OnboardingPage());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColor.primary,
      body: Stack(
        children: [
          const Positioned(top: -80, right: -60, child: PurpleBlob(size: 260)),
          Positioned(
            bottom: -70,
            left: -50,
            child: PurpleBlob(
              size: 210,
              color: AppColor.pink.withValues(alpha: 0.9),
            ),
          ),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    height: 96,
                    width: 96,
                    decoration: const BoxDecoration(
                      color: AppColor.white,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.shopping_bag_outlined,
                      size: 46,
                      color: AppColor.primary,
                    ),
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'ECOMMERCE',
                    style: TextStyle(
                      color: AppColor.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 6,
                    ),
                  ),
                  const SizedBox(height: 30),
                  if (_error == null)
                    const SizedBox(
                      height: 26,
                      width: 26,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        color: AppColor.white,
                      ),
                    )
                  else ...[
                    Text(
                      _error!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColor.white),
                    ),
                    const SizedBox(height: 18),
                    SizedBox(
                      width: 180,
                      child: CustomButton(
                        label: 'Try again',
                        style: CustomButtonStyle.black,
                        height: 46,
                        onPressed: _start,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
