export type Category = {
  id: number;
  name: string;
  slug: string;
  image_url: string;
};

export type Product = {
  id: number;
  category_id: number;
  name: string;
  slug: string;
  description: string;
  unit: string;
  price: string | number;
  compare_at_price: string | number | null;
  discount_label: string | null;
  badge: string | null;
  image_url: string;
  rating: string | number;
  reviews_count: number;
  sold_count: number;
  stock_status: string;
  is_featured: boolean;
  category?: Category;
};

export type Coupon = {
  id: number;
  code: string;
  title: string;
  description: string;
  discount_type: "fixed" | "percent";
  discount_value: string | number;
  minimum_subtotal: string | number;
};

export type Address = {
  id: number;
  type: string;
  line: string;
  is_default: boolean;
};

export type DeliveryOption = {
  id: string;
  name: string;
  description: string;
  fee: number;
};

export type PaymentMethod = {
  id: string;
  name: string;
  detail: string;
};

export type StorefrontData = {
  brand: {
    name: string;
    tagline: string;
    location: string;
  };
  categories: Category[];
  products: Product[];
  coupons: Coupon[];
  addresses: Address[];
  delivery_options: DeliveryOption[];
  payment_methods: PaymentMethod[];
};
