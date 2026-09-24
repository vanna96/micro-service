import React, { useState } from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalSplitTenderProps {
  totalPayable: number;
  onConfirm: () => void;
  onClose: () => void;
}

export function ModalSplitTender({
  totalPayable,
  onConfirm,
  onClose,
}: ModalSplitTenderProps) {
  const currency = useCurrency();
  const { t } = useTranslation();
  const [splitRows] = useState([
    { id: 1, method: "" },
    { id: 2, method: "" },
  ]);

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-lg">
        <div className="modal-content shadow-lg border-0 rounded-4">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <h6 className="modal-title fw-bold mb-0">{t("splitTender")}</h6>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="bg-light p-3 rounded-3 mb-3 d-flex justify-content-between">
              <span className="text-muted fs-13">{t("totalOrderAmount")}</span>
              <span className="fw-bold fs-17 text-primary font-monospace">
                {formatCurrency(totalPayable, currency)}
              </span>
            </div>
            {splitRows.map((row) => (
              <div key={row.id} className="row g-2 align-items-center mb-2">
                <div className="col-4">
                  <select className="form-select form-select-sm" defaultValue={row.method}>
                    <option value="" disabled>{t("selectMethod")}</option>
                    <option value="Cash">{t("cash")}</option>
                    <option value="Card">{t("card")}</option>
                    <option value="UPI">{t("upiQr")}</option>
                    <option value="Bank">{t("bankTransfer")}</option>
                  </select>
                </div>
                <div className="col-6">
                  <input
                    type="number"
                    className="form-control form-control-sm font-monospace"
                    placeholder="0.00"
                  />
                </div>
                <div className="col-2 text-end">
                  <span className="badge bg-light border text-muted">{t("pending")}</span>
                </div>
              </div>
            ))}
            <div className="d-flex gap-2 mt-4">
              <button type="button" className="btn btn-light w-50" onClick={onClose}>
                {t("cancel")}
              </button>
              <button
                type="button"
                className="btn btn-primary w-50 fw-semibold"
                onClick={onConfirm}
              >
                {t("completeSplitSale")}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
