import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/utils/action_result.dart';
import 'package:e_commerce/features/admin/products/bloc/state/product_admin_state.dart';
import 'package:e_commerce/features/home/bloc/cubit/home_cubit.dart';
import 'package:e_commerce/features/home/model/product_model.dart';

class AdminProductsCubit extends HomeCubit {
  AdminProductsCubit() : super();
}

class ProductAdminCubit extends Cubit<ProductAdminState> {
  ProductAdminCubit() : super(ProductAdminInitial());

  Future<void> save({
    int? id,
    required String name,
    required String description,
    required String price,
    required String cost,
    required String stock,
    required String category,
    Uint8List? imageBytes,
    String? imageName,
  }) async {
    emit(ProductAdminSaving());
    try {
      final fields = <String, dynamic>{
        if (id != null) '_method': 'PUT',
        'name': name.trim(),
        'description': description.trim(),
        'price': price.trim(),
        'cost': cost.trim().isEmpty ? '0' : cost.trim(),
        'stock': stock.trim().isEmpty ? '0' : stock.trim(),
        'category': category.trim().toLowerCase(),
        if (imageBytes != null)
          'image': MultipartFile.fromBytes(
            imageBytes,
            filename: imageName ?? 'image.jpg',
          ),
      };

      final response = await Api.post(
        id == null ? ApiConstants.products : ApiConstants.product(id),
        data: FormData.fromMap(fields),
      );
      emit(ProductAdminSaved(
        ProductModel.fromJson(response.map),
        id == null ? 'Product created.' : 'Product updated.',
      ));
    } catch (e) {
      emit(ProductAdminError(ApiException.from(e).displayMessage));
    }
  }

  Future<ActionResult<void>> delete(int id) async {
    try {
      await Api.delete(ApiConstants.product(id));
      return ActionResult<void>.success();
    } catch (e) {
      return ActionResult<void>.failure(ApiException.from(e).displayMessage);
    }
  }
}
