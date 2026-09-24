import React, { useEffect, useRef, useState } from "react";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosCatalogToolbarProps {
  searchQuery: string;
  onSearchChange: (val: string) => void;
  stockFilter: string;
  onStockFilterChange: (filter: string) => void;
  onOpenScanModal: () => void;
}

export function PosCatalogToolbar({
  searchQuery,
  onSearchChange,
  stockFilter,
  onStockFilterChange,
  onOpenScanModal,
}: PosCatalogToolbarProps) {
  const { t } = useTranslation();
  const [inputValue, setInputValue] = useState(searchQuery);
  const inputRef = useRef<HTMLInputElement | null>(null);

  // Sync internal input value when external searchQuery changes (e.g. reset/cleared)
  useEffect(() => {
    setInputValue(searchQuery);
  }, [searchQuery]);

  // Global '/' keyboard shortcut to focus search input unless already in an input/textarea
  useEffect(() => {
    const handleGlobalSlash = (e: KeyboardEvent) => {
      const activeEl = document.activeElement;
      const isInputActive =
        activeEl instanceof HTMLInputElement ||
        activeEl instanceof HTMLTextAreaElement ||
        activeEl?.getAttribute("contenteditable") === "true";

      if (e.key === "/" && !isInputActive) {
        e.preventDefault();
        inputRef.current?.focus();
        inputRef.current?.select();
      }
    };

    window.addEventListener("keydown", handleGlobalSlash);
    return () => window.removeEventListener("keydown", handleGlobalSlash);
  }, []);

  const commitSearch = (val: string) => {
    const trimmed = val.trim();
    if (trimmed !== searchQuery.trim()) {
      onSearchChange(trimmed);
    }
  };

  const handleBlur = () => {
    commitSearch(inputValue);
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") {
      e.preventDefault();
      commitSearch(inputValue);
      inputRef.current?.blur();
    } else if (e.key === "Escape") {
      setInputValue(searchQuery);
      inputRef.current?.blur();
    }
  };

  const handleClear = () => {
    setInputValue("");
    if (searchQuery) {
      onSearchChange("");
    }
    inputRef.current?.focus();
  };

  return (
    <div className="bg-white p-3 rounded-3 border mb-3 shadow-xs">
      <div className="row g-2 align-items-center">
        {/* Search Bar - triggers search on blur, Enter, or clicking search button */}
        <div className="col-md-6 col-lg-5">
          <div className="position-relative">
            <button
              type="button"
              className="btn btn-link position-absolute top-50 start-0 ms-1 translate-middle-y text-muted p-1 text-decoration-none shadow-none"
              onClick={() => commitSearch(inputValue)}
              title={t("searchPlaceholder")}
              style={{ zIndex: 5 }}
            >
              <i className="ri-search-2-line fs-15"></i>
            </button>
            <input
              ref={inputRef}
              type="text"
              className="form-control form-control-sm py-2 rounded-2"
              style={{ paddingLeft: "36px", paddingRight: "34px" }}
              placeholder={t("searchPlaceholder")}
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              onBlur={handleBlur}
              onKeyDown={handleKeyDown}
            />
            {inputValue && (
              <button
                type="button"
                className="btn btn-sm position-absolute top-50 end-0 me-2 translate-middle-y text-muted border-0 p-0"
                onClick={handleClear}
                title={t("clearSearch")}
                style={{ zIndex: 5 }}
              >
                <i className="ri-close-circle-fill fs-15"></i>
              </button>
            )}
          </div>
        </div>

        {/* Stock Filter Chips with Explicit Spacing */}
        <div className="col-md-6 col-lg-7 d-flex justify-content-md-end">
          <div className="pos-filter-chips">
            <button
              type="button"
              className={`btn btn-sm rounded-pill px-3 py-1 fs-12 ${
                stockFilter === "all" ? "btn-primary" : "btn-light border text-muted"
              }`}
              onClick={() => onStockFilterChange("all")}
            >
              {t("allItems")}
            </button>
            <button
              type="button"
              className={`btn btn-sm rounded-pill px-3 py-1 fs-12 ${
                stockFilter === "instock"
                  ? "btn-primary"
                  : "btn-light border text-muted"
              }`}
              onClick={() => onStockFilterChange("instock")}
            >
              {t("inStock")}
            </button>
            <button
              type="button"
              className={`btn btn-sm rounded-pill px-3 py-1 fs-12 ${
                stockFilter === "lowstock"
                  ? "btn-primary"
                  : "btn-light border text-muted"
              }`}
              onClick={() => onStockFilterChange("lowstock")}
            >
              {t("lowStock")}
            </button>

            {/* Scan Barcode Modal Button */}
            <button
              type="button"
              className="btn btn-sm btn-outline-primary rounded-2 px-2.5 py-1 d-flex align-items-center gap-1 ms-1"
              onClick={onOpenScanModal}
              title={t("scan")}
            >
              <i className="ri-qr-scan-2-line fs-14"></i>
              <span className="fs-12">{t("scan")}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
