export interface ApiMeta {
  page?: number;
  limit?: number;
  total?: number;
}

export interface ApiResponse<T> {
  ok: boolean;
  data: T;
  meta?: ApiMeta;
  error?: string;
  detail?: string;
}

export type PaginatedResponse<T> = ApiResponse<T> & { meta: Required<Pick<ApiMeta, 'page' | 'limit'>> & Partial<ApiMeta> };

export interface Category {
  category_id: number;
  name: string;
  slug: string;
  description: string | null;
  created_at: string;
  updated_at: string;
}

export interface Artisan {
  artisan_id: number;
  workshop_name: string;
  contact_name: string | null;
  email: string | null;
  phone: string | null;
  region: string | null;
  bio: string | null;
  instagram: string | null;
  created_at: string;
  updated_at: string;
}

export interface Product {
  product_id: number;
  sku: string;
  name: string;
  category_id: number;
  artisan_id: number;
  category?: string;
  artisan?: string;
  price: number;
  stock: number;
  badge_label: string | null;
  description: string | null;
  image_url: string | null;
  is_active: 0 | 1;
  created_at?: string;
  updated_at?: string;
}

export type ProductListItem = Pick<Product, 'product_id' | 'sku' | 'name' | 'price' | 'stock' | 'badge_label' | 'description' | 'image_url' | 'is_active'> &
  Required<Pick<Product, 'category' | 'artisan'>>;

export interface CatalogItem {
  product_id: number;
  sku: string;
  name: string;
  category: string;
  artisan: string;
  price: number;
  stock: number;
  badge_label: string;
  description: string | null;
  image_url: string | null;
}

export interface LowStockProduct {
  product_id: number;
  sku: string;
  name: string;
  artisan: string;
  stock: number;
  price: number;
}

export interface InventoryOverview {
  total_products: number;
  total_units: number;
  total_value_usd: number;
}

export interface CategoryTotalsRow {
  category: string;
  product_count: number;
  units_available: number;
  inventory_value: number;
}

export interface Customer {
  customer_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  province: string | null;
  city: string | null;
  address_line: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateProductPayload {
  sku: string;
  name: string;
  category_slug: string;
  artisan_workshop: string;
  price: number;
  stock: number;
  badge_label?: string | null;
  description?: string | null;
  image_url?: string | null;
}

export interface PingStatus {
  ok: boolean;
  service: string;
  time: string;
}

export type CartStatus = 'open' | 'converted' | 'abandoned';

export interface CartItemDetail {
  cart_item_id: number;
  product_id: number;
  quantity: number;
  sku: string;
  name: string;
  price: number;
  line_total: number;
}

export interface CartListItem {
  cart_id: number;
  customer_id: number | null;
  status: CartStatus;
  created_at: string;
  updated_at: string;
  customer_email: string | null;
}

export interface CartSummary {
  cart_id: number;
  customer_id: number | null;
  status: CartStatus;
  created_at: string;
  updated_at: string;
  first_name?: string | null;
  last_name?: string | null;
  email?: string | null;
  items: CartItemDetail[];
  subtotal: number;
  total_items: number;
}

export interface CreateCartPayload {
  customer_id?: number;
  customer_email?: string;
  status?: CartStatus;
}

export interface UpdateCartPayload {
  status?: CartStatus;
  customer_id?: number;
  customer_email?: string;
}

export interface AddCartItemPayload {
  cart_id: number;
  product_id?: number;
  sku?: string;
  quantity: number;
}

export interface UpdateCartItemPayload {
  cart_item_id: number;
  quantity: number;
}

export interface OrderItem {
  order_item_id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  line_total: number;
  sku: string;
  name: string;
}

export interface OrderListItem {
  order_id: number;
  order_number: string;
  status: 'pending' | 'paid' | 'fulfilled' | 'shipped' | 'completed' | 'cancelled';
  payment_status: 'pending' | 'paid' | 'refunded' | 'failed';
  subtotal: number;
  tax: number;
  shipping_cost: number;
  total: number;
  currency: string;
  placed_at: string;
  updated_at: string;
  customer_email: string | null;
}

export interface OrderDetail extends OrderListItem {
  customer_id: number;
  cart_id: number | null;
  notes?: string | null;
  first_name?: string;
  last_name?: string;
  email?: string;
  items: OrderItem[];
}

export interface CreateOrderPayload {
  cart_id: number;
  tax?: number;
  shipping_cost?: number;
  currency?: string;
  status?: 'pending' | 'paid' | 'fulfilled' | 'shipped' | 'completed' | 'cancelled';
  payment_status?: 'pending' | 'paid' | 'refunded' | 'failed';
  notes?: string;
}

export interface CustomerPayload {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string | null;
  province?: string | null;
  city?: string | null;
  address_line?: string | null;
}

export interface UpdateCustomerPayload extends Partial<CustomerPayload> {}
