import React from "react";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosBottomToolbarProps {
  heldCount: number;
  onOpenModal: (modal: string) => void;
}

export function PosBottomToolbar({
  heldCount,
  onOpenModal,
}: PosBottomToolbarProps) {
  const { t } = useTranslation();

  return (
    <div className="pos-bottom-features mt-auto">
      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("hold")}
      >
        <div className="pos-tool-icon bg-warning-subtle text-warning">
          <i className="ri-pause-circle-line"></i>
        </div>
        <span>{t("hold")} ({heldCount})</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("invoice")}
      >
        <div className="pos-tool-icon bg-pink-subtle text-pink">
          <i className="ri-file-text-line"></i>
        </div>
        <span>{t("invoice")}</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("pay_later")}
      >
        <div className="pos-tool-icon bg-secondary-subtle text-secondary">
          <i className="ri-time-line"></i>
        </div>
        <span>{t("payLater")}</span>
      </button>

      <button
        type="button"
        className="pos-tool-btn"
        onClick={() => onOpenModal("history")}
      >
        <div className="pos-tool-icon bg-danger-subtle text-danger">
          <i className="ri-history-line"></i>
        </div>
        <span>{t("history")}</span>
      </button>
    </div>
  );
}
