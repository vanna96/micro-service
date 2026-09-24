import React from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalPaymentSuccessProps {
  invoiceNumber: string;
  totalPayable: number;
  selectedPayMethod: string;
  currentDate: string;
  onViewInvoice?: () => void;
  onNextSale?: () => void;
  onNextSaleAndInvoice?: () => void;
  onClose: () => void;
}

export function ModalPaymentSuccess({
  invoiceNumber,
  totalPayable,
  selectedPayMethod,
  currentDate,
  onViewInvoice,
  onNextSale,
  onNextSaleAndInvoice,
  onClose,
}: ModalPaymentSuccessProps) {
  const currency = useCurrency();
  const { t } = useTranslation();
  const handleNextSaleAndInvoice = onNextSaleAndInvoice || (() => {
    onViewInvoice?.();
    onNextSale?.();
  });

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
          <h5 className="fw-bolder text-body mb-1">{t("transactionSuccessful")}</h5>
          <p className="text-muted fs-12 mb-3">{t("paymentRecordedSuccessfully")}</p>
          <div className="bg-primary-subtle text-primary border border-primary-subtle font-monospace mb-3 px-3 py-2 rounded-2 w-100 text-center fs-13">
            <i className="ri-file-list-3-line me-1" aria-hidden="true"></i>
            {t("invoice")} {invoiceNumber}
          </div>
          <div className="bg-light p-3 rounded-3 mb-4 border">
            <span className="text-muted fs-12 d-block">{t("amountCharged")}</span>
            <h3 className="fw-bolder text-primary my-1 font-monospace">
              {formatCurrency(totalPayable, currency)}
            </h3>
            <small className="text-muted fs-11">
              {[currentDate, selectedPayMethod].filter(Boolean).join(" | ")}
            </small>
          </div>
          <div className="d-flex">
            <button
              type="button"
              className="btn btn-primary w-100 py-2 rounded-2 fw-semibold fs-14 d-flex align-items-center justify-content-center gap-2"
              onClick={handleNextSaleAndInvoice}
            >
              <i className="ri-file-list-3-line" aria-hidden="true"></i>
              <span>{t("nextSaleAndInvoice")}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
