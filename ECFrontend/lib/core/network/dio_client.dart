import 'dart:async';

import 'package:dio/dio.dart';
import 'package:e_commerce/core/network/api_constants.dart';
import 'package:e_commerce/core/storage/secure_storage_service.dart';

class DioClient {
  DioClient._internal() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        connectTimeout: const Duration(seconds: 20),
        receiveTimeout: const Duration(seconds: 20),
        headers: {
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await SecureStorageService().getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) async {
          final path = error.requestOptions.path;
          final isAuthEntry = path == ApiConstants.login ||
              path == ApiConstants.register;
          if (error.response?.statusCode == 401 && !isAuthEntry) {
            await SecureStorageService().clearToken();
            _unauthorized.add(null);
          }
          handler.next(error);
        },
      ),
    );
  }

  static final DioClient _instance = DioClient._internal();

  factory DioClient() => _instance;

  late final Dio _dio;

  final StreamController<void> _unauthorized =
      StreamController<void>.broadcast();

  Stream<void> get onUnauthorized => _unauthorized.stream;

  Dio get client => _dio;
}
