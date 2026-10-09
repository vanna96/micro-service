/* Product images come from the tenant catalog and may use external storage. */
/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import { useRouter } from "next/router";
import {
    ArrowLeft,
    Crown,
    Gift,
    Heart,
    Minus,
    Package,
    Plus,
    RotateCcw,
    Scale,
    Search,
    SlidersHorizontal,
    Sparkles,
    Star,
    Tag,
    X,
    Camera,
    Loader2,
} from "lucide-react";
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from "react";
import { ShopApiError, shopApi } from "@/lib/shop-api";
import type { ShopCategory, ShopProduct, ShopResponse } from "@/types/shop";
import { useShop } from "./shop-provider";
import { ShopProductConfigModal } from "./shop-config-modal";

export { ShopProductConfigModal };
import { ImageCropModal } from "./image-cropper";

export function ShopImage({
    src,
    alt,
    className = "",
}: {
    src?: string | null;
    alt: string;
    className?: string;
}) {
    const [failed, setFailed] = useState("");
    const safe =
        src &&
        /^(?:https?:\/\/|\/(?!\/)|data:image\/(?:png|jpeg|webp);base64,)/.test(
            src,
        );
    return safe && failed !== src ? (
        <img
            className={className}
            src={src}
            alt={alt}
            loading="lazy"
            onError={() => setFailed(src)}
        />
    ) : (
        <span
            className={`shop-image-placeholder ${className}`}
            role="img"
            aria-label={alt}
        >
            <Package size={32} strokeWidth={1.3} />
        </span>
    );
}

export function ShopHeader({
    title,
    children,
    back = false,
    action,
}: {
    title: string;
    children?: ReactNode;
    back?: boolean;
    action?: ReactNode;
}) {
    const { t } = useShop();
    const router = useRouter();
    return (
        <header className="shop-header">
            {(title || back || action) && (
                <div className="shop-header-row">
                    {back && (
                        <button
                            className="shop-icon-button"
                            aria-label={t("Back")}
                            onClick={() =>
                                window.history.length > 1
                                    ? router.back()
                                    : void router.push("/")
                            }
                        >
                            <ArrowLeft size={21} />
                        </button>
                    )}
                    {title ? <h1>{title}</h1> : <div className="shop-header-spacer" />}
                    {action}
                </div>
            )}
            {children}
        </header>
    );
}

export function SearchField({
    value,
    onChange,
    onSubmit,
    placeholder = "Search products",
    onImageSearch,
    isImageSearching,
}: {
    value: string;
    onChange: (value: string) => void;
    onSubmit?: () => void;
    placeholder?: string;
    onImageSearch?: (file: File) => void;
    isImageSearching?: boolean;
}) {
    const { t } = useShop();
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [cropImageSrc, setCropImageSrc] = useState<string | null>(null);
    return (
        <form
            className="shop-search"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit?.();
            }}
        >
            <Search size={19} />
            <input
                aria-label={t(placeholder)}
                placeholder={t(placeholder)}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                type="search"
                autoComplete="off"
            />
            {value && (
                <button
                    type="button"
                    aria-label={t("Clear Search")}
                    onClick={() => onChange("")}
                >
                    <X size={17} />
                </button>
            )}
            {onImageSearch && !value && (
                <>
                    <input
                        type="file"
                        accept="image/*"
                        style={{ display: "none" }}
                        ref={fileInputRef}
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = () => {
                                    setCropImageSrc(reader.result as string);
                                };
                                reader.readAsDataURL(file);
                            }
                            if (e.target) {
                                e.target.value = "";
                            }
                        }}
                    />
                    <button
                        type="button"
                        aria-label="Search by Image"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={isImageSearching}
                        style={{
                            opacity: isImageSearching ? 0.5 : 1,
                            padding: "0 4px",
                            background: "transparent",
                            width: "auto",
                            height: "auto",
                            color: "#94a3b8"
                        }}
                    >
                        {isImageSearching ? (
                            <Loader2 size={20} strokeWidth={1.5} className="animate-spin" style={{ animation: "spin 1s linear infinite" }} />
                        ) : (
                            <Camera size={20} strokeWidth={1.5} />
                        )}
                    </button>
                </>
            )}

            {cropImageSrc && onImageSearch && (
                <ImageCropModal
                    imageSrc={cropImageSrc}
                    onClose={() => setCropImageSrc(null)}
                    onCropComplete={(croppedFile) => {
                        setCropImageSrc(null);
                        onImageSearch(croppedFile);
                    }}
                />
            )}
        </form>
    );
}

