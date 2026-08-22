import React from "react";
import { Product, ProductUOM, VariantValue } from "@/types/pos-types";

interface ModalConfigProductProps {
  product: Product;
  selectedUOM: ProductUOM | null;
  onSelectUOM: (uom: ProductUOM) => void;
  selectedVariants: Record<string, VariantValue>;
  onSelectVariant: (optionName: string, val: VariantValue) => void;
  quantity: number;
  onUpdateQuantity: (delta: number) => void;
  modalUnitPrice: number;
  onAddToCart: () => void;
  onClose: () => void;
}

export function ModalConfigProduct({
  product,
  selectedUOM,
  onSelectUOM,
  selectedVariants,
  onSelectVariant,
  quantity,
  onUpdateQuantity,
  modalUnitPrice,
  onAddToCart,
  onClose,
}: ModalConfigProductProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered" style={{ maxWidth: "490px" }}>
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <div className="d-flex align-items-center gap-2">
              <div className="avatar size-8 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center">
                <i
                  className={
                    product.hasUOM
                      ? "ri-scales-3-line fs-16"
                      : "ri-list-settings-line fs-16"
                  }
                ></i>
              </div>
              <div>
                <h6 className="modal-title fw-bold mb-0">{product.name}</h6>
                <small className="text-muted font-monospace">{product.sku}</small>
              </div>
            </div>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>

          <div className="modal-body p-4">
            {/* Product Preview Card */}
            <div className="d-flex align-items-center gap-3 p-2.5 bg-light rounded-3 mb-3 border">
              <img
                src={product.image}
                alt={product.name}
                style={{ width: "54px", height: "54px", objectFit: "contain" }}
                className="rounded bg-white p-1"
              />
              <div>
                <span className="text-muted fs-11 d-block">
                  {selectedUOM
                    ? `Unit: ${selectedUOM.name}`
                    : `Base Price: $${product.price.toFixed(2)}`}
                </span>
                <h5 className="fw-bolder text-primary mb-0 font-monospace">
                  ${modalUnitPrice.toFixed(2)}
                </h5>
              </div>
            </div>

            {/* 1. Unit of Measure (UOM) Selector */}
            {product.hasUOM && product.uomList && (
              <div className="mb-3 p-3 bg-light bg-opacity-50 border rounded-3">
                <label className="form-label fs-12 fw-bold text-body mb-2 d-flex align-items-center gap-1.5">
                  <i className="ri-scales-3-line text-primary"></i>
                  Select Unit of Measure (UOM):
                </label>
                <div className="d-flex flex-column gap-2">
                  {product.uomList.map((uom) => {
                    const isSelected = selectedUOM?.shortCode === uom.shortCode;
                    return (
                      <button
                        key={uom.shortCode}
                        type="button"
                        className={`btn d-flex align-items-center justify-content-between p-2.5 rounded-2 border text-start ${
                          isSelected
                            ? "btn-primary text-white border-primary shadow-xs"
                            : "btn-light bg-white text-body border-slate-200"
                        }`}
                        onClick={() => onSelectUOM(uom)}
                      >
                        <div className="d-flex align-items-center gap-2">
                          <span
                            className={`badge ${
                              isSelected
                                ? "bg-white text-primary"
                                : "bg-light border text-muted"
                            } fs-11 px-2 py-0.5`}
                          >
                            {uom.shortCode}
                          </span>
                          <span className="fs-13 fw-semibold">{uom.name}</span>
                        </div>
                        <span
                          className={`fs-14 fw-bold font-monospace ${
                            isSelected ? "text-white" : "text-primary"
                          }`}
                        >
                          ${uom.price.toFixed(2)}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            {/* 2. Variant Options Selectors (RAM, Storage, Color, Size) */}
            {product.variantOptions?.map((optionGroup) => (
              <div key={optionGroup.name} className="mb-3">
                <label className="form-label fs-12 fw-bold text-body mb-1.5">
                  Select {optionGroup.name}:
                </label>
                <div className="d-flex flex-wrap gap-1.5">
                  {optionGroup.values.map((val) => {
                    const isSelected =
                      selectedVariants[optionGroup.name]?.label === val.label;
                    return (
                      <button
                        key={val.label}
                        type="button"
                        className={`btn btn-sm rounded-2 px-3 py-1.5 fs-12 fw-medium ${
                          isSelected
                            ? "btn-primary text-white shadow-xs"
                            : "btn-outline-secondary bg-white text-body"
                        }`}
                        onClick={() => onSelectVariant(optionGroup.name, val)}
                      >
                        {val.label}
                        {val.priceDelta ? ` (+$${val.priceDelta})` : ""}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}

            {/* Quantity Stepper */}
            <div className="d-flex align-items-center justify-content-between pt-2 border-top mt-3">
              <span className="fs-12 fw-bold text-muted">Quantity:</span>
              <div className="qty-stepper">
                <button
                  type="button"
                  className="qty-btn"
                  onClick={() => onUpdateQuantity(-1)}
                >
                  −
                </button>
                <span className="px-3 fs-13 fw-bold">{quantity}</span>
                <button
                  type="button"
                  className="qty-btn"
                  onClick={() => onUpdateQuantity(1)}
                >
                  +
                </button>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="d-flex gap-2 mt-4">
              <button
                type="button"
                className="btn btn-light w-40 rounded-2"
                onClick={onClose}
              >
                Cancel
              </button>
              <button
                type="button"
                className="btn btn-primary w-60 rounded-2 fw-bold d-flex align-items-center justify-content-center gap-1.5"
                onClick={onAddToCart}
              >
                <span>Add to Cart</span>
                <span>•</span>
                <span className="font-monospace">
                  ${(modalUnitPrice * quantity).toFixed(2)}
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
