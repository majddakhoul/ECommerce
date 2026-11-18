type Listener = () => void;

const unauthorizedListeners = new Set<Listener>();

export const sessionEvents = {
  onUnauthorized(listener: Listener): () => void {
    unauthorizedListeners.add(listener);
    return () => {
      unauthorizedListeners.delete(listener);
    };
  },
  emitUnauthorized(): void {
    unauthorizedListeners.forEach((listener) => listener());
  },
};
