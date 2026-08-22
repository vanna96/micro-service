import React from "react";

interface PosBottomToolbarProps {
  heldCount: number;
  onOpenModal: (modal: string) => void;
}

export function PosBottomToolbar({
  heldCount,
  onOpenModal,
}: PosBottomToolbarProps) {
  return (
    <div className="pos-bottom-features mt-auto">
      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("payment_success")}
      >
        <div className="pos-tool-icon bg-purple-subtle text-purple">
          <i className="ri-money-dollar-box-line"></i>
        </div>
        <span>Payment</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("hold")}
      >
        <div className="pos-tool-icon bg-warning-subtle text-warning">
          <i className="ri-pause-circle-line"></i>
        </div>
        <span>Hold ({heldCount})</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("invoice")}
      >
        <div className="pos-tool-icon bg-pink-subtle text-pink">
          <i className="ri-file-text-line"></i>
        </div>
        <span>Invoice</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("pay_later")}
      >
        <div className="pos-tool-icon bg-secondary-subtle text-secondary">
          <i className="ri-time-line"></i>
        </div>
        <span>Pay Later</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("history")}
      >
        <div className="pos-tool-icon bg-danger-subtle text-danger">
          <i className="ri-history-line"></i>
        </div>
        <span>History</span>
      </button>
    </div>
  );
}
