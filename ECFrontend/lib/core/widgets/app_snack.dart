import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

class AppSnack {
  AppSnack._();

  static void show(
    BuildContext context,
    String message, {
    bool error = false,
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    final messenger = ScaffoldMessenger.maybeOf(context);
    if (messenger == null) return;
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: error ? AppColor.danger : AppColor.black,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Text(message),
          action: actionLabel == null
              ? null
              : SnackBarAction(
                  label: actionLabel,
                  textColor: AppColor.pink,
                  onPressed: onAction ?? () {},
                ),
        ),
      );
  }

  static void error(BuildContext context, String message) =>
      show(context, message, error: true);
}
