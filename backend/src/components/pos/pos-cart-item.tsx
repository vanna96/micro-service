import React from "react";
import { CartItem } from "@/types/pos-types";

interface PosCartItemProps {
  item: CartItem;
  onUpdateQty: (itemId: string, delta: number) => void;
  onRemoveItem: (itemId: string) => void;
}

export function PosCartItem({
  item,
  onUpdateQty,
  onRemoveItem,
}: PosCartItemProps) {
  return (
    <div className="pos-cart-row">
      <img
        src={item.product.image}
        alt={item.product.name}
        style={{ width: "38px", height: "38px", objectFit: "contain" }}
        className="rounded bg-light p-1 flex-shrink-0"
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

        <small className="text-muted fs-11 font-monospace">
          ${item.unitPrice.toFixed(2)}/unit
        </small>
      </div>

      <div className="qty-stepper">
        <button
          type="button"
          className="qty-btn"
          onClick={() => onUpdateQty(item.id, -1)}
        >
          −
        </button>
        <span className="px-2 fs-11 fw-bold">{item.quantity}</span>
        <button
          type="button"
          className="qty-btn"
          onClick={() => onUpdateQty(item.id, 1)}
        >
          +
        </button>
      </div>

      <div className="text-end" style={{ minWidth: "55px" }}>
        <span className="fs-12 fw-bold text-body font-monospace">
          ${(item.unitPrice * item.quantity).toFixed(2)}
        </span>
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
