export interface CartItem {
  productId: number;
  name: string;
  price: number;
  quantity: number;
  subtotal: number;
  availableStock: number;
}

export interface Cart {
  items: CartItem[];
  totalQuantity: number;
  totalPrice: number;
}

export const emptyCart = (): Cart => ({ items: [], totalQuantity: 0, totalPrice: 0 });
