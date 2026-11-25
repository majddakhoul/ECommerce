import { BrowserRouter } from "react-router-dom";
import { useMemo } from "react";
import { Config } from "../../Services/Config";
import { Providers } from "./Providers";
import { buildDependencies } from "./container";
import { createQueryClient } from "./queryClient";
import { AppRoutes } from "./routes";

export function App() {
  const dependencies = useMemo(() => buildDependencies(Config), []);
  const queryClient = useMemo(() => createQueryClient(), []);

  return (
    <Providers dependencies={dependencies} queryClient={queryClient}>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </Providers>
  );
}
