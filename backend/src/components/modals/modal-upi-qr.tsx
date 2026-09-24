import React from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalUpiQrProps {
  totalPayable: number;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalUpiQr({ totalPayable, onConfirm, onClose }: ModalUpiQrProps) {
  const currency = useCurrency();
  const { t } = useTranslation();

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-sm">
        <div className="modal-content shadow-lg border-0 rounded-4 text-center p-4">
          <span className="text-muted fs-12">{t("scanQrToPay")}</span>
          <h4 className="fw-bolder text-primary my-2 font-monospace">
            {formatCurrency(totalPayable, currency)}
          </h4>
          <div className="bg-light p-3 rounded-3 border d-inline-block mx-auto mb-3">
            <img
              src="/assets/qr-CvtWFzmv.png"
              alt="QR"
              style={{ width: "160px", height: "160px" }}
            />
          </div>
          <p className="text-muted fs-12 mb-4">
            {t("supportedPaymentApps")}
          </p>
          <div className="d-flex gap-2">
            <button type="button" className="btn btn-light w-50" onClick={onClose}>
              {t("cancel")}
            </button>
            <button
              type="button"
              className="btn btn-primary w-50 fw-semibold"
              onClick={onConfirm}
            >
              {t("verify")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
