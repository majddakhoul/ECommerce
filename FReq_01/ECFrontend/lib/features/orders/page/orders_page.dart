import 'package:flutter/material.dart';
import 'package:e_commerce/features/orders/widget/orders_list_view.dart';
import 'package:e_commerce/core/widgets/app_top_bar.dart';

class OrdersPage extends StatelessWidget {
  const OrdersPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appTopBar('MY ORDERS'),
      body: const OrdersListView(),
    );
  }
}
