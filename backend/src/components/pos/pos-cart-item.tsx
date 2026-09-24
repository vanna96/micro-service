import React from "react";
import { CartItem } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { normalizeMediaUrl } from "@/lib/pos-api";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosCartItemProps {
  item: CartItem;
  onUpdateQty: (itemId: string, delta: number) => void;
  onAddPaidItem: (item: CartItem) => void;
  onRemoveItem: (itemId: string) => void;
}

export function PosCartItem({
  item,
  onUpdateQty,
  onAddPaidItem,
  onRemoveItem,
}: PosCartItemProps) {
  const tenantCurrency = useCurrency();
  const { t } = useTranslation();
  const currency = item.product.currency || tenantCurrency;
  const isStockControlled = item.product.stockControl !== false;
  const maxQuantity = isStockControlled
    ? item.selectedVariant
      ? Math.max(0, Number(item.selectedVariant.stock ?? 0))
      : typeof item.product.stock === "number"
        ? Math.max(0, Math.floor(item.product.stock / (Number(item.selectedUOM?.conversionFactorToBase ?? 1) || 1)))
        : null
    : null;
  const isAtMaxStock = maxQuantity !== null && item.quantity >= maxQuantity;

  return (
    <div className="pos-cart-row">
      <img
        src={normalizeMediaUrl(item.product?.image)}
        alt={item.product?.name || "Product"}
        style={{ width: "38px", height: "38px", objectFit: "contain" }}
        className="rounded bg-light p-1 flex-shrink-0"
        onError={(e) => {
          if (e.currentTarget.src !== "/assets/no-order-CCjZwO4J.svg") {
            e.currentTarget.src = "/assets/no-order-CCjZwO4J.svg";
          }
        }}
      />
      <div className="flex-grow-1 overflow-hidden">
        <h6
          className="fs-12 fw-semibold mb-0 text-truncate"
          title={item.product.name}
        >
          {item.product.name}
        </h6>

        {/* Display Selected UOM & Variants */}
        <div className="d-flex flex-wrap gap-1 mt-0.5">
          {item.selectedUOM && (
            <span className="badge bg-info-subtle text-info border border-info-subtle px-1.5 py-0 fs-10 fw-semibold">
              {item.selectedUOM.name}
            </span>
          )}

          {item.promotionReward && (
            <span className="badge bg-success-subtle text-success border border-success-subtle px-1.5 py-0 fs-10 fw-semibold">
              <i className="ri-gift-line me-1" aria-hidden="true"></i>
              {t("freeReward")}
            </span>
          )}

          {item.selectedVariants &&
            Object.entries(item.selectedVariants).map(([k, v]) => (
              <span
                key={k}
                className="badge bg-light text-secondary border px-1.5 py-0 fs-10 fw-medium"
              >
                {v.label}
              </span>
            ))}
        </div>

        {item.promotionReward ? (
          <small className="d-flex align-items-center gap-1 fs-11 font-monospace">
            <del className="text-danger">
              {formatCurrency(item.unitPrice, currency)}{t("perUnit")}
            </del>
            <strong className="text-success">{t("free")}</strong>
          </small>
        ) : (
          <small
            className="d-flex align-items-center gap-1 fs-11 font-monospace text-muted"
            title={t("chargeAmount")}
          >
            <span>{formatCurrency(item.unitPrice, currency)}{t("perUnit")}</span>
            <i className="ri-lock-line" aria-hidden="true"></i>
          </small>
        )}
      </div>

      <div className="qty-stepper">
        <button
          type="button"
          className="qty-btn"
          disabled={Boolean(item.promotionReward)}
          title={item.promotionReward ? t("freeReward") : undefined}
          onClick={() => onUpdateQty(item.id, -1)}
        >
          −
        </button>
        <span className="px-2 fs-11 fw-bold">{item.quantity}</span>
        <button
          type="button"
          className="qty-btn"
          disabled={isAtMaxStock}
          title={
            isAtMaxStock
              ? `${t("outOfStock")} (${maxQuantity})`
              : item.promotionReward
                ? t("add")
                : undefined
          }
          onClick={() => {
            if (isAtMaxStock) return;
            if (item.promotionReward) {
              onAddPaidItem(item);
              return;
            }

            onUpdateQty(item.id, 1);
          }}
        >
          +
        </button>
      </div>

      <div className="text-end" style={{ minWidth: "55px" }}>
        {item.promotionReward ? (
          <span className="d-flex flex-column align-items-end font-monospace">
            <del className="fs-11 fw-semibold text-danger">
              {formatCurrency(item.unitPrice * item.quantity, currency)}
            </del>
            <strong className="fs-12 text-success">
              {formatCurrency(0, currency)}
            </strong>
          </span>
        ) : (
          <span className="fs-12 fw-bold text-body font-monospace">
            {formatCurrency(item.unitPrice * item.quantity, currency)}
          </span>
        )}
      </div>

      <button
        type="button"
        className="btn btn-sm p-0 text-muted hover-danger border-0"
        onClick={() => onRemoveItem(item.id)}
      >
        <i className="ri-close-line fs-14"></i>
      </button>
    </div>
  );
}
