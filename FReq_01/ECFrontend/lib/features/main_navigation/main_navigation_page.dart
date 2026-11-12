import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:e_commerce/features/admin/admin_page.dart';
import 'package:e_commerce/features/authintication/bloc/auth_cubit/auth_cubit.dart';
import 'package:e_commerce/features/authintication/bloc/auth_state/auth_state.dart';
import 'package:e_commerce/features/cart/bloc/cubit/cart_cubit.dart';
import 'package:e_commerce/features/cart/bloc/state/cart_state.dart';
import 'package:e_commerce/features/cart/cart_page.dart';
import 'package:e_commerce/features/home/home_page.dart';
import 'package:e_commerce/features/main_navigation/nav_controller.dart';
import 'package:e_commerce/features/orders/page/orders_page.dart';
import 'package:e_commerce/features/profile/profile_page.dart';

class MainNavigation extends StatefulWidget {
  const MainNavigation({super.key});

  @override
  State<MainNavigation> createState() => _MainNavigationState();
}

class _MainNavigationState extends State<MainNavigation> {
  @override
  void initState() {
    super.initState();
    NavController.index.value = NavController.home;
    context.read<CartCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<AuthCubit, AuthState>(
      builder: (context, authState) {
        final isAdmin = authState is AuthSuccess && authState.user.isAdmin;
        return _buildScaffold(isAdmin);
      },
    );
  }

  Widget _buildScaffold(bool isAdmin) {
    final pages = <Widget>[
      const HomePage(),
      const CartPage(),
      const OrdersPage(),
      const ProfilePage(),
      if (isAdmin) const AdminPage(),
    ];

    return ValueListenableBuilder<int>(
      valueListenable: NavController.index,
      builder: (context, value, _) {
        final index = value >= pages.length ? 0 : value;

        return Scaffold(
          body: IndexedStack(index: index, children: pages),
          bottomNavigationBar: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: (i) => NavController.index.value = i,
            destinations: [
              const NavigationDestination(
                icon: Icon(Icons.home_outlined),
                selectedIcon: Icon(Icons.home_rounded),
                label: 'Home',
              ),
              NavigationDestination(
                icon: BlocBuilder<CartCubit, CartState>(
                  builder: (context, state) {
                    final count = context.read<CartCubit>().count;
                    return Badge(
                      isLabelVisible: count > 0,
                      label: Text('$count'),
                      child: const Icon(Icons.shopping_bag_outlined),
                    );
                  },
                ),
                selectedIcon: const Icon(Icons.shopping_bag_rounded),
                label: 'Cart',
              ),
              const NavigationDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long_rounded),
                label: 'Orders',
              ),
              const NavigationDestination(
                icon: Icon(Icons.person_outline_rounded),
                selectedIcon: Icon(Icons.person_rounded),
                label: 'Profile',
              ),
              if (isAdmin)
                const NavigationDestination(
                  icon: Icon(Icons.admin_panel_settings_outlined),
                  selectedIcon: Icon(Icons.admin_panel_settings_rounded),
                  label: 'Admin',
                ),
            ],
          ),
        );
      },
    );
  }
}
