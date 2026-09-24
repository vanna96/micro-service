import React, { useState } from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalCardTerminalProps {
  totalPayable: number;
  onConfirm: (details: { provider: string; reference: string; lastFour: string }) => Promise<void>;
  onClose: () => void;
}

export function ModalCardTerminal({
  totalPayable,
  onConfirm,
  onClose,
}: ModalCardTerminalProps) {
  const currency = useCurrency();
  const { t } = useTranslation();
  const [cardTab, setCardTab] = useState<"visa" | "mastercard">("visa");
  const [lastFour, setLastFour] = useState("");
  const [reference, setReference] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const processCard = async () => {
    if (lastFour.length !== 4 || !reference.trim() || isSaving) return;
    setIsSaving(true);
    setError(null);
    try {
      await onConfirm({ provider: cardTab === "visa" ? "Visa" : "Mastercard", reference: reference.trim(), lastFour });
    } catch (caught: unknown) {
      setError(caught instanceof Error ? caught.message : t("unableToSaveCardPayment"));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">{t("cardPaymentTerminal")}</h6>
            <button type="button" className="btn-close" disabled={isSaving} onClick={onClose}></button>
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
                <label className="form-label fs-12 fw-medium text-muted">{t("last4CardDigits")}</label>
                <input
                  type="text"
                  className="form-control"
                  inputMode="numeric"
                  maxLength={4}
                  placeholder="1234"
                  value={lastFour}
                  onChange={(event) => setLastFour(event.target.value.replace(/\D/g, "").slice(0, 4))}
                />
              </div>
              <div className="col-12">
                <label className="form-label fs-12 fw-medium text-muted">{t("approvalReferenceNo")}</label>
                <input type="text" className="form-control" maxLength={128} placeholder={t("enterTerminalReference")} value={reference} onChange={(event) => setReference(event.target.value)} />
              </div>
              <div className="col-12">
                <div className="bg-light p-2.5 rounded-2 d-flex justify-content-between">
                  <span className="text-muted fs-13">{t("chargeAmount")}</span>
                  <span className="fw-bold fs-16 text-primary font-monospace">
                    {formatCurrency(totalPayable, currency)}
                  </span>
                </div>
              </div>
            </div>

            {error && <div className="alert alert-danger py-2 mt-3 mb-0 fs-12">{error}</div>}

            <div className="d-flex gap-2 mt-4">
              <button type="button" className="btn btn-light w-50" disabled={isSaving} onClick={onClose}>
                {t("cancel")}
              </button>
              <button
                type="button"
                className="btn btn-primary w-50 fw-semibold"
                disabled={lastFour.length !== 4 || !reference.trim() || isSaving}
                onClick={processCard}
              >
                {isSaving ? t("saving") : t("processCard")}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
