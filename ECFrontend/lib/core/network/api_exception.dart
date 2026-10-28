import 'package:dio/dio.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, List<String>> errors;

  ApiException(
    this.message, {
    this.statusCode,
    this.errors = const {},
  });

  String get displayMessage {
    if (errors.isEmpty) return message;
    return errors.values.expand((list) => list).join('\n');
  }

  factory ApiException.from(Object error) {
    if (error is ApiException) return error;

    if (error is DioException) {
      final data = error.response?.data;
      final status = error.response?.statusCode;

      if (data is Map) {
        final errors = <String, List<String>>{};
        final raw = data['errors'];
        if (raw is Map) {
          raw.forEach((key, value) {
            errors[key.toString()] = value is List
                ? value.map((e) => e.toString()).toList()
                : [value.toString()];
          });
        }
        return ApiException(
          data['message']?.toString() ?? 'Request failed.',
          statusCode: status,
          errors: errors,
        );
      }

      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return ApiException(
            'The connection timed out. Please try again.',
            statusCode: status,
          );
        case DioExceptionType.connectionError:
          return ApiException(
            'Cannot reach the server. Check your connection and the API address.',
            statusCode: status,
          );
        default:
          return ApiException(
            error.message ?? 'Something went wrong.',
            statusCode: status,
          );
      }
    }

    return ApiException('Something went wrong. Please try again.');
  }

  @override
  String toString() => message;
}
