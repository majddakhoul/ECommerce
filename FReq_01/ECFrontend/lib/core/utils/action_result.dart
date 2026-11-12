class ActionResult<T> {
  final T? data;
  final String? error;

  const ActionResult._(this.data, this.error);

  factory ActionResult.success([T? data]) => ActionResult._(data, null);

  factory ActionResult.failure(String error) => ActionResult._(null, error);

  bool get ok => error == null;
}
