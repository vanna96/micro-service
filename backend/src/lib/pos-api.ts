import { CashTenderInput, CartItem, Category, CurrencyInfo, Customer, CustomerPriceList, HeldOrder, Invoice, PaymentTenderInput, PosBranch, PosCartPricing, PosCompanyInfo, PosSaleSnapshot, PosSaleTotals, Product } from "@/types/pos-types";

interface ApiEnvelope<T> {
  success: boolean;
  data: T;
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface PosProductsPage {
  products: Product[];
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
  hasMore: boolean;
}

export interface FetchPosProductsOptions {
  page?: number;
  perPage?: number;
  categoryId?: string;
  branchId?: string;
  search?: string;
  sort?: string;
  tryOn?: boolean;
  featured?: boolean;
  premium?: boolean;
  newArrival?: boolean;
}

interface ApiBranch {
  id: number;
  code: string;
  name: string;
  foreign_name: string;
  location: string;
}

interface ApiCategory {
  id: number;
  name: string;
  image_url: string | null;
  thumbnail_url: string | null;
}

interface ApiOptionValue {
  id: number;
  name: string;
  price_adjustment: number;
}

interface ApiOptionGroup {
  id: number;
  name: string;
  type: string;
  values: ApiOptionValue[];
}

interface ApiVariant {
  id: number;
  sku: string;
  resolved_price: number;
  stock: number;
  is_default: boolean;
  option_value_ids: number[];
}

interface ApiUomUnit {
  id: number;
  name: string;
  code: string;
  symbol: string;
  conversion_factor_to_base?: number;
  base_price: number;
  reduce_by_percent: number;
  price: number;
  is_auto: boolean;
  is_base_unit: boolean;
}

interface ApiProduct {
  id: number;
  sku: string;
  name: string;
  item_type: "uom" | "variation";
  price: number;
  currency?: { code: string; symbol: string; decimal_places: number } | null;
  stock: number;
  image_url: string | null;
  thumbnail_url: string | null;
  category: { id: number; name: string } | null;
  uom_group: { units: ApiUomUnit[] } | null;
  option_groups: ApiOptionGroup[];
  variants: ApiVariant[];
  is_premium?: boolean;
  is_featured?: boolean;
  is_new_arrival?: boolean;
  is_try_on_enabled?: boolean;
  stock_control?: boolean;
  purchase?: boolean;
  sale?: boolean;
  promotions?: Array<{
    id: number;
    code: string;
    name: string;
    type: "item_price" | "subtotal_discount" | "bogo";
    role: "item" | "buy" | "get" | null;
    label: string;
    summary: string;
    promotional_price: number | null;
    discount_percent: number | null;
    threshold_amount: number | null;
    reward_discount_percent: number | null;
    buy_quantity: number | null;
    get_quantity: number | null;
    buy_item_id: number | null;
    get_item_id: number | null;
    buy_item_name: string | null;
    get_item_name: string | null;
  }>;
}

interface ApiCustomer {
  id: number;
  code: string;
  name: string;
  email: string;
  phone: string;
  profile_image_url: string | null;
  price_list_id: number | null;
}

interface ApiPriceList {
  id: number;
  code: string;
  name: string;
  pricing_method: "fixed" | "discount" | null;
  fixed_amount: number | null;
  discount_percent: number;
  is_default: boolean;
}

interface ApiCurrency {
  code: string;
  symbol: string;
  decimal_places: number;
}

interface ApiPosCustomers {
  customers: ApiCustomer[];
  price_lists: ApiPriceList[];
  currency?: ApiCurrency | null;
  currencies?: ApiCurrency[];
  currency_rates?: Record<string, number>;
  company?: {
    name?: string;
    email?: string;
    phone?: string;
    address?: string;
    receipt_footer?: string;
  };
}

export interface PosCatalog {
  categories: Category[];
  products: Product[];
}

export interface PosCustomerData {
  customers: Customer[];
  priceLists: CustomerPriceList[];
  currency: CurrencyInfo | null;
  currencies: CurrencyInfo[];
  currencyRates: Record<string, number>;
  company: PosCompanyInfo;
}

interface ApiPosSale {
  id: number;
  sale_id: string;
  status: "current" | "held" | "completed";
  reference: string;
  notes: string;
  snapshot: Partial<PosSaleSnapshot> & Partial<Pick<HeldOrder, "subTotal" | "totalPayable" | "createdAt">>;
  updated_at: string | null;
  invoice_number?: string;
  customer_name?: string;
  payment_method?: string;
  item_count?: number;
  subtotal_base?: number;
  discount_base?: number;
  tax_base?: number;
  service_fee_base?: number;
  total_base?: number;
  completed_at?: string | null;
}

interface ApiPosSales {
  current: ApiPosSale | null;
  held: ApiPosSale[];
  history: ApiPosSale[];
}

export interface PosSalesData {
  current: PosSaleSnapshot | null;
  heldOrders: HeldOrder[];
  invoices: Invoice[];
}

const apiBase = (process.env.NEXT_PUBLIC_API_URL || "/v1/api").replace(/\/$/, "");
const posClientTokenKey = "vpos.pos-client-token";
const emptyPosCustomer: Customer = {
  id: "",
  code: "",
  name: "",
  email: "",
  phone: "",
  status: "",
  avatar: "",
  priceListId: null,
  priceList: null,
};

function mapCurrency(currency?: ApiCurrency | null): CurrencyInfo | null {
  if (!currency || !currency.code) return null;
  return {
    code: String(currency.code).toUpperCase(),
    symbol: String(currency.symbol || currency.code),
    decimalPlaces: Math.max(0, Number(currency.decimal_places)),
  };
}

async function request<T>(path: string): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    cache: "no-store",
    headers: {
      Accept: "application/json",
    },
  });

  if (!response.ok) {
    throw new Error(`Catalog request failed with status ${response.status}`);
  }

  const payload = (await response.json()) as ApiEnvelope<T>;
  if (!payload.success) {
    throw new Error("The catalog API returned an unsuccessful response");
  }

  return payload.data;
}

