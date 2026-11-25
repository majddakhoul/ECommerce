import type { ButtonHTMLAttributes, ReactNode } from "react";
import { Icon, type IconName } from "./Icon";

type Variant = "black" | "purple" | "outline" | "danger" | "ghost";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  small?: boolean;
  block?: boolean;
  pill?: boolean;
  loading?: boolean;
  icon?: IconName;
  children?: ReactNode;
}

const VARIANT_CLASS: Record<Variant, string> = {
  black: "",
  purple: "btn-purple",
  outline: "btn-outline",
  danger: "btn-danger",
  ghost: "btn-ghost",
};

export function Button({ variant = "black", small, block, pill, loading, icon, children, className, disabled, type = "button", ...rest }: ButtonProps) {
  const classes = ["btn", VARIANT_CLASS[variant], small && "btn-sm", block && "btn-block", pill && "btn-pill", className]
    .filter(Boolean)
    .join(" ");

  return (
    <button type={type} className={classes} disabled={disabled || loading} {...rest}>
      {loading ? <span className="spinner" style={{ width: 18, height: 18, borderWidth: 2 }} /> : icon ? <Icon name={icon} size={small ? 16 : 18} /> : null}
      {children}
    </button>
  );
}

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  icon: IconName;
  label: string;
  count?: number;
}

export function IconButton({ icon, label, count, className, type = "button", ...rest }: IconButtonProps) {
  return (
    <button type={type} className={["icon-btn", className].filter(Boolean).join(" ")} aria-label={label} title={label} {...rest}>
      <Icon name={icon} />
      {count ? <span className="badge-dot">{count > 99 ? "99+" : count}</span> : null}
    </button>
  );
}
