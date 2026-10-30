import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:e_commerce/core/network/api_exception.dart';
import 'package:e_commerce/core/utils/validators.dart';
import 'package:e_commerce/features/authintication/login/model/login_model.dart';
import 'package:e_commerce/features/cart/model/cart_model.dart';
import 'package:e_commerce/features/home/model/product_model.dart';
import 'package:e_commerce/features/orders/model/order_model.dart';

void main() {
  group('Validators', () {
    test('email', () {
      expect(Validators.email('a@b.co'), isNull);
      expect(Validators.email('nope'), isNotNull);
      expect(Validators.email(''), isNotNull);
    });

    test('password needs 8 chars with letters and numbers', () {
      expect(Validators.password('abc12'), isNotNull);
      expect(Validators.password('abcdefgh'), isNotNull);
      expect(Validators.password('abcdef12'), isNull);
    });

    test('numbers', () {
      expect(Validators.number('19.99'), isNull);
      expect(Validators.number('x'), isNotNull);
      expect(Validators.integer('3'), isNull);
      expect(Validators.integer('3.5'), isNotNull);
    });
  });

  group('Models', () {
    test('ProductModel parses decimal strings from the API', () {
      final product = ProductModel.fromJson({
        'id': 1,
        'name': 'Shirt',
        'price': '19.99',
        'cost': '10.00',
        'stock': 5,
        'category': 'shirt',
        'image': null,
      });
      expect(product.price, 19.99);
      expect(product.inStock, isTrue);
    });

    test('LoginModel parses the session payload', () {
      final session = LoginModel.fromJson({
        'user': {
          'id': 1,
          'name': 'Admin',
          'email': 'admin@example.com',
          'is_active': true,
          'roles': ['admin'],
        },
        'access_token': 'abc',
        'token_type': 'bearer',
        'expires_in': 3600,
      });
      expect(session.accessToken, 'abc');
      expect(session.user.isAdmin, isTrue);
    });

    test('CartModel parses totals', () {
      final cart = CartModel.fromJson({
        'items': [
          {
            'product_id': 1,
            'name': 'Shirt',
            'price': '10.00',
            'quantity': 2,
            'subtotal': '20.00',
            'available_stock': 5,
          },
        ],
        'total_quantity': 2,
        'total_price': '20.00',
      });
      expect(cart.items.length, 1);
      expect(cart.totalPrice, 20.0);
      expect(cart.isEmpty, isFalse);
    });

    test('OrderModel parses items and status', () {
      final order = OrderModel.fromJson({
        'id': 7,
        'user_id': 2,
        'status': 'pending',
        'total_price': '30.00',
        'items': [
          {
            'id': 1,
            'product_id': null,
            'product_name': 'Shirt',
            'quantity': 3,
            'price': '10.00',
            'subtotal': '30.00',
          },
        ],
      });
      expect(order.itemsCount, 3);
      expect(order.items.first.productId, isNull);
      expect(OrderStatus.canCancel(order.status), isTrue);
      expect(OrderStatus.canCancel(OrderStatus.delivered), isFalse);
    });
  });

  group('ApiException', () {
    test('reads the validation envelope', () {
      final request = RequestOptions(path: '/auth/register');
      final error = DioException(
        requestOptions: request,
        response: Response<dynamic>(
          requestOptions: request,
          statusCode: 422,
          data: {
            'status': false,
            'code': 422,
            'message': 'The given data was invalid.',
            'data': null,
            'errors': {
              'email': ['The email has already been taken.'],
            },
          },
        ),
      );
      final exception = ApiException.from(error);
      expect(exception.statusCode, 422);
      expect(exception.displayMessage, 'The email has already been taken.');
    });
  });
}
