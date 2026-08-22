import React from "react";

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
  return (
    <div className="bg-white p-3 rounded-3 border mb-3 shadow-xs">
      <div className="row g-2 align-items-center">
        {/* Search Bar */}
        <div className="col-md-6 col-lg-5">
          <div className="position-relative">
            <i className="ri-search-2-line position-absolute top-50 start-0 ms-3 translate-middle-y text-muted fs-15"></i>
            <input
              type="text"
              className="form-control form-control-sm ps-9 pe-8 py-2 rounded-2"
              placeholder="Search product by name or SKU... (/)"
              value={searchQuery}
              onChange={(e) => onSearchChange(e.target.value)}
            />
            {searchQuery && (
              <button
                type="button"
                className="btn btn-sm position-absolute top-50 end-0 me-2 translate-middle-y text-muted border-0 p-0"
                onClick={() => onSearchChange("")}
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
              All Items
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
              In Stock
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
              Low Stock
            </button>

            {/* Scan Barcode Modal Button */}
            <button
              type="button"
              className="btn btn-sm btn-outline-primary rounded-2 px-2.5 py-1 d-flex align-items-center gap-1 ms-1"
              onClick={onOpenScanModal}
              title="Scan Barcode"
            >
              <i className="ri-qr-scan-2-line fs-14"></i>
              <span className="fs-12">Scan</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
