import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/purple_blob.dart';
import 'package:e_commerce/features/authintication/login/login_page.dart';

class OnboardingPage extends StatefulWidget {
  const OnboardingPage({super.key});

  @override
  State<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends State<OnboardingPage> {
  static const List<_Slide> _slides = [
    _Slide(
      icon: Icons.checkroom_rounded,
      title: 'New In\nEvery Day',
      text: 'Browse the latest collection and find the pieces you love.',
    ),
    _Slide(
      icon: Icons.shopping_bag_outlined,
      title: 'Fill Your\nCart',
      text: 'Add items to your cart and check out in a few taps.',
    ),
    _Slide(
      icon: Icons.local_shipping_outlined,
      title: 'Track Your\nOrders',
      text: 'Follow every order from confirmation to delivery.',
    ),
  ];

  final PageController _controller = PageController();
  int _page = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    await SecureStorageService().setOnboardingSeen();
    Get.offAll(() => const LoginPage());
  }

  void _next() {
    if (_page == _slides.length - 1) {
      _finish();
    } else {
      _controller.nextPage(
        duration: const Duration(milliseconds: 320),
        curve: Curves.easeOut,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final last = _page == _slides.length - 1;

    return Scaffold(
      backgroundColor: AppColor.primary,
      body: Stack(
        children: [
          const Positioned(top: -90, left: -70, child: PurpleBlob(size: 260)),
          Positioned(
            bottom: 120,
            right: -80,
            child: PurpleBlob(
              size: 200,
              color: AppColor.pink.withValues(alpha: 0.9),
            ),
          ),
          SafeArea(
            child: Column(
              children: [
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: _finish,
                    child: const Text(
                      'Skip',
                      style: TextStyle(
                        color: AppColor.white,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
                Expanded(
                  child: PageView.builder(
                    controller: _controller,
                    itemCount: _slides.length,
                    onPageChanged: (value) => setState(() => _page = value),
                    itemBuilder: (context, index) {
                      final slide = _slides[index];
                      return Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 32),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              height: 110,
                              width: 110,
                              decoration: const BoxDecoration(
                                color: AppColor.pink,
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                slide.icon,
                                size: 52,
                                color: AppColor.primary,
                              ),
                            ),
                            const SizedBox(height: 34),
                            Text(
                              slide.title,
                              style: const TextStyle(
                                color: AppColor.white,
                                fontSize: 40,
                                height: 1.1,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 14),
                            Text(
                              slide.text,
                              style: TextStyle(
                                color: AppColor.white.withValues(alpha: 0.85),
                                fontSize: 16,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(32, 0, 32, 28),
                  child: Row(
                    children: [
                      Row(
                        children: [
                          for (int i = 0; i < _slides.length; i++)
                            AnimatedContainer(
                              duration: const Duration(milliseconds: 250),
                              margin: const EdgeInsets.only(right: 6),
                              height: 8,
                              width: i == _page ? 26 : 8,
                              decoration: BoxDecoration(
                                color: i == _page
                                    ? AppColor.white
                                    : AppColor.white.withValues(alpha: 0.4),
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                        ],
                      ),
                      const Spacer(),
                      SizedBox(
                        width: 160,
                        child: CustomButton(
                          label: last ? 'Get started' : 'Next',
                          style: CustomButtonStyle.black,
                          height: 50,
                          onPressed: _next,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Slide {
  final IconData icon;
  final String title;
  final String text;

  const _Slide({required this.icon, required this.title, required this.text});
}
