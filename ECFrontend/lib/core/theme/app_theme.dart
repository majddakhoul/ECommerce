import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

class AppTheme {
  AppTheme._();

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColor.accent,
      brightness: Brightness.light,
    ).copyWith(
      primary: AppColor.accent,
      onPrimary: AppColor.white,
      secondary: AppColor.pink,
      surface: AppColor.white,
      error: AppColor.danger,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColor.background,
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColor.white,
        indicatorColor: AppColor.accentLight,
        elevation: 8,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 12,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            color: selected ? AppColor.accent : AppColor.textSecondary,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            color: selected ? AppColor.accent : AppColor.textSecondary,
          );
        }),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColor.white,
        selectedColor: AppColor.accent,
        side: const BorderSide(color: AppColor.border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        labelStyle: const TextStyle(fontWeight: FontWeight.w600),
      ),
      dividerTheme: const DividerThemeData(color: AppColor.border, space: 1),
    );
  }
}
