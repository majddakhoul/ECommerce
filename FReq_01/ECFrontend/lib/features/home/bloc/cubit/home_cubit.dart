import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/core/network/api.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/features/home/bloc/state/home_state.dart';
import 'package:e_commerce/features/home/model/product_model.dart';

class HomeCubit extends Cubit<HomeState> {
  HomeCubit() : super(HomeInitial());

  static const int _perPage = 12;

  String search = '';
  String? category;
  bool inStockOnly = false;

  int _requestId = 0;

  void resetFilters() {
    search = '';
    category = null;
    inStockOnly = false;
  }

  Future<void> load() async {
    final id = ++_requestId;
    emit(HomeLoading());
    try {
      final categories = await _fetchCategories();
      final page = await _fetchProducts(1);
      if (id != _requestId) return;
      emit(HomeSuccess(
        products: page.items,
        categories: categories,
        meta: page.meta,
      ));
    } catch (e) {
      if (id != _requestId) return;
      emit(HomeError(ApiException.from(e).displayMessage));
    }
  }

  Future<void> refreshProducts() async {
    final current = state;
    if (current is! HomeSuccess) {
      await load();
      return;
    }
    final id = ++_requestId;
    emit(current.copyWith(refreshing: true));
    try {
      final categories = await _fetchCategories();
      final page = await _fetchProducts(1);
      if (id != _requestId) return;
      emit(HomeSuccess(
        products: page.items,
        categories: categories,
        meta: page.meta,
      ));
    } catch (e) {
      if (id != _requestId) return;
      emit(current.copyWith(refreshing: false));
    }
  }

  Future<void> loadMore() async {
    final current = state;
    if (current is! HomeSuccess) return;
    if (!current.meta.hasMore || current.loadingMore || current.refreshing) {
      return;
    }
    final id = _requestId;
    emit(current.copyWith(loadingMore: true));
    try {
      final page = await _fetchProducts(current.meta.currentPage + 1);
      if (id != _requestId) return;
      emit(current.copyWith(
        products: [...current.products, ...page.items],
        meta: page.meta,
        loadingMore: false,
      ));
    } catch (_) {
      if (id != _requestId) return;
      emit(current.copyWith(loadingMore: false));
    }
  }

  Future<void> setSearch(String value) {
    search = value.trim();
    return refreshProducts();
  }

  Future<void> setCategory(String? value) {
    category = value;
    return refreshProducts();
  }

  Future<void> setInStockOnly(bool value) {
    inStockOnly = value;
    return refreshProducts();
  }

  Future<List<String>> _fetchCategories() async {
    try {
      final response = await Api.get(ApiConstants.categories);
      final data = response.data;
      if (data is List) return data.map((e) => e.toString()).toList();
    } catch (_) {}
    return <String>[];
  }

  Future<_ProductPage> _fetchProducts(int page) async {
    final response = await Api.get(
      ApiConstants.products,
      query: {
        'search': search,
        'category': category,
        'in_stock': inStockOnly ? 1 : null,
        'per_page': _perPage,
        'page': page,
      },
    );
    return _ProductPage(
      items: response.list.map(ProductModel.fromJson).toList(),
      meta: response.meta ?? const PageMeta(),
    );
  }
}

class _ProductPage {
  final List<ProductModel> items;
  final PageMeta meta;

  _ProductPage({required this.items, required this.meta});
}
