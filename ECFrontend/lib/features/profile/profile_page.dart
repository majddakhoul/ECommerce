import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/confirm_dialog.dart';
import 'package:e_commerce/core/widgets/purple_blob.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/bloc/auth_state/auth_state.dart';
import 'package:e_commerce/features/profile/page/change_password_page.dart';
import 'package:e_commerce/features/profile/page/edit_profile_page.dart';

class ProfilePage extends StatelessWidget {
  const ProfilePage({super.key});

  Future<void> _logout(BuildContext context) async {
    final confirmed = await confirmDialog(
      context,
      title: 'Log out',
      message: 'Do you want to log out of your account?',
      confirmLabel: 'Log out',
      danger: true,
    );
    if (!confirmed || !context.mounted) return;
    await context.read<AuthCubit>().logout();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: BlocBuilder<AuthCubit, AuthState>(
        builder: (context, state) {
          final user = state is AuthSuccess ? state.user : null;
          final name = user?.name ?? '';

          return ListView(
            padding: EdgeInsets.zero,
            children: [
              SizedBox(
                height: 250,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Container(color: AppColor.pink),
                    const Positioned(
                      top: -60,
                      left: -50,
                      child: PurpleBlob(size: 190),
                    ),
                    const Positioned(
                      bottom: -70,
                      right: -40,
                      child: PurpleBlob(size: 170),
                    ),
                    SafeArea(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            height: 84,
                            width: 84,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: AppColor.white,
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: AppColor.primary,
                                width: 3,
                              ),
                            ),
                            child: Text(
                              name.isEmpty
                                  ? '?'
                                  : name.substring(0, 1).toUpperCase(),
                              style: const TextStyle(
                                fontSize: 34,
                                fontWeight: FontWeight.w800,
                                color: AppColor.primary,
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            name,
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            user?.email ?? '',
                            style: const TextStyle(
                              color: AppColor.textPrimary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (user != null && user.roles.isNotEmpty)
                      Wrap(
                        spacing: 8,
                        children: [
                          for (final role in user.roles)
                            RoleBadge(
                              label: role,
                              color: role == 'admin'
                                  ? AppColor.primary
                                  : AppColor.accent,
                            ),
                        ],
                      ),
                    const SizedBox(height: 18),
                    _tile(
                      icon: Icons.person_outline_rounded,
                      label: 'Edit profile',
                      onTap: () => Get.to(() => const EditProfilePage()),
                    ),
                    _tile(
                      icon: Icons.lock_outline_rounded,
                      label: 'Change password',
                      onTap: () => Get.to(() => const ChangePasswordPage()),
                    ),
                    _tile(
                      icon: Icons.logout_rounded,
                      label: 'Log out',
                      color: AppColor.danger,
                      onTap: () => _logout(context),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _tile({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    Color color = AppColor.textPrimary,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: AppColor.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColor.border),
      ),
      child: ListTile(
        onTap: onTap,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        leading: Icon(icon, color: color),
        title: Text(
          label,
          style: TextStyle(fontWeight: FontWeight.w700, color: color),
        ),
        trailing: Icon(
          Icons.chevron_right_rounded,
          color: color == AppColor.danger ? color : AppColor.textSecondary,
        ),
      ),
    );
  }
}
