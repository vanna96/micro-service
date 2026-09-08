import React from "react";

interface ModalBankTransferProps {
  totalPayable: number;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalBankTransfer({
  totalPayable,
  onConfirm,
  onClose,
}: ModalBankTransferProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">Bank Transfer Confirmation</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="bg-light p-3 rounded-3 mb-3 d-flex justify-content-between">
              <span className="text-muted fs-13">Total Amount</span>
              <span className="fw-bold fs-16 text-primary font-monospace">
                ${totalPayable.toFixed(2)}
              </span>
            </div>
            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">Bank Name</label>
              <input type="text" className="form-control" placeholder="Enter bank name" />
            </div>
            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">UTR / Reference No.</label>
              <input type="text" className="form-control" placeholder="Enter reference number" />
            </div>
            <div className="d-flex gap-2 mt-4">
              <button type="button" className="btn btn-light w-50" onClick={onClose}>
                Cancel
              </button>
              <button
                type="button"
                className="btn btn-primary w-50 fw-semibold"
                onClick={onConfirm}
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
