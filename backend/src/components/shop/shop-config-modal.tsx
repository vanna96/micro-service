import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import {
    Minus,
    Plus,
    Scale,
    SlidersHorizontal,
    Sparkles,
    X,
} from "lucide-react";
import { shopApi } from "@/lib/shop-api";
import type {
    ShopOptionGroup,
    ShopPricing,
    ShopProduct,
    ShopUnit,
    ShopVariant,
} from "@/types/shop";
import { useShop } from "./shop-provider";
import { ShopImage } from "./shop-ui";

interface ShopProductConfigModalProps {
    product: ShopProduct;
    onClose: () => void;
}

export function ShopProductConfigModal({
    product,
    onClose,
}: ShopProductConfigModalProps) {
    const { t, name, money, ready, sessionError, add, announce, cart } =
        useShop();
    const sheetRef = useRef<HTMLDivElement>(null);

    // Units
    const units = product.uom_group?.units || [];
    const hasUOM = units.length > 0;
    const [selectedUnitId, setSelectedUnitId] = useState<number | null>(() => {
        const base = units.find((u) => u.is_base_unit);
        return base?.id ?? units[0]?.id ?? null;
    });
    const selectedUnit: ShopUnit | null =
        units.find((u) => u.id === selectedUnitId) || units[0] || null;

    // Option groups & variants
    const groups: ShopOptionGroup[] = product.option_groups || [];
    const variants: ShopVariant[] = product.variants || [];
    const [options, setOptions] = useState<Record<number, number[]>>(() =>
        Object.fromEntries(
            groups.map((group) => {
                const isVariant = group.type === "variant";
                const defaultVals = group.values
                    .filter((value) => value.is_default)
                    .map((value) => value.id);
                if (
                    isVariant &&
                    defaultVals.length === 0 &&
                    group.values.length > 0
                ) {
                    return [group.id, [group.values[0].id]];
                }
                return [group.id, defaultVals];
            }),
        ),
    );

    const [quantity, setQuantity] = useState(1);

    const selectedOptions = Object.values(options).flat();
    const variantGroups = groups.filter((group) => group.type === "variant");
    const variantOptions = variantGroups.flatMap(
        (group) => options[group.id] || [],
    );
    const variant = variants.length
        ? variantGroups.length
            ? variants.find(
                  (value) =>
                      value.option_value_ids.length === variantOptions.length &&
                      value.option_value_ids.every((id) =>
                          variantOptions.includes(id),
                      ),
              )
            : variants.find((value) => value.is_default) || variants[0]
        : null;

    const valid =
        groups.every((group) => {
            const count = (options[group.id] || []).length;
            const isVariant = group.type === "variant";
            const min = isVariant
                ? 1
                : Math.max(
                      Number(group.min_selections || 0),
                      group.is_required ? 1 : 0,
                  );
            const max = isVariant
                ? 1
                : group.selection_type === "single"
                  ? 1
                  : group.max_selections !== null &&
                      group.max_selections !== undefined
                    ? Number(group.max_selections)
                    : null;
            return count >= min && (max === null || count <= max);
        }) &&
        (!variants.length || !!variant);

    // Stock calculations
    const conversionFactor = selectedUnit?.conversion_factor_to_base || 1;
    const baseAvailable = variant?.stock ?? product.stock;
    const inCartBase = cart
        .filter(
            (line) =>
                line.item_id === product.id &&
                (!variant || line.variant_id === variant.id),
        )
        .reduce((sum, line) => {
            const u = units.find((val) => val.id === line.uom_id);
            return sum + line.quantity * (u?.conversion_factor_to_base || 1);
        }, 0);

    const maxQuantity = product.stock_control
        ? Math.max(
              0,
              Math.floor((baseAvailable - inCartBase) / conversionFactor),
          )
        : 9999;

    const isOutOfStock = product.stock_control && maxQuantity <= 0;

    const getUnitStock = (u: ShopUnit) => {
        if (!product.stock_control) return null;
        const factor =
            u.conversion_factor_to_base > 0 ? u.conversion_factor_to_base : 1;
        const remainingBase = Math.max(0, baseAvailable - inCartBase);
        return Math.floor(remainingBase / factor);
    };

    const isUnitOutOfStock = (u: ShopUnit) => {
        if (!product.stock_control) return false;
        const stock = getUnitStock(u);
        return stock !== null && stock <= 0;
    };

    // Calculate prices
    const unitPrice = selectedUnit ? selectedUnit.price : product.price;
    const optionsPriceDelta = groups
        .flatMap((g) => g.values)
        .filter((v) => selectedOptions.includes(v.id))
        .reduce((sum, v) => sum + (v.price_adjustment || 0), 0);
    const currentUnitPrice = (variant?.resolved_price ?? unitPrice) + optionsPriceDelta;
    const totalPrice = currentUnitPrice * quantity;

    // Real-time promo pricing quote
    const line = {
        item_id: product.id,
        quantity,
        variant_id: variant?.id || null,
        uom_id: selectedUnitId,
        option_value_ids: selectedOptions,
    };
    const quoteKey = valid ? JSON.stringify(line) : "";
    const [quote, setQuote] = useState<{
        key: string;
        data: ShopPricing | null;
        error: string;
    }>({ key: "", data: null, error: "" });

    useEffect(() => {
        if (!quoteKey) return;
        let active = true;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            shopApi<ShopPricing>("cart/price", {
                method: "POST",
                body: JSON.stringify({
                    items: [JSON.parse(quoteKey)],
                    currency_mode: "base",
                }),
                signal: controller.signal,
            })
                .then((response) => {
                    if (active)
                        setQuote({
                            key: quoteKey,
                            data: response.data,
                            error: "",
                        });
                })
                .catch(() => {
                    // Fall back gracefully to local calculation
                });
        }, 200);
        return () => {
            active = false;
            controller.abort();
            clearTimeout(timer);
        };
    }, [quoteKey]);

    // Handle escape key
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") onClose();
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [onClose]);

    // Focus sheet on mount
    useEffect(() => {
        const screen = document.querySelector<HTMLElement>(".shop-screen");
        const bodyOverflow = document.body.style.overflow;
        const screenOverflow = screen?.style.overflow || "";
        const screenOverscroll = screen?.style.overscrollBehavior || "";
        const screenTouchAction = screen?.style.touchAction || "";

        document.body.style.overflow = "hidden";
        if (screen) {
            screen.style.overflow = "hidden";
            screen.style.overscrollBehavior = "none";
            screen.style.touchAction = "none";
        }
        sheetRef.current?.focus();

        return () => {
            document.body.style.overflow = bodyOverflow;
            if (screen) {
                screen.style.overflow = screenOverflow;
                screen.style.overscrollBehavior = screenOverscroll;
                screen.style.touchAction = screenTouchAction;
            }
        };
    }, []);

    const handleAddToCart = () => {
        if (isOutOfStock || !valid || quantity > maxQuantity || !ready || !!sessionError) {
            return;
        }
        add({
            item_id: product.id,
            quantity,
            variant_id: variant?.id || null,
            uom_id: selectedUnitId,
            option_value_ids: selectedOptions,
            product,
        });
        onClose();
    };

    const portalTarget =
        typeof document === "undefined"
            ? null
            : document.querySelector<HTMLElement>(".shop-app");

    if (!portalTarget) return null;

    return createPortal(
        <div
            className="shop-overlay"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onClose();
            }}
        >
            <div
                className="shop-sheet shop-config-sheet"
                ref={sheetRef}
                role="dialog"
                aria-modal="true"
                aria-label={name(product)}
                tabIndex={-1}
            >
                {/* Top Drag Handle */}
                <span className="shop-sheet-handle" />

                {/* Header */}
                <div className="shop-config-header">
                    <div className="shop-config-icon-circle">
                        {hasUOM ? (
                            <Scale size={18} className="shop-config-icon-indigo" />
                        ) : (
                            <SlidersHorizontal
                                size={18}
                                className="shop-config-icon-indigo"
                            />
                        )}
                    </div>
                    <div className="shop-config-header-text">
                        <h2 className="shop-config-title">{name(product)}</h2>
                        {product.sku && (
                            <span className="shop-sku">{product.sku}</span>
                        )}
                    </div>
                    <button
                        type="button"
                        className="shop-icon-button"
                        onClick={onClose}
                        aria-label={t("Close")}
                    >
                        <X size={20} />
                    </button>
                </div>

                {/* Scrollable Body */}
                <div className="shop-config-body">
                    {/* Product Preview Card */}
                    <div className="shop-config-preview">
                        <div className="shop-config-thumb">
                            <ShopImage
                                src={product.image_url}
                                alt={name(product)}
                            />
                        </div>
                        <div className="shop-config-preview-info">
                            <span className="shop-config-unit-label">
                                {selectedUnit
                                    ? `${t("Unit")}: ${name(selectedUnit)}`
                                    : `${t("Base Price")}: ${money(product.price, product.currency)}`}
                            </span>
                            <span className="shop-config-price">
                                {money(currentUnitPrice, product.currency)}
                            </span>
                        </div>
                    </div>

                    {/* Virtual Try-On Banner */}
                    {product.is_try_on_enabled && (
                        <div className="shop-config-tryon-banner">
                            <div className="shop-config-tryon-icon">
                                <Sparkles size={16} />
                            </div>
                            <div className="shop-config-tryon-text">
                                <strong>{t("Virtual Try-On Supported")}</strong>
                                <span>
                                    {t("This item is flagged for customer garment virtual preview")}
                                </span>
                            </div>
                            <span className="shop-config-active-pill">
                                {t("Active")}
                            </span>
                        </div>
                    )}

                    {/* UOM Selector Section */}
                    {hasUOM && (
                        <div className="shop-config-section">
                            <h3 className="shop-config-section-title">
                                {t("Select Unit of Measure (UOM)")}:
                            </h3>
                            <div className="shop-config-uom-list">
                                {units.map((u) => {
                                    const isSelected = selectedUnitId === u.id;
                                    const outOfStock = isUnitOutOfStock(u);
                                    const unitStock = getUnitStock(u);

                                    return (
                                        <button
                                            key={u.id}
                                            type="button"
                                            disabled={outOfStock}
                                            className={`shop-config-uom-item${
                                                isSelected ? " is-selected" : ""
                                            }${outOfStock ? " is-out-of-stock" : ""}`}
                                            onClick={() => {
                                                setSelectedUnitId(u.id);
                                                const maxForThis = getUnitStock(u);
                                                if (
                                                    maxForThis !== null &&
                                                    maxForThis > 0 &&
                                                    quantity > maxForThis
                                                ) {
                                                    setQuantity(maxForThis);
                                                }
                                            }}
                                        >
                                            <div className="shop-config-uom-left">
                                                <span className="shop-config-uom-code">
                                                    {(u.code || u.name).toUpperCase()}
                                                </span>
                                                <span className="shop-config-uom-name">
                                                    {name(u)}
                                                </span>
                                                {product.stock_control && (
                                                    <span
                                                        className={`shop-config-stock-pill${
                                                            outOfStock
                                                                ? " out"
                                                                : " in"
                                                        }`}
                                                    >
                                                        {outOfStock
                                                            ? t("Out of stock")
                                                            : `${unitStock} ${t("available")}`}
                                                    </span>
                                                )}
                                            </div>
                                            <span className="shop-config-uom-price">
                                                {money(u.price, product.currency)}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Options / Variants Section */}
                    {groups.map((group) => {
                        const isMultiple = group.selection_type === "multiple";
                        const min = group.type === "variant"
                            ? 1
                            : Math.max(
                                  Number(group.min_selections || 0),
                                  group.is_required ? 1 : 0,
                              );
                        const max = group.type === "variant"
                            ? 1
                            : group.selection_type === "single"
                              ? 1
                              : group.max_selections !== null &&
                                  group.max_selections !== undefined
                                ? Number(group.max_selections)
                                : null;
                        const isRequired = min > 0;
                        const isNullable = min === 0;
                        const currentSelections = options[group.id] || [];
                        const selectedCount = currentSelections.length;

                        return (
                            <div className="shop-config-section" key={group.id}>
                                <div className="shop-group-header">
                                    <div>
                                        <h4 className="shop-group-title">
                                            {name(group)}
                                            {isRequired && (
                                                <span className="shop-star-required">*</span>
                                            )}
                                        </h4>
                                        <p className="shop-group-rules">
                                            {isMultiple
                                                ? `${t("Select")} ${min > 0 ? `${min}–` : ""}${max || group.values.length}`
                                                : isRequired
                                                  ? t("Choose 1 option")
                                                  : t("Optional")}
                                        </p>
                                    </div>
                                    <span
                                        className={`shop-status-pill ${
                                            isRequired ? "required" : "optional"
                                        }`}
                                    >
                                        {t(isRequired ? "Required" : "Optional")}
                                        {isMultiple && max && (
                                            <> · {selectedCount}/{max}</>
                                        )}
                                    </span>
                                </div>

                                <div className="shop-options">
                                    {isNullable && !isMultiple && (
                                        <button
                                            type="button"
                                            aria-pressed={selectedCount === 0}
                                            className={`shop-option-item shop-option-none ${
                                                selectedCount === 0 ? "selected" : ""
                                            }`}
                                            onClick={() => {
                                                setOptions({
                                                    ...options,
                                                    [group.id]: [],
                                                });
                                            }}
                                        >
                                            <span>{t("None")}</span>
                                        </button>
                                    )}

                                    {group.values.map((value) => {
                                        const isSelected = currentSelections.includes(value.id);
                                        return (
                                            <button
                                                type="button"
                                                key={value.id}
                                                aria-pressed={isSelected}
                                                className={`shop-option-item ${
                                                    isSelected ? "selected" : ""
                                                }`}
                                                onClick={() => {
                                                    if (isMultiple) {
                                                        if (isSelected) {
                                                            setOptions({
                                                                ...options,
                                                                [group.id]: currentSelections.filter(
                                                                    (id) => id !== value.id,
                                                                ),
                                                            });
                                                        } else {
                                                            if (max !== null && selectedCount >= max) {
                                                                if (max === 1) {
                                                                    setOptions({
                                                                        ...options,
                                                                        [group.id]: [value.id],
                                                                    });
                                                                } else {
                                                                    announce(t("Maximum selections reached"));
                                                                }
                                                            } else {
                                                                setOptions({
                                                                    ...options,
                                                                    [group.id]: [
                                                                        ...currentSelections,
                                                                        value.id,
                                                                    ],
                                                                });
                                                            }
                                                        }
                                                    } else {
                                                        setOptions({
                                                            ...options,
                                                            [group.id]: [value.id],
                                                        });
                                                    }
                                                }}
                                            >
                                                <span>{name(value)}</span>
                                                {value.price_adjustment ? (
                                                    <small className="shop-option-delta">
                                                        +{money(value.price_adjustment, product.currency)}
                                                    </small>
                                                ) : null}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    {/* Quantity Stepper Row */}
                    <div className="shop-config-qty-row">
                        <div className="shop-config-qty-label">
                            <span className="shop-config-qty-title">
                                {t("Quantity")}:
                            </span>
                            {product.stock_control && (
                                <span
                                    className={`shop-config-qty-stock${
                                        isOutOfStock ? " out" : ""
                                    }`}
                                >
                                    {isOutOfStock
                                        ? t("Out of stock")
                                        : `${maxQuantity} ${t("available")}`}
                                </span>
                            )}
                        </div>

                        <div className="shop-counter">
                            <button
                                type="button"
                                aria-label={t("Decrease quantity")}
                                disabled={quantity <= 1}
                                onClick={() => setQuantity(quantity - 1)}
                            >
                                <Minus size={16} />
                            </button>
                            <strong>{quantity}</strong>
                            <button
                                type="button"
                                aria-label={t("Increase quantity")}
                                disabled={quantity >= maxQuantity}
                                onClick={() => setQuantity(quantity + 1)}
                            >
                                <Plus size={16} />
                            </button>
                        </div>
                    </div>
                </div>

                {/* Bottom Actions Bar */}
                <div className="shop-config-actions">
                    <button
                        type="button"
                        className="shop-config-btn-cancel"
                        onClick={onClose}
                    >
                        {t("Cancel")}
                    </button>
                    <button
                        type="button"
                        className="shop-config-btn-add"
                        disabled={
                            isOutOfStock ||
                            !valid ||
                            quantity > maxQuantity ||
                            !ready ||
                            !!sessionError
                        }
                        onClick={handleAddToCart}
                    >
                        <Plus size={18} />
                        <span>
                            {t("Add to Cart")} •{" "}
                            {quote.key === quoteKey && quote.data
                                ? money(quote.data.final_total, product.currency)
                                : money(totalPrice, product.currency)}
                        </span>
                    </button>
                </div>
            </div>
        </div>,
        portalTarget,
    );
}
