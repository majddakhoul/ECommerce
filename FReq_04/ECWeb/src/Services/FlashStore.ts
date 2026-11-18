type Listener = () => void;

export class FlashStore {
  private flashing = new Set<number>();
  private listeners = new Set<Listener>();
  private timers = new Map<number, ReturnType<typeof setTimeout>>();

  constructor(private readonly durationMs = 1400) {}

  readonly subscribe = (listener: Listener): (() => void) => {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  };

  readonly snapshot = (): ReadonlySet<number> => this.flashing;

  flash(productId: number): void {
    const next = new Set(this.flashing);
    next.add(productId);
    this.flashing = next;
    this.emit();

    const existing = this.timers.get(productId);
    if (existing) {
      clearTimeout(existing);
    }
    this.timers.set(
      productId,
      setTimeout(() => {
        const after = new Set(this.flashing);
        after.delete(productId);
        this.flashing = after;
        this.timers.delete(productId);
        this.emit();
      }, this.durationMs),
    );
  }

  private emit(): void {
    this.listeners.forEach((listener) => listener());
  }
}

export const flashStore = new FlashStore();
