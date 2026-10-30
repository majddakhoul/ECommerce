import 'package:dio/dio.dart';
import 'package:e_commerce/core/utils/json_utils.dart';

class PageMeta {
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  const PageMeta({
    this.currentPage = 1,
    this.perPage = 15,
    this.total = 0,
    this.lastPage = 1,
  });

  bool get hasMore => currentPage < lastPage;

  factory PageMeta.fromJson(dynamic json) {
    if (json is! Map) return const PageMeta();
    return PageMeta(
      currentPage: toInt(json['current_page']),
      perPage: toInt(json['per_page']),
      total: toInt(json['total']),
      lastPage: toInt(json['last_page']),
    );
  }
}

class ApiResponse {
  final bool status;
  final int code;
  final String? message;
  final dynamic data;
  final PageMeta? meta;

  const ApiResponse({
    required this.status,
    required this.code,
    this.message,
    this.data,
    this.meta,
  });

  factory ApiResponse.fromResponse(Response<dynamic> response) {
    final body = response.data;
    if (body is Map) {
      return ApiResponse(
        status: body['status'] == true,
        code: toInt(body['code']),
        message: body['message']?.toString(),
        data: body['data'],
        meta: body['meta'] != null ? PageMeta.fromJson(body['meta']) : null,
      );
    }
    return ApiResponse(status: true, code: response.statusCode ?? 200);
  }

  Map<String, dynamic> get map {
    final value = data;
    if (value is Map) return Map<String, dynamic>.from(value);
    return <String, dynamic>{};
  }

  List<Map<String, dynamic>> get list {
    final value = data;
    if (value is List) {
      return value
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return <Map<String, dynamic>>[];
  }
}
