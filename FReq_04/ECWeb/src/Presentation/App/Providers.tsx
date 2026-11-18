import { QueryClientProvider, type QueryClient } from "@tanstack/react-query";
import type { ReactNode } from "react";
import type { Dependencies } from "../../Domain/UseCases/Dependencies";
import { DependencyProvider } from "../../Domain/UseCases/Dependencies";
import { SessionProvider } from "../../Domain/UseCases/Auth/useSession";
import { useRealtimeSync } from "../../Domain/UseCases/Realtime/useRealtimeSync";
import { Toasts } from "../Components/Toasts";

function RealtimeSync() {
  useRealtimeSync();
  return null;
}

interface ProvidersProps {
  dependencies: Dependencies;
  queryClient: QueryClient;
  children: ReactNode;
}

export function Providers({ dependencies, queryClient, children }: ProvidersProps) {
  return (
    <DependencyProvider value={dependencies}>
      <QueryClientProvider client={queryClient}>
        <SessionProvider>
          <RealtimeSync />
          {children}
          <Toasts />
        </SessionProvider>
      </QueryClientProvider>
    </DependencyProvider>
  );
}
