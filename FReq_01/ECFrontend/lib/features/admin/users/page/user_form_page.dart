import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/custom_from_field.dart';
import 'package:e_commerce/features/admin/users/bloc/cubit/users_cubit.dart';
import 'package:e_commerce/features/authintication/register/model/user_model.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class UserFormPage extends StatefulWidget {
  final UsersCubit cubit;
  final UserModel? user;

  const UserFormPage({super.key, required this.cubit, this.user});

  @override
  State<UserFormPage> createState() => _UserFormPageState();
}

class _UserFormPageState extends State<UserFormPage> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;
  final TextEditingController _password = TextEditingController();
  bool _active = true;
  String _role = 'user';
  List<String> _roleNames = const ['user', 'admin'];
  bool _saving = false;

  bool get _editing => widget.user != null;

  @override
  void initState() {
    super.initState();
    final user = widget.user;
    _name = TextEditingController(text: user?.name ?? '');
    _email = TextEditingController(text: user?.email ?? '');
    _active = user?.isActive ?? true;
    if (!_editing) _loadRoles();
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _loadRoles() async {
    final result = await widget.cubit.availableRoles();
    if (!mounted || !result.ok || result.data == null) return;
    final names = result.data!.map((e) => e.name).toList();
    if (names.isNotEmpty) setState(() => _roleNames = names);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);

    final result = _editing
        ? await widget.cubit.update(
            widget.user!.id,
            name: _name.text,
            email: _email.text,
            password: _password.text,
            isActive: _active,
          )
        : await widget.cubit.create(
            name: _name.text,
            email: _email.text,
            password: _password.text,
            isActive: _active,
            role: _role,
          );

    if (!mounted) return;
    setState(() => _saving = false);

    if (result.ok) {
      AppSnack.show(context, _editing ? 'User updated.' : 'User created.');
      Get.back(result: result.data);
    } else {
      AppSnack.error(context, result.error!);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar(_editing ? 'EDIT USER' : 'NEW USER'),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CustomFormField(
                controller: _name,
                label: 'Name',
                icon: Icons.person_outline_rounded,
                textInputAction: TextInputAction.next,
                validator: (v) => Validators.notEmpty(v, 'Name'),
              ),
              const SizedBox(height: 18),
              CustomFormField(
                controller: _email,
                label: 'Email',
                icon: Icons.mail_outline_rounded,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                validator: Validators.email,
              ),
              const SizedBox(height: 18),
              CustomFormField(
                controller: _password,
                label: _editing ? 'New password (optional)' : 'Password',
                icon: Icons.lock_outline_rounded,
                isPassword: true,
                textInputAction: TextInputAction.done,
                validator: _editing
                    ? Validators.optionalPassword
                    : Validators.password,
              ),
              const SizedBox(height: 18),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text(
                  'Active account',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                value: _active,
                onChanged: (value) => setState(() => _active = value),
              ),
              if (!_editing) ...[
                const SizedBox(height: 8),
                const Text(
                  'Role',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 10,
                  children: [
                    for (final name in _roleNames)
                      ChoiceChip(
                        label: Text(name),
                        selected: _role == name,
                        showCheckmark: false,
                        onSelected: (_) => setState(() => _role = name),
                        labelStyle: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: _role == name
                              ? AppColor.white
                              : AppColor.textPrimary,
                        ),
                      ),
                  ],
                ),
              ],
              const SizedBox(height: 28),
              CustomButton(
                label: _editing ? 'Save changes' : 'Create user',
                loading: _saving,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
