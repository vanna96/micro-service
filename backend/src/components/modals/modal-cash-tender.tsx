import React from "react";

interface ModalCashTenderProps {
  totalPayable: number;
  cashReceived: string;
  onCashChange: (val: string) => void;
  cashChange: number;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalCashTender({
  totalPayable,
  cashReceived,
  onCashChange,
  cashChange,
  onConfirm,
  onClose,
}: ModalCashTenderProps) {
  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <div className="d-flex align-items-center gap-2">
              <div className="avatar size-8 bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center">
                <i className="ri-money-dollar-circle-fill fs-16"></i>
              </div>
              <h6 className="modal-title fw-bold mb-0">Cash Tender</h6>
            </div>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="bg-light p-3 rounded-3 mb-3 d-flex justify-content-between align-items-center border">
              <span className="text-muted fs-13">Total Payable</span>
              <span className="fs-18 fw-bold text-primary font-monospace">
                ${totalPayable.toFixed(2)}
              </span>
            </div>

            <div className="mb-3">
              <label className="form-label fs-12 fw-semibold text-muted">
                Cash Received from Customer
              </label>
              <div className="input-group input-group-lg">
                <span className="input-group-text bg-light fw-bold text-muted">$</span>
                <input
                  type="number"
                  className="form-control fw-bold font-monospace fs-20 text-primary"
                  placeholder="0.00"
                  value={cashReceived}
                  onChange={(e) => onCashChange(e.target.value)}
                  autoFocus
                />
              </div>
            </div>

            {/* Fast Denominations */}
            <div className="d-flex flex-wrap gap-2 mb-3">
              {[20, 50, 100, 200].map((val) => (
                <button
                  key={val}
                  type="button"
                  className="btn btn-outline-secondary flex-fill py-1.5 fs-13 fw-semibold rounded-2"
                  onClick={() => onCashChange(val.toString())}
                >
                  ${val}
                </button>
              ))}
              <button
                type="button"
                className="btn btn-outline-primary flex-fill py-1.5 fs-13 fw-semibold rounded-2"
                onClick={() => onCashChange(totalPayable.toFixed(2))}
              >
                Exact (${totalPayable.toFixed(2)})
              </button>
            </div>

            {/* Change Alert */}
            <div className="alert alert-success d-flex justify-content-between align-items-center mb-4 py-2.5 rounded-3 border-0">
              <span className="fs-13 fw-medium">Change to Return</span>
              <span className="fw-bold fs-18 font-monospace">
                ${cashChange.toFixed(2)}
              </span>
            </div>

            <div className="d-flex gap-2">
              <button type="button" className="btn btn-light w-50 rounded-2" onClick={onClose}>
                Cancel
              </button>
              <button
                type="button"
                className="btn btn-primary w-50 rounded-2 fw-semibold"
                onClick={onConfirm}
              >
                Confirm & Print
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
