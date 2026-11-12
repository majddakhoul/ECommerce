import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/product_image.dart';
import 'package:e_commerce/features/home/model/product_model.dart';

class ProductCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;
  final Widget? trailing;

  const ProductCard({
    super.key,
    required this.product,
    required this.onTap,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  ProductImage(url: product.image, name: product.name),
                  if (!product.inStock)
                    Container(
                      color: AppColor.black.withValues(alpha: 0.45),
                      alignment: Alignment.center,
                      child: const Text(
                        'OUT OF STOCK',
                        style: TextStyle(
                          color: AppColor.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                          letterSpacing: 1,
                        ),
                      ),
                    ),
                  if (trailing != null)
                    Positioned(top: 6, right: 6, child: trailing!),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            product.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          ),
          const SizedBox(height: 2),
          Text(
            money(product.price),
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: AppColor.textSecondary,
              fontWeight: FontWeight.w500,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}
