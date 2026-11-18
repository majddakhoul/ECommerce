import { MutationCache, QueryClient } from "@tanstack/react-query";
import { toApiError } from "../../Domain/Models/ApiError";
import { notify } from "../../Services/Notifications";

export const createQueryClient = (): QueryClient =>
  new QueryClient({
    mutationCache: new MutationCache({
      onError: (error, _variables, _context, mutation) => {
        if (mutation.meta?.silent) {
          return;
        }
        const apiError = toApiError(error);
        if (apiError.isUnauthorized) {
          return;
        }
        notify.danger("Action failed", apiError.message);
      },
    }),
    defaultOptions: {
      queries: {
        staleTime: 15_000,
        refetchOnWindowFocus: false,
        retry: (count, error) => {
          const status = toApiError(error).status;
          return count < 2 && (status === 0 || status >= 500);
        },
      },
    },
  });
