import { useDependencies } from "../../Domain/UseCases/Dependencies";
import { useConnectionStatus } from "../../Domain/UseCases/Realtime/useRealtimeSync";
import type { ConnectionStatus } from "../../Domain/Models/Realtime";

const LABELS: Record<ConnectionStatus, string> = {
  disabled: "Live updates off",
  connecting: "Connecting",
  connected: "Live",
  reconnecting: "Reconnecting",
  failed: "Offline",
};

export function LivePill() {
  const { mode } = useDependencies();
  const status = useConnectionStatus();
  const label = mode === "sync" ? "Sync mode" : LABELS[status];
  const css = mode === "sync" ? "disabled" : status;

  return (
    <span className={`live-pill ${css}`} title={mode === "sync" ? "Synchronous API: no queue, no live updates" : "Asynchronous API with live updates"}>
      <span className="live-dot" />
      {label}
    </span>
  );
}
