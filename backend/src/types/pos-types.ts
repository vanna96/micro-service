export interface Category {
  id: string;
  name: string;
  icon?: string;
}

export interface VariantValue {
  label: string;
  priceDelta?: number;
}

export interface ProductVariantOption {
  name: string;
  values: VariantValue[];
}

export interface ProductUOM {
  name: string;
  shortCode: string;
  price: number;
  isDefault?: boolean;
}

export interface Product {
  id: string;
  name: string;
  sku: string;
  price: number;
  category: string;
  image: string;
  stockStatus: 'instock' | 'outofstock' | 'lowstock';
  brand?: string;
  hasVariants?: boolean;
  variantOptions?: ProductVariantOption[];
  hasUOM?: boolean;
  uomList?: ProductUOM[];
  defaultUOM?: string;
}

export interface CartItem {
  id: string;
  product: Product;
  quantity: number;
  selectedUOM?: ProductUOM;
  selectedVariants?: Record<string, VariantValue>;
  unitPrice: number;
}

export interface Customer {
  id: string;
  code: string;
  name: string;
  email: string;
  phone: string;
  status: string;
  avatar: string;
  priceListId: string | null;
  priceList: CustomerPriceList | null;
}

export interface CustomerPriceList {
  id: string;
  code: string;
  name: string;
  pricingMethod: "fixed" | "discount" | null;
  discountPercent: number;
  isDefault: boolean;
}

export interface HeldOrder {
  id: string;
  reference: string;
  orderType: 'Takeaway' | 'Dine-in' | 'Delivery';
  customer: Customer;
  cart: CartItem[];
  subTotal: number;
  discountPercent: number;
  taxPercent: number;
  serviceFee: number;
  totalPayable: number;
  createdAt: string;
  notes?: string;
}

export interface Invoice {
  id: string;
  customer: string;
  paymentMethod: 'Cash' | 'Card' | 'UPI' | 'Bank Transfer';
  date: string;
  time: string;
  amount: number;
  status: 'Completed' | 'Pending' | 'Refunded';
}
