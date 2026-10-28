import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/widgets/role_badge.dart';
import 'package:e_commerce/core/widgets/state_views.dart';
import 'package:e_commerce/features/admin/users/bloc/cubit/users_cubit.dart';
import 'package:e_commerce/features/admin/users/bloc/state/users_state.dart';
import 'package:e_commerce/features/admin/users/page/user_details_page.dart';
import 'package:e_commerce/features/admin/users/page/user_form_page.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';
import 'package:e_commerce/core/theme/app_input.dart';

class UsersPage extends StatelessWidget {
  const UsersPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => UsersCubit()..load(),
      child: const _UsersView(),
    );
  }
}

class _UsersView extends StatefulWidget {
  const _UsersView();

  @override
  State<_UsersView> createState() => _UsersViewState();
}

class _UsersViewState extends State<_UsersView> {
  final ScrollController _scroll = ScrollController();
  final TextEditingController _search = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients &&
          _scroll.position.pixels >= _scroll.position.maxScrollExtent - 300) {
        context.read<UsersCubit>().loadMore();
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _scroll.dispose();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) context.read<UsersCubit>().setSearch(value);
    });
  }

  Future<void> _openDetails(UserModel user) async {
    final cubit = context.read<UsersCubit>();
    await Get.to(() => UserDetailsPage(user: user, cubit: cubit));
    cubit.load();
  }

  Future<void> _openForm() async {
    final cubit = context.read<UsersCubit>();
    await Get.to(() => UserFormPage(cubit: cubit));
    cubit.load();
  }

  @override
  Widget build(BuildContext context) {
    final cubit = context.read<UsersCubit>();

    return Scaffold(
      appBar: appTopBar('USERS'),
      floatingActionButton: FloatingActionButton(
        onPressed: _openForm,
        backgroundColor: AppColor.black,
        foregroundColor: AppColor.white,
        child: const Icon(Icons.person_add_alt_1_rounded),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 6, 20, 0),
            child: TextField(
              controller: _search,
              onChanged: _onSearch,
              decoration: AppInput.decoration(
                hint: 'Search name or email',
                prefix: const Icon(Icons.search_rounded),
              ),
            ),
          ),
          BlocBuilder<UsersCubit, UsersState>(
            builder: (context, state) {
              return SizedBox(
                height: 52,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  children: [
                    _chip('All', cubit.isActive == null && cubit.role == null,
                        () {
                      cubit.isActive = null;
                      cubit.role = null;
                      cubit.load();
                    }),
                    _chip('Active', cubit.isActive == true,
                        () => cubit.setActive(true)),
                    _chip('Disabled', cubit.isActive == false,
                        () => cubit.setActive(false)),
                    _chip('Admins', cubit.role == 'admin',
                        () => cubit.setRole(cubit.role == 'admin' ? null : 'admin')),
                  ],
                ),
              );
            },
          ),
          Expanded(
            child: BlocBuilder<UsersCubit, UsersState>(
              builder: (context, state) {
                if (state is UsersInitial || state is UsersLoading) {
                  return const LoadingView();
                }
                if (state is UsersError) {
                  return ErrorView(
                    message: state.message,
                    onRetry: () => context.read<UsersCubit>().load(),
                  );
                }
                final success = state as UsersSuccess;
                if (success.users.isEmpty) {
                  return const EmptyView(
                    icon: Icons.people_outline_rounded,
                    title: 'No users found',
                  );
                }
                return RefreshIndicator(
                  color: AppColor.accent,
                  onRefresh: context.read<UsersCubit>().load,
                  child: ListView.separated(
                    controller: _scroll,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(20, 4, 20, 90),
                    itemCount:
                        success.users.length + (success.loadingMore ? 1 : 0),
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      if (index >= success.users.length) {
                        return const Padding(
                          padding: EdgeInsets.all(12),
                          child: LoadingView(),
                        );
                      }
                      return _UserTile(
                        user: success.users[index],
                        onTap: () => _openDetails(success.users[index]),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        showCheckmark: false,
        onSelected: (_) => onTap(),
        labelStyle: TextStyle(
          fontWeight: FontWeight.w700,
          color: selected ? AppColor.white : AppColor.textPrimary,
        ),
      ),
    );
  }
}

class _UserTile extends StatelessWidget {
  final UserModel user;
  final VoidCallback onTap;

  const _UserTile({required this.user, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColor.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColor.border),
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 24,
              backgroundColor: AppColor.pink,
              child: Text(
                user.name.isEmpty ? '?' : user.name.substring(0, 1).toUpperCase(),
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColor.accent,
                  fontSize: 18,
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    user.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  Text(
                    user.email,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColor.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      for (final role in user.roles)
                        RoleBadge(
                          label: role,
                          color: role == 'admin'
                              ? AppColor.primary
                              : AppColor.accent,
                        ),
                      if (!user.isActive)
                        const RoleBadge(
                          label: 'disabled',
                          color: AppColor.danger,
                        ),
                      if (user.isLocked)
                        const RoleBadge(
                          label: 'locked',
                          color: AppColor.warning,
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded,
                color: AppColor.textSecondary),
          ],
        ),
      ),
    );
  }
}