export function EmptyState({
    title,
    detail,
    icon,
    children,
}: {
    title: string;
    detail?: string;
    icon?: ReactNode;
    children?: ReactNode;
}) {
    const { t } = useShop();
    return (
        <div className="shop-empty">
            {icon || <Package size={44} strokeWidth={1.2} />}
            <h2>{t(title)}</h2>
            {detail && <p>{t(detail)}</p>}
            {children}
        </div>
    );
}
export function ErrorState({
    error,
    retry,
}: {
    error: string;
    retry?: () => void;
}) {
    const { t } = useShop();
    return (
        <div className="shop-error" role="alert">
            <p>{t(error)}</p>
            {retry && (
                <button className="shop-text-button" onClick={retry}>
                    <RotateCcw size={16} />
                    {t("Retry")}
                </button>
            )}
        </div>
    );
}
export function LoadingCards({ message }: { message?: string }) {
    return (
        <div style={{ display: "flex", flexDirection: "column", gap: "16px", width: "100%" }}>
            {message && (
                <div style={{ textAlign: "center", color: "#64748b", padding: "20px 0", display: "flex", flexDirection: "column", alignItems: "center", gap: "12px" }}>
                    <Loader2 size={32} className="animate-spin" style={{ animation: "spin 1s linear infinite", color: "#3b82f6" }} />
                    <span style={{ fontSize: "15px", fontWeight: 500 }}>{message}</span>
                </div>
            )}
            <div className="shop-grid" aria-busy="true">
                {[1, 2, 3, 4, 5, 6].map((id) => (
                    <div key={id} className="shop-skeleton" />
                ))}
            </div>
        </div>
    );
}

export function Sheet({
    title,
    close,
    children,
}: {
    title: string;
    close: () => void;
    children: ReactNode;
}) {
    const { t } = useShop();
    const dialog = useRef<HTMLDivElement>(null);
    const closeRef = useRef(close);
    useEffect(() => {
        closeRef.current = close;
    }, [close]);
    useEffect(() => {
        const previous = document.activeElement as HTMLElement | null;
        const overflow = document.body.style.overflow;
        const screen = document.querySelector<HTMLElement>(".shop-screen");
        const screenOverflow = screen?.style.overflow || "";
        const screenOverscroll = screen?.style.overscrollBehavior || "";
        document.body.style.overflow = "hidden";
        if (screen) {
            screen.style.overflow = "hidden";
            screen.style.overscrollBehavior = "none";
        }
        dialog.current?.focus();
        const handle = (event: KeyboardEvent) => {
            if (event.key === "Escape") closeRef.current();
            if (event.key === "Tab") {
                const elements = Array.from(
                    dialog.current?.querySelectorAll<HTMLElement>(
                        'button:not(:disabled), a[href], input:not(:disabled), select, textarea, [tabindex="0"]',
                    ) || [],
                );
                const first = elements[0],
                    last = elements[elements.length - 1];
                if (!first) {
                    event.preventDefault();
                    return;
                }
                if (
                    event.shiftKey &&
                    (document.activeElement === first ||
                        document.activeElement === dialog.current)
                ) {
                    event.preventDefault();
                    last.focus();
                } else if (
                    !event.shiftKey &&
                    (document.activeElement === last ||
                        document.activeElement === dialog.current)
                ) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };
        document.addEventListener("keydown", handle);
        return () => {
            document.body.style.overflow = overflow;
            if (screen) {
                screen.style.overflow = screenOverflow;
                screen.style.overscrollBehavior = screenOverscroll;
            }
            document.removeEventListener("keydown", handle);
            previous?.focus();
        };
    }, []);
    return (
        <div
            className="shop-overlay"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) close();
            }}
        >
            <div
                className="shop-sheet"
                ref={dialog}
                role="dialog"
                aria-modal="true"
                aria-label={t(title)}
                tabIndex={-1}
            >
                <span className="shop-sheet-handle" />
                <div className="shop-sheet-heading">
                    <h2>{t(title)}</h2>
                    <button
                        className="shop-icon-button"
                        onClick={close}
                        aria-label={t("Close")}
                    >
                        <X size={22} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

