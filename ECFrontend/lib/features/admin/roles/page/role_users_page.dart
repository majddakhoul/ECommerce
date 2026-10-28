import 'package:flutter/material.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/admin/roles/bloc/cubit/roles_cubit.dart';
import 'package:e_commerce/features/admin/roles/model/role_model.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class RoleUsersPage extends StatefulWidget {
  final RoleModel role;

  const RoleUsersPage({super.key, required this.role});

  @override
  State<RoleUsersPage> createState() => _RoleUsersPageState();
}

class _RoleUsersPageState extends State<RoleUsersPage> {
  final RolesCubit _cubit = RolesCubit();
  bool _loading = true;
  String? _error;
  List<UserModel> _users = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _cubit.close();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _cubit.usersOf(widget.role.id);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (result.ok) {
        _users = result.data ?? const [];
      } else {
        _error = result.error;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar(widget.role.name.toUpperCase()),
      body: Builder(
        builder: (context) {
          if (_loading) return const LoadingView();
          if (_error != null) {
            return ErrorView(message: _error!, onRetry: _load);
          }
          if (_users.isEmpty) {
            return const EmptyView(
              icon: Icons.people_outline_rounded,
              title: 'No users have this role',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: _users.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final user = _users[index];
              return Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColor.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColor.border),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user.name,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          Text(
                            user.email,
                            style: const TextStyle(
                              color: AppColor.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (!user.isActive)
                      const RoleBadge(
                        label: 'disabled',
                        color: AppColor.danger,
                      ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
