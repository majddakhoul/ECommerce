import 'package:flutter/material.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/theme/app_color.dart';

class ProductImage extends StatelessWidget {
  final String? url;
  final String name;
  final BoxFit fit;

  const ProductImage({
    super.key,
    required this.url,
    required this.name,
    this.fit = BoxFit.cover,
  });

  @override
  Widget build(BuildContext context) {
    final resolved = ApiConstants.image(url);
    if (resolved == null) return _placeholder();

    return Image.network(
      resolved,
      fit: fit,
      width: double.infinity,
      height: double.infinity,
      errorBuilder: (_, __, ___) => _placeholder(),
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return Container(
          color: AppColor.pink,
          alignment: Alignment.center,
          child: const SizedBox(
            height: 22,
            width: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColor.accent,
            ),
          ),
        );
      },
    );
  }

  Widget _placeholder() {
    final letter = name.isEmpty ? '?' : name.substring(0, 1).toUpperCase();
    return Container(
      color: AppColor.pink,
      alignment: Alignment.center,
      child: Text(
        letter,
        style: const TextStyle(
          fontSize: 42,
          fontWeight: FontWeight.w800,
          color: AppColor.accent,
        ),
      ),
    );
  }
}
