import React from "react";
import { Product, CartItem } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosProductCardProps {
  product: Product;
  cart: CartItem[];
  onProductClick: (p: Product) => void;
  onDirectAdd: (p: Product) => void;
  onUpdateQty: (itemId: string, delta: number) => void;
}

export function PosProductCard({
  product,
  cart,
  onProductClick,
  onDirectAdd,
  onUpdateQty,
}: PosProductCardProps) {
  const tenantCurrency = useCurrency();
  const { t } = useTranslation();
  const itemsInCart = cart.filter((i) => i.product.id === product.id);
  const paidItemsInCart = itemsInCart.filter((item) => !item.promotionReward);
  const totalInCart = itemsInCart.reduce((s, i) => s + i.quantity, 0);
  const hasConfig = product.hasVariants || product.hasUOM;
  const promotions = product.promotions || [];
  const visiblePromotions = promotions.slice(0, 2);
  const itemPricePromotion = promotions.find(
    (promotion) =>
      promotion.type === "item_price" &&
      promotion.promotionalPrice !== null &&
      promotion.promotionalPrice < product.price
  );
  const displayPrice = itemPricePromotion?.promotionalPrice ?? product.price;
  const promotionTitle = promotions
    .map((promotion) => `${promotion.name}: ${promotion.summary}`)
    .join("\n");

  const isStockControlled = product.stockControl !== false;
  const availableStock = typeof product.stock === "number" ? Math.max(0, product.stock) : null;
  const isOutOfStock = isStockControlled && (
    product.hasVariants
      ? (product.variants ? product.variants.length > 0 && product.variants.every((v) => Number(v.stock ?? 0) <= 0) : false)
      : availableStock !== null && availableStock <= 0
  );
  const isAtMaxStock = isStockControlled && !hasConfig && availableStock !== null && totalInCart >= availableStock;

  return (
    <div
      className={`pos-card cursor-pointer${isOutOfStock && !hasConfig ? " opacity-75" : ""}`}
      onClick={() => {
        if (isOutOfStock && !hasConfig) return;
        onProductClick(product);
      }}
    >
      {/* Product Thumbnail */}
      <div className="pos-card-img-wrap">
        <img src={product.image} alt={product.name} className="pos-card-img" />

        {/* Top-left Badges Container */}
        <div className="position-absolute top-0 start-0 m-2 d-flex flex-column gap-1 align-items-start" style={{ zIndex: 2 }}>
          {/* Stock Badge */}
          {isStockControlled && (product.stockStatus === "outofstock" || isOutOfStock) && (
            <span className="badge bg-danger text-white fs-10 px-2 py-0.5 rounded shadow-xs">
              {t("outOfStock")}
            </span>
          )}
          {isStockControlled && !isOutOfStock && product.stockStatus === "lowstock" && (
            <span className="badge bg-warning text-dark fs-10 px-2 py-0.5 rounded shadow-xs">
              {t("lowStock")}
            </span>
          )}

          {/* Virtual Try-On Badge */}
          {product.isTryOnEnabled && (
            <span
              className="badge text-white border-0 fs-10 px-2 py-0.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1"
              style={{ backgroundColor: "#7c3aed" }}
              title={t("tryOn")}
            >
              <i className="ri-magic-line fs-10"></i>
              <span>{t("tryOn")}</span>
            </span>
          )}

          {/* Premium Badge */}
          {product.isPremium && (
            <span
              className="badge text-white border-0 fs-10 px-2 py-0.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1"
              style={{ backgroundColor: "#d97706" }}
              title={t("premium")}
            >
              <i className="ri-vip-crown-line fs-10"></i>
              <span>{t("premium")}</span>
            </span>
          )}

          {/* Featured Badge */}
          {product.isFeatured && (
            <span
              className="badge text-white border-0 fs-10 px-2 py-0.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1"
              style={{ backgroundColor: "#2563eb" }}
              title={t("featured")}
            >
              <i className="ri-star-fill fs-10"></i>
              <span>{t("featured")}</span>
            </span>
          )}

          {/* New Arrival Badge */}
          {product.isNewArrival && (
            <span
              className="badge text-white border-0 fs-10 px-2 py-0.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1"
              style={{ backgroundColor: "#059669" }}
              title={t("newArrival")}
            >
              <i className="ri-sparkling-fill fs-10"></i>
              <span>{t("newArrival")}</span>
            </span>
          )}

          {/* Default UOM Badge */}
          {product.hasUOM && (
            <span className="badge bg-info-subtle text-info border border-info-subtle fs-10 px-2 py-0.5 rounded-pill bg-white shadow-xs">
              UOM:{" "}
              {product.uomList?.find((u) => u.isDefault)?.shortCode ||
                product.uomList?.[0]?.shortCode}
            </span>
          )}

          {/* Variant Badge */}
          {product.hasVariants && !product.hasUOM && (
            <span className="badge bg-primary-subtle text-primary border border-primary-subtle fs-10 px-2 py-0.5 rounded-pill bg-white shadow-xs">
              {t("options")}
            </span>
          )}
        </div>

        {/* In Cart Indicator */}
        {totalInCart > 0 && (
          <span className="badge bg-success text-white position-absolute top-0 end-0 m-2 fs-11 px-2 py-0.5 rounded-pill shadow-xs" style={{ zIndex: 2 }}>
            {totalInCart} {t("inCart")}
          </span>
        )}

        {visiblePromotions.length > 0 && (
          <div
            className="pos-promotion-badges"
            title={promotionTitle}
            aria-label={promotionTitle}
          >
            {visiblePromotions.map((promotion) => (
              <span
                key={`${promotion.id}-${promotion.role || "order"}`}
                className={`pos-promotion-badge pos-promotion-badge-${promotion.type}`}
              >
                <i
                  className={promotion.type === "bogo" ? "ri-gift-line" : "ri-price-tag-3-line"}
                  aria-hidden="true"
                ></i>
                {promotion.label}
              </span>
            ))}
            {promotions.length > visiblePromotions.length && (
              <span className="pos-promotion-badge pos-promotion-badge-more">
                +{promotions.length - visiblePromotions.length}
              </span>
            )}
          </div>
        )}
      </div>

      {/* Info & Price */}
      <div className="d-flex flex-column flex-grow-1 justify-content-between">
        <div>
          <div className="mb-0.5">
            <span className="text-muted fs-11 font-monospace d-block">
              {product.sku}
            </span>
          </div>
          <h6 className="pos-card-title" title={product.name}>
            {product.name}
          </h6>
        </div>

        <div className="d-flex align-items-center justify-content-between pt-2 border-top">
          <span className="pos-card-price">
            {itemPricePromotion && (
              <del className="pos-card-original-price">
                {formatCurrency(product.price, product.currency || tenantCurrency)}
              </del>
            )}
            <span className="fs-15 fw-bold text-primary">
              {formatCurrency(displayPrice, product.currency || tenantCurrency)}
              {product.hasUOM && (
                <small className="fs-11 text-muted fw-normal">
                  /{product.defaultUOM?.split(" ")[0]}
                </small>
              )}
            </span>
          </span>

          {hasConfig ? (
            <button
              type="button"
              className="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 fs-11 fw-semibold d-flex align-items-center gap-1"
              onClick={(e) => {
                e.stopPropagation();
                onProductClick(product);
              }}
            >
              <i
                className={
                  product.hasUOM ? "ri-scales-3-line" : "ri-list-settings-line"
                }
              ></i>
              {product.hasUOM ? t("selectUOM") : t("options")}
            </button>
          ) : totalInCart > 0 ? (
            <div className="qty-stepper" onClick={(e) => e.stopPropagation()}>
              <button
                type="button"
                className="qty-btn"
                onClick={() => {
                  const item = paidItemsInCart[0];
                  if (item) onUpdateQty(item.id, -1);
                }}
                disabled={paidItemsInCart.length === 0}
                title={paidItemsInCart.length === 0 ? t("freeReward") : undefined}
              >
                −
              </button>
              <span className="px-2 fs-12 fw-bold text-body">{totalInCart}</span>
              <button
                type="button"
                className="qty-btn"
                disabled={isAtMaxStock}
                title={isAtMaxStock ? `${t("outOfStock")} (${availableStock})` : undefined}
                onClick={() => {
                  if (isAtMaxStock) return;
                  const item = paidItemsInCart[0];
                  if (item) {
                    onUpdateQty(item.id, 1);
                  } else {
                    onDirectAdd(product);
                  }
                }}
              >
                +
              </button>
            </div>
          ) : isOutOfStock && !hasConfig ? (
            <button
              type="button"
              className="btn btn-sm btn-light text-muted rounded-pill px-3 py-1 fs-12 fw-medium border"
              disabled
            >
              {t("outOfStock")}
            </button>
          ) : (
            <button
              type="button"
              className="btn btn-sm btn-primary rounded-pill px-3 py-1 fs-12 fw-medium d-flex align-items-center gap-1"
              onClick={(e) => {
                e.stopPropagation();
                onDirectAdd(product);
              }}
            >
              <i className="ri-add-line"></i> {t("add")}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
