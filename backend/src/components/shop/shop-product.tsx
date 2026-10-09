import { useRouter } from "next/router";
import { Crown, Heart, Minus, Plus, Scale, Sparkles, Star, Tag } from "lucide-react";
import { useEffect, useState } from "react";
import { shopApi, ShopApiError } from "@/lib/shop-api";
import type { ShopPricing, ShopProduct } from "@/types/shop";
import { useShop } from "./shop-provider";
import {
    ErrorState,
    LoadingCards,
    ShopHeader,
    ShopImage,
    useShopResource,
} from "./shop-ui";

export function ProductScreen({ id }: { id: string }) {
    const resource = useShopResource<ShopProduct>(`products/${id}`);
    const { t } = useShop();
    return (
        <>
            <ShopHeader title={t("Product details")} back />
            <div className="shop-content">
                {resource.loading && <LoadingCards />}
                {resource.error && (
                    <ErrorState
                        error={resource.error}
                        retry={resource.reload}
                    />
                )}
                {resource.data && (
                    <ProductConfiguration
                        key={resource.data.id}
                        product={resource.data}
                    />
                )}
            </div>
        </>
    );
}

function ProductConfiguration({ product }: { product: ShopProduct }) {
    const {
        t,
        name,
        money,
        ready,
        sessionError,
        add,
        favorites,
        toggleFavorite,
        announce,
        cart,
    } = useShop();
    const router = useRouter();
    const units = product.uom_group?.units || [];
    const groups = product.option_groups || [];
    const variants = product.variants || [];
    const [unitId, setUnitId] = useState(
        units.find((unit) => unit.is_base_unit)?.id || units[0]?.id || null,
    );
    const [options, setOptions] = useState<Record<number, number[]>>(() =>
        Object.fromEntries(
            groups.map((group) => {
                const isVariant = group.type === "variant";
                const defaultVals = group.values
                    .filter((value) => value.is_default)
                    .map((value) => value.id);
                if (isVariant && defaultVals.length === 0 && group.values.length > 0) {
                    return [group.id, [group.values[0].id]];
                }
                return [group.id, defaultVals];
            }),
        ),
    );
    const [quantity, setQuantity] = useState(1);
    const [image, setImage] = useState(product.image_url);
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
    const unit = units.find((value) => value.id === unitId);
    const available = variant?.stock ?? product.stock;
    const inCartBase = cart
        .filter(
            (line) =>
                line.item_id === product.id &&
                (!variant || line.variant_id === variant.id),
        )
        .reduce(
            (sum, line) =>
                sum +
                line.quantity *
                    (units.find((value) => value.id === line.uom_id)
                        ?.conversion_factor_to_base || 1),
            0,
        );
    const maxQuantity = product.stock_control
        ? Math.max(
              0,
              Math.floor(
                  (available - inCartBase) /
                      (unit?.conversion_factor_to_base || 1),
              ),
          )
        : 9999;
    const line = {
        item_id: product.id,
        quantity,
        variant_id: variant?.id || null,
        uom_id: unitId,
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
                .catch((error) => {
                    if (active)
                        setQuote({
                            key: quoteKey,
                            data: null,
                            error:
                                error instanceof Error
                                    ? error.message
                                    : "Please try again.",
                        });
                });
        }, 250);
        return () => {
            active = false;
            controller.abort();
            clearTimeout(timer);
        };
    }, [quoteKey]);
    const favorite = favorites.some((value) => value.id === product.id);
    const galleries = [
        product.image_url,
        ...(product.galleries || []).map((value) => value.image_url),
    ].filter(
        (value, index, values) => value && values.indexOf(value) === index,
    );
    // Render catalog HTML as readable text instead of executing tenant markup.
    const description = product.description
        .replace(/<\/(?:p|div|li)>|<br\s*\/?\s*>/gi, "\n")
        .replace(/<[^>]*>/g, "")
        .replace(/&nbsp;/g, " ")
        .replace(/&amp;/g, "&");
    return (
        <>
            <div className="shop-detail-image">
                <ShopImage src={image} alt={name(product)} />
                <button
                    className={`shop-heart${favorite ? " selected" : ""}`}
                    aria-pressed={favorite}
                    aria-label={t("Favorites")}
                    onClick={async () => {
                        try {
                            await toggleFavorite(product);
                        } catch (error) {
                            if (
                                error instanceof ShopApiError &&
                                error.status === 401
                            )
                                void router.push(
                                    `/account?next=/products/${product.id}`,
                                );
                            else
                                announce(
                                    error instanceof Error
                                        ? error.message
                                        : t("Retry"),
                                );
                        }
                    }}
                >
                    <Heart
                        size={22}
                        fill={favorite ? "currentColor" : "none"}
                    />
                </button>
            </div>
            {galleries.length > 1 && (
                <div className="shop-thumbnails">
                    {galleries.map((src) => (
                        <button
                            key={src}
                            aria-label={t("Product image")}
                            aria-pressed={image === src}
                            onClick={() => setImage(src)}
                        >
                            <ShopImage src={src} alt={name(product)} />
                        </button>
                    ))}
                </div>
            )}
            {product.is_try_on_enabled && (
                <div className="shop-tryon-banner">
                    <div className="shop-tryon-content">
                        <div className="shop-tryon-icon">
                            <Sparkles size={16} />
                        </div>
                        <div>
                            <strong>{t("Virtual Try-On Supported")}</strong>
                            <p>{t("Interactive preview available")}</p>
                        </div>
                    </div>
                    <span className="shop-badge-pill shop-badge-tryon">{t("Active")}</span>
                </div>
            )}
            <section className="shop-panel">
                <div className="shop-detail-badge-row">
                    {product.is_try_on_enabled && (
                        <span className="shop-badge-pill shop-badge-tryon">
                            <Sparkles size={10} />
                            <span>{t("Try-On")}</span>
                        </span>
                    )}
                    {product.is_premium && (
                        <span className="shop-badge-pill shop-badge-premium">
                            <Crown size={10} />
                            <span>{t("Premium")}</span>
                        </span>
                    )}
                    {product.is_featured && (
                        <span className="shop-badge-pill shop-badge-featured">
                            <Star size={10} fill="currentColor" />
                            <span>{t("Featured")}</span>
                        </span>
                    )}
                    {product.is_new_arrival && (
                        <span className="shop-badge-pill shop-badge-new">
                            <Sparkles size={10} fill="currentColor" />
                            <span>{t("New")}</span>
                        </span>
                    )}
                </div>
                <span className="shop-sku">{product.sku}</span>
                <h2 className="shop-detail-title">{name(product)}</h2>
                <div className="shop-detail-price">
                    <strong>
                        {quote.key === quoteKey && quote.data
                            ? money(quote.data.final_total)
                            : money(product.price, product.currency)}
                    </strong>
                    {product.rating !== null && (
                        <span>
                            <Star size={15} fill="#f8b84e" stroke="#f8b84e" />
                            {product.rating}{" "}
                            <small>({product.review_count})</small>
                        </span>
                    )}
                </div>
                <span
                    className={`shop-pill ${maxQuantity === 0 ? "shop-pill-danger" : ""}`}
                >
                    {t(maxQuantity === 0 ? "Out of stock" : "In Stock")}
                </span>
            </section>
            {!!units.length && (
                <section className="shop-panel">
                    <h3>{t("Unit")}</h3>
                    <div className="shop-unit-options">
                        {units.map((value) => (
                            <button
                                key={value.id}
                                aria-pressed={unitId === value.id}
                                className={
                                    unitId === value.id ? "selected" : ""
                                }
                                onClick={() => {
                                    setUnitId(value.id);
                                    setQuantity(1);
                                }}
                            >
                                <span>{name(value)}</span>
                                <strong>
                                    {money(value.price, product.currency)}
                                </strong>
                            </button>
                        ))}
                    </div>
                </section>
            )}
            {groups.map((group) => {
                const isVariant = group.type === "variant";
                const min = isVariant
                    ? 1
                    : Math.max(
                          Number(group.min_selections || 0),
                          group.is_required ? 1 : 0,
                      );
                const isMultiple = group.selection_type === "multiple" && !isVariant;
                const max = isVariant
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
                const isUnderMin = selectedCount < min;
                const isAtMax = max !== null && selectedCount >= max;

                return (
                    <section className="shop-panel" key={group.id}>
                        <div className="shop-group-header">
                            <div>
                                <h3 className="shop-group-title">
                                    {name(group)}
                                    {isRequired && (
                                        <span className="shop-star-required">*</span>
                                    )}
                                </h3>
                                <p className="shop-group-rules">
                                    {isMultiple ? (
                                        <>
                                            {t("Select")} {min > 0 ? `${min}–` : ""}{max ? max : group.values.length}
                                        </>
                                    ) : isRequired ? (
                                        t("Choose 1 option")
                                    ) : (
                                        t("Optional")
                                    )}
                                </p>
                            </div>
                            <span
                                className={`shop-status-pill ${isRequired ? "required" : "optional"}`}
                            >
                                {t(isRequired ? "Required" : "Optional")}
                                {isMultiple && max && (
                                    <> · {selectedCount}/{max}</>
                                )}
                            </span>
                        </div>

                        <div className="shop-options">
                            {/* Nullable single-select: explicit "None" button */}
                            {isNullable && !isMultiple && (
                                <button
                                    type="button"
                                    aria-pressed={selectedCount === 0}
                                    className={`shop-option-item shop-option-none ${selectedCount === 0 ? "selected" : ""}`}
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
                                const selected = currentSelections.includes(value.id);
                                return (
                                    <button
                                        type="button"
                                        aria-pressed={selected}
                                        className={`shop-option-item ${selected ? "selected" : ""}`}
                                        key={value.id}
                                        onClick={() => {
                                            if (isMultiple) {
                                                if (selected) {
                                                    // Deselect
                                                    setOptions({
                                                        ...options,
                                                        [group.id]: currentSelections.filter(
                                                            (id) => id !== value.id,
                                                        ),
                                                    });
                                                } else {
                                                    // Select new
                                                    if (max !== null && selectedCount >= max) {
                                                        if (max === 1) {
                                                            // Replace single in multiple
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
                                                // Single selection
                                                if (selected) {
                                                    if (isNullable) {
                                                        // Toggle off to none!
                                                        setOptions({
                                                            ...options,
                                                            [group.id]: [],
                                                        });
                                                    }
                                                } else {
                                                    setOptions({
                                                        ...options,
                                                        [group.id]: [value.id],
                                                    });
                                                }
                                            }
                                        }}
                                    >
                                        {value.color_hex &&
                                            /^#[0-9a-f]{3,8}$/i.test(value.color_hex) && (
                                                <span
                                                    className="shop-color-dot"
                                                    style={{ background: value.color_hex }}
                                                />
                                            )}
                                        <span>{name(value)}</span>
                                        {value.price_adjustment > 0 && (
                                            <small className="shop-price-delta">
                                                +{money(value.price_adjustment, product.currency)}
                                            </small>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Validation notice */}
                        {isUnderMin && (
                            <small className="shop-group-notice text-danger">
                                {t("Please select at least")} {min} {t("option(s)")}
                            </small>
                        )}
                        {isAtMax && isMultiple && (
                            <small className="shop-group-notice text-muted">
                                {t("Maximum selections reached")}
                            </small>
                        )}
                    </section>
                );
            })}
            {description && (
                <section className="shop-panel">
                    <h3>{t("Description")}</h3>
                    <p className="shop-description">{description}</p>
                </section>
            )}
            <section className="shop-panel">
                <div className="shop-quantity-row">
                    <h3>{t("Quantity")}</h3>
                    <div className="shop-counter">
                        <button
                            aria-label={t("Decrease quantity")}
                            disabled={quantity <= 1}
                            onClick={() => setQuantity(quantity - 1)}
                        >
                            <Minus size={17} />
                        </button>
                        <strong>{quantity}</strong>
                        <button
                            aria-label={t("Increase quantity")}
                            disabled={quantity >= maxQuantity}
                            onClick={() => setQuantity(quantity + 1)}
                        >
                            <Plus size={17} />
                        </button>
                    </div>
                </div>
                {quote.key === quoteKey && quote.error && (
                    <ErrorState error={quote.error} />
                )}
                <button
                    className="shop-button"
                    disabled={
                        !ready ||
                        !!sessionError ||
                        !valid ||
                        quantity > maxQuantity ||
                        quote.key !== quoteKey ||
                        !quote.data ||
                        !!quote.error
                    }
                    onClick={() => add({ ...line, product })}
                >
                    <Plus size={19} />
                    {t("Add to Cart")}
                    {valid && quote.key === quoteKey && quote.data && (
                        <span>{money(quote.data.final_total)}</span>
                    )}
                </button>
                {!valid && <p className="shop-muted">{t("Choose options")}</p>}
            </section>
        </>
    );
}