export function ProductCard({
    product,
    list = false,
}: {
    product: ShopProduct;
    list?: boolean;
}) {
    const {
        t,
        name,
        money,
        favorites,
        toggleFavorite,
        ready,
        sessionError,
        add,
        updateCart,
        announce,
        cart,
    } = useShop();
    const router = useRouter();
    const [liking, setLiking] = useState(false);
    const [configOpen, setConfigOpen] = useState(false);
    const selected = favorites.some((item) => item.id === product.id);
    const unavailable = Boolean(product.stock_control && product.stock <= 0);

    const defaultUnit =
        product.uom_group?.units.find((unit) => unit.is_base_unit) ||
        product.uom_group?.units[0];
    const hasUOM = Boolean(
        product.uom_group && product.uom_group.units.length > 1,
    );
    const hasVariants = Boolean(product.variants && product.variants.length > 0);
    const hasOptions = Boolean(
        product.option_groups && product.option_groups.length > 0,
    );
    const configure = hasUOM || hasVariants || hasOptions;

    const cartLines = cart.filter((line) => line.item_id === product.id);
    const count = cartLines.reduce((sum, line) => sum + line.quantity, 0);

    // Promotions
    const promotions = product.promotions || [];
    const visiblePromotions = promotions.slice(0, 1);
    const itemPricePromo = promotions.find(
        (p) =>
            p.type === "item_price" &&
            p.promotional_price !== null &&
            p.promotional_price !== undefined &&
            p.promotional_price < product.price,
    );
    const displayPrice = itemPricePromo?.promotional_price ?? product.price;
    const cardMoney = (value: number) =>
        money(value, product.currency).replace(/^(\S+)\s+/, "$1");

    const isAtMaxStock = Boolean(
        product.stock_control &&
            !configure &&
            typeof product.stock === "number" &&
            count >= product.stock,
    );

    const handleIncrement = () => {
        if (configure) {
            setConfigOpen(true);
            return;
        }
        if (isAtMaxStock) {
            announce(t("Maximum stock reached"));
            return;
        }
        add({
            item_id: product.id,
            quantity: 1,
            variant_id: null,
            uom_id: defaultUnit?.id || null,
            option_value_ids: [],
            product,
        });
    };

    const handleDecrement = () => {
        if (!cartLines.length) return;
        const target = cartLines[0];
        if (target.quantity <= 1) {
            updateCart(cart.filter((line) => line !== target));
        } else {
            updateCart(
                cart.map((line) =>
                    line === target
                        ? { ...line, quantity: line.quantity - 1 }
                        : line,
                ),
            );
        }
    };

    return (
        <article
            className={`shop-product-card${list ? " shop-product-list" : ""}${unavailable ? " is-out-of-stock" : ""}`}
        >
            <div className="shop-product-image">
                <Link
                    href={`/products/${product.id}`}
                    aria-label={name(product)}
                >
                    <ShopImage src={product.image_url} alt={name(product)} />
                </Link>

                {/* Top-left Badges Container (Status badges only, max 4, matching Flutter) */}
                <div className="shop-card-badges-column">
                    {/* Stock Status Badge */}
                    {unavailable && (
                        <span className="shop-badge-pill shop-badge-danger">
                            {t("Out of stock")}
                        </span>
                    )}

                    {/* Virtual Try-On Badge */}
                    {product.is_try_on_enabled && (
                        <span className="shop-badge-pill shop-badge-tryon">
                            <Sparkles size={9} />
                            <span>{t("Try-On")}</span>
                        </span>
                    )}

                    {/* Premium Badge */}
                    {product.is_premium && (
                        <span className="shop-badge-pill shop-badge-premium">
                            <Crown size={9} />
                            <span>{t("Premium")}</span>
                        </span>
                    )}

                    {/* Featured Badge */}
                    {product.is_featured && (
                        <span className="shop-badge-pill shop-badge-featured">
                            <Star size={9} fill="currentColor" />
                            <span>{t("Featured")}</span>
                        </span>
                    )}

                    {/* New Arrival Badge */}
                    {product.is_new_arrival && (
                        <span className="shop-badge-pill shop-badge-new">
                            <Sparkles size={9} fill="currentColor" />
                            <span>{t("New")}</span>
                        </span>
                    )}

                    {/* Default UOM or Options Badge */}
                    {hasUOM && defaultUnit ? (
                        <span className="shop-badge-pill shop-badge-uom">
                            UOM: {(defaultUnit.code || defaultUnit.name).toLowerCase()}
                        </span>
                    ) : hasVariants ? (
                        <span className="shop-badge-pill shop-badge-options">
                            {t("Options")}
                        </span>
                    ) : null}
                </div>

                {/* Top-Right Favorite Button */}
                <button
                    className={`shop-heart${selected ? " selected" : ""}`}
                    aria-label={t("Favorites")}
                    aria-pressed={selected}
                    disabled={liking || !ready}
                    onClick={async () => {
                        setLiking(true);
                        try {
                            await toggleFavorite(product);
                        } catch (error) {
                            if (
                                error instanceof ShopApiError &&
                                error.status === 401
                            )
                                void router.push(
                                    "/account?next=" +
                                        encodeURIComponent(router.asPath),
                                );
                            else
                                announce(
                                    error instanceof Error
                                        ? error.message
                                        : t("Retry"),
                                );
                        } finally {
                            setLiking(false);
                        }
                    }}
                >
                    <Heart
                        size={16}
                        fill={selected ? "currentColor" : "none"}
                    />
                </button>

                {/* Bottom-left: Promotions (Matching Flutter _buildBottomPromotions) */}
                {visiblePromotions.length > 0 && (
                    <div className="shop-card-bottom-promos">
                        {visiblePromotions.map((promo, idx) => (
                            <span
                                key={idx}
                                className={`shop-badge-promo-pill ${
                                    promo.type === "bogo"
                                        ? "shop-promo-bogo"
                                        : promo.type === "item_price"
                                          ? "shop-promo-item_price"
                                          : "shop-promo-discount"
                                }`}
                                title={promo.summary || promo.name}
                            >
                                {promo.type === "bogo" ? (
                                    <Gift size={9} />
                                ) : (
                                    <Tag size={9} />
                                )}
                                <span>{promo.label || promo.name}</span>
                            </span>
                        ))}
                    </div>
                )}

                {/* Bottom-right: In-Cart Count (Matching Flutter _buildInCartBadge) */}
                {count > 0 && (
                    <span
                        className="shop-card-in-cart-badge"
                        title={`${count} ${t("In Cart")}`}
                    >
                        {count}
                    </span>
                )}
            </div>

            <div className="shop-product-copy">
                {product.sku && (
                    <span className="shop-sku">{product.sku}</span>
                )}
                <Link
                    href={`/products/${product.id}`}
                    className="shop-product-name"
                    title={name(product)}
                >
                    {name(product)}
                </Link>

                <div className="shop-product-divider" />

                <div
                    className={`shop-product-footer${configure ? " has-config-action" : ""}`}
                >
                    <div className="shop-price-box">
                        {itemPricePromo && (
                            <del className="shop-price-original">
                                {cardMoney(product.price)}
                            </del>
                        )}
                        <span className="shop-price-main">
                            {cardMoney(displayPrice)}
                            {defaultUnit && (
                                <small className="shop-price-unit">
                                    /{defaultUnit.name}
                                </small>
                            )}
                        </span>
                    </div>

                    {configure ? (
                        <button
                            type="button"
                            className="shop-btn-pill-action"
                            onClick={() => setConfigOpen(true)}
                        >
                            {hasUOM ? (
                                <Scale size={12} />
                            ) : (
                                <SlidersHorizontal size={12} />
                            )}
                            <span>{hasUOM ? t("UOM") : t("Options")}</span>
                        </button>
                    ) : count > 0 ? (
                        <div className="shop-card-qty-stepper">
                            <button
                                type="button"
                                onClick={handleDecrement}
                                aria-label={t("Decrease")}
                            >
                                <Minus size={13} />
                            </button>
                            <span>{count}</span>
                            <button
                                type="button"
                                disabled={isAtMaxStock}
                                onClick={handleIncrement}
                                aria-label={t("Increase")}
                            >
                                <Plus size={13} />
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            className="shop-btn-pill-action shop-btn-pill-add"
                            disabled={unavailable || !ready || !!sessionError}
                            onClick={handleIncrement}
                        >
                            <Plus size={13} />
                            <span>{t("Add")}</span>
                        </button>
                    )}
                </div>
            </div>

            {configOpen && (
                <ShopProductConfigModal
                    product={product}
                    onClose={() => setConfigOpen(false)}
                />
            )}
        </article>
    );
}

