import React from "react";

interface ModalUpiQrProps {
  totalPayable: number;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalUpiQr({ totalPayable, onConfirm, onClose }: ModalUpiQrProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-sm">
        <div className="modal-content shadow-lg border-0 rounded-4 text-center p-4">
          <span className="text-muted fs-12">Scan QR to Pay</span>
          <h4 className="fw-bolder text-primary my-2 font-monospace">
            ${totalPayable.toFixed(2)}
          </h4>
          <div className="bg-light p-3 rounded-3 border d-inline-block mx-auto mb-3">
            <img
              src="/assets/qr-CvtWFzmv.png"
              alt="QR"
              style={{ width: "160px", height: "160px" }}
            />
          </div>
          <p className="text-muted fs-12 mb-4">
            Supported: Google Pay, Apple Pay, PhonePe, Paytm
          </p>
          <div className="d-flex gap-2">
            <button type="button" className="btn btn-light w-50" onClick={onClose}>
              Cancel
            </button>
            <button
              type="button"
              className="btn btn-primary w-50 fw-semibold"
              onClick={onConfirm}
            >
              Verify
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
