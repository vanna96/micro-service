import React from "react";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { CashTenderInput, CurrencyInfo } from "@/types/pos-types";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalCashTenderProps {
  totalPayable: number;
  currencies: CurrencyInfo[];
  currencyRates: Record<string, number>;
  onConfirm: (tenders: CashTenderInput[], tenderSummary?: { tendered: number; change: number }) => Promise<void>;
  onTenderChange?: (summary: { tendered: number; change: number }) => void;
  onClose: () => void;
}

function inputStep(currency: CurrencyInfo): string {
  if (currency.decimalPlaces === 0) return "1";

  return `0.${"0".repeat(Math.max(0, currency.decimalPlaces - 1))}1`;
}

export function ModalCashTender({
  totalPayable,
  currencies,
  currencyRates,
  onConfirm,
  onTenderChange,
  onClose,
}: ModalCashTenderProps) {
  const baseCurrency = useCurrency();
  const { t } = useTranslation();
  const [received, setReceived] = React.useState<Record<string, string>>({});
  const [isSaving, setIsSaving] = React.useState(false);
  const [saveError, setSaveError] = React.useState<string | null>(null);
  const configuredCurrencies = [
    ...(currencies.length > 0 ? currencies : baseCurrency ? [baseCurrency] : []),
  ].sort((left, right) => {
    if (left.code === baseCurrency?.code) return -1;
    if (right.code === baseCurrency?.code) return 1;
    return 0;
  });
  const rateFor = (currency: CurrencyInfo): number => {
    if (!baseCurrency || currency.code === baseCurrency.code) return 1;

    return Number(currencyRates[currency.code]);
  };
  const totalReceivedInBase = configuredCurrencies.reduce((total, currency) => {
    const rate = rateFor(currency);
    const amount = Math.max(0, Number(received[currency.code]) || 0);

    return rate > 0 ? total + amount / rate : total;
  }, 0);
  const remainingBalance = Math.max(0, totalPayable - totalReceivedInBase);
  const cashChange = Math.max(0, totalReceivedInBase - totalPayable);
  const baseDecimalPlaces = Math.max(0, baseCurrency?.decimalPlaces ?? 2);
  const tolerance = 0.5 / (10 ** baseDecimalPlaces);
  const isFullyPaid = remainingBalance <= tolerance;
  const statusAmount = isFullyPaid ? cashChange : remainingBalance;
  const statusCurrencies = configuredCurrencies.filter((currency) => rateFor(currency) > 0);
  const baseCurrencyReceived = baseCurrency
    ? Math.max(0, Number(received[baseCurrency.code]) || 0)
    : 0;
  const receivedWithoutBaseCurrency = Math.max(0, totalReceivedInBase - baseCurrencyReceived);
  const exactBaseAmount = Math.max(0, totalPayable - receivedWithoutBaseCurrency);

  const onTenderChangeRef = React.useRef(onTenderChange);
  onTenderChangeRef.current = onTenderChange;
  const lastReportedRef = React.useRef<{ tendered: number; change: number } | null>(null);

  React.useEffect(() => {
    if (
      lastReportedRef.current &&
      lastReportedRef.current.tendered === totalReceivedInBase &&
      lastReportedRef.current.change === cashChange
    ) {
      return;
    }
    lastReportedRef.current = { tendered: totalReceivedInBase, change: cashChange };
    onTenderChangeRef.current?.({
      tendered: totalReceivedInBase,
      change: cashChange,
    });
  }, [totalReceivedInBase, cashChange]);

  const updateReceived = (currencyCode: string, value: string) => {
    setReceived((current) => ({ ...current, [currencyCode]: value }));
    setSaveError(null);
  };

  const confirmCashTender = async () => {
    if (!isFullyPaid || isSaving) return;

    const tenders = configuredCurrencies
      .map((currency) => ({
        currencyCode: currency.code,
        amount: Math.max(0, Number(received[currency.code]) || 0),
      }))
      .filter((tender) => tender.amount > 0);

    setIsSaving(true);
    setSaveError(null);
    try {
      await onConfirm(tenders, { tendered: totalReceivedInBase, change: cashChange });
    } catch (error: unknown) {
      setSaveError(error instanceof Error ? error.message : t("unableToSaveCashPayment"));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div className="modal-header border-bottom bg-light px-4 py-3">
            <div className="d-flex align-items-center gap-2">
              <div className="avatar size-8 bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center">
                <i className="ri-hand-coin-line fs-16" aria-hidden="true"></i>
              </div>
              <h6 className="modal-title fw-bold mb-0">{t("cashTender")}</h6>
            </div>
            <button type="button" className="btn-close" disabled={isSaving} onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            <div className="bg-light p-3 rounded-3 mb-3 d-flex justify-content-between align-items-center border">
              <span className="text-muted fs-13">{t("totalPayable")}</span>
              <span className="fs-18 fw-bold text-primary font-monospace">
                {formatCurrency(totalPayable, baseCurrency)}
              </span>
            </div>

            <div className="mb-3">
              <label className="form-label fs-12 fw-semibold text-muted">
                {t("cashReceivedFromCustomer")}
              </label>
              <div className="d-flex flex-column gap-2">
                {configuredCurrencies.map((currency, index) => {
                  const rate = rateFor(currency);
                  const rateAvailable = rate > 0;

                  return (
                    <div key={currency.code}>
                      <div className="input-group input-group-lg">
                        <span className="input-group-text bg-light fw-bold text-muted cash-tender-symbol">
                          {currency.symbol}
                        </span>
                        <input
                          type="number"
                          min="0"
                          step={inputStep(currency)}
                          className="form-control fw-bold font-monospace fs-20 text-primary py-2 px-3"
                          placeholder={(0).toFixed(currency.decimalPlaces)}
                          value={received[currency.code] || ""}
                          disabled={!rateAvailable}
                          onChange={(event) => updateReceived(currency.code, event.target.value)}
                          aria-label={`${t("cashReceivedFromCustomer")} ${currency.code}`}
                          autoFocus={index === 0}
                        />
                      </div>
                      {currency.code !== baseCurrency?.code && rateAvailable && (
                        <div className="text-end text-muted fs-10 mt-1">
                          1 {baseCurrency?.code} = {rate.toLocaleString(undefined, { maximumFractionDigits: 8 })} {currency.code}
                        </div>
                      )}
                      {!rateAvailable && (
                        <div className="text-end text-danger fs-10 mt-1">
                          {t("exchangeRateNotConfigured")} {currency.code}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            {baseCurrency && (
              <div className="d-flex flex-wrap gap-2 mb-3">
                {[20, 50, 100, 200].map((amount) => (
                  <button
                    key={amount}
                    type="button"
                    className="btn btn-outline-secondary flex-fill py-2 px-2 fs-13 fw-semibold rounded-2"
                    onClick={() => updateReceived(baseCurrency.code, amount.toFixed(baseCurrency.decimalPlaces))}
                  >
                    {formatCurrency(amount, baseCurrency)}
                  </button>
                ))}
                <button
                  type="button"
                  className="btn btn-outline-primary flex-fill py-2 px-2.5 fs-13 fw-semibold rounded-2"
                  onClick={() => updateReceived(baseCurrency.code, exactBaseAmount.toFixed(baseCurrency.decimalPlaces))}
                >
                  {t("exact")} ({formatCurrency(exactBaseAmount, baseCurrency)})
                </button>
              </div>
            )}

            <div className={`alert ${isFullyPaid ? "alert-success" : "alert-warning"} d-flex justify-content-between align-items-center mb-4 p-3 rounded-3 border-0`}>
              <span className="fs-13 fw-medium">
                {isFullyPaid ? t("changeToReturn") : t("remainingBalance")}
              </span>
              <span className="fw-bold fs-18 font-monospace text-end">
                {statusCurrencies.map((currency, index) => (
                  <React.Fragment key={currency.code}>
                    {index > 0 && <span className="mx-1">/</span>}
                    <span>{formatCurrency(statusAmount * rateFor(currency), currency)}</span>
                  </React.Fragment>
                ))}
              </span>
            </div>

            {saveError && (
              <div className="alert alert-danger py-2 fs-12" role="alert">{saveError}</div>
            )}

            <div className="d-flex gap-2 pt-1">
              <button type="button" className="btn btn-light w-50 py-2.5 rounded-2 fw-medium" disabled={isSaving} onClick={onClose}>
                {t("cancel")}
              </button>
              <button
                type="button"
                className="btn btn-primary w-50 py-2.5 rounded-2 fw-semibold"
                disabled={!isFullyPaid || isSaving}
                onClick={confirmCashTender}
              >
                {isSaving ? t("saving") : t("confirmAndPrint")}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