async function requestEnvelope<T>(path: string): Promise<ApiEnvelope<T>> {
  const response = await fetch(`${apiBase}${path}`, {
    cache: "no-store",
    headers: {
      Accept: "application/json",
    },
  });

  if (!response.ok) {
    throw new Error(`Catalog request failed with status ${response.status}`);
  }

  const payload = (await response.json()) as ApiEnvelope<T>;
  if (!payload.success) {
    throw new Error("The catalog API returned an unsuccessful response");
  }

  return payload;
}

export function normalizeMediaUrl(url?: string | null): string {
  if (!url || typeof url !== "string") return "/assets/no-order-CCjZwO4J.svg";

  const trimmed = url.trim();
  if (!trimmed) return "/assets/no-order-CCjZwO4J.svg";

  if (trimmed.startsWith("data:") || trimmed.startsWith("blob:")) {
    return trimmed;
  }

  if (trimmed.startsWith("/")) {
    return trimmed;
  }

  const uploadsIndex = trimmed.indexOf("/uploads/");
  if (uploadsIndex !== -1) {
    return trimmed.slice(uploadsIndex);
  }

  const storageIndex = trimmed.indexOf("/storage/");
  if (storageIndex !== -1) {
    return trimmed.slice(storageIndex);
  }

  try {
    const parsed = new URL(trimmed);
    if (
      parsed.hostname === "localhost" ||
      parsed.hostname === "127.0.0.1" ||
      parsed.hostname.endsWith(".localhost")
    ) {
      return parsed.pathname + parsed.search;
    }
  } catch {
    // Ignore URL parse error
  }

  return trimmed;
}

