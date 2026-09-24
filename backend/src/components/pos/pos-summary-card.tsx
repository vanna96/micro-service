import React from "react";
import { AppliedPromotion, DiscountType } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosSummaryCardProps {
  subTotal: number;
  discountType: DiscountType;
  discountValue: number;
  onDiscountTypeChange: (type: DiscountType) => void;
  onDiscountValueChange: (value: number) => void;
  isDiscountLocked?: boolean;
  manualDiscountAmount: number;
  appliedPromotion: AppliedPromotion | null;
  promotionDiscountAmount: number;
  isPromotionPricing: boolean;
  isPromotionChecked: boolean;
  promotionPricingError: string | null;
  taxPercent: number;
  onTaxChange: (val: number) => void;
  gstAmount: number;
  serviceFee: number;
  onServiceFeeChange: (val: number) => void;
  totalPayable: number;
  totalItemCount: number;
}

export function PosSummaryCard({
  subTotal,
  discountType,
  discountValue,
  onDiscountTypeChange,
  onDiscountValueChange,
  isDiscountLocked = false,
  manualDiscountAmount,
  appliedPromotion,
  promotionDiscountAmount,
  isPromotionPricing,
  isPromotionChecked,
  promotionPricingError,
  taxPercent,
  onTaxChange,
  gstAmount,
  serviceFee,
  onServiceFeeChange,
  totalPayable,
  totalItemCount,
}: PosSummaryCardProps) {
  const currency = useCurrency();
  const symbol = currency?.symbol || "";
  const { t } = useTranslation();

  return (
    <div className="pos-summary-card">
      {/* Subtotal */}
      <div className="d-flex justify-content-between text-muted fs-12 mb-2 pb-1 border-bottom">
        <span className="fw-medium">{t("subtotal")}</span>
        <span className="text-body fw-bold font-monospace">
          {formatCurrency(subTotal, currency)}
        </span>
      </div>

      <div className="d-flex flex-column gap-2 fs-12">
        {/* Dynamic Discount Input */}
        <div className="d-flex align-items-center justify-content-between">
          <span className="text-muted fw-medium d-flex align-items-center gap-1">
            {isDiscountLocked ? t("customerDiscount") : t("manualDiscount")}
            {isDiscountLocked && (
              <i
                className="ri-lock-line fs-10"
                title={t("customerDiscount")}
                aria-label={t("customerDiscount")}
              ></i>
            )}
          </span>
          <div className="d-flex align-items-center gap-1">
            <div
              className="input-group input-group-sm"
              style={{ width: "118px" }}
              title={isDiscountLocked ? t("customerDiscount") : undefined}
            >
              <input
                type="number"
                min="0"
                max={discountType === "percentage" ? 100 : subTotal}
                step={discountType === "percentage" ? "0.01" : "0.01"}
                className="form-control form-control-sm text-end font-monospace py-0 px-1 fs-11 fw-semibold"
                aria-label={discountType === "percentage" ? t("manualDiscount") : t("manualDiscount")}
                value={discountValue}
                disabled={isDiscountLocked}
                onChange={(e) =>
                  onDiscountValueChange(
                    Math.max(
                      0,
                      Math.min(
                        discountType === "percentage" ? 100 : subTotal,
                        parseFloat(e.target.value) || 0
                      )
                    )
                  )
                }
              />
              <select
                className="form-select form-select-sm py-0 ps-2 pe-4 fs-11 bg-light fw-semibold"
                style={{ width: "48px", flex: "0 0 48px" }}
                aria-label={t("manualDiscount")}
                value={discountType}
                disabled={isDiscountLocked}
                onChange={(event) => onDiscountTypeChange(event.target.value as DiscountType)}
              >
                <option value="percentage">%</option>
                <option value="fixed">{symbol}</option>
              </select>
            </div>
            <span
              className="text-danger fw-bold font-monospace ms-1"
              style={{ minWidth: "62px", textAlign: "right" }}
            >
              {manualDiscountAmount > 0 ? `− ${formatCurrency(manualDiscountAmount, currency)}` : formatCurrency(0, currency)}
            </span>
          </div>
        </div>

        {isPromotionPricing && (
          <div className="d-flex align-items-center justify-content-between rounded bg-light px-2 py-2 text-muted">
            <span className="d-flex align-items-center gap-2">
              <span className="spinner-border spinner-border-sm" aria-hidden="true"></span>
              {t("checkingPromotions")}
            </span>
          </div>
        )}

        {!isPromotionPricing && appliedPromotion && (
          <div className="alert alert-success d-flex align-items-start justify-content-between gap-2 py-2 px-2 mb-0 fs-11">
            <span>
              <strong className="d-block">
                <i className="ri-coupon-3-line me-1" aria-hidden="true"></i>
                {appliedPromotion.name}
              </strong>
              <span className="d-block mt-1">{appliedPromotion.summary}</span>
            </span>
            <strong className="text-nowrap font-monospace">
              − {formatCurrency(promotionDiscountAmount, currency)}
            </strong>
          </div>
        )}

        {!isPromotionPricing && isPromotionChecked && !appliedPromotion && !promotionPricingError && (
          <div className="d-flex align-items-center gap-1 px-2 text-muted fs-11" role="status">
            <i className="ri-checkbox-circle-line" aria-hidden="true"></i>
            {t("noPromotionApplies")}
          </div>
        )}

        {!isPromotionPricing && promotionPricingError && (
          <div className="alert alert-warning py-2 px-2 mb-0 fs-11" role="status">
            <i className="ri-error-warning-line me-1" aria-hidden="true"></i>
            {t("promotionRefreshError")}
          </div>
        )}

        {/* Dynamic Tax / GST Input */}
        <div className="d-flex align-items-center justify-content-between">
          <span className="text-muted fw-medium">{t("gstTax")}</span>
          <div className="d-flex align-items-center gap-1">
            <div className="input-group input-group-sm" style={{ width: "118px" }}>
              <input
                type="number"
                min="0"
                max="100"
                className="form-control form-control-sm text-end font-monospace py-0 px-1 fs-11 fw-semibold"
                value={taxPercent}
                onChange={(e) =>
                  onTaxChange(
                    Math.max(0, Math.min(100, parseFloat(e.target.value) || 0))
                  )
                }
              />
              <span
                className="input-group-text justify-content-center py-0 px-1 fs-11 bg-light"
                style={{ width: "48px", flex: "0 0 48px" }}
              >
                %
              </span>
            </div>
            <span
              className="text-body fw-bold font-monospace ms-1"
              style={{ minWidth: "62px", textAlign: "right" }}
            >
              {formatCurrency(gstAmount, currency)}
            </span>
          </div>
        </div>

        {/* Dynamic Service Fee Input */}
        <div className="d-flex align-items-center justify-content-between">
          <span className="text-muted fw-medium">{t("serviceFee")}</span>
          <div className="d-flex align-items-center gap-1">
            <div className="input-group input-group-sm" style={{ width: "118px" }}>
              <input
                type="number"
                min="0"
                step="1"
                className="form-control form-control-sm text-end font-monospace py-0 px-1 fs-11 fw-semibold"
                value={serviceFee}
                onChange={(e) =>
                  onServiceFeeChange(Math.max(0, parseFloat(e.target.value) || 0))
                }
              />
              <span
                className="input-group-text justify-content-center py-0 px-1 fs-11 bg-light"
                style={{ width: "48px", flex: "0 0 48px" }}
              >
                {symbol}
              </span>
            </div>
            <span
              className="text-body fw-bold font-monospace ms-1"
              style={{ minWidth: "62px", textAlign: "right" }}
            >
              {formatCurrency(serviceFee, currency)}
            </span>
          </div>
        </div>

        {/* Grand Total */}
        <div className="d-flex justify-content-between align-items-center pt-2 mt-1 border-top">
          <div>
            <span className="fs-13 fw-bold text-body d-block">{t("grandTotal")}</span>
            <small className="text-muted fs-10">
              {totalItemCount} {t("itemsIncluded")}
            </small>
          </div>
          <span className="fs-18 fw-bolder text-primary font-monospace">
            {formatCurrency(totalPayable, currency)}
          </span>
        </div>
      </div>
    </div>
  );
}
