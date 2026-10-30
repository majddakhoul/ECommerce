import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/purple_blob.dart';
import 'package:e_commerce/features/admin/orders/admin_orders_page.dart';
import 'package:e_commerce/features/admin/products/page/admin_products_page.dart';
import 'package:e_commerce/features/admin/roles/page/roles_page.dart';
import 'package:e_commerce/features/admin/users/page/users_page.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class AdminPage extends StatelessWidget {
  const AdminPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('ADMIN'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        children: [
          _card(
            icon: Icons.inventory_2_outlined,
            title: 'Products',
            subtitle: 'Create, edit and delete products',
            onTap: () => Get.to(() => const AdminProductsPage()),
          ),
          _card(
            icon: Icons.receipt_long_outlined,
            title: 'Orders',
            subtitle: 'All orders, status updates',
            onTap: () => Get.to(() => const AdminOrdersPage()),
          ),
          _card(
            icon: Icons.people_alt_outlined,
            title: 'Users',
            subtitle: 'Accounts, roles, unlock',
            onTap: () => Get.to(() => const UsersPage()),
          ),
          _card(
            icon: Icons.shield_outlined,
            title: 'Roles',
            subtitle: 'Manage roles',
            onTap: () => Get.to(() => const RolesPage()),
          ),
        ],
      ),
    );
  }

  Widget _card({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 110,
        margin: const EdgeInsets.only(bottom: 14),
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: AppColor.pink,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Stack(
          children: [
            const Positioned(
              top: -40,
              left: -40,
              child: PurpleBlob(size: 110),
            ),
            Positioned(
              top: 14,
              left: 14,
              child: Icon(icon, color: AppColor.white, size: 26),
            ),
            Positioned(
              left: 20,
              bottom: 16,
              right: 60,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: AppColor.textPrimary,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const Positioned(
              right: 18,
              bottom: 18,
              child: Icon(Icons.arrow_forward_rounded),
            ),
          ],
        ),
      ),
    );
  }
}