export function useShopResource<T>(path: string | null) {
    const [state, setState] = useState<{
        data: T | null;
        meta: ShopResponse<T>["meta"];
        error: string;
        loading: boolean;
    }>({ data: null, meta: undefined, error: "", loading: true });
    const [revision, setRevision] = useState(0);
    const reload = useCallback(() => setRevision((value) => value + 1), []);
    useEffect(() => {
        let active = true;
        if (!path) return;
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setState({ data: null, meta: undefined, error: "", loading: true });
            try {
                const response = await shopApi<T>(path, {
                    signal: controller.signal,
                });
                if (active)
                    setState({
                        data: response.data,
                        meta: response.meta,
                        error: "",
                        loading: false,
                    });
            } catch (error) {
                if (active)
                    setState({
                        data: null,
                        meta: undefined,
                        error:
                            error instanceof Error
                                ? error.message
                                : "Please try again.",
                        loading: false,
                    });
            }
        }, 0);
        return () => {
            active = false;
            clearTimeout(timer);
            controller.abort();
        };
    }, [path, revision]);
    return { ...state, reload };
}

export function CategoryVisual({
    category,
    name,
}: {
    category: ShopCategory;
    name?: string;
}) {
    if (category.image_url) {
        return <ShopImage src={category.image_url} alt={name || category.name} />;
    }
    const key = `${category.name || ""} ${category.foreign_name || ""}`.toLowerCase();

    if (/fruit|ផ្លែឈើ/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <circle cx="24" cy="26" r="16" fill="url(#cat-fruit)" />
                <path d="M24 10C24 10 26 14 24 17" stroke="#854d0e" strokeWidth="2.5" strokeLinecap="round" />
                <path d="M24 12C28 9 34 11 34 11C34 11 34 16 28 16C25.5 16 24 14 24 12Z" fill="#22c55e" />
                <circle cx="19" cy="21" r="2.5" fill="#fed7aa" opacity="0.6" />
                <defs>
                    <radialGradient id="cat-fruit" cx="35%" cy="35%" r="65%">
                        <stop offset="0%" stopColor="#fdba74" />
                        <stop offset="55%" stopColor="#f97316" />
                        <stop offset="100%" stopColor="#ea580c" />
                    </radialGradient>
                </defs>
            </svg>
        );
    }
    if (/veg|carrot|salad|បន្លែ/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="21" y="26" width="6" height="14" rx="3" fill="#86efac" />
                <circle cx="18" cy="22" r="9" fill="url(#cat-veg1)" />
                <circle cx="30" cy="22" r="9" fill="url(#cat-veg1)" />
                <circle cx="24" cy="16" r="10" fill="url(#cat-veg2)" />
                <defs>
                    <linearGradient id="cat-veg1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#4ade80" />
                        <stop offset="100%" stopColor="#16a34a" />
                    </linearGradient>
                    <linearGradient id="cat-veg2" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#22c55e" />
                        <stop offset="100%" stopColor="#15803d" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/dair|milk|ទឹកដោះគោ/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="15" y="15" width="18" height="24" rx="4" fill="url(#cat-dairy)" />
                <rect x="19" y="10" width="10" height="6" rx="2" fill="#38bdf8" />
                <rect x="15" y="24" width="18" height="8" fill="#38bdf8" opacity="0.25" />
                <circle cx="24" cy="28" r="3" fill="#ffffff" />
                <defs>
                    <linearGradient id="cat-dairy" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#f0f9ff" />
                        <stop offset="100%" stopColor="#e0f2fe" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/bev|drink|soda|coffee|tea|ភេសជ្ជៈ/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="15" y="14" width="18" height="25" rx="5" fill="url(#cat-bev)" />
                <rect x="17" y="10" width="14" height="5" rx="2" fill="#cbd5e1" />
                <rect x="18" y="22" width="12" height="10" rx="3" fill="#ffffff" opacity="0.9" />
                <path d="M21 27L24 24L27 27" stroke="#16a34a" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                <defs>
                    <linearGradient id="cat-bev" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#22c55e" />
                        <stop offset="100%" stopColor="#15803d" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/snack|អាហារសម្រន់/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="14" y="13" width="20" height="25" rx="4" fill="url(#cat-snack)" />
                <path d="M14 17H34M14 34H34" stroke="#d97706" strokeWidth="1.5" strokeDasharray="2 2" />
                <circle cx="24" cy="25" r="5" fill="#fef3c7" />
                <path d="M22 25C22 23.9 22.9 23 24 23C25.1 23 26 23.9 26 25C26 26.1 25.1 27 24 27" stroke="#d97706" strokeWidth="1.6" strokeLinecap="round" />
                <defs>
                    <linearGradient id="cat-snack" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#fbbf24" />
                        <stop offset="100%" stopColor="#f59e0b" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/bak|bread|cake|burger|នំបុ័ង/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M14 22C14 16.5 18.5 13 24 13C29.5 13 34 16.5 34 22H14Z" fill="url(#cat-bakery)" />
                <rect x="12" y="24" width="24" height="4" rx="2" fill="#854d0e" />
                <rect x="14" y="30" width="20" height="6" rx="3" fill="url(#cat-bakery)" />
                <circle cx="21" cy="18" r="1" fill="#fef3c7" />
                <circle cx="27" cy="18" r="1" fill="#fef3c7" />
                <circle cx="24" cy="16" r="1" fill="#fef3c7" />
                <defs>
                    <linearGradient id="cat-bakery" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#fcd34d" />
                        <stop offset="100%" stopColor="#d97706" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/meat|beef|steak|សាច់/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M15 22C13 26 15 32 20 34C25 36 31 33 34 28C36 24 34 18 29 16C24 14 17 18 15 22Z" fill="url(#cat-meat)" />
                <path d="M17 23C15.5 26.5 17 31 21 32.5C25 34 29.5 31.5 32 27.5C33.5 24.5 32 19.5 28 18C24 16.5 18.5 19.5 17 23Z" stroke="#fee2e2" strokeWidth="2" />
                <circle cx="23" cy="25" r="3.5" fill="#fecdd3" />
                <circle cx="23" cy="25" r="1.8" fill="#ffffff" />
                <defs>
                    <linearGradient id="cat-meat" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#fb7185" />
                        <stop offset="100%" stopColor="#e11d48" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/fit|sport|gym|កីឡា/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="22" y="16" width="4" height="16" rx="2" fill="#94a3b8" />
                <rect x="17" y="18" width="14" height="12" rx="3" fill="#38bdf8" />
                <rect x="13" y="15" width="6" height="18" rx="2.5" fill="url(#cat-fit)" />
                <rect x="29" y="15" width="6" height="18" rx="2.5" fill="url(#cat-fit)" />
                <defs>
                    <linearGradient id="cat-fit" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#0284c7" />
                        <stop offset="100%" stopColor="#0369a1" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/beaut|cosmetic|សម្ផស្ស/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="15" y="18" width="18" height="20" rx="5" fill="url(#cat-beauty)" />
                <rect x="20" y="13" width="8" height="6" rx="2" fill="#fbcfe8" />
                <circle cx="24" cy="11" r="3" fill="#f43f5e" />
                <path d="M20 28C20 25.8 21.8 24 24 24C26.2 24 28 25.8 28 28" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" />
                <defs>
                    <linearGradient id="cat-beauty" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#f472b6" />
                        <stop offset="100%" stopColor="#db2777" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/bag|កាបូប/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M19 18V14C19 11.2 21.2 9 24 9C26.8 9 29 11.2 29 14V18" stroke="#d97706" strokeWidth="3" strokeLinecap="round" />
                <path d="M13 18H35L33 37C33 38.1 32.1 39 31 39H17C15.9 39 15 38.1 15 37L13 18Z" fill="url(#cat-bag)" />
                <circle cx="24" cy="25" r="2.5" fill="#fef3c7" />
                <defs>
                    <linearGradient id="cat-bag" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#fbbf24" />
                        <stop offset="100%" stopColor="#d97706" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/electr|phone|tech|គ្រឿងអេឡិចត្រូនិក/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="16" y="10" width="16" height="28" rx="4" fill="url(#cat-elec)" />
                <rect x="18" y="13" width="12" height="20" rx="2" fill="#0f172a" />
                <circle cx="24" cy="35" r="1.5" fill="#94a3b8" />
                <defs>
                    <linearGradient id="cat-elec" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#60a5fa" />
                        <stop offset="100%" stopColor="#2563eb" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/access|jewel|gem|គ្រឿងតុបតែង/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M18 15L30 15L36 22L24 35L12 22L18 15Z" fill="url(#cat-gem)" />
                <path d="M18 15L24 35L30 15" stroke="#ffffff" strokeWidth="1.2" opacity="0.6" />
                <path d="M12 22H36" stroke="#ffffff" strokeWidth="1.2" opacity="0.6" />
                <defs>
                    <linearGradient id="cat-gem" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#c084fc" />
                        <stop offset="100%" stopColor="#7e22ce" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/watch|clock|នាឡិកា/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <rect x="20" y="8" width="8" height="32" rx="3" fill="#64748b" />
                <rect x="16" y="15" width="16" height="18" rx="5" fill="url(#cat-watch)" />
                <circle cx="24" cy="24" r="5" fill="#0f172a" />
                <path d="M24 21V24H26.5" stroke="#38bdf8" strokeWidth="1.5" strokeLinecap="round" />
                <defs>
                    <linearGradient id="cat-watch" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#94a3b8" />
                        <stop offset="100%" stopColor="#475569" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/foot|shoe|sneaker|ស្បែកជើង/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M12 28L17 18C17.5 17 19 17 20 18L23 22L31 23C34 23 37 25 37 28V31C37 32 36 33 35 33H14C12.9 33 12 32.1 12 31V28Z" fill="url(#cat-shoe)" />
                <path d="M12 31H37V33C37 34 36 35 35 35H14C12.9 35 12 34 12 33V31Z" fill="#ffffff" />
                <circle cx="19" cy="22" r="1.2" fill="#ffffff" />
                <circle cx="22" cy="24" r="1.2" fill="#ffffff" />
                <defs>
                    <linearGradient id="cat-shoe" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#2dd4bf" />
                        <stop offset="100%" stopColor="#0f766e" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/cloth|wear|shirt|pant|dress|សម្លៀកបំពាក់/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M18 12C20 14 24 15 24 15C24 15 28 14 30 12L37 17L33 22L30 20V36H18V20L15 22L11 17L18 12Z" fill="url(#cat-cloth)" />
                <defs>
                    <linearGradient id="cat-cloth" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#a78bfa" />
                        <stop offset="100%" stopColor="#6d28d9" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    if (/food|dish|meal|restaurant|ម្ហូប/i.test(key)) {
        return (
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M14 26C14 32 18.5 36 24 36C29.5 36 34 32 34 26H14Z" fill="url(#cat-food)" />
                <rect x="12" y="24" width="24" height="3" rx="1.5" fill="#f97316" />
                <path d="M18 19C18 16 19 14 19 14M24 19C24 16 25 14 25 14M29 19C29 16 30 14 30 14" stroke="#fb923c" strokeWidth="1.8" strokeLinecap="round" />
                <defs>
                    <linearGradient id="cat-food" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#fb923c" />
                        <stop offset="100%" stopColor="#ea580c" />
                    </linearGradient>
                </defs>
            </svg>
        );
    }
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="#ff762d" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="m7.5 4.27 9 5.15" />
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
            <path d="m3.3 7 8.7 5 8.7-5" />
            <path d="M12 22V12" />
        </svg>
    );
}

export function ShopUnavailableState({
    error,
    retry,
}: {
    error: string;
    retry?: () => void;
}) {
    const { t } = useShop();
    return (
        <div className="shop-unavailable-card">
            <div className="shop-unavailable-icon">
                <svg
                    width="44"
                    height="44"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#f59e0b"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                </svg>
            </div>
            <div className="shop-unavailable-badge">
                <span className="shop-pulse-dot" aria-hidden="true" />
                503 • {t("Maintenance")}
            </div>
            <h2>{t("The store is temporarily unavailable.")}</h2>
            <p>{t(error || "We are performing system updates. Please check back shortly.")}</p>
            {retry && (
                <button
                    type="button"
                    className="shop-retry-button"
                    onClick={retry}
                >
                    <RotateCcw size={16} aria-hidden="true" />
                    {t("Retry Connection")}
                </button>
            )}
        </div>
    );
}
