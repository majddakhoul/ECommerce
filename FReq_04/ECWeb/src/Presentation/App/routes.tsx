import { Navigate, Route, Routes } from "react-router-dom";
import { AdminLayout } from "../Layouts/AdminLayout";
import { AuthLayout } from "../Layouts/AuthLayout";
import { GuestOnly, RequireAdmin, RequireAuth } from "../Layouts/Guards";
import { ShopLayout } from "../Layouts/ShopLayout";
import { DashboardPage } from "../Pages/Admin/DashboardPage";
import { LabPage } from "../Pages/Admin/LabPage";
import { OrdersAdminPage } from "../Pages/Admin/OrdersAdminPage";
import { ProductsAdminPage } from "../Pages/Admin/ProductsAdminPage";
import { RolesAdminPage } from "../Pages/Admin/RolesAdminPage";
import { UsersAdminPage } from "../Pages/Admin/UsersAdminPage";
import { LoginPage, RegisterPage } from "../Pages/Auth/AuthPages";
import { CartPage } from "../Pages/Shop/CartPage";
import { HomePage } from "../Pages/Shop/HomePage";
import { OrdersPage } from "../Pages/Shop/OrdersPage";
import { ProductDetailPage } from "../Pages/Shop/ProductDetailPage";
import { ProfilePage } from "../Pages/Shop/ProfilePage";

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<GuestOnly />}>
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
        </Route>
      </Route>

      <Route element={<RequireAuth />}>
        <Route element={<ShopLayout />}>
          <Route index element={<HomePage />} />
          <Route path="/products/:id" element={<ProductDetailPage />} />
          <Route path="/cart" element={<CartPage />} />
          <Route path="/orders" element={<OrdersPage />} />
          <Route path="/profile" element={<ProfilePage />} />
        </Route>

        <Route element={<RequireAdmin />}>
          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<DashboardPage />} />
            <Route path="products" element={<ProductsAdminPage />} />
            <Route path="orders" element={<OrdersAdminPage />} />
            <Route path="users" element={<UsersAdminPage />} />
            <Route path="roles" element={<RolesAdminPage />} />
            <Route path="lab" element={<LabPage />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
