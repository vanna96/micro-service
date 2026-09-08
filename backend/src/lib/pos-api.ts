import { Category, Customer, CustomerPriceList, Product } from "@/types/pos-types";

interface ApiEnvelope<T> {
  success: boolean;
  data: T;
}

interface ApiCategory {
  id: number;
  name: string;
  image_url: string | null;
  thumbnail_url: string | null;
}

interface ApiOptionValue {
  name: string;
  price_adjustment: number;
}

interface ApiOptionGroup {
  name: string;
  type: string;
  values: ApiOptionValue[];
}

interface ApiUomUnit {
  name: string;
  code: string;
  symbol: string;
  price: number;
  is_base_unit: boolean;
}

interface ApiProduct {
  id: number;
  sku: string;
  name: string;
  item_type: "uom" | "variation";
  price: number;
  stock: number;
  image_url: string | null;
  thumbnail_url: string | null;
  category: { id: number; name: string } | null;
  uom_group: { units: ApiUomUnit[] } | null;
  option_groups: ApiOptionGroup[];
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
  discount_percent: number;
  is_default: boolean;
}

interface ApiPosCustomers {
  customers: ApiCustomer[];
  price_lists: ApiPriceList[];
}

export interface PosCatalog {
  categories: Category[];
  products: Product[];
}

export interface PosCustomerData {
  customers: Customer[];
  priceLists: CustomerPriceList[];
}

const apiBase = (process.env.NEXT_PUBLIC_API_URL || "/v1/api").replace(/\/$/, "");

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

function mapProduct(item: ApiProduct): Product {
  const units = item.uom_group?.units || [];
  const variantGroups = (item.option_groups || []).filter(
    (group) => group.type === "variant" && group.values.length > 0
  );
  const defaultUnit = units.find((unit) => unit.is_base_unit) || units[0];

  return {
    id: String(item.id),
    name: item.name,
    sku: item.sku,
    price: Number(item.price),
    category: item.category ? String(item.category.id) : "uncategorized",
    image: item.image_url || item.thumbnail_url || "/assets/no-order-CCjZwO4J.svg",
    stockStatus:
      item.stock <= 0 ? "outofstock" : item.stock <= 10 ? "lowstock" : "instock",
    hasVariants: item.item_type === "variation" && variantGroups.length > 0,
    variantOptions: variantGroups.map((group) => ({
      name: group.name,
      values: group.values.map((value) => ({
        label: value.name,
        priceDelta: Number(value.price_adjustment || 0),
      })),
    })),
    hasUOM: item.item_type === "uom" && units.length > 1,
    uomList: units.map((unit) => ({
      name: unit.name,
      shortCode: unit.symbol || unit.code,
      price: Number(unit.price),
      isDefault: unit.is_base_unit,
    })),
    defaultUOM: defaultUnit?.name,
  };
}

export async function fetchPosCatalog(): Promise<PosCatalog> {
  const [categories, products] = await Promise.all([
    request<ApiCategory[]>("/mobile/categories?per_page=100"),
    request<ApiProduct[]>("/mobile/products?per_page=100&sort=name_asc"),
  ]);

  return {
    categories: [
      { id: "all", name: "All" },
      ...categories.map((category) => ({
        id: String(category.id),
        name: category.name,
        icon: category.image_url || category.thumbnail_url || undefined,
      })),
    ],
    products: products.map(mapProduct),
  };
}

export async function fetchPosCustomers(): Promise<PosCustomerData> {
  const data = await request<ApiPosCustomers>("/mobile/pos/customers");
  const priceLists = data.price_lists.map((priceList) => ({
    id: String(priceList.id),
    code: priceList.code,
    name: priceList.name,
    pricingMethod: priceList.pricing_method,
    discountPercent: Number(priceList.discount_percent || 0),
    isDefault: Boolean(priceList.is_default),
  }));
  const priceListsById = new Map(priceLists.map((priceList) => [priceList.id, priceList]));

  return {
    priceLists,
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
