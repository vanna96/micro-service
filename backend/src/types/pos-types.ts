export interface Category {
  id: string;
  name: string;
  icon?: string;
}

export interface PosBranch {
  id: string;
  code: string;
  name: string;
  foreignName: string;
  location: string;
}

export interface CurrencyInfo {
  code: string;
  symbol: string;
  decimalPlaces: number;
}

export interface CashTenderInput {
  currencyCode: string;
  amount: number;
}

export interface PaymentTenderInput extends CashTenderInput {
  provider?: string;
  reference?: string;
  metadata?: Record<string, string>;
}

export interface PosSaleTotals {
  subTotal: number;
  discountAmount: number;
  taxAmount: number;
  serviceFee: number;
  totalPayable: number;
}

export interface PosCompanyInfo {
  name: string;
  email: string;
  phone: string;
  address: string;
  receiptFooter: string;
}

export interface VariantValue {
  id: number;
  label: string;
  priceDelta?: number;
}

export interface ProductVariantOption {
  id: number;
  name: string;
  values: VariantValue[];
}

export interface ProductVariant {
  id: number;
  sku: string;
  price: number;
  stock: number;
  isDefault: boolean;
  optionValueIds: number[];
}

export interface ProductUOM {
  id?: number;
  code?: string;
  name: string;
  shortCode: string;
  conversionFactorToBase?: number;
  basePrice?: number;
  reduceByPercent?: number;
  price: number;
  isAutoPrice?: boolean;
  isDefault?: boolean;
}

export type PromotionType = 'item_price' | 'subtotal_discount' | 'bogo';

export interface AppliedPromotion {
  id: number;
  code: string;
  name: string;
  type: PromotionType;
  summary: string;
  savings: number;
}

export interface ProductPromotion {
  id: number;
  code: string;
  name: string;
  type: PromotionType;
  role: 'item' | 'buy' | 'get' | null;
  label: string;
  summary: string;
  promotionalPrice: number | null;
  discountPercent: number | null;
  thresholdAmount: number | null;
  rewardDiscountPercent: number | null;
  buyQuantity: number | null;
  getQuantity: number | null;
  buyItemId: number | null;
  getItemId: number | null;
  buyItemName: string | null;
  getItemName: string | null;
}

export interface PosPromotionAutoAddItem {
  promotion_id: number;
  promotion_name: string;
  item_id: number;
  uom_id: number | null;
  quantity: number;
}

export interface PosCartPricingItem {
  item_id: number;
  item_variant_id: number | null;
  uom_id?: number | null;
  sku: string;
  name: string;
  quantity: number;
  unit_price: number;
  option_total: number;
  line_subtotal: number;
  discount_amount: number;
  line_total: number;
}

export interface PosCartPricing {
  currency_mode: 'native' | 'base';
  items: PosCartPricingItem[];
  subtotal: number;
  discount_total: number;
  final_total: number;
  auto_add_items: PosPromotionAutoAddItem[];
  applied_promotion: AppliedPromotion | null;
}

export interface Product {
  id: string;
  name: string;
  sku: string;
  price: number;
  currency?: CurrencyInfo;
  category: string;
  image: string;
  stock?: number;
  stockStatus: 'instock' | 'outofstock' | 'lowstock';
  brand?: string;
  hasVariants?: boolean;
  variantOptions?: ProductVariantOption[];
  variants?: ProductVariant[];
  hasUOM?: boolean;
  uomList?: ProductUOM[];
  defaultUOM?: string;
  promotions?: ProductPromotion[];
  isPremium?: boolean;
  isFeatured?: boolean;
  isNewArrival?: boolean;
  isTryOnEnabled?: boolean;
  stockControl?: boolean;
  purchase?: boolean;
  sale?: boolean;
}

export interface CartItem {
  id: string;
  product: Product;
  quantity: number;
  selectedUOM?: ProductUOM;
  selectedVariants?: Record<string, VariantValue>;
  selectedVariant?: ProductVariant;
  unitPrice: number;
  promotionReward?: {
    promotionId: number;
    promotionName: string;
  };
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
  fixedAmount: number | null;
  discountPercent: number;
  isDefault: boolean;
}

export type DiscountType = 'percentage' | 'fixed';

export interface PosSaleSnapshot {
  saleId: string;
  invoiceNumber: string;
  orderType: 'Takeaway' | 'Dine-in' | 'Delivery';
  customer: Customer;
  cart: CartItem[];
  discountType: DiscountType;
  discountValue: number;
  taxPercent: number;
  serviceFee: number;
  appliedPromotion?: AppliedPromotion | null;
  promotionDiscountAmount?: number;
}

export interface HeldOrder extends PosSaleSnapshot {
  id: string;
  databaseId: number;
  reference: string;
  subTotal: number;
  totalPayable: number;
  createdAt: string;
  notes?: string;
}

export interface Invoice {
  databaseId: number;
  saleId: string;
  id: string;
  customer: string;
  paymentMethod: string;
  date: string;
  time: string;
  amount: number;
  status: 'Completed' | 'Pending' | 'Refunded';
  snapshot: PosSaleSnapshot;
  subTotal: number;
  discountAmount: number;
  taxAmount: number;
  serviceFee: number;
  totalItemCount: number;
}
