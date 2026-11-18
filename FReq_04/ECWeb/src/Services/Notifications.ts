export type ToastKind = "info" | "success" | "warning" | "danger";

export interface Toast {
  id: number;
  kind: ToastKind;
  title: string;
  message?: string;
}

type Listener = () => void;

class NotificationCenter {
  private toasts: Toast[] = [];
  private listeners = new Set<Listener>();
  private counter = 0;

  readonly subscribe = (listener: Listener): (() => void) => {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  };

  readonly snapshot = (): Toast[] => this.toasts;

  notify(kind: ToastKind, title: string, message?: string, lifetimeMs = 5000): number {
    const id = ++this.counter;
    this.toasts = [...this.toasts.slice(-4), { id, kind, title, message }];
    this.emit();
    if (lifetimeMs > 0) {
      setTimeout(() => this.dismiss(id), lifetimeMs);
    }
    return id;
  }

  dismiss(id: number): void {
    this.toasts = this.toasts.filter((toast) => toast.id !== id);
    this.emit();
  }

  clear(): void {
    this.toasts = [];
    this.emit();
  }

  private emit(): void {
    this.listeners.forEach((listener) => listener());
  }
}

export const notifications = new NotificationCenter();

export const notify = {
  info: (title: string, message?: string) => notifications.notify("info", title, message),
  success: (title: string, message?: string) => notifications.notify("success", title, message),
  warning: (title: string, message?: string) => notifications.notify("warning", title, message),
  danger: (title: string, message?: string) => notifications.notify("danger", title, message),
};
