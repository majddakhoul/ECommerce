import { createContext, useContext, type ReactNode } from "react";
import type { AuthRepository } from "../Repositories/AuthRepository";
import type { BenchmarkRepository } from "../Repositories/BenchmarkRepository";
import type { CartRepository } from "../Repositories/CartRepository";
import type { OrderRepository } from "../Repositories/OrderRepository";
import type { ProductRepository } from "../Repositories/ProductRepository";
import type { RealtimeGateway } from "../Repositories/RealtimeGateway";
import type { RoleRepository, UserRepository } from "../Repositories/UserRepository";
import type { ApiMode } from "../Models/ApiMode";

export interface Dependencies {
  mode: ApiMode;
  auth: AuthRepository;
  products: ProductRepository;
  cart: CartRepository;
  orders: OrderRepository;
  users: UserRepository;
  roles: RoleRepository;
  realtime: RealtimeGateway;
  benchmark: BenchmarkRepository;
  hasToken: () => boolean;
  clearToken: () => void;
}

const DependencyContext = createContext<Dependencies | null>(null);

export function DependencyProvider({ value, children }: { value: Dependencies; children: ReactNode }) {
  return <DependencyContext.Provider value={value}>{children}</DependencyContext.Provider>;
}

export const useDependencies = (): Dependencies => {
  const value = useContext(DependencyContext);
  if (!value) {
    throw new Error("useDependencies must be used inside DependencyProvider.");
  }
  return value;
};
