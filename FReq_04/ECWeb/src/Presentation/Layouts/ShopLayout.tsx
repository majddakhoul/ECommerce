import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useSession } from "../../Domain/UseCases/Auth/useSession";
import { useCartCount } from "../../Domain/UseCases/Cart/useCart";
import { IconButton } from "../Components/Button";
import { Icon, type IconName } from "../Components/Icon";
import { LivePill } from "../Components/LivePill";

interface NavItem {
  to: string;
  label: string;
  icon: IconName;
  end?: boolean;
  badge?: number;
}

export function ShopLayout() {
  const { isAdmin } = useSession();
  const cartCount = useCartCount();
  const navigate = useNavigate();

  const items: NavItem[] = [
    { to: "/", label: "Home", icon: "home", end: true },
    { to: "/cart", label: "Cart", icon: "bag", badge: cartCount },
    { to: "/orders", label: "Orders", icon: "receipt" },
    { to: "/profile", label: "Profile", icon: "user" },
    ...(isAdmin ? [{ to: "/admin", label: "Admin", icon: "shield" as IconName }] : []),
  ];

  return (
    <>
      <header className="app-header">
        <div className="container">
          <div className="header-start">
            <nav className="top-nav" aria-label="Main">
              {items.map((item) => (
                <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => (isActive ? "active" : "")}>
                  {item.label}
                </NavLink>
              ))}
            </nav>
          </div>
          <NavLink to="/" className="brand">
            Ecommerce
          </NavLink>
          <div className="header-end">
            <LivePill />
            <IconButton icon="bag" label="Open cart" count={cartCount} onClick={() => navigate("/cart")} />
          </div>
        </div>
      </header>

      <main className="page">
        <div className="container">
          <Outlet />
        </div>
      </main>

      <nav className="bottom-nav" aria-label="Main">
        {items.map((item) => (
          <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => (isActive ? "active" : "")}>
            <Icon name={item.icon} />
            {item.badge ? <span className="badge-dot" style={{ top: 2, right: 8 }}>{item.badge}</span> : null}
            {item.label}
          </NavLink>
        ))}
      </nav>
    </>
  );
}
