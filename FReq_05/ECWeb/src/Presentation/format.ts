export const formatDateTime = (value: string | null | undefined): string => {
  if (!value) {
    return "-";
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? "-"
    : date.toLocaleString("en-US", { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
};

export const capitalize = (value: string): string => value.charAt(0).toUpperCase() + value.slice(1);

export const initialOf = (name: string): string => name.trim().charAt(0).toUpperCase() || "?";