function mapProduct(item: ApiProduct): Product {
  const units = item.uom_group?.units || [];
  const variants = item.variants || [];
  const variantGroups = (item.option_groups || []).filter(
    (group) => group.type === "variant" && group.values.length > 0
  );
  const catalogStock =
    item.item_type === "variation" && variantGroups.length > 0
      ? variants.reduce((total, variant) => total + Math.max(0, Number(variant.stock)), 0)
      : item.stock;
  const defaultUnit = units.find((unit) => unit.is_base_unit) || units[0];
  const isStockControl = item.stock_control !== false;

  return {
    id: String(item.id),
    name: item.name,
    sku: item.sku,
    price: Number(defaultUnit?.price ?? item.price),
    currency: item.currency ? (mapCurrency(item.currency) || undefined) : undefined,
    category: item.category ? String(item.category.id) : "uncategorized",
    image: normalizeMediaUrl(item.image_url || item.thumbnail_url),
    stock: Number(catalogStock ?? 0),
    stockStatus:
      !isStockControl ? "instock" : catalogStock <= 0 ? "outofstock" : catalogStock <= 10 ? "lowstock" : "instock",
    stockControl: isStockControl,
    hasVariants: item.item_type === "variation" && variantGroups.length > 0,
    variantOptions: variantGroups.map((group) => ({
      id: group.id,
      name: group.name,
      values: group.values.map((value) => ({
        id: value.id,
        label: value.name,
        priceDelta: Number(value.price_adjustment || 0),
      })),
    })),
    variants: variants.map((variant) => ({
      id: variant.id,
      sku: variant.sku,
      price: Number(variant.resolved_price),
      stock: Number(variant.stock),
      isDefault: Boolean(variant.is_default),
      optionValueIds: variant.option_value_ids.map(Number),
    })),
    hasUOM: item.item_type === "uom" && units.length > 1,
    uomList: units.map((unit) => ({
      id: Number(unit.id),
      code: unit.code,
      name: unit.name,
      shortCode: unit.symbol || unit.code,
      conversionFactorToBase: Number(unit.conversion_factor_to_base ?? 1),
      basePrice: Number(unit.base_price),
      reduceByPercent: Number(unit.reduce_by_percent),
      price: Number(unit.price),
      isAutoPrice: Boolean(unit.is_auto),
      isDefault: unit.is_base_unit,
    })),
    defaultUOM: defaultUnit?.name,
    promotions: (item.promotions || []).map((promotion) => ({
      id: promotion.id,
      code: promotion.code,
      name: promotion.name,
      type: promotion.type,
      role: promotion.role,
      label: promotion.label,
      summary: promotion.summary,
      promotionalPrice: promotion.promotional_price === null
        ? null
        : Number(promotion.promotional_price),
      discountPercent: promotion.discount_percent === null
        ? null
        : Number(promotion.discount_percent),
      thresholdAmount: promotion.threshold_amount === null
        ? null
        : Number(promotion.threshold_amount),
      rewardDiscountPercent: promotion.reward_discount_percent === null
        ? null
        : Number(promotion.reward_discount_percent),
      buyQuantity: promotion.buy_quantity === null ? null : Number(promotion.buy_quantity),
      getQuantity: promotion.get_quantity === null ? null : Number(promotion.get_quantity),
      buyItemId: promotion.buy_item_id === null ? null : Number(promotion.buy_item_id),
      getItemId: promotion.get_item_id === null ? null : Number(promotion.get_item_id),
      buyItemName: promotion.buy_item_name || null,
      getItemName: promotion.get_item_name || null,
    })),
    isPremium: Boolean(item.is_premium),
    isFeatured: Boolean(item.is_featured),
    isNewArrival: Boolean(item.is_new_arrival),
    isTryOnEnabled: Boolean(item.is_try_on_enabled),
    purchase: item.purchase !== false,
    sale: item.sale !== false,
  };
}

export async function fetchPosCategories(): Promise<Category[]> {
  const categories = await request<ApiCategory[]>("/pos/categories?per_page=100");
  return [
    { id: "all", name: "All" },
    ...categories.map((category) => ({
      id: String(category.id),
      name: category.name,
      icon: category.image_url || category.thumbnail_url || undefined,
    })),
  ];
}

export async function fetchPosBranches(): Promise<PosBranch[]> {
  const branches = await request<ApiBranch[]>("/pos/branches");

  return branches.map((branch) => ({
    id: String(branch.id),
    code: branch.code,
    name: branch.name,
    foreignName: branch.foreign_name,
    location: branch.location,
  }));
}

export async function fetchPosProducts({
  page = 1,
  perPage = 20,
  categoryId,
  branchId,
  search,
  sort = "name_asc",
  tryOn,
  featured,
  premium,
  newArrival,
}: FetchPosProductsOptions = {}): Promise<PosProductsPage> {
  const params = new URLSearchParams();
  params.set("page", String(page));
  params.set("per_page", String(perPage));
  params.set("sort", sort);

  if (categoryId && categoryId !== "all") {
    params.set("category_id", categoryId);
  }
  if (branchId) {
    params.set("branch_id", branchId);
  }
  if (search && search.trim()) {
    params.set("search", search.trim());
  }
  if (tryOn) {
    params.set("try_on", "1");
  }
  if (featured) {
    params.set("featured", "1");
  }
  if (premium) {
    params.set("premium", "1");
  }
  if (newArrival) {
    params.set("new_arrival", "1");
  }

  const payload = await requestEnvelope<ApiProduct[]>(`/pos/products?${params.toString()}`);
  const products = payload.data.filter((item) => item.sale !== false).map(mapProduct);
  const meta = payload.meta;
  const currentPage = meta?.current_page ?? page;
  const lastPage = meta?.last_page ?? (products.length < perPage ? currentPage : currentPage + 1);
  const total = meta?.total ?? products.length;

  return {
    products,
    page: currentPage,
    perPage: meta?.per_page ?? perPage,
    total,
    lastPage,
    hasMore: currentPage < lastPage,
  };
}

