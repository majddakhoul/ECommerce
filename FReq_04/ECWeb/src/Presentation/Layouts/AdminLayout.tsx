import { NavLink, Outlet } from "react-router-dom";
import { useSession } from "../../Domain/UseCases/Auth/useSession";
import { Icon, type IconName } from "../Components/Icon";
import { LivePill } from "../Components/LivePill";

const LINKS: { to: string; label: string; icon: IconName; end?: boolean }[] = [
  { to: "/admin", label: "Dashboard", icon: "grid", end: true },
  { to: "/admin/products", label: "Products", icon: "box" },
  { to: "/admin/orders", label: "Orders", icon: "receipt" },
  { to: "/admin/users", label: "Users", icon: "users" },
  { to: "/admin/roles", label: "Roles", icon: "shield" },
  { to: "/admin/lab", label: "Lab", icon: "lab" },
];

export function AdminLayout() {
  const { user, logout } = useSession();

  return (
    <div className="admin-shell">
      <aside className="admin-side">
        <div className="brand">Ecommerce</div>
        {LINKS.map((link) => (
          <NavLink key={link.to} to={link.to} end={link.end} className={({ isActive }) => (isActive ? "active" : "")}>
            <Icon name={link.icon} size={20} />
            {link.label}
          </NavLink>
        ))}
        <div className="spacer" />
        <NavLink to="/">
          <Icon name="arrowLeft" size={20} />
          Back to shop
        </NavLink>
        <a href="#logout" onClick={(event) => { event.preventDefault(); void logout(); }}>
          <Icon name="logout" size={20} />
          Sign out
        </a>
      </aside>

      <div style={{ minWidth: 0 }}>
        <div className="admin-top">
          <div className="stack" style={{ gap: 2 }}>
            <strong>{user?.name}</strong>
            <span className="muted" style={{ fontSize: 12 }}>
              {user?.email}
            </span>
          </div>
          <div className="row">
            <LivePill />
            <NavLink to="/" className="btn btn-outline btn-sm" style={{ height: 38 }}>
              Shop
            </NavLink>
          </div>
        </div>
        <nav className="admin-tabs" style={{ padding: "10px 20px", background: "#fff", borderBottom: "1px solid var(--border)" }} aria-label="Admin">
          {LINKS.map((link) => (
            <NavLink key={link.to} to={link.to} end={link.end} className={({ isActive }) => (isActive ? "active" : "")}>
              {link.label}
            </NavLink>
          ))}
        </nav>
        <main className="admin-main">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
