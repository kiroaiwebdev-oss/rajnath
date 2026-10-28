import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Base URL of the shared backend. Override at build time:
///   flutter build apk --dart-define=API_BASE=https://staging.example.com/api/v1
const apiBase = String.fromEnvironment('API_BASE', defaultValue: 'https://astrorajnathjha.com/api/v1');

/// Website origin derived from [apiBase] (strips a trailing `/api/v1`).
String siteOriginFor(String base) => base.replaceFirst(RegExp(r'/api/v1/?$'), '').replaceFirst(RegExp(r'/$'), '');
final siteOrigin = siteOriginFor(apiBase);

class ApiException implements Exception {
  ApiException(this.message, {this.code, this.status, this.fieldErrors = const {}});
  final String message;
  final String? code;
  final int? status;
  final Map<String, String> fieldErrors;
  bool get isOffline => code == 'offline';
  @override
  String toString() => message;
}

/// Thin HTTP client. The Bearer token lives only in Android Keystore-backed secure storage.
class ApiClient {
  ApiClient({Dio? dio, FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage(),
        dio = dio ?? Dio(BaseOptions(baseUrl: apiBase, connectTimeout: const Duration(seconds: 10), receiveTimeout: const Duration(seconds: 25), headers: {'Accept': 'application/json'})) {
    this.dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) async {
      final t = await token();
      if (t != null) o.headers['Authorization'] = 'Bearer $t';
      h.next(o);
    }));
  }

  final Dio dio;
  final FlutterSecureStorage _storage;
  static const _tokenKey = 'arj_token';
  void Function()? onUnauthorized;

  Future<String?> token() => _storage.read(key: _tokenKey);
  Future<void> saveToken(String t) => _storage.write(key: _tokenKey, value: t);
  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) => _wrap(() => dio.get(path, queryParameters: query));
  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) => _wrap(() => dio.post(path, data: body));
  Future<Map<String, dynamic>> patch(String path, Map<String, dynamic> body) => _wrap(() => dio.patch(path, data: body));
  Future<Map<String, dynamic>> delete(String path, [Map<String, dynamic>? body]) => _wrap(() => dio.delete(path, data: body));

  /// Multipart upload (e.g. palm photos). Same error mapping as the JSON calls.
  Future<Map<String, dynamic>> postMultipart(String path, FormData form) =>
      _wrap(() => dio.post(path, data: form, options: Options(sendTimeout: const Duration(minutes: 2), receiveTimeout: const Duration(minutes: 1))));

  Future<Map<String, dynamic>> _wrap(Future<Response<dynamic>> Function() call) async {
    try {
      final r = await call();
      return r.data is Map<String, dynamic> ? r.data as Map<String, dynamic> : <String, dynamic>{};
    } on DioException catch (e) {
      throw toApiException(e);
    }
  }

  ApiException toApiException(DioException e) {
    if (e.type == DioExceptionType.connectionError || e.type == DioExceptionType.connectionTimeout || e.type == DioExceptionType.receiveTimeout || e.type == DioExceptionType.sendTimeout) {
      return ApiException('No internet connection. Please check and try again.', code: 'offline');
    }
    final status = e.response?.statusCode;
    final data = e.response?.data;
    if (status == 401) {
      onUnauthorized?.call();
      return ApiException('Please sign in again.', code: 'unauthorized', status: 401);
    }
    if (status == 429) return ApiException('Too many attempts. Please wait a minute and try again.', code: 'rate_limited', status: 429);
    if (data is Map && data['errors'] is Map) {
      final fields = <String, String>{};
      (data['errors'] as Map).forEach((k, v) => fields['$k'] = v is List && v.isNotEmpty ? '${v.first}' : '$v');
      return ApiException(fields.values.first, code: 'validation', status: status, fieldErrors: fields);
    }
    if (data is Map && data['error'] is Map) {
      return ApiException('${data['error']['message']}', code: '${data['error']['code']}', status: status);
    }
    return ApiException('Something went wrong. Please try again.', status: status);
  }
}
