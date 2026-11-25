export interface Product {
  id: number;
  name: string;
  description: string | null;
  price: number;
  cost: number | null;
  stock: number;
  inStock: boolean;
  category: string | null;
  image: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface ProductFilter {
  search?: string;
  category?: string;
  inStock?: boolean;
  page?: number;
  perPage?: number;
}

export interface ProductDraft {
  name: string;
  description: string;
  price: number;
  cost: number;
  stock: number;
  category: string;
  image?: File | null;
}
