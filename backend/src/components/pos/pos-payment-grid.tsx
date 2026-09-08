import React from "react";

interface PosPaymentGridProps {
  selectedMethod: string;
  onSelectMethod: (method: string) => void;
  onOpenModal: (modal: string) => void;
  totalPayable: number;
}

export function PosPaymentGrid({
  selectedMethod,
  onSelectMethod,
  onOpenModal,
  totalPayable,
}: PosPaymentGridProps) {
  const paymentModal = {
    Cash: "cash",
    Card: "card",
    UPI: "upi",
    Bank: "bank",
    Split: "split",
  }[selectedMethod];

  return (
    <div className="px-3 pb-2">
      <div className="pos-pay-grid">
        <button
          type="button"
          className={`pay-pill-btn ${selectedMethod === "Cash" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Cash");
            onOpenModal("cash");
          }}
        >
          <i className="ri-money-dollar-circle-fill text-success fs-14"></i>
          <span>Cash</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${selectedMethod === "Card" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Card");
            onOpenModal("card");
          }}
        >
          <i className="ri-bank-card-fill text-primary fs-14"></i>
          <span>Card</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${selectedMethod === "UPI" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("UPI");
            onOpenModal("upi");
          }}
        >
          <i className="ri-qr-code-line text-info fs-14"></i>
          <span>QR / UPI</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${selectedMethod === "Bank" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Bank");
            onOpenModal("bank");
          }}
        >
          <i className="ri-bank-line text-warning fs-14"></i>
          <span>Bank</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${selectedMethod === "Split" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Split");
            onOpenModal("split");
          }}
        >
          <i className="ri-split-cells-vertical text-secondary fs-14"></i>
          <span>Split Tender</span>
        </button>
      </div>

      <button
        type="button"
        className="btn btn-charge w-100 d-flex align-items-center justify-content-between"
        onClick={() => paymentModal && onOpenModal(paymentModal)}
        disabled={!paymentModal}
      >
        <span>Pay & Print [F12]</span>
        <span className="badge bg-white text-primary fs-13 font-monospace px-2 py-1">
          ${totalPayable.toFixed(2)}
        </span>
      </button>
    </div>
  );
}
