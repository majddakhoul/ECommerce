import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/core/utils/json_utils.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/confirm_dialog.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/features/admin/roles/model/role_model.dart';
import 'package:e_commerce/features/admin/users/bloc/cubit/users_cubit.dart';
import 'package:e_commerce/features/admin/users/page/user_form_page.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class UserDetailsPage extends StatefulWidget {
  final UserModel user;
  final UsersCubit cubit;

  const UserDetailsPage({super.key, required this.user, required this.cubit});

  @override
  State<UserDetailsPage> createState() => _UserDetailsPageState();
}

class _UserDetailsPageState extends State<UserDetailsPage> {
  late UserModel _user;
  List<RoleModel> _roles = const [];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _user = widget.user;
    _refresh();
  }

  Future<void> _refresh() async {
    final userResult = await widget.cubit.show(_user.id);
    final rolesResult = await widget.cubit.availableRoles();
    if (!mounted) return;
    setState(() {
      if (userResult.ok && userResult.data != null) _user = userResult.data!;
      if (rolesResult.ok && rolesResult.data != null) {
        _roles = rolesResult.data!;
      }
    });
  }

  Future<void> _run(
    Future<ActionResult<UserModel>> Function() action, {
    String? success,
  }) async {
    setState(() => _busy = true);
    final result = await action();
    if (!mounted) return;
    setState(() => _busy = false);
    if (result.ok) {
      if (result.data != null) _user = result.data!;
      if (success != null) AppSnack.show(context, success);
      setState(() {});
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  RoleModel? _roleByName(String name) {
    for (final role in _roles) {
      if (role.name == name) return role;
    }
    return null;
  }

  Future<void> _removeRole(String name) async {
    final role = _roleByName(name);
    if (role == null) {
      AppSnack.error(context, 'Role not found.');
      return;
    }
    final confirmed = await confirmDialog(
      context,
      title: 'Remove role',
      message: 'Remove the "$name" role from ${_user.name}?',
      confirmLabel: 'Remove',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    await _run(
      () => widget.cubit.removeRole(_user.id, role.id),
      success: 'Role removed.',
    );
  }

  Future<void> _addRole() async {
    final available =
        _roles.where((role) => !_user.roles.contains(role.name)).toList();
    if (available.isEmpty) {
      AppSnack.show(context, 'The user already has every role.');
      return;
    }

    final selected = await showModalBottomSheet<RoleModel>(
      context: context,
      backgroundColor: AppColor.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Assign role',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              for (final role in available)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    role.name,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  trailing: const Icon(Icons.add_circle_outline_rounded),
                  onTap: () => Navigator.of(sheetContext).pop(role),
                ),
            ],
          ),
        );
      },
    );

    if (selected == null || !mounted) return;
    await _run(
      () => widget.cubit.assignRole(_user.id, selected.id),
      success: 'Role assigned.',
    );
  }

  Future<void> _edit() async {
    final result = await Get.to(
      () => UserFormPage(cubit: widget.cubit, user: _user),
    );
    if (result is UserModel && mounted) setState(() => _user = result);
    _refresh();
  }

  Future<void> _delete() async {
    final confirmed = await confirmDialog(
      context,
      title: 'Delete user',
      message: 'Delete ${_user.name}? This cannot be undone.',
      confirmLabel: 'Delete',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _busy = true);
    final result = await widget.cubit.delete(_user.id);
    if (!mounted) return;
    setState(() => _busy = false);
    if (result.ok) {
      AppSnack.show(context, 'User deleted.');
      Get.back();
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('USER'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: [
          if (_busy)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: LinearProgressIndicator(
                minHeight: 2,
                color: AppColor.accent,
                backgroundColor: AppColor.accentLight,
              ),
            ),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColor.pinkSoft,
              borderRadius: BorderRadius.circular(22),
            ),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 36,
                  backgroundColor: AppColor.pink,
                  child: Text(
                    _user.name.isEmpty
                        ? '?'
                        : _user.name.substring(0, 1).toUpperCase(),
                    style: const TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      color: AppColor.accent,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _user.name,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(_user.email),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  children: [
                    RoleBadge(
                      label: _user.isActive ? 'active' : 'disabled',
                      color:
                          _user.isActive ? AppColor.success : AppColor.danger,
                    ),
                    if (_user.isLocked)
                      const RoleBadge(
                        label: 'locked',
                        color: AppColor.warning,
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  'Last login: ${formatDate(_user.lastLoginAt)}',
                  style: const TextStyle(
                    color: AppColor.textSecondary,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Roles',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                ),
              ),
              TextButton.icon(
                onPressed: _busy ? null : _addRole,
                icon: const Icon(Icons.add_rounded),
                label: const Text('Add role'),
              ),
            ],
          ),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final role in _user.roles)
                RoleBadge(
                  label: role,
                  color:
                      role == 'admin' ? AppColor.primary : AppColor.accent,
                  onDeleted: _busy ? null : () => _removeRole(role),
                ),
              if (_user.roles.isEmpty)
                const Text(
                  'No roles assigned.',
                  style: TextStyle(color: AppColor.textSecondary),
                ),
            ],
          ),
          const SizedBox(height: 28),
          CustomButton(
            label: 'Edit user',
            style: CustomButtonStyle.purple,
            onPressed: _busy ? null : _edit,
          ),
          if (_user.isLocked) ...[
            const SizedBox(height: 10),
            CustomButton(
              label: 'Unlock account',
              style: CustomButtonStyle.outline,
              onPressed: _busy
                  ? null
                  : () => _run(
                        () => widget.cubit.unlock(_user.id),
                        success: 'Account unlocked.',
                      ),
            ),
          ],
          const SizedBox(height: 10),
          CustomButton(
            label: 'Delete user',
            style: CustomButtonStyle.danger,
            onPressed: _busy ? null : _delete,
          ),
        ],
      ),
    );
  }
}
