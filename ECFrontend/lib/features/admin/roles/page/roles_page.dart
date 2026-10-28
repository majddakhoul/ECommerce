import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/confirm_dialog.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/admin/roles/bloc/cubit/roles_cubit.dart';
import 'package:e_commerce/features/admin/roles/bloc/state/roles_state.dart';
import 'package:e_commerce/features/admin/roles/model/role_model.dart';
import 'package:e_commerce/features/admin/roles/page/role_users_page.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';
import 'package:e_commerce/core/theme/app_input.dart';

class RolesPage extends StatelessWidget {
  const RolesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => RolesCubit()..load(),
      child: const _RolesView(),
    );
  }
}

class _RolesView extends StatelessWidget {
  const _RolesView();

  Future<void> _create(BuildContext context) async {
    final cubit = context.read<RolesCubit>();
    final name = await _askName(context, title: 'New role');
    if (name == null || !context.mounted) return;
    final result = await cubit.create(name);
    if (!context.mounted) return;
    if (result.ok) {
      AppSnack.show(context, 'Role created.');
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  Future<void> _rename(BuildContext context, RoleModel role) async {
    final cubit = context.read<RolesCubit>();
    final name = await _askName(context, title: 'Rename role', initial: role.name);
    if (name == null || name == role.name || !context.mounted) return;
    final result = await cubit.rename(role.id, name);
    if (!context.mounted) return;
    if (result.ok) {
      AppSnack.show(context, 'Role updated.');
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  Future<void> _delete(BuildContext context, RoleModel role) async {
    final cubit = context.read<RolesCubit>();
    final confirmed = await confirmDialog(
      context,
      title: 'Delete role',
      message: 'Delete the "${role.name}" role?',
      confirmLabel: 'Delete',
      danger: true,
    );
    if (!confirmed || !context.mounted) return;
    final result = await cubit.delete(role.id);
    if (!context.mounted) return;
    if (result.ok) {
      AppSnack.show(context, 'Role deleted.');
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  Future<String?> _askName(
    BuildContext context, {
    required String title,
    String initial = '',
  }) {
    return showDialog<String>(
      context: context,
      builder: (_) => _RoleNameDialog(title: title, initial: initial),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('ROLES'),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _create(context),
        backgroundColor: AppColor.black,
        foregroundColor: AppColor.white,
        child: const Icon(Icons.add_rounded),
      ),
      body: BlocBuilder<RolesCubit, RolesState>(
        builder: (context, state) {
          if (state is RolesInitial || state is RolesLoading) {
            return const LoadingView();
          }
          if (state is RolesError) {
            return ErrorView(
              message: state.message,
              onRetry: () => context.read<RolesCubit>().load(),
            );
          }
          final success = state as RolesSuccess;
          if (success.roles.isEmpty) {
            return const EmptyView(
              icon: Icons.shield_outlined,
              title: 'No roles yet',
            );
          }
          return Column(
            children: [
              if (success.busy)
                const LinearProgressIndicator(
                  minHeight: 2,
                  color: AppColor.accent,
                  backgroundColor: AppColor.accentLight,
                ),
              Expanded(
                child: RefreshIndicator(
                  color: AppColor.accent,
                  onRefresh: context.read<RolesCubit>().load,
                  child: ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 90),
                    itemCount: success.roles.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final role = success.roles[index];
                      return Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: AppColor.white,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: AppColor.border),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Text(
                                        role.name,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          fontSize: 15,
                                        ),
                                      ),
                                      if (role.isBuiltIn) ...[
                                        const SizedBox(width: 8),
                                        const RoleBadge(
                                          label: 'built-in',
                                          color: AppColor.primary,
                                        ),
                                      ],
                                    ],
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${role.usersCount} user(s)',
                                    style: const TextStyle(
                                      color: AppColor.textSecondary,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              tooltip: 'Users',
                              onPressed: () =>
                                  Get.to(() => RoleUsersPage(role: role)),
                              icon: const Icon(Icons.people_outline_rounded),
                            ),
                            if (!role.isBuiltIn) ...[
                              IconButton(
                                tooltip: 'Rename',
                                onPressed: () => _rename(context, role),
                                icon: const Icon(Icons.edit_outlined),
                              ),
                              IconButton(
                                tooltip: 'Delete',
                                onPressed: () => _delete(context, role),
                                icon: const Icon(
                                  Icons.delete_outline_rounded,
                                  color: AppColor.danger,
                                ),
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _RoleNameDialog extends StatefulWidget {
  final String title;
  final String initial;

  const _RoleNameDialog({required this.title, required this.initial});

  @override
  State<_RoleNameDialog> createState() => _RoleNameDialogState();
}

class _RoleNameDialogState extends State<_RoleNameDialog> {
  late final TextEditingController _controller;
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initial);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: AppColor.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: Text(
        widget.title,
        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
      ),
      content: Form(
        key: _formKey,
        child: TextFormField(
          controller: _controller,
          autofocus: true,
          decoration: AppInput.decoration(
            hint: 'e.g. editor',
            helper: 'Lowercase letters, digits, - and _',
          ),
          validator: (value) {
            final text = (value ?? '').trim();
            if (text.length < 2) return 'At least 2 characters';
            if (!RegExp(r'^[a-z0-9_-]+$').hasMatch(text)) {
              return 'Use lowercase letters, digits, - or _';
            }
            return null;
          },
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text(
            'Cancel',
            style: TextStyle(color: AppColor.textSecondary),
          ),
        ),
        TextButton(
          onPressed: () {
            if (_formKey.currentState!.validate()) {
              Navigator.of(context).pop(_controller.text.trim());
            }
          },
          child: const Text(
            'Save',
            style: TextStyle(
              color: AppColor.accent,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}
