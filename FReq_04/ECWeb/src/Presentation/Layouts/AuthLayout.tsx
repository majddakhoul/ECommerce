import { Outlet } from "react-router-dom";

export function AuthLayout() {
  return (
    <div className="auth-shell">
      <div className="auth-art" aria-hidden="true">
        <div className="auth-art-copy">
          <h1>Shop in real time.</h1>
          <p>Stock, carts and orders update the moment they change.</p>
        </div>
      </div>
      <div className="auth-panel">
        <div className="auth-card">
          <div className="brand">Ecommerce</div>
          <Outlet />
        </div>
      </div>
    </div>
  );
}
