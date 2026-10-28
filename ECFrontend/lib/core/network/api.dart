import 'package:dio/dio.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/network/api_response.dart';
import 'package:e_commerce/core/network/dio_client.dart';

class Api {
  Api._();

  static Dio get _dio => DioClient().client;

  static Future<ApiResponse> get(
    String path, {
    Map<String, dynamic>? query,
  }) {
    return _run(() => _dio.get<dynamic>(path, queryParameters: _clean(query)));
  }

  static Future<ApiResponse> post(String path, {Object? data}) {
    return _run(() => _dio.post<dynamic>(path, data: data));
  }

  static Future<ApiResponse> put(String path, {Object? data}) {
    return _run(() => _dio.put<dynamic>(path, data: data));
  }

  static Future<ApiResponse> delete(String path) {
    return _run(() => _dio.delete<dynamic>(path));
  }

  static Future<ApiResponse> _run(
    Future<Response<dynamic>> Function() request,
  ) async {
    try {
      return ApiResponse.fromResponse(await request());
    } catch (e) {
      throw ApiException.from(e);
    }
  }

  static Map<String, dynamic>? _clean(Map<String, dynamic>? query) {
    if (query == null) return null;
    final result = <String, dynamic>{};
    query.forEach((key, value) {
      if (value == null) return;
      if (value is String && value.trim().isEmpty) return;
      result[key] = value;
    });
    return result;
  }
}
