export type LocalizedName = { name: string; foreign_name?: string };
export type ShopCurrency = {
    code: string;
    symbol: string;
    decimal_places: number;
    exchange_rate?: number;
};
export type ShopStore = {
    id: string;
    name: string;
    logo_url: string;
    domain: string;
    domains: string[];
    facebook_login_enabled: boolean;
    google_login_enabled?: boolean;
};
export type ShopCategory = LocalizedName & {
    id: number;
    image_url: string;
    parent_id?: number | null;
};
export type ShopBranch = LocalizedName & { id: number; location: string };
export type ShopOption = LocalizedName & {
    id: number;
    price_adjustment: number;
    is_default: boolean;
    color_hex?: string;
};
export type ShopOptionGroup = LocalizedName & {
    id: number;
    type: string;
    selection_type: string;
    is_required: boolean;
    min_selections: number;
    max_selections: number | null;
    values: ShopOption[];
};
export type ShopUnit = LocalizedName & {
    id: number;
    code: string;
    price: number;
    is_base_unit: boolean;
    conversion_factor_to_base: number;
};
export type ShopVariant = {
    id: number;
    name: string;
    resolved_price: number;
    stock: number;
    is_default: boolean;
    option_value_ids: number[];
};
export type ShopProduct = LocalizedName & {
    id: number;
    sku: string;
    description: string;
    image_url: string;
    galleries: { image_url: string }[];
    price: number;
    formatted_price: string;
    currency: ShopCurrency | null;
    rating: number | null;
    review_count: number;
    stock: number;
    stock_control: boolean;
    sale: boolean;
    status: string;
    is_new_arrival: boolean;
    is_premium: boolean;
    is_featured?: boolean;
    is_try_on_enabled?: boolean;
    category: (LocalizedName & { id: number }) | null;
    branch: ShopBranch | null;
    uom_group: { units: ShopUnit[] } | null;
    option_groups: ShopOptionGroup[];
    variants: ShopVariant[];
    promotions: {
        id?: number;
        code?: string;
        name: string;
        type?: string;
        role?: string;
        label?: string;
        summary?: string;
        promotional_price?: number | null;
        discount_percent?: number | null;
    }[];
};
export type ShopBanner = {
    type?: string;
    id: number;
    title?: string;
    subtitle?: string;
    badge?: string;
    discount?: string;
    image_url: string;
    mediaType?: string;
    media_type?: string;
    link?: string;
    backgroundColor?: string;
};
export type ShopBootstrap = {
    store: ShopStore;
    currency: ShopCurrency | null;
    currencies: ShopCurrency[];
    branches: ShopBranch[];
    banners: ShopBanner[];
    categories: ShopCategory[];
    featured_products: ShopProduct[];
    new_arrivals: ShopProduct[];
    best_sellers: ShopProduct[];
    recommended_products: ShopProduct[];
};
export type ShopUser = {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string;
    country_code: string;
    email_verified?: boolean;
    profile_image_url: string | null;
};
export type ShopAddress = {
    id: number;
    label: string;
    recipient_name: string;
    country_code: string;
    phone: string;
    address_line: string;
    city: string;
    note: string;
    is_default: boolean;
};
export type ShopLine = {
    item_id: number;
    quantity: number;
    variant_id: number | null;
    uom_id: number | null;
    option_value_ids: number[];
    product: ShopProduct | null;
};
export type ShopPricing = {
    currency_mode: "native" | "base";
    subtotal: number;
    discount_total: number;
    final_total: number;
    applied_promotion: {
        id: number;
        code: string;
        name: string;
        type: "item_price" | "subtotal_discount" | "bogo";
        summary: string;
        savings: number;
    } | null;
    items: {
        item_id: number;
        item_variant_id?: number | null;
        uom_id?: number | null;
        selected_options?: { values: { id: number }[] }[];
        unit_price?: number;
        line_subtotal: number;
        name: string;
        quantity: number;
        line_total: number;
        discount_amount: number;
        uom_name?: string;
    }[];
    auto_add_items: {
        promotion_id: number;
        promotion_name: string;
        item_id: number;
        uom_id: number | null;
        quantity: number;
    }[];
};
export type ShopOrder = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    payment_method: string;
    delivery_method: string;
    delivery_address: ShopAddress | null;
    total: number;
    subtotal: number;
    discount_total: number;
    currency_code: string;
    total_items: number;
    created_at: string;
    note: string;
    items: {
        id: number;
        name: string;
        foreign_name: string;
        quantity: number;
        line_total: number;
        image_url: string;
    }[];
};
export type ShopNotification = {
    id: number;
    title: string;
    message: string;
    read_at: string | null;
    created_at: string;
};
export type ShopResponse<T> = {
    success: boolean;
    data: T;
    message?: string;
    code?: string;
    retry_after?: number;
    errors?: Record<string, string[]>;
    meta?: {
        current_page: number;
        last_page: number;
        total: number;
        unread_count?: number;
    };
};
export type ShopPageProps = {
    origin: string;
    bootstrap: ShopBootstrap | null;
    stores: ShopStore[] | null;
    initialError: string | null;
    central: boolean;
};
