import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useSession } from "../../Domain/UseCases/Auth/useSession";
import { LoadingBlock } from "../Components/Feedback";

export function RequireAuth() {
  const { isAuthenticated, isLoading } = useSession();
  const location = useLocation();

  if (isLoading) {
    return <LoadingBlock />;
  }
  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }
  return <Outlet />;
}

export function RequireAdmin() {
  const { isAdmin, isLoading } = useSession();

  if (isLoading) {
    return <LoadingBlock />;
  }
  if (!isAdmin) {
    return <Navigate to="/" replace />;
  }
  return <Outlet />;
}

export function GuestOnly() {
  const { isAuthenticated, isLoading } = useSession();

  if (isLoading) {
    return <LoadingBlock />;
  }
  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }
  return <Outlet />;
}
