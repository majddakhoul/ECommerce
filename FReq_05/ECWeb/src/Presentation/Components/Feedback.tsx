import type { ReactNode } from "react";
import { toApiError } from "../../Domain/Models/ApiError";
import { Button } from "./Button";

export function Spinner() {
  return <div className="spinner" role="status" aria-label="Loading" />;
}

export function LoadingBlock({ label = "Loading" }: { label?: string }) {
  return (
    <div className="empty">
      <Spinner />
      <span>{label}</span>
    </div>
  );
}

export function EmptyState({ title, message, action }: { title: string; message?: string; action?: ReactNode }) {
  return (
    <div className="empty">
      <h3>{title}</h3>
      {message ? <p>{message}</p> : null}
      {action}
    </div>
  );
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  return (
    <div className="empty">
      <h3>Something went wrong</h3>
      <p>{toApiError(error).message}</p>
      {onRetry ? (
        <Button variant="outline" small onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </div>
  );
}

export function SkeletonGrid({ count = 8 }: { count?: number }) {
  return (
    <div className="product-grid">
      {Array.from({ length: count }, (_, index) => (
        <div key={index} className="skeleton" style={{ aspectRatio: "1 / 1.2" }} />
      ))}
    </div>
  );
}
