import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/purple_blob.dart';

class AuthScaffold extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget child;

  const AuthScaffold({
    super.key,
    required this.title,
    required this.subtitle,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColor.primary,
      body: LayoutBuilder(
        builder: (context, constraints) {
          return SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: IntrinsicHeight(
                child: Column(
                  children: [
                    SizedBox(
                      height: 230,
                      width: double.infinity,
                      child: Stack(
                        children: [
                          const Positioned(
                            top: -70,
                            right: -50,
                            child: PurpleBlob(size: 220),
                          ),
                          Positioned(
                            top: 90,
                            left: -40,
                            child: PurpleBlob(
                              size: 110,
                              color: AppColor.pink.withValues(alpha: 0.9),
                            ),
                          ),
                          SafeArea(
                            child: Padding(
                              padding: const EdgeInsets.fromLTRB(28, 24, 28, 0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'ECOMMERCE',
                                    style: TextStyle(
                                      color: AppColor.white,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 4,
                                      fontSize: 14,
                                    ),
                                  ),
                                  const Spacer(),
                                  Text(
                                    title,
                                    style: const TextStyle(
                                      color: AppColor.white,
                                      fontSize: 32,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    subtitle,
                                    style: TextStyle(
                                      color:
                                          AppColor.white.withValues(alpha: 0.8),
                                    ),
                                  ),
                                  const SizedBox(height: 18),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.fromLTRB(28, 32, 28, 28),
                        decoration: const BoxDecoration(
                          color: AppColor.white,
                          borderRadius: BorderRadius.vertical(
                            top: Radius.circular(34),
                          ),
                        ),
                        child: child,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