export async function fetchPosCatalog(): Promise<PosCatalog> {
  const [categories, productsPage] = await Promise.all([
    fetchPosCategories(),
    fetchPosProducts({ page: 1, perPage: 20, sort: "name_asc" }),
  ]);

  return {
    categories,
    products: productsPage.products,
  };
}

export async function fetchPosProduct(itemId: number): Promise<Product> {
  return mapProduct(await request<ApiProduct>(`/pos/products/${itemId}`));
}

export async function fetchPosCustomers(): Promise<PosCustomerData> {
  const data = await request<ApiPosCustomers>("/pos/customers");
  const priceLists = data.price_lists.map((priceList) => ({
    id: String(priceList.id),
    code: priceList.code,
    name: priceList.name,
    pricingMethod: priceList.pricing_method,
    fixedAmount: priceList.fixed_amount === null ? null : Number(priceList.fixed_amount),
    discountPercent: Number(priceList.discount_percent || 0),
    isDefault: Boolean(priceList.is_default),
  }));
  const priceListsById = new Map(priceLists.map((priceList) => [priceList.id, priceList]));

  return {
    priceLists,
    currency: mapCurrency(data.currency),
    currencies: (data.currencies || [])
      .map(mapCurrency)
      .filter((currency): currency is CurrencyInfo => currency !== null),
    currencyRates: data.currency_rates || {},
    company: {
      name: data.company?.name || "",
      email: data.company?.email || "",
      phone: data.company?.phone || "",
      address: data.company?.address || "",
      receiptFooter: data.company?.receipt_footer || "",
    },
    customers: data.customers.map((customer) => {
      const priceListId = customer.price_list_id ? String(customer.price_list_id) : null;

      return {
        id: String(customer.id),
        code: customer.code,
        name: customer.name,
        email: customer.email,
        phone: customer.phone,
        status: "Active",
        avatar: customer.profile_image_url || "",
        priceListId,
        priceList: priceListId ? priceListsById.get(priceListId) || null : null,
      };
    }),
  };
}

export function createPosSaleId(): string {
  return window.crypto.randomUUID();
}

export function getOrCreatePosClientToken(): string {
  const savedToken = window.localStorage.getItem(posClientTokenKey);
  if (savedToken) return savedToken;

  const token = createPosSaleId();
  window.localStorage.setItem(posClientTokenKey, token);
  return token;
}

async function posSaleRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    ...init,
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...init?.headers,
    },
  });
  const payload = await response.json().catch(() => null) as (ApiEnvelope<T> & { message?: string }) | null;

  if (!response.ok || !payload?.success) {
    throw new Error(payload?.message || `Unable to save the POS sale (${response.status})`);
  }

  return payload.data;
}

export async function pricePosCart(
  cart: CartItem[],
  signal?: AbortSignal
): Promise<PosCartPricing> {
  return posSaleRequest<PosCartPricing>("/pos/cart/price", {
    method: "POST",
    signal,
    body: JSON.stringify({
      currency_mode: "base",
      items: cart.map((item) => ({
        item_id: Number(item.product.id),
        variant_id: item.selectedVariant?.id || null,
        uom_id: item.selectedUOM?.id || null,
        option_value_ids: Object.values(item.selectedVariants || {})
          .map((value) => Number(value.id))
          .filter((id) => Number.isInteger(id) && id > 0),
        quantity: item.quantity,
      })),
    }),
  });
}

