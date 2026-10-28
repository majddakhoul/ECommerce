import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

AppBar appTopBar(String title, {List<Widget>? actions}) {
  return AppBar(
    title: Text(title),
    actions: actions,
    centerTitle: true,
    backgroundColor: AppColor.background,
    foregroundColor: AppColor.textPrimary,
    elevation: 0,
    scrolledUnderElevation: 0,
    titleTextStyle: const TextStyle(
      color: AppColor.textPrimary,
      fontSize: 16,
      fontWeight: FontWeight.w700,
      letterSpacing: 2,
    ),
  );
}
