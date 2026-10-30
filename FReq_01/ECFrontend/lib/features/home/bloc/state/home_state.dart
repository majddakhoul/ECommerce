import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/features/home/model/product_model.dart';

abstract class HomeState {}

class HomeInitial extends HomeState {}

class HomeLoading extends HomeState {}

class HomeSuccess extends HomeState {
  final List<ProductModel> products;
  final List<String> categories;
  final PageMeta meta;
  final bool refreshing;
  final bool loadingMore;

  HomeSuccess({
    required this.products,
    required this.categories,
    required this.meta,
    this.refreshing = false,
    this.loadingMore = false,
  });

  HomeSuccess copyWith({
    List<ProductModel>? products,
    List<String>? categories,
    PageMeta? meta,
    bool? refreshing,
    bool? loadingMore,
  }) {
    return HomeSuccess(
      products: products ?? this.products,
      categories: categories ?? this.categories,
      meta: meta ?? this.meta,
      refreshing: refreshing ?? this.refreshing,
      loadingMore: loadingMore ?? this.loadingMore,
    );
  }
}

class HomeError extends HomeState {
  final String message;

  HomeError(this.message);
}
