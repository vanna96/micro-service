import React from "react";

interface PosSummaryCardProps {
  subTotal: number;
  discountPercent: number;
  onDiscountChange: (val: number) => void;
  discountAmount: number;
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
  discountPercent,
  onDiscountChange,
  discountAmount,
  taxPercent,
  onTaxChange,
  gstAmount,
  serviceFee,
  onServiceFeeChange,
  totalPayable,
  totalItemCount,
}: PosSummaryCardProps) {
  return (
    <div className="pos-summary-card">
      {/* Subtotal */}
      <div className="d-flex justify-content-between text-muted fs-12 mb-2 pb-1 border-bottom">
        <span className="fw-medium">Subtotal</span>
        <span className="text-body fw-bold font-monospace">
          ${subTotal.toFixed(2)}
        </span>
      </div>

      <div className="d-flex flex-column gap-2 fs-12">
        {/* Dynamic Discount Input */}
        <div className="d-flex align-items-center justify-content-between">
          <span className="text-muted fw-medium">Discount</span>
          <div className="d-flex align-items-center gap-1">
            <div className="input-group input-group-sm" style={{ width: "75px" }}>
              <input
                type="number"
                min="0"
                max="100"
                className="form-control form-control-sm text-end font-monospace py-0 px-1 fs-11 fw-semibold"
                value={discountPercent}
                onChange={(e) =>
                  onDiscountChange(
                    Math.max(0, Math.min(100, parseFloat(e.target.value) || 0))
                  )
                }
              />
              <span className="input-group-text py-0 px-1 fs-11 bg-light">%</span>
            </div>
            <span
              className="text-danger fw-bold font-monospace ms-1"
              style={{ minWidth: "62px", textAlign: "right" }}
            >
              {discountPercent > 0 ? `− $${discountAmount.toFixed(2)}` : "$0.00"}
            </span>
          </div>
        </div>

        {/* Dynamic Tax / GST Input */}
        <div className="d-flex align-items-center justify-content-between">
          <span className="text-muted fw-medium">GST Tax</span>
          <div className="d-flex align-items-center gap-1">
            <div className="input-group input-group-sm" style={{ width: "75px" }}>
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
              <span className="input-group-text py-0 px-1 fs-11 bg-light">%</span>
            </div>
            <span
              className="text-body fw-bold font-monospace ms-1"
              style={{ minWidth: "62px", textAlign: "right" }}
            >
              ${gstAmount.toFixed(2)}
            </span>
          </div>
        </div>

        {/* Dynamic Service Fee Input */}
        <div className="d-flex align-items-center justify-content-between">
          <span className="text-muted fw-medium">Service Fee</span>
          <div className="d-flex align-items-center gap-1">
            <div className="input-group input-group-sm" style={{ width: "75px" }}>
              <span className="input-group-text py-0 px-1 fs-11 bg-light">$</span>
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
            </div>
            <span
              className="text-body fw-bold font-monospace ms-1"
              style={{ minWidth: "62px", textAlign: "right" }}
            >
              ${serviceFee.toFixed(2)}
            </span>
          </div>
        </div>

        {/* Grand Total */}
        <div className="d-flex justify-content-between align-items-center pt-2 mt-1 border-top">
          <div>
            <span className="fs-13 fw-bold text-body d-block">Grand Total</span>
            <small className="text-muted fs-10">
              {totalItemCount} Items included
            </small>
          </div>
          <span className="fs-18 fw-bolder text-primary font-monospace">
            ${totalPayable.toFixed(2)}
          </span>
        </div>
      </div>
    </div>
  );
}
