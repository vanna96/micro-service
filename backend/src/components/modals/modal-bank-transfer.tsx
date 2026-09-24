import React, { useState } from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalBankTransferProps {
  totalPayable: number;
  onConfirm: (details: { bankName: string; reference: string }) => Promise<void>;
  onClose: () => void;
}

export function ModalBankTransfer({
  totalPayable,
  onConfirm,
  onClose,
}: ModalBankTransferProps) {
  const currency = useCurrency();
  const { t } = useTranslation();
  const [bankName, setBankName] = useState("");
  const [reference, setReference] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const confirmTransfer = async () => {
    if (!bankName.trim() || !reference.trim() || isSaving) return;
    setIsSaving(true);
    setError(null);
    try {
      await onConfirm({ bankName: bankName.trim(), reference: reference.trim() });
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : t("unableToSaveBankPayment"));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">{t("bankTransferConfirmation")}</h6>
            <button type="button" className="btn-close" disabled={isSaving} onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="bg-light p-3 rounded-3 mb-3 d-flex justify-content-between">
              <span className="text-muted fs-13">{t("totalAmount")}</span>
              <span className="fw-bold fs-16 text-primary font-monospace">
                {formatCurrency(totalPayable, currency)}
              </span>
            </div>
            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">{t("bankName")}</label>
              <input
                type="text"
                className="form-control"
                maxLength={64}
                placeholder={t("enterBankName")}
                value={bankName}
                onChange={(event) => setBankName(event.target.value)}
              />
            </div>
            <div className="mb-3">
              <label className="form-label fs-12 fw-medium text-muted">{t("utrReferenceNo")}</label>
              <input
                type="text"
                className="form-control"
                maxLength={128}
                placeholder={t("enterReferenceNumber")}
                value={reference}
                onChange={(event) => setReference(event.target.value)}
              />
            </div>
            {error && <div className="alert alert-danger py-2 mb-0 fs-12">{error}</div>}
            <div className="d-flex gap-2 mt-4">
              <button type="button" className="btn btn-light w-50" disabled={isSaving} onClick={onClose}>
                {t("cancel")}
              </button>
              <button
                type="button"
                className="btn btn-primary w-50 fw-semibold"
                disabled={!bankName.trim() || !reference.trim() || isSaving}
                onClick={confirmTransfer}
              >
                {isSaving ? t("saving") : t("confirm")}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