function normalizePosSaleSnapshot(sale: ApiPosSale): PosSaleSnapshot {
  const snapshot = sale.snapshot || {};

  return {
    saleId: sale.sale_id,
    invoiceNumber: snapshot.invoiceNumber || "",
    orderType: snapshot.orderType || "Takeaway",
    customer: {
      ...emptyPosCustomer,
      ...(snapshot.customer || {}),
    },
    cart: Array.isArray(snapshot.cart) ? snapshot.cart : [],
    discountType: snapshot.discountType || "percentage",
    discountValue: Number(snapshot.discountValue || 0),
    taxPercent: Number(snapshot.taxPercent || 0),
    serviceFee: Number(snapshot.serviceFee || 0),
    appliedPromotion: snapshot.appliedPromotion || null,
    promotionDiscountAmount: Number(snapshot.promotionDiscountAmount || 0),
  };
}

function mapHeldOrder(sale: ApiPosSale): HeldOrder {
  const snapshot = normalizePosSaleSnapshot(sale);

  return {
    ...snapshot,
    id: `HOLD-${sale.id}`,
    databaseId: sale.id,
    saleId: sale.sale_id,
    reference: sale.reference,
    notes: sale.notes || undefined,
    subTotal: Number(sale.snapshot.subTotal || 0),
    totalPayable: Number(sale.snapshot.totalPayable || 0),
    createdAt: sale.snapshot.createdAt || (sale.updated_at
      ? new Date(sale.updated_at).toLocaleString()
      : "Saved"),
  };
}

function mapInvoice(sale: ApiPosSale): Invoice {
  const snapshot = normalizePosSaleSnapshot(sale);
  const completedAt = sale.completed_at ? new Date(sale.completed_at) : new Date(sale.updated_at || Date.now());

  return {
    databaseId: sale.id,
    saleId: sale.sale_id,
    id: sale.invoice_number || sale.reference || `SALE-${sale.id}`,
    customer: sale.customer_name || snapshot.customer.name || "Walk-in customer",
    paymentMethod: sale.payment_method || "Not yet",
    date: completedAt.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" }),
    time: completedAt.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" }),
    amount: Number(sale.total_base || 0),
    status: "Completed",
    snapshot,
    subTotal: Number(sale.subtotal_base || 0),
    discountAmount: Number(sale.discount_base || 0),
    taxAmount: Number(sale.tax_base || 0),
    serviceFee: Number(sale.service_fee_base || 0),
    totalItemCount: Number(sale.item_count || 0),
  };
}

export async function fetchPosSales(clientToken: string): Promise<PosSalesData> {
  const data = await posSaleRequest<ApiPosSales>(
    `/pos/sales?client_token=${encodeURIComponent(clientToken)}`
  );

  return {
    current: data.current
      ? normalizePosSaleSnapshot(data.current)
      : null,
    heldOrders: data.held.map(mapHeldOrder),
    invoices: (data.history || []).map(mapInvoice),
  };
}

export async function saveCurrentPosSale(
  clientToken: string,
  snapshot: PosSaleSnapshot
): Promise<void> {
  await posSaleRequest<ApiPosSale>("/pos/sales/current", {
    method: "PUT",
    body: JSON.stringify({
      client_token: clientToken,
      sale_id: snapshot.saleId,
      snapshot,
    }),
  });
}

export async function completeCashPosSale(
  clientToken: string,
  snapshot: PosSaleSnapshot,
  totals: PosSaleTotals,
  tenders: CashTenderInput[]
): Promise<void> {
  await completePaymentPosSale(clientToken, snapshot, totals, "Cash", tenders);
}

export async function completePaymentPosSale(
  clientToken: string,
  snapshot: PosSaleSnapshot,
  totals: PosSaleTotals,
  paymentMethod: "Cash" | "Card" | "Bank",
  tenders: PaymentTenderInput[]
): Promise<void> {
  await posSaleRequest<ApiPosSale>("/pos/sales/complete-payment", {
    method: "POST",
    body: JSON.stringify({
      client_token: clientToken,
      sale_id: snapshot.saleId,
      reference: snapshot.invoiceNumber,
      payment_method: paymentMethod,
      snapshot,
      totals: {
        sub_total: totals.subTotal,
        discount_amount: totals.discountAmount,
        tax_amount: totals.taxAmount,
        service_fee: totals.serviceFee,
        total_payable: totals.totalPayable,
      },
      tenders: tenders.map((tender) => ({
        currency_code: tender.currencyCode,
        amount: tender.amount,
        provider: tender.provider || null,
        reference: tender.reference || null,
        metadata: tender.metadata || null,
      })),
    }),
  });
}

