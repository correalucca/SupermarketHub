export interface User {
  id: number;
  name: string;
  email: string;
}

export interface Product {
  id: number;
  sku: string;
  name: string;
  price: number;
  category: string;
  stock_quantity: number;
  created_at?: string;
  updated_at?: string;
}

export interface ProductFormData {
  sku: string;
  name: string;
  price: number;
  category: string;
  stock_quantity: number;
}

export interface SaleItem {
  product_id: number;
  quantity: number;
}

export interface SaleItemData {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  subtotal: number;
  product: Product;
}

export interface Sale {
  id: number;
  total: number;
  status: string;
  items: SaleItemData[];
  created_at?: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
