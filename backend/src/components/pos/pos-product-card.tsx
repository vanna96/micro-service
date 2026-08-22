import React from "react";
import { Product, CartItem } from "@/types/pos-types";

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
  const itemsInCart = cart.filter((i) => i.product.id === product.id);
  const totalInCart = itemsInCart.reduce((s, i) => s + i.quantity, 0);
  const hasConfig = product.hasVariants || product.hasUOM;

  return (
    <div
      className="pos-card cursor-pointer"
      onClick={() => onProductClick(product)}
    >
      {/* Product Thumbnail */}
      <div className="pos-card-img-wrap">
        <img src={product.image} alt={product.name} className="pos-card-img" />

        {/* Top-left Badges Container */}
        <div className="position-absolute top-0 start-0 m-2 d-flex flex-column gap-1 align-items-start">
          {/* Stock Badge */}
          {product.stockStatus === "lowstock" && (
            <span className="badge bg-warning text-dark fs-10 px-2 py-0.5 rounded shadow-xs">
              Low Stock
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
              Options
            </span>
          )}
        </div>

        {/* In Cart Indicator */}
        {totalInCart > 0 && (
          <span className="badge bg-success text-white position-absolute top-0 end-0 m-2 fs-11 px-2 py-0.5 rounded-pill shadow-xs">
            {totalInCart} in cart
          </span>
        )}
      </div>

      {/* Info & Price */}
      <div className="d-flex flex-column flex-grow-1 justify-content-between">
        <div>
          <span className="text-muted fs-11 font-monospace d-block">
            {product.sku}
          </span>
          <h6 className="pos-card-title" title={product.name}>
            {product.name}
          </h6>
        </div>

        <div className="d-flex align-items-center justify-content-between pt-2 border-top">
          <span className="fs-15 fw-bold text-primary">
            ${product.price.toFixed(2)}
            {product.hasUOM && (
              <small className="fs-11 text-muted fw-normal">
                /{product.defaultUOM?.split(" ")[0]}
              </small>
            )}
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
              {product.hasUOM ? "Select UOM" : "Options"}
            </button>
          ) : totalInCart > 0 ? (
            <div className="qty-stepper" onClick={(e) => e.stopPropagation()}>
              <button
                type="button"
                className="qty-btn"
                onClick={() => {
                  const item = itemsInCart[0];
                  if (item) onUpdateQty(item.id, -1);
                }}
              >
                −
              </button>
              <span className="px-2 fs-12 fw-bold text-body">{totalInCart}</span>
              <button
                type="button"
                className="qty-btn"
                onClick={() => {
                  const item = itemsInCart[0];
                  if (item) onUpdateQty(item.id, 1);
                }}
              >
                +
              </button>
            </div>
          ) : (
            <button
              type="button"
              className="btn btn-sm btn-primary rounded-pill px-3 py-1 fs-12 fw-medium d-flex align-items-center gap-1"
              onClick={(e) => {
                e.stopPropagation();
                onDirectAdd(product);
              }}
            >
              <i className="ri-add-line"></i> Add
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
