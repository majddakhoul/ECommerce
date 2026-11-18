import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import type { Credentials, Registration, User } from "../../Models/User";
import { isAdmin } from "../../Rules/access";
import { sessionEvents } from "../../../Services/SessionEvents";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";

interface SessionValue {
  user: User | null;
  isAuthenticated: boolean;
  isAdmin: boolean;
  isLoading: boolean;
  login: (credentials: Credentials) => Promise<User>;
  register: (registration: Registration) => Promise<User>;
  logout: () => Promise<void>;
}

const SessionContext = createContext<SessionValue | null>(null);

const REFRESH_INTERVAL_MS = 25 * 60 * 1000;

export function SessionProvider({ children }: { children: ReactNode }) {
  const { auth, hasToken, clearToken } = useDependencies();
  const queryClient = useQueryClient();
  const [tokenPresent, setTokenPresent] = useState<boolean>(() => hasToken());

  const meQuery = useQuery({
    queryKey: keys.me,
    queryFn: () => auth.me(),
    enabled: tokenPresent,
    retry: false,
    staleTime: 60_000,
  });

  const reset = useCallback(() => {
    clearToken();
    setTokenPresent(false);
    queryClient.clear();
  }, [clearToken, queryClient]);

  useEffect(() => sessionEvents.onUnauthorized(reset), [reset]);

  useEffect(() => {
    if (!tokenPresent) {
      return undefined;
    }
    const timer = setInterval(() => {
      auth.refresh().catch(() => undefined);
    }, REFRESH_INTERVAL_MS);
    return () => clearInterval(timer);
  }, [auth, tokenPresent]);

  const loginMutation = useMutation({
    mutationFn: (credentials: Credentials) => auth.login(credentials),
    meta: { silent: true },
    onSuccess: (session) => {
      queryClient.clear();
      queryClient.setQueryData(keys.me, session.user);
      setTokenPresent(true);
    },
  });

  const registerMutation = useMutation({
    mutationFn: (registration: Registration) => auth.register(registration),
    meta: { silent: true },
    onSuccess: (session) => {
      queryClient.clear();
      queryClient.setQueryData(keys.me, session.user);
      setTokenPresent(true);
    },
  });

  const logout = useCallback(async () => {
    try {
      await auth.logout();
    } catch {
      clearToken();
    }
    setTokenPresent(false);
    queryClient.clear();
  }, [auth, clearToken, queryClient]);

  const user = tokenPresent ? (meQuery.data ?? null) : null;

  const value = useMemo<SessionValue>(
    () => ({
      user,
      isAuthenticated: user !== null,
      isAdmin: isAdmin(user),
      isLoading: tokenPresent && meQuery.isPending,
      login: async (credentials) => (await loginMutation.mutateAsync(credentials)).user,
      register: async (registration) => (await registerMutation.mutateAsync(registration)).user,
      logout,
    }),
    [user, tokenPresent, meQuery.isPending, loginMutation, registerMutation, logout],
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export const useSession = (): SessionValue => {
  const value = useContext(SessionContext);
  if (!value) {
    throw new Error("useSession must be used inside SessionProvider.");
  }
  return value;
};
