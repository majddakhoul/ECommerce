import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

class AppInput {
  AppInput._();

  static OutlineInputBorder _border(
    Color color, {
    double width = 1,
    double radius = 14,
  }) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(radius),
      borderSide: BorderSide(color: color, width: width),
    );
  }

  static InputDecoration decoration({
    String? hint,
    String? helper,
    Widget? prefix,
    Widget? suffix,
    bool borderless = false,
  }) {
    final radius = borderless ? 16.0 : 14.0;
    final normal = borderless
        ? OutlineInputBorder(
            borderRadius: BorderRadius.circular(radius),
            borderSide: BorderSide.none,
          )
        : _border(AppColor.border, radius: radius);
    final focused = borderless
        ? normal
        : _border(AppColor.accent, width: 1.5, radius: radius);

    return InputDecoration(
      hintText: hint,
      helperText: helper,
      prefixIcon: prefix,
      suffixIcon: suffix,
      filled: true,
      fillColor: AppColor.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      hintStyle: const TextStyle(color: AppColor.textSecondary),
      border: normal,
      enabledBorder: normal,
      disabledBorder: normal,
      focusedBorder: focused,
      errorBorder: _border(AppColor.danger, radius: radius),
      focusedErrorBorder: _border(AppColor.danger, width: 1.5, radius: radius),
    );
  }
}
