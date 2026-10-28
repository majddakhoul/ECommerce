import 'package:flutter/foundation.dart';
import 'package:get/get.dart';

class NavController {
  NavController._();

  static const int home = 0;
  static const int cart = 1;
  static const int orders = 2;
  static const int profile = 3;
  static const int admin = 4;

  static final ValueNotifier<int> index = ValueNotifier<int>(home);

  static void goTo(int tab) {
    Get.until((route) => route.isFirst);
    index.value = tab;
  }
}
