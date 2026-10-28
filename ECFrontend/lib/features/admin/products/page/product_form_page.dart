import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:e_commerce/core/theme/app_color.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/core/widgets/app_snack.dart';
import 'package:e_commerce/core/widgets/custom_button.dart';
import 'package:e_commerce/core/widgets/custom_from_field.dart';
import 'package:e_commerce/core/widgets/product_image.dart';
import 'package:e_commerce/features/admin/products/bloc/cubit/product_admin_cubit.dart';
import 'package:e_commerce/features/admin/products/bloc/state/product_admin_state.dart';
import 'package:e_commerce/features/home/model/product_model.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class ProductFormPage extends StatelessWidget {
  final ProductModel? product;

  const ProductFormPage({super.key, this.product});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => ProductAdminCubit(),
      child: _ProductFormView(product: product),
    );
  }
}

class _ProductFormView extends StatefulWidget {
  final ProductModel? product;

  const _ProductFormView({required this.product});

  @override
  State<_ProductFormView> createState() => _ProductFormViewState();
}

class _ProductFormViewState extends State<_ProductFormView> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _price;
  late final TextEditingController _cost;
  late final TextEditingController _stock;
  late final TextEditingController _category;
  final ImagePicker _picker = ImagePicker();
  Uint8List? _imageBytes;
  String? _imageName;

  bool get _editing => widget.product != null;

  @override
  void initState() {
    super.initState();
    final p = widget.product;
    _name = TextEditingController(text: p?.name ?? '');
    _description = TextEditingController(text: p?.description ?? '');
    _price = TextEditingController(text: p == null ? '' : '${p.price}');
    _cost = TextEditingController(text: p == null ? '' : '${p.cost}');
    _stock = TextEditingController(text: p == null ? '' : '${p.stock}');
    _category = TextEditingController(text: p?.category ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _price.dispose();
    _cost.dispose();
    _stock.dispose();
    _category.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    try {
      final file = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1600,
        imageQuality: 85,
      );
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      if (bytes.lengthInBytes > 2 * 1024 * 1024) {
        AppSnack.error(context, 'The image must be 2MB or smaller.');
        return;
      }
      setState(() {
        _imageBytes = bytes;
        _imageName = file.name;
      });
    } catch (_) {
      if (mounted) AppSnack.error(context, 'Could not open the image picker.');
    }
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    context.read<ProductAdminCubit>().save(
          id: widget.product?.id,
          name: _name.text,
          description: _description.text,
          price: _price.text,
          cost: _cost.text,
          stock: _stock.text,
          category: _category.text,
          imageBytes: _imageBytes,
          imageName: _imageName,
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar(_editing ? 'EDIT PRODUCT' : 'NEW PRODUCT'),
      body: BlocConsumer<ProductAdminCubit, ProductAdminState>(
        listener: (context, state) {
          if (state is ProductAdminSaved) {
            AppSnack.show(context, state.message);
            Get.back(result: state.product);
          } else if (state is ProductAdminError) {
            AppSnack.error(context, state.message);
          }
        },
        builder: (context, state) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  GestureDetector(
                    onTap: _pickImage,
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(22),
                      child: SizedBox(
                        height: 200,
                        width: double.infinity,
                        child: Stack(
                          fit: StackFit.expand,
                          children: [
                            if (_imageBytes != null)
                              Image.memory(_imageBytes!, fit: BoxFit.cover)
                            else if (_editing)
                              ProductImage(
                                url: widget.product!.image,
                                name: widget.product!.name,
                              )
                            else
                              Container(color: AppColor.pink),
                            Container(
                              color: AppColor.black.withValues(alpha: 0.18),
                              alignment: Alignment.center,
                              child: const Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.add_photo_alternate_outlined,
                                    color: AppColor.white,
                                    size: 36,
                                  ),
                                  SizedBox(height: 6),
                                  Text(
                                    'Choose image (jpg, png, webp - max 2MB)',
                                    style: TextStyle(
                                      color: AppColor.white,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  CustomFormField(
                    controller: _name,
                    label: 'Name',
                    textInputAction: TextInputAction.next,
                    validator: (v) => Validators.notEmpty(v, 'Name'),
                  ),
                  const SizedBox(height: 16),
                  CustomFormField(
                    controller: _description,
                    label: 'Description',
                    maxLines: 4,
                    keyboardType: TextInputType.multiline,
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: CustomFormField(
                          controller: _price,
                          label: 'Price',
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          textInputAction: TextInputAction.next,
                          validator: (v) => Validators.number(v, 'Price'),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: CustomFormField(
                          controller: _cost,
                          label: 'Cost',
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          textInputAction: TextInputAction.next,
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? null
                              : Validators.number(v, 'Cost'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: CustomFormField(
                          controller: _stock,
                          label: 'Stock',
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.next,
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? null
                              : Validators.integer(v, 'Stock'),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: CustomFormField(
                          controller: _category,
                          label: 'Category',
                          hint: 'e.g. shirt',
                          textInputAction: TextInputAction.done,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 28),
                  CustomButton(
                    label: _editing ? 'Save changes' : 'Create product',
                    loading: state is ProductAdminSaving,
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
