export const LOW_STOCK_THRESHOLD = 10;

export type StockLevel = "out" | "low" | "ok";

export const stockLevel = (stock: number): StockLevel => {
  if (stock <= 0) {
    return "out";
  }
  return stock <= LOW_STOCK_THRESHOLD ? "low" : "ok";
};

export const shouldApplyStockEvent = (lastAppliedVersion: number | undefined, incomingVersion: number): boolean =>
  incomingVersion === 0 || lastAppliedVersion === undefined || incomingVersion >= lastAppliedVersion;
