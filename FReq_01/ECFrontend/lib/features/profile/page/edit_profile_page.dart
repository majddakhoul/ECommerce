import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/custom_from_field.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/profile/bloc/cubit/profile_cubit.dart';
import 'package:e_commerce/features/profile/bloc/state/profile_state.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class EditProfilePage extends StatelessWidget {
  const EditProfilePage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => ProfileCubit(),
      child: const _EditProfileView(),
    );
  }
}

class _EditProfileView extends StatefulWidget {
  const _EditProfileView();

  @override
  State<_EditProfileView> createState() => _EditProfileViewState();
}

class _EditProfileViewState extends State<_EditProfileView> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthCubit>().user;
    _name = TextEditingController(text: user?.name ?? '');
    _email = TextEditingController(text: user?.email ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    super.dispose();
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    context.read<ProfileCubit>().updateProfile(
          name: _name.text,
          email: _email.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('EDIT PROFILE'),
      body: BlocConsumer<ProfileCubit, ProfileState>(
        listener: (context, state) {
          if (state is ProfileSaved) {
            context.read<AuthCubit>().updateUser(state.user);
            AppSnack.show(context, state.message);
            Get.back();
          } else if (state is ProfileError) {
            AppSnack.error(context, state.message);
          }
        },
        builder: (context, state) {
          final saving = state is ProfileSaving;
          return SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
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
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _submit(),
                    validator: Validators.email,
                  ),
                  const SizedBox(height: 28),
                  CustomButton(
                    label: 'Save changes',
                    loading: saving,
                    onPressed: _submit,
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
