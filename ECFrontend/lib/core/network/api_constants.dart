class ApiConstants {
  ApiConstants._();

  static const String _override = String.fromEnvironment('API_BASE_URL');

  static const String _default =
      'https://expectant-grumble-ungraded.ngrok-free.dev';

  static String get origin {
    final value = _override.isNotEmpty ? _override : _default;
    return value.endsWith('/') ? value.substring(0, value.length - 1) : value;
  }

  static String get baseUrl => '$origin/api/v1';

  static String? image(String? url) {
    if (url == null || url.isEmpty) return null;
    final uri = Uri.tryParse(url);
    if (uri == null) return url;
    if (!uri.hasScheme) {
      return '$origin${url.startsWith('/') ? '' : '/'}$url';
    }
    return '$origin${uri.path}';
  }

  static const String register = '/auth/register';
  static const String login = '/auth/login';
  static const String me = '/auth/me';
  static const String password = '/auth/password';
  static const String refresh = '/auth/refresh';
  static const String logout = '/auth/logout';

  static const String users = '/users';
  static String user(int id) => '/users/$id';
  static String unlockUser(int id) => '/users/$id/unlock';
  static String userRoles(int id) => '/users/$id/roles';
  static String userRole(int userId, int roleId) =>
      '/users/$userId/roles/$roleId';

  static const String roles = '/roles';
  static String role(int id) => '/roles/$id';
  static String roleUsers(int id) => '/roles/$id/users';

  static const String products = '/products';
  static const String categories = '/products/categories';
  static String product(int id) => '/products/$id';

  static const String cart = '/cart';
  static const String cartItems = '/cart/items';
  static String cartItem(int productId) => '/cart/items/$productId';
  static const String cartCheckout = '/cart/checkout';

  static const String orders = '/orders';
  static const String myOrders = '/orders/my';
  static String order(int id) => '/orders/$id';
  static String orderStatus(int id) => '/orders/$id/status';
  static String cancelOrder(int id) => '/orders/$id/cancel';
}
