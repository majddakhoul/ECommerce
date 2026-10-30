import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/features/home/model/product_model.dart';
import 'package:e_commerce/features/home/product_details/bloc/state/product_details_state.dart';

class ProductDetailsCubit extends Cubit<ProductDetailsState> {
  ProductDetailsCubit(ProductModel initial)
      : super(ProductDetailsSuccess(initial));

  Future<void> load() async {
    final current = state.product;
    emit(ProductDetailsSuccess(current, refreshing: true));
    try {
      final response = await Api.get(ApiConstants.product(current.id));
      emit(ProductDetailsSuccess(ProductModel.fromJson(response.map)));
    } catch (e) {
      emit(ProductDetailsError(current, ApiException.from(e).displayMessage));
    }
  }
}
