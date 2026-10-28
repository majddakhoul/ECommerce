import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

enum CustomButtonStyle { black, purple, outline, danger }

class CustomButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final IconData? icon;
  final CustomButtonStyle style;
  final double height;

  const CustomButton({
    super.key,
    required this.label,
    this.onPressed,
    this.loading = false,
    this.icon,
    this.style = CustomButtonStyle.black,
    this.height = 54,
  });

  @override
  Widget build(BuildContext context) {
    Color background;
    Color foreground;
    BorderSide side = BorderSide.none;

    switch (style) {
      case CustomButtonStyle.black:
        background = AppColor.black;
        foreground = AppColor.white;
        break;
      case CustomButtonStyle.purple:
        background = AppColor.accent;
        foreground = AppColor.white;
        break;
      case CustomButtonStyle.danger:
        background = AppColor.danger;
        foreground = AppColor.white;
        break;
      case CustomButtonStyle.outline:
        background = AppColor.white;
        foreground = AppColor.textPrimary;
        side = const BorderSide(color: AppColor.border, width: 1.5);
        break;
    }

    final enabled = onPressed != null && !loading;

    return SizedBox(
      width: double.infinity,
      height: height,
      child: ElevatedButton(
        onPressed: enabled ? onPressed : null,
        style: ElevatedButton.styleFrom(
          backgroundColor: background,
          foregroundColor: foreground,
          disabledBackgroundColor: background.withValues(alpha: 0.55),
          disabledForegroundColor: foreground.withValues(alpha: 0.8),
          elevation: 0,
          side: side,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        child: loading
            ? SizedBox(
                height: 22,
                width: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  color: foreground,
                ),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (icon != null) ...[
                    Icon(icon, size: 20),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    label,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
