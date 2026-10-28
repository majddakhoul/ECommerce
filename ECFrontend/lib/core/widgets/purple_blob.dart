import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';

class PurpleBlob extends StatelessWidget {
  final double size;
  final Color color;

  const PurpleBlob({
    super.key,
    this.size = 120,
    this.color = AppColor.accent,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: size,
      width: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}
