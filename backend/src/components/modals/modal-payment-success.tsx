import React from "react";

interface ModalPaymentSuccessProps {
  totalPayable: number;
  selectedPayMethod: string;
  currentDate: string;
  onPrint: () => void;
  onNextSale: () => void;
  onClose: () => void;
}

export function ModalPaymentSuccess({
  totalPayable,
  selectedPayMethod,
  currentDate,
  onPrint,
  onNextSale,
  onClose,
}: ModalPaymentSuccessProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered" style={{ maxWidth: "380px" }}>
        <div className="modal-content shadow-lg border-0 rounded-4 text-center p-4">
          <button
            type="button"
            className="btn-close position-absolute top-0 end-0 p-3"
            onClick={onClose}
          ></button>
          <div
            className="size-16 rounded-circle bg-success-subtle text-success mx-auto d-flex align-items-center justify-content-center mb-3"
            style={{ width: "64px", height: "64px" }}
          >
            <i className="ri-checkbox-circle-fill fs-36"></i>
          </div>
          <h5 className="fw-bolder text-body mb-1">Transaction Successful!</h5>
          <p className="text-muted fs-12 mb-3">The payment was recorded successfully.</p>
          <div className="bg-light p-3 rounded-3 mb-4 border">
            <span className="text-muted fs-12 d-block">Amount Charged</span>
            <h3 className="fw-bolder text-primary my-1 font-monospace">
              ${totalPayable.toFixed(2)}
            </h3>
            <small className="text-muted fs-11">
              {[currentDate, selectedPayMethod].filter(Boolean).join(" | ")}
            </small>
          </div>
          <div className="d-flex gap-2">
            <button
              type="button"
              className="btn btn-outline-secondary flex-fill rounded-2"
              onClick={onPrint}
            >
              <i className="ri-printer-line me-1"></i> Print
            </button>
            <button
              type="button"
              className="btn btn-primary flex-fill rounded-2 fw-semibold"
              onClick={onNextSale}
            >
              Next Sale
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