export async function holdPosSale(
  clientToken: string,
  order: HeldOrder
): Promise<HeldOrder> {
  const sale = await posSaleRequest<ApiPosSale>("/pos/sales/hold", {
    method: "POST",
    body: JSON.stringify({
      client_token: clientToken,
      sale_id: order.saleId,
      reference: order.reference,
      notes: order.notes || null,
      snapshot: order,
    }),
  });

  return mapHeldOrder(sale);
}

export async function restoreHeldPosSale(
  clientToken: string,
  order: HeldOrder
): Promise<PosSaleSnapshot> {
  const sale = await posSaleRequest<ApiPosSale>(
    `/pos/sales/${order.databaseId}/restore`,
    {
      method: "POST",
      body: JSON.stringify({ client_token: clientToken }),
    }
  );

  return normalizePosSaleSnapshot(sale);
}

export async function deleteHeldPosSale(
  clientToken: string,
  order: HeldOrder
): Promise<void> {
  await posSaleRequest<never>(`/pos/sales/${order.databaseId}`, {
    method: "DELETE",
    body: JSON.stringify({ client_token: clientToken }),
  });
}

export interface PosDisplayPayload {
  token: string;
  action: "cart_updated" | "payment_pending" | "payment_success" | "cart_cleared" | "idle";
  cart?: any[];
  totals?: {
    subTotal: number;
    discountAmount: number;
    gstAmount: number;
    totalPayable: number;
    totalItemCount: number;
    currencySymbol?: string;
    currencyCode?: string;
  } | null;
  payment?: {
    method: "qr" | "bank" | "cash" | "card" | "split" | "later";
    amount: number;
    provider?: string;
    qrCodeUrl?: string;
    bankName?: string;
    reference?: string;
    tendered?: number;
    change?: number;
  } | null;
  customer?: {
    id?: number | string;
    name: string;
    phone?: string;
  } | null;
  branch?: {
    id?: string;
    name?: string;
  } | null;
  company?: {
    name?: string;
    logo?: string;
  } | null;
  updated_at?: string;
}

export async function syncPosDisplay(payload: PosDisplayPayload): Promise<void> {
  try {
    await fetch(`${apiBase}/pos/display/sync`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });
  } catch (error) {
    console.warn("Failed to sync customer display:", error);
  }
}

export async function fetchPosDisplayState(token: string): Promise<PosDisplayPayload | null> {
  try {
    const res = await fetch(`${apiBase}/pos/display/state?token=${encodeURIComponent(token)}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data || null;
  } catch {
    return null;
  }
}

export interface PromoSlide {
  id: string | number;
  type: "video" | "gradient" | "image";
  badge: string;
  badgeBg?: string;
  badgeColor?: string;
  title: string;
  subtitle: string;
  discount?: string;
  mediaUrl?: string;
  gradient: string;
  icon: string;
  tag?: string;
  placement?: string;
  sort_order?: number;
  status?: string;
}

export async function fetchPosDisplayPromotions(token?: string, type: string = "second_screen"): Promise<PromoSlide[]> {
  try {
    const query = new URLSearchParams();
    if (token) query.set("token", token);
    if (type) query.set("type", type);
    const res = await fetch(`${apiBase}/pos/display/promotions?${query.toString()}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!res.ok) return [];
    const json = await res.json();
    const list = Array.isArray(json.data) ? json.data : [];
    return list.map((slide: PromoSlide) => ({
      ...slide,
      mediaUrl: slide.mediaUrl ? normalizeMediaUrl(slide.mediaUrl) : "",
    }));
  } catch (err) {
    console.warn("Failed to fetch promotional slides from backend:", err);
    return [];
  }
}

export async function savePosDisplayPromotions(
  slides: PromoSlide[],
  token?: string,
  type: string = "second_screen"
): Promise<{ success: boolean; data?: PromoSlide[]; message?: string }> {
  try {
    const res = await fetch(`${apiBase}/pos/display/promotions`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        token,
        type,
        slides,
      }),
    });
    const json = await res.json();
    return {
      success: !!json.success,
      data: json.data,
      message: json.message,
    };
  } catch (error) {
    console.error("Failed to save promotions to backend:", error);
    return { success: false, message: "Network error saving promotions" };
  }
}
