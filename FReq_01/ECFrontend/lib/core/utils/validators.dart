class Validators {
  Validators._();

  static String? notEmpty(String? value, [String label = 'This field']) {
    if (value == null || value.trim().isEmpty) return '$label is required';
    return null;
  }

  static String? email(String? value) {
    if (value == null || value.trim().isEmpty) return 'Email is required';
    final regex = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');
    if (!regex.hasMatch(value.trim())) return 'Enter a valid email';
    return null;
  }

  static String? password(String? value) {
    if (value == null || value.isEmpty) return 'Password is required';
    if (value.length < 8) return 'Password must be at least 8 characters';
    if (!RegExp(r'[A-Za-z]').hasMatch(value) ||
        !RegExp(r'[0-9]').hasMatch(value)) {
      return 'Password must contain letters and numbers';
    }
    return null;
  }

  static String? optionalPassword(String? value) {
    if (value == null || value.isEmpty) return null;
    return password(value);
  }

  static String? number(String? value, [String label = 'Value']) {
    if (value == null || value.trim().isEmpty) return '$label is required';
    final parsed = double.tryParse(value.trim());
    if (parsed == null) return '$label must be a number';
    if (parsed < 0) return '$label cannot be negative';
    return null;
  }

  static String? integer(String? value, [String label = 'Value']) {
    if (value == null || value.trim().isEmpty) return '$label is required';
    final parsed = int.tryParse(value.trim());
    if (parsed == null) return '$label must be a whole number';
    if (parsed < 0) return '$label cannot be negative';
    return null;
  }
}
