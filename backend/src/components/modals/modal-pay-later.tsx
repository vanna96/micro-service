import React from "react";

interface ModalPayLaterProps {
  customerName: string;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalPayLater({
  customerName,
  onConfirm,
  onClose,
}: ModalPayLaterProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">Record Credit / Pay Later</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">Customer Account</label>
              <input type="text" className="form-control" defaultValue={customerName} />
            </div>
            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">Due Date</label>
              <input type="date" className="form-control" defaultValue="2026-08-25" />
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
                Save Credit Sale
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
