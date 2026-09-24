import React from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

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
  const currency = useCurrency();
  const { t } = useTranslation();
  const activeMethod = selectedMethod || "Cash";

  const paymentModal = {
    Cash: "cash",
    Card: "card",
    UPI: "upi",
    Bank: "bank",
  }[activeMethod] || "cash";

  const methodLabel: Record<string, string> = {
    Cash: t("cash"),
    Card: t("card"),
    UPI: t("qrUpi"),
    Bank: t("bank"),
  };

  return (
    <div className="px-3 pb-2">
      <div className="pos-pay-grid">
        <button
          type="button"
          className={`pay-pill-btn ${activeMethod === "Cash" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Cash");
            onOpenModal("cash");
          }}
        >
          <i className="ri-money-dollar-circle-fill text-success fs-14" aria-hidden="true"></i>
          <span>{t("cash")}</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${activeMethod === "Card" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Card");
            onOpenModal("card");
          }}
        >
          <i className="ri-bank-card-fill text-primary fs-14" aria-hidden="true"></i>
          <span>{t("card")}</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${activeMethod === "UPI" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("UPI");
            onOpenModal("upi");
          }}
        >
          <i className="ri-qr-code-line text-info fs-14" aria-hidden="true"></i>
          <span>{t("qrUpi")}</span>
        </button>

        <button
          type="button"
          className={`pay-pill-btn ${activeMethod === "Bank" ? "active" : ""}`}
          onClick={() => {
            onSelectMethod("Bank");
            onOpenModal("bank");
          }}
        >
          <i className="ri-bank-line text-warning fs-14" aria-hidden="true"></i>
          <span>{t("bank")}</span>
        </button>
      </div>

      {/* Primary Pay Action Button */}
      <button
        type="button"
        className="btn btn-charge w-100 d-flex align-items-center justify-content-between"
        onClick={() => onOpenModal(paymentModal)}
      >
        <span>{t("payMethod", { method: methodLabel[activeMethod] || activeMethod })}</span>
        <span className="badge bg-white text-primary fs-13 font-monospace px-2 py-1">
          {formatCurrency(totalPayable, currency)}
        </span>
      </button>
    </div>
  );
}
