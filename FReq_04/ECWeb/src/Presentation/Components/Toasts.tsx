import { useSyncExternalStore } from "react";
import { notifications } from "../../Services/Notifications";
import { Icon } from "./Icon";

export function Toasts() {
  const toasts = useSyncExternalStore(notifications.subscribe, notifications.snapshot);

  if (toasts.length === 0) {
    return null;
  }

  return (
    <div className="toasts" role="region" aria-live="polite" aria-label="Notifications">
      {toasts.map((toast) => (
        <div key={toast.id} className={`toast ${toast.kind}`}>
          <div className="grow">
            <strong>{toast.title}</strong>
            {toast.message ? <span>{toast.message}</span> : null}
          </div>
          <button className="icon-btn" style={{ width: 26, height: 26 }} aria-label="Dismiss" onClick={() => notifications.dismiss(toast.id)}>
            <Icon name="x" size={14} />
          </button>
        </div>
      ))}
    </div>
  );
}
