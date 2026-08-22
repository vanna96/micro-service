import React, { useState } from "react";

interface ModalCardTerminalProps {
  totalPayable: number;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalCardTerminal({
  totalPayable,
  onConfirm,
  onClose,
}: ModalCardTerminalProps) {
  const [cardTab, setCardTab] = useState<"visa" | "mastercard">("visa");

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">Card Payment Terminal</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="d-flex gap-2 mb-3">
              <button
                type="button"
                className={`btn btn-sm flex-fill py-2 border rounded-2 ${
                  cardTab === "visa" ? "btn-primary" : "btn-light"
                }`}
                onClick={() => setCardTab("visa")}
              >
                <img
                  src="/assets/visa-Xmp1xRly.png"
                  alt="Visa"
                  style={{ height: "16px" }}
                  className="me-2"
                />
                Visa
              </button>
              <button
                type="button"
                className={`btn btn-sm flex-fill py-2 border rounded-2 ${
                  cardTab === "mastercard" ? "btn-primary" : "btn-light"
                }`}
                onClick={() => setCardTab("mastercard")}
              >
                <img
                  src="/assets/mastercard-CH_EB252.png"
                  alt="Mastercard"
                  style={{ height: "16px" }}
                  className="me-2"
                />
                Mastercard
              </button>
            </div>

            <div className="row g-3">
              <div className="col-12">
                <label className="form-label fs-12 fw-medium text-muted">Card Number</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="•••• •••• •••• ••••"
                  defaultValue="4242 •••• •••• 1234"
                />
              </div>
              <div className="col-6">
                <label className="form-label fs-12 fw-medium text-muted">Expiry</label>
                <input type="text" className="form-control" defaultValue="12/28" />
              </div>
              <div className="col-6">
                <label className="form-label fs-12 fw-medium text-muted">CVV</label>
                <input type="password" className="form-control" defaultValue="123" />
              </div>
              <div className="col-12">
                <div className="bg-light p-2.5 rounded-2 d-flex justify-content-between">
                  <span className="text-muted fs-13">Charge Amount</span>
                  <span className="fw-bold fs-16 text-primary font-monospace">
                    ${totalPayable.toFixed(2)}
                  </span>
                </div>
              </div>
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
                Process Card
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
