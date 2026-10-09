import Link from "next/link";
import { useRouter } from "next/router";
import {
    Banknote,
    CheckCircle2,
    CreditCard,
    Gift,
    Landmark,
    Minus,
    Plus,
    QrCode,
    ShoppingBag,
    Trash2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { cartPayload, lineKey, shopApi } from "@/lib/shop-api";
import type { ShopAddress, ShopOrder, ShopPricing } from "@/types/shop";
import { useShop } from "./shop-provider";
import {
    EmptyState,
    ErrorState,
    Sheet,
    ShopHeader,
    ShopImage,
    useShopResource,
} from "./shop-ui";

export function CartScreen() {
    const { cart, updateCart, money, t, name, user, ready, flushCart } =
        useShop();
    const router = useRouter();
    const [checkout, setCheckout] = useState(false);
    const [order, setOrder] = useState<ShopOrder | null>(null);
    const [revision, setRevision] = useState(0);
    const itemsKey = JSON.stringify(cartPayload(cart));
    const [quote, setQuote] = useState<{
        key: string;
        data: ShopPricing | null;
        error: string;
    }>({ key: "", data: null, error: "" });
    useEffect(() => {
        if (itemsKey === "[]") return;
        let active = true;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            shopApi<ShopPricing>("cart/price", {
                method: "POST",
                body: JSON.stringify({
                    currency_mode: "base",
                    items: JSON.parse(itemsKey),
                }),
                signal: controller.signal,
            })
                .then((response) => {
                    if (active)
                        setQuote({
                            key: itemsKey,
                            data: response.data,
                            error: "",
                        });
                })
                .catch((error) => {
                    if (active)
                        setQuote({
                            key: itemsKey,
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
    }, [itemsKey, revision]);
    const pricing = quote.key === itemsKey ? quote.data : null;
    const changeQuantity = (key: string, quantity: number) =>
        updateCart(
            cart
                .filter((line) => quantity > 0 || lineKey(line) !== key)
                .map((line) =>
                    lineKey(line) === key ? { ...line, quantity } : line,
                ),
        );
    return (
        <>
            <ShopHeader
                title={t("My Cart")}
                action={
                    cart.length > 0 ? (
                        <button
                            className="shop-icon-button"
                            aria-label={t("Clear Cart")}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        t(
                                            "Are you sure you want to remove all items from your cart?",
                                        ),
                                    )
                                )
                                    updateCart([]);
                            }}
                        >
                            <Trash2 size={19} />
                        </button>
                    ) : undefined
                }
            />
            <div className="shop-content">
                {!ready ? (
                    <p className="shop-muted">{t("Loading…")}</p>
                ) : !cart.length ? (
                    <EmptyState
                        title="Your cart is empty"
                        icon={<ShoppingBag size={48} strokeWidth={1.2} />}
                    >
                        <Link className="shop-button" href="/shop">
                            {t("Continue shopping")}
                        </Link>
                    </EmptyState>
                ) : (
                    <>
                        <div className="shop-cart-lines">
                            {cart.map((line) => {
                                const product = line.product;
                                const key = lineKey(line);
                                const unit = product?.uom_group?.units.find(
                                    (value) => value.id === line.uom_id,
                                );
                                const variant = product?.variants.find(
                                    (value) => value.id === line.variant_id,
                                );
                                const configurationKey = lineKey({
                                    ...line,
                                    option_value_ids: [
                                        ...new Set([
                                            ...line.option_value_ids,
                                            ...(variant?.option_value_ids ||
                                                []),
                                        ]),
                                    ],
                                });
                                const pricedLine = pricing?.items.find(
                                    (value) =>
                                        lineKey({
                                            item_id: value.item_id,
                                            variant_id:
                                                value.item_variant_id || null,
                                            uom_id: value.uom_id || null,
                                            option_value_ids:
                                                value.selected_options?.flatMap(
                                                    (group) =>
                                                        group.values.map(
                                                            (option) =>
                                                                option.id,
                                                        ),
                                                ) || [],
                                        }) === configurationKey,
                                );
                                const hasLinePromotion = Boolean(
                                    pricedLine &&
                                        pricedLine.discount_amount > 0 &&
                                        pricing?.applied_promotion?.type !==
                                            "subtotal_discount",
                                );
                                const pricedQuantity = Math.max(
                                    1,
                                    pricedLine?.quantity || line.quantity,
                                );
                                const originalUnitPrice = pricedLine
                                    ? pricedLine.line_subtotal / pricedQuantity
                                    : unit?.price ??
                                      variant?.resolved_price ??
                                      product?.price ??
                                      0;
                                const finalUnitPrice = pricedLine
                                    ? pricedLine.line_total / pricedQuantity
                                    : originalUnitPrice;
                                const optionNames =
                                    product?.option_groups.flatMap((group) =>
                                        group.values
                                            .filter((value) =>
                                                line.option_value_ids.includes(
                                                    value.id,
                                                ),
                                            )
                                            .map(name),
                                    ) || [];
                                const max = product?.stock_control
                                    ? Math.floor(
                                          (variant?.stock ?? product.stock) /
                                              (unit?.conversion_factor_to_base ||
                                                  1),
                                      )
                                    : Infinity;
                                return (
                                    <article
                                        className="shop-cart-line"
                                        key={key}
                                    >
                                        <Link
                                            href={`/products/${line.item_id}`}
                                        >
                                            <ShopImage
                                                src={product?.image_url}
                                                alt={
                                                    product
                                                        ? name(product)
                                                        : t("Unavailable item")
                                                }
                                            />
                                        </Link>
                                        <div>
                                            <Link
                                                className="shop-product-name"
                                                href={`/products/${line.item_id}`}
                                            >
                                                {product
                                                    ? name(product)
                                                    : t("Unavailable item")}
                                            </Link>
                                            <p>
                                                {[
                                                    unit && name(unit),
                                                    ...optionNames,
                                                ]
                                                    .filter(Boolean)
                                                    .join(" · ")}
                                            </p>
                                            {product && (
                                                <div className="shop-cart-price">
                                                    {hasLinePromotion && (
                                                        <span className="shop-cart-promo-badge">
                                                            {t("Promo price")}
                                                        </span>
                                                    )}
                                                    <div>
                                                        {hasLinePromotion && (
                                                            <del>
                                                                {money(
                                                                    originalUnitPrice,
                                                                )}
                                                            </del>
                                                        )}
                                                        <strong>
                                                            {money(
                                                                hasLinePromotion
                                                                    ? finalUnitPrice
                                                                    : originalUnitPrice,
                                                                pricedLine
                                                                    ? undefined
                                                                    : product.currency,
                                                            )}
                                                        </strong>
                                                        {(unit?.name ||
                                                            pricedLine?.uom_name) && (
                                                            <span>
                                                                /
                                                                {unit
                                                                    ? name(unit)
                                                                    : pricedLine?.uom_name}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {pricedLine &&
                                                        line.quantity > 1 && (
                                                            <small>
                                                                {t("Line total")}: {" "}
                                                                {money(
                                                                    pricedLine.line_total,
                                                                )}
                                                            </small>
                                                        )}
                                                </div>
                                            )}
                                            <div className="shop-counter">
                                                <button
                                                    aria-label={t(
                                                        "Decrease quantity",
                                                    )}
                                                    onClick={() =>
                                                        changeQuantity(
                                                            key,
                                                            line.quantity - 1,
                                                        )
                                                    }
                                                >
                                                    <Minus size={16} />
                                                </button>
                                                <strong>{line.quantity}</strong>
                                                <button
                                                    disabled={
                                                        line.quantity >= max
                                                    }
                                                    aria-label={t(
                                                        "Increase quantity",
                                                    )}
                                                    onClick={() =>
                                                        changeQuantity(
                                                            key,
                                                            line.quantity + 1,
                                                        )
                                                    }
                                                >
                                                    <Plus size={16} />
                                                </button>
                                            </div>
                                        </div>
                                        <button
                                            className="shop-icon-button shop-remove"
                                            aria-label={t("Remove")}
                                            onClick={() =>
                                                changeQuantity(key, 0)
                                            }
                                        >
                                            <Trash2 size={17} />
                                        </button>
                                    </article>
                                );
                            })}
                            {pricing?.auto_add_items?.map((reward, index) => {
                                const pricedReward = pricing.items.find(
                                    (item) =>
                                        item.item_id === reward.item_id &&
                                        (!reward.uom_id ||
                                            item.uom_id === reward.uom_id),
                                );

                                return (
                                    <article
                                        className="shop-cart-line shop-cart-reward-line"
                                        key={`${reward.promotion_id}:${reward.item_id}:${reward.uom_id || 0}:${index}`}
                                    >
                                        <span className="shop-cart-reward-icon">
                                            <Gift size={28} />
                                        </span>
                                        <div>
                                            <strong>
                                                {pricedReward?.name ||
                                                    t("Free reward")}
                                            </strong>
                                            <span className="shop-cart-reward-badge">
                                                <Gift size={12} />
                                                {t("Free reward")}
                                            </span>
                                            <p>
                                                {reward.promotion_name} · {" "}
                                                {t("Quantity:")} {reward.quantity}
                                            </p>
                                            <div className="shop-cart-reward-price">
                                                {pricedReward && (
                                                    <del>
                                                        {money(
                                                            pricedReward.line_subtotal,
                                                        )}
                                                    </del>
                                                )}
                                                <strong>{t("FREE")}</strong>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                        {quote.key === itemsKey && quote.error && (
                            <ErrorState
                                error={quote.error}
                                retry={() => setRevision((value) => value + 1)}
                            />
                        )}
                        {pricing ? (
                            <section className="shop-panel shop-totals">
                                <div>
                                    <span>{t("Subtotal")}</span>
                                    <strong>{money(pricing.subtotal)}</strong>
                                </div>
                                {pricing.applied_promotion &&
                                pricing.discount_total > 0 ? (
                                    <div className="shop-promotion-applied">
                                        <div>
                                            <span className="shop-promotion-code">
                                                {pricing.applied_promotion.code}
                                            </span>
                                            <strong>
                                                {pricing.applied_promotion.name}
                                            </strong>
                                            {pricing.applied_promotion.summary && (
                                                <small>
                                                    {
                                                        pricing
                                                            .applied_promotion
                                                            .summary
                                                    }
                                                </small>
                                            )}
                                        </div>
                                        <strong>
                                            −{money(pricing.discount_total)}
                                        </strong>
                                    </div>
                                ) : (
                                    <p className="shop-promotion-none">
                                        {t(
                                            "No automatic promotion applies to this cart.",
                                        )}
                                    </p>
                                )}
                                {pricing.auto_add_items?.map((reward, index) => {
                                    const line = pricing.items.find(
                                        (item) =>
                                            item.item_id === reward.item_id &&
                                            (!reward.uom_id ||
                                                item.uom_id === reward.uom_id),
                                    );

                                    return (
                                        <div
                                            className="shop-savings"
                                            key={index}
                                        >
                                            <span>
                                                {t("Free reward")} · {" "}
                                                {line?.name || reward.item_id} × {" "}
                                                {reward.quantity}
                                            </span>
                                            <strong>{money(0)}</strong>
                                        </div>
                                    );
                                })}
                                {pricing.discount_total > 0 && (
                                    <div className="shop-savings">
                                        <span>{t("Promotion Discount")}</span>
                                        <strong>
                                            −{money(pricing.discount_total)}
                                        </strong>
                                    </div>
                                )}
                                <div className="shop-total">
                                    <span>{t("Total")}</span>
                                    <strong>
                                        {money(pricing.final_total)}
                                    </strong>
                                </div>
                            </section>
                        ) : (
                            <p className="shop-muted" aria-live="polite">
                                {t("Checking promotions…")}
                            </p>
                        )}
                        <button
                            className="shop-button"
                            disabled={!pricing}
                            onClick={() =>
                                user
                                    ? setCheckout(true)
                                    : void router.push("/account?next=/cart")
                            }
                        >
                            <ShoppingBag size={19} />
                            {t(user ? "Checkout" : "Sign in")}
                            {pricing && (
                                <span>{money(pricing.final_total)}</span>
                            )}
                        </button>
                    </>
                )}
            </div>
            {checkout && pricing && (
                <CheckoutSheet
                    pricing={pricing}
                    close={() => setCheckout(false)}
                    submit={async (fields) => {
                        await flushCart();
                        const response = await shopApi<ShopOrder>("orders", {
                            method: "POST",
                            body: JSON.stringify({
                                ...fields,
                                items: JSON.parse(itemsKey),
                                currency_mode: "base",
                                expected_subtotal: pricing.subtotal,
                                expected_discount_total: pricing.discount_total,
                                expected_total: pricing.final_total,
                                expected_promotion_id:
                                    pricing.applied_promotion?.id || null,
                            }),
                        });
                        updateCart([]);
                        setCheckout(false);
                        setOrder(response.data);
                    }}
                    reprice={() => {
                        setQuote({ key: "", data: null, error: "" });
                        setRevision((value) => value + 1);
                        setCheckout(false);
                    }}
                />
            )}
            {order && (
                <Sheet title="Order accepted" close={() => setOrder(null)}>
                    <div className="shop-order-success">
                        <CheckCircle2 size={74} strokeWidth={1.3} />
                        <h2>{t("Order accepted")}</h2>
                        <p>{order.order_number}</p>
                        <strong>{money(order.total)}</strong>
                        <Link
                            className="shop-button"
                            href={`/account/orders/${order.id}`}
                            onClick={() => setOrder(null)}
                        >
                            {t("View order")}
                        </Link>
                        <button
                            className="shop-text-button"
                            onClick={() => {
                                setOrder(null);
                                void router.push("/shop");
                            }}
                        >
                            {t("Continue shopping")}
                        </button>
                    </div>
                </Sheet>
            )}
        </>
    );
}

function CheckoutSheet({
    pricing,
    close,
    submit,
    reprice,
}: {
    pricing: ShopPricing;
    close: () => void;
    submit: (fields: Record<string, unknown>) => Promise<void>;
    reprice: () => void;
}) {
    const { t, money, bootstrap } = useShop();
    const addresses = useShopResource<ShopAddress[]>("addresses");
    const [addressId, setAddressId] = useState(0);
    const [delivery, setDelivery] = useState("Home Delivery");
    const [method, setMethod] = useState("Cash");
    const [note, setNote] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const locked = useRef(false);
    const selectedAddress =
        addressId ||
        addresses.data?.find((value) => value.is_default)?.id ||
        addresses.data?.[0]?.id ||
        0;
    const payments = [
        { name: "Cash", icon: Banknote },
        { name: "Card", icon: CreditCard },
        { name: "QR / UPI", icon: QrCode },
        { name: "Bank", icon: Landmark },
    ];
    return (
        <Sheet title="Checkout" close={() => !locked.current && close()}>
            <form
                className="shop-form shop-sheet-content"
                onSubmit={async (event) => {
                    event.preventDefault();
                    if (locked.current) return;
                    locked.current = true;
                    setBusy(true);
                    setError("");
                    try {
                        await submit({
                            address_id:
                                delivery === "Home Delivery"
                                    ? selectedAddress
                                    : null,
                            delivery_method: delivery,
                            payment_method: method,
                            note,
                        });
                    } catch (error) {
                        const message =
                            error instanceof Error ? error.message : t("Retry");
                        setError(message);
                    } finally {
                        locked.current = false;
                        setBusy(false);
                    }
                }}
            >
                <label>
                    {t("Delivery")}
                    <select
                        value={delivery}
                        disabled={busy}
                        onChange={(event) => setDelivery(event.target.value)}
                    >
                        <option value="Home Delivery">
                            {t("Home Delivery")}
                        </option>
                        <option value="Pickup">{t("Pickup")}</option>
                    </select>
                </label>
                {delivery === "Home Delivery" && (
                    <>
                        <label>
                            {t("Select address")}
                            <select
                                value={selectedAddress}
                                disabled={busy || addresses.loading}
                                required
                                onChange={(event) =>
                                    setAddressId(Number(event.target.value))
                                }
                            >
                                <option value="">{t("Select address")}</option>
                                {addresses.data?.map((address) => (
                                    <option key={address.id} value={address.id}>
                                        {address.label} · {address.address_line}
                                        , {address.city}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {addresses.error && (
                            <ErrorState
                                error={addresses.error}
                                retry={addresses.reload}
                            />
                        )}
                        {addresses.data?.length === 0 && (
                            <Link
                                className="shop-text-button"
                                href="/account/addresses"
                                onClick={close}
                            >
                                {t("Add address")}
                            </Link>
                        )}
                    </>
                )}
                <h3>{t("Payment Method")}</h3>
                <div className="shop-payment-grid">
                    {payments.map((value) => (
                        <button
                            key={value.name}
                            type="button"
                            className={method === value.name ? "selected" : ""}
                            aria-pressed={method === value.name}
                            disabled={busy}
                            onClick={() => setMethod(value.name)}
                        >
                            <value.icon size={19} />
                            {t(value.name)}
                        </button>
                    ))}
                </div>
                <label>
                    {t("Notes")}
                    <textarea
                        value={note}
                        maxLength={1000}
                        disabled={busy}
                        onChange={(event) => setNote(event.target.value)}
                    />
                </label>
                <div className="shop-totals">
                    <div>
                        <span>{t("Subtotal")}</span>
                        <strong>{money(pricing.subtotal)}</strong>
                    </div>
                    {pricing.discount_total > 0 && (
                        <div className="shop-savings">
                            <span>
                                {pricing.applied_promotion?.name ||
                                    t("Savings")}
                            </span>
                            <strong>−{money(pricing.discount_total)}</strong>
                        </div>
                    )}
                    <div className="shop-total">
                        <span>{t("Total Cost")}</span>
                        <strong>{money(pricing.final_total)}</strong>
                    </div>
                    {bootstrap?.currencies
                        .filter(
                            (currency) =>
                                currency.exchange_rate &&
                                currency.code !== bootstrap.currency?.code,
                        )
                        .map((currency) => (
                            <small key={currency.code} className="shop-muted">
                                ≈{" "}
                                {money(
                                    pricing.final_total *
                                        currency.exchange_rate!,
                                    currency,
                                )}
                            </small>
                        ))}
                </div>
                <p className="shop-terms">
                    {t(
                        "By placing an order you agree to our Terms And Conditions",
                    )}{" "}
                    <Link href="/account/terms">{t("Terms & Conditions")}</Link>
                </p>
                {error && (
                    <>
                        <ErrorState error={error} />
                        <button
                            type="button"
                            className="shop-text-button"
                            disabled={busy}
                            onClick={reprice}
                        >
                            {t("Review cart")}
                        </button>
                        <Link
                            className="shop-text-button"
                            href="/account/orders"
                        >
                            {t("Order history")}
                        </Link>
                    </>
                )}
                <button
                    className="shop-button"
                    disabled={
                        busy ||
                        (delivery === "Home Delivery" &&
                            (!selectedAddress ||
                                addresses.loading ||
                                !!addresses.error))
                    }
                >
                    {t(busy ? "Saving Order..." : "Place order")}
                    <span>{money(pricing.final_total)}</span>
                </button>
            </form>
        </Sheet>
    );
}
