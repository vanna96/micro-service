import React from "react";
import { Product, ProductUOM, VariantValue } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalConfigProductProps {
  product: Product;
  selectedUOM: ProductUOM | null;
  onSelectUOM: (uom: ProductUOM) => void;
  selectedVariants: Record<string, VariantValue>;
  onSelectVariant: (optionName: string, val: VariantValue) => void;
  isVariantValueDisabled: (groupIndex: number, val: VariantValue) => boolean;
  hasValidVariant: boolean;
  quantity: number;
  onUpdateQuantity: (delta: number) => void;
  maxQuantity: number | null;
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
  isVariantValueDisabled,
  hasValidVariant,
  quantity,
  onUpdateQuantity,
  maxQuantity,
  modalUnitPrice,
  onAddToCart,
  onClose,
}: ModalConfigProductProps) {
  const tenantCurrency = useCurrency();
  const currency = product.currency || tenantCurrency;
  const { t } = useTranslation();

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
              <div className="flex-grow-1">
                <div className="d-flex flex-wrap gap-1 mb-1">
                  {product.isTryOnEnabled && (
                    <span className="badge text-white fs-10 px-2 py-0.5 rounded-pill shadow-xs" style={{ backgroundColor: "#7c3aed" }}>
                      <i className="ri-magic-line me-1"></i>{t("tryOn")}
                    </span>
                  )}
                  {product.isPremium && (
                    <span className="badge text-white fs-10 px-2 py-0.5 rounded-pill shadow-xs" style={{ backgroundColor: "#d97706" }}>
                      <i className="ri-vip-crown-line me-1"></i>{t("premium")}
                    </span>
                  )}
                  {product.isFeatured && (
                    <span className="badge text-white fs-10 px-2 py-0.5 rounded-pill shadow-xs" style={{ backgroundColor: "#2563eb" }}>
                      <i className="ri-star-fill me-1"></i>{t("featured")}
                    </span>
                  )}
                  {product.isNewArrival && (
                    <span className="badge text-white fs-10 px-2 py-0.5 rounded-pill shadow-xs" style={{ backgroundColor: "#059669" }}>
                      <i className="ri-sparkling-fill me-1"></i>{t("newArrival")}
                    </span>
                  )}
                </div>
                <span className="text-muted fs-11 d-block">
                  {selectedUOM
                    ? `${t("unit")} ${selectedUOM.name}`
                    : `${t("basePrice")} ${formatCurrency(product.price, currency)}`}
                </span>
                <h5 className="fw-bolder text-primary mb-0 font-monospace">
                  {formatCurrency(modalUnitPrice, currency)}
                </h5>
              </div>
            </div>

            {/* Virtual Try-On Supported Banner */}
            {product.isTryOnEnabled && (
              <div className="p-2.5 rounded-3 mb-3 d-flex align-items-center justify-content-between border" style={{ backgroundColor: "#f5f3ff", borderColor: "#ddd6fe" }}>
                <div className="d-flex align-items-center gap-2">
                  <div className="rounded-circle d-flex align-items-center justify-content-center text-white" style={{ width: "28px", height: "28px", backgroundColor: "#7c3aed" }}>
                    <i className="ri-magic-line fs-14"></i>
                  </div>
                  <div>
                    <div className="fw-bold fs-12" style={{ color: "#5b21b6" }}>{t("virtualTryOnSupported")}</div>
                    <div className="text-muted fs-11">{t("tryOnDescription")}</div>
                  </div>
                </div>
                <span className="badge text-white px-2 py-1 rounded-pill fs-10" style={{ backgroundColor: "#7c3aed" }}>
                  {t("active")}
                </span>
              </div>
            )}

            {/* 1. Unit of Measure (UOM) Selector */}
            {product.hasUOM && product.uomList && (
              <div className="mb-3 p-3 bg-light bg-opacity-50 border rounded-3">
                <label className="form-label fs-12 fw-bold text-body mb-2 d-flex align-items-center gap-1.5">
                  <i className="ri-scales-3-line text-primary"></i>
                  {t("selectUomLabel")}
                </label>
                <div className="d-flex flex-column gap-2">
                  {product.uomList.map((uom) => {
                    const isSelected = selectedUOM?.shortCode === uom.shortCode;
                    const isStockControlled = product.stockControl !== false;
                    const factor = Number(uom.conversionFactorToBase ?? 1) || 1;
                    const uomStock = isStockControlled && typeof product.stock === "number"
                      ? Math.max(0, Math.floor(product.stock / factor))
                      : null;
                    const isUomOutOfStock = isStockControlled && uomStock !== null && uomStock <= 0;

                    return (
                      <button
                        key={uom.shortCode}
                        type="button"
                        disabled={isUomOutOfStock}
                        title={isUomOutOfStock ? t("outOfStockForUnit") : undefined}
                        className={`btn d-flex align-items-center justify-content-between p-2.5 rounded-2 border text-start ${
                          isSelected
                            ? "btn-primary text-white border-primary shadow-xs"
                            : isUomOutOfStock
                              ? "btn-light bg-light text-muted border-slate-200 opacity-60 cursor-not-allowed"
                              : "btn-light bg-white text-body border-slate-200"
                        }`}
                        onClick={() => {
                          if (isUomOutOfStock) return;
                          onSelectUOM(uom);
                        }}
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
                          {isStockControlled && uomStock !== null && (
                            <span
                              className={`badge fs-10 px-1.5 py-0.5 rounded ${
                                isUomOutOfStock
                                  ? isSelected ? "bg-danger text-white" : "bg-danger-subtle text-danger"
                                  : isSelected ? "bg-white text-primary" : "bg-light text-muted border"
                              }`}
                            >
                              {isUomOutOfStock ? t("outOfStock") : `${uomStock} ${t("available")}`}
                            </span>
                          )}
                        </div>
                        <span
                          className={`fs-14 fw-bold font-monospace ${
                            isSelected ? "text-white" : "text-primary"
                          }`}
                        >
                          {formatCurrency(uom.price, currency)}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            {/* 2. Variant Options Selectors (RAM, Storage, Color, Size) */}
            {product.variantOptions?.map((optionGroup, groupIndex) => (
              <div key={optionGroup.name} className="mb-3">
                <label className="form-label fs-12 fw-bold text-body mb-1.5">
                  {t("selectOption")} {optionGroup.name}:
                </label>
                <div className="d-flex flex-wrap gap-1.5">
                  {optionGroup.values.map((val) => {
                    const isDisabled = isVariantValueDisabled(groupIndex, val);
                    const isSelected =
                      selectedVariants[optionGroup.name]?.label === val.label;
                    return (
                      <button
                        key={val.label}
                        type="button"
                        className={`btn btn-sm rounded-2 px-3 py-1.5 fs-12 fw-medium ${isSelected
                            ? "btn-primary text-white shadow-xs"
                            : isDisabled
                              ? "btn-light text-muted border"
                              : "btn-outline-secondary bg-white text-body"
                          }`}
                        disabled={isDisabled}
                        aria-disabled={isDisabled}
                        title={
                          isDisabled ? t("combinationInactive") : undefined
                        }
                        onClick={() => onSelectVariant(optionGroup.name, val)}
                      >
                        {val.label}
                        {val.priceDelta ? ` (+${formatCurrency(val.priceDelta, currency)})` : ""}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}

            {/* Quantity Stepper */}
            <div className="d-flex align-items-center justify-content-between pt-2 border-top mt-3">
              <div>
                <span className="fs-12 fw-bold text-muted d-block">{t("quantity")}</span>
                {product.stockControl !== false && maxQuantity !== null && (
                  <small className={maxQuantity > 0 ? "text-muted" : "text-danger"}>
                    {maxQuantity > 0 ? `${maxQuantity} ${t("available")}` : t("outOfStock")}
                  </small>
                )}
              </div>
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
                  disabled={maxQuantity !== null && quantity >= maxQuantity}
                  title={maxQuantity !== null && quantity >= maxQuantity ? `Maximum available stock (${maxQuantity}) reached` : undefined}
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
                {t("cancel")}
              </button>
              <button
                type="button"
                className="btn btn-primary w-60 rounded-2 fw-bold d-flex align-items-center justify-content-center gap-1.5"
                disabled={
                  (product.hasVariants && !hasValidVariant) ||
                  (product.hasUOM && selectedUOM && product.stockControl !== false && typeof product.stock === "number" && (
                    Math.floor(product.stock / (Number(selectedUOM.conversionFactorToBase ?? 1) || 1)) <= 0 ||
                    quantity > Math.floor(product.stock / (Number(selectedUOM.conversionFactorToBase ?? 1) || 1))
                  )) ||
                  (product.stockControl !== false && maxQuantity !== null && (maxQuantity <= 0 || quantity > maxQuantity))
                }
                onClick={onAddToCart}
              >
                <span>{t("addToCart")}</span>
                <span>•</span>
                <span className="font-monospace">
                  {formatCurrency(modalUnitPrice * quantity, currency)}
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
