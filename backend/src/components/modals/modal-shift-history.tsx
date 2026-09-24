import React, { useState, useMemo } from "react";
import { Invoice } from "@/types/pos-types";
import { formatCurrency, useCurrency } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

interface ModalShiftHistoryProps {
  invoices: Invoice[];
  onViewInvoice: (invoice: Invoice) => void;
  onClose: () => void;
}

export function ModalShiftHistory({
  invoices,
  onViewInvoice,
  onClose,
}: ModalShiftHistoryProps) {
  const currency = useCurrency();
  const { t } = useTranslation();

  // Search & Filters
  const [search, setSearch] = useState<string>("");
  const [methodFilter, setMethodFilter] = useState<string>("all");

  // Sorting
  const [sortField, setSortField] = useState<keyof Invoice | "dateTime">("dateTime");
  const [sortDirection, setSortDirection] = useState<"asc" | "desc">("desc");

  // Pagination
  const [pageSize, setPageSize] = useState<number>(10);
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Shift KPI Statistics
  const shiftStats = useMemo(() => {
    let totalAmount = 0;
    let cashAmount = 0;
    let cardAmount = 0;
    let digitalAmount = 0;

    invoices.forEach((inv) => {
      const amt = Number(inv.amount) || 0;
      totalAmount += amt;
      const m = (inv.paymentMethod || "").toLowerCase();
      if (m.includes("cash")) {
        cashAmount += amt;
      } else if (m.includes("card")) {
        cardAmount += amt;
      } else {
        digitalAmount += amt;
      }
    });

    return {
      totalCount: invoices.length,
      totalAmount,
      cashAmount,
      cardAmount,
      digitalAmount,
    };
  }, [invoices]);

  // Method Counts for Filter Tabs
  const methodCounts = useMemo(() => {
    let cash = 0;
    let card = 0;
    let digital = 0;

    invoices.forEach((inv) => {
      const m = (inv.paymentMethod || "").toLowerCase();
      if (m.includes("cash")) cash++;
      else if (m.includes("card")) card++;
      else digital++;
    });

    return {
      all: invoices.length,
      cash,
      card,
      digital,
    };
  }, [invoices]);

  // Filtered & Sorted Invoices
  const filteredAndSortedInvoices = useMemo(() => {
    const q = search.trim().toLowerCase();

    const filtered = invoices.filter((inv) => {
      // Payment method filter
      if (methodFilter !== "all") {
        const m = (inv.paymentMethod || "").toLowerCase();
        if (methodFilter === "cash" && !m.includes("cash")) return false;
        if (methodFilter === "card" && !m.includes("card")) return false;
        if (methodFilter === "digital" && (m.includes("cash") || m.includes("card"))) return false;
      }

      // Search keyword filter
      if (q) {
        const idMatch = (inv.id || "").toLowerCase().includes(q);
        const custMatch = (inv.customer || "").toLowerCase().includes(q);
        const methodMatch = (inv.paymentMethod || "").toLowerCase().includes(q);
        const amountMatch = String(inv.amount || "").includes(q);
        const dateMatch = `${inv.date} ${inv.time}`.toLowerCase().includes(q);
        if (!idMatch && !custMatch && !methodMatch && !amountMatch && !dateMatch) {
          return false;
        }
      }

      return true;
    });

    // Sorting
    return [...filtered].sort((a, b) => {
      let comparison = 0;
      if (sortField === "dateTime") {
        const dtA = `${a.date} ${a.time}`;
        const dtB = `${b.date} ${b.time}`;
        comparison = dtA.localeCompare(dtB);
      } else if (sortField === "amount" || sortField === "totalItemCount") {
        const numA = Number(a[sortField]) || 0;
        const numB = Number(b[sortField]) || 0;
        comparison = numA - numB;
      } else {
        const strA = String(a[sortField] || "").toLowerCase();
        const strB = String(b[sortField] || "").toLowerCase();
        comparison = strA.localeCompare(strB);
      }

      return sortDirection === "asc" ? comparison : -comparison;
    });
  }, [invoices, search, methodFilter, sortField, sortDirection]);

  // Pagination bounds
  const totalEntries = filteredAndSortedInvoices.length;
  const totalPages = Math.max(1, Math.ceil(totalEntries / pageSize));
  const validCurrentPage = Math.min(Math.max(1, currentPage), totalPages);

  const startIndex = totalEntries === 0 ? 0 : (validCurrentPage - 1) * pageSize;
  const endIndex = Math.min(startIndex + pageSize, totalEntries);
  const paginatedInvoices = filteredAndSortedInvoices.slice(startIndex, endIndex);

  const handleSort = (field: keyof Invoice | "dateTime") => {
    if (sortField === field) {
      setSortDirection((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSortField(field);
      setSortDirection("desc");
    }
  };

  const renderSortIcon = (field: keyof Invoice | "dateTime") => {
    if (sortField !== field) {
      return <i className="ri-arrow-up-down-line text-muted opacity-40 ms-1 fs-11" />;
    }
    return sortDirection === "asc" ? (
      <i className="ri-arrow-up-s-line text-primary ms-1 fs-12 fw-bold" />
    ) : (
      <i className="ri-arrow-down-s-line text-primary ms-1 fs-12 fw-bold" />
    );
  };

  const getPaginationPages = () => {
    const delta = 1;
    const range: (number | string)[] = [];
    for (let i = Math.max(2, validCurrentPage - delta); i <= Math.min(totalPages - 1, validCurrentPage + delta); i++) {
      range.push(i);
    }
    if (validCurrentPage - delta > 2) {
      range.unshift("...");
    }
    if (validCurrentPage + delta < totalPages - 1) {
      range.push("...");
    }
    range.unshift(1);
    if (totalPages > 1) {
      range.push(totalPages);
    }
    return range;
  };

  const exportToCsv = () => {
    if (invoices.length === 0) return;
    const headers = [
      t("invoiceNumberCol"),
      t("date"),
      t("time"),
      t("customerCol"),
      t("methodCol"),
      t("itemsCol"),
      t("subtotal"),
      t("discount"),
      t("gstTax"),
      t("totalCol"),
    ];
    const rows = invoices.map((inv) => [
      `"${inv.id}"`,
      `"${inv.date}"`,
      `"${inv.time}"`,
      `"${(inv.customer || t("walkInCustomer")).replace(/"/g, '""')}"`,
      `"${inv.paymentMethod}"`,
      inv.totalItemCount ?? inv.snapshot?.cart?.length ?? 0,
      inv.subTotal ?? 0,
      inv.discountAmount ?? 0,
      inv.taxAmount ?? 0,
      inv.amount ?? 0,
    ]);

    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map((e) => e.join(","))].join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `shift-sales-invoices-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const renderPaymentBadge = (method: string) => {
    const m = (method || "").toLowerCase();
    if (m.includes("cash")) {
      return (
        <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-11 rounded-pill d-inline-flex align-items-center gap-1">
          <i className="ri-money-dollar-circle-line fs-12"></i>
          <span>{t("cash")}</span>
        </span>
      );
    }
    if (m.includes("card")) {
      return (
        <span className="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-11 rounded-pill d-inline-flex align-items-center gap-1">
          <i className="ri-bank-card-line fs-12"></i>
          <span>{t("card")}</span>
        </span>
      );
    }
    return (
      <span
        className="badge px-2 py-1 fs-11 rounded-pill d-inline-flex align-items-center gap-1"
        style={{ backgroundColor: "#f3e8ff", color: "#7e22ce", border: "1px solid #e9d5ff" }}
      >
        <i className="ri-qr-code-line fs-12"></i>
        <span>{method || t("qrUpi")}</span>
      </span>
    );
  };

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ zIndex: 1060 }}>
      <div className="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable" style={{ maxWidth: "1140px" }}>
        <div className="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          {/* Header */}
          <div className="modal-header border-bottom bg-white px-4 py-3 d-flex align-items-center justify-content-between">
            <div className="d-flex align-items-center gap-2">
              <div
                className="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                style={{ width: "36px", height: "36px" }}
              >
                <i className="ri-file-list-3-line fs-18"></i>
              </div>
              <div>
                <h6 className="modal-title fw-bold mb-0">{t("shiftSalesAndInvoices")}</h6>
                <p className="text-muted fs-11 mb-0">
                  {t("trackAndAuditCompletedSales")}
                </p>
              </div>
            </div>
            <div className="d-flex align-items-center gap-2">
              {invoices.length > 0 && (
                <button
                  type="button"
                  className="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 rounded-2 fs-12 py-1 px-2.5"
                  onClick={exportToCsv}
                  title={t("exportCsv")}
                >
                  <i className="ri-download-line fs-13"></i>
                  <span>{t("exportCsv")}</span>
                </button>
              )}
              <button
                type="button"
                className="btn-close"
                onClick={onClose}
                aria-label="Close"
              ></button>
            </div>
          </div>

          <div className="modal-body p-4 bg-light">
            {/* KPI Statistics Summary Cards */}
            <div className="row g-2 mb-3">
              <div className="col-6 col-md-3">
                <div className="card border shadow-xs rounded-3 p-3 bg-white h-100">
                  <div className="d-flex align-items-center justify-content-between text-muted fs-11 mb-1">
                    <span>{t("totalSales")}</span>
                    <i className="ri-funds-line text-primary fs-14"></i>
                  </div>
                  <h5 className="fw-bolder font-monospace text-dark mb-0">
                    {formatCurrency(shiftStats.totalAmount, currency)}
                  </h5>
                  <span className="text-muted fs-11 mt-1">
                    {shiftStats.totalCount} {shiftStats.totalCount === 1 ? t("saleRecorded") : t("salesRecorded")}
                  </span>
                </div>
              </div>
              <div className="col-6 col-md-3">
                <div className="card border shadow-xs rounded-3 p-3 bg-white h-100">
                  <div className="d-flex align-items-center justify-content-between text-muted fs-11 mb-1">
                    <span>{t("cashTenderKpi")}</span>
                    <i className="ri-money-dollar-circle-line text-success fs-14"></i>
                  </div>
                  <h5 className="fw-bolder font-monospace text-success mb-0">
                    {formatCurrency(shiftStats.cashAmount, currency)}
                  </h5>
                  <span className="text-muted fs-11 mt-1">
                    {methodCounts.cash} {methodCounts.cash === 1 ? t("cashReceipt") : t("cashReceipts")}
                  </span>
                </div>
              </div>
              <div className="col-6 col-md-3">
                <div className="card border shadow-xs rounded-3 p-3 bg-white h-100">
                  <div className="d-flex align-items-center justify-content-between text-muted fs-11 mb-1">
                    <span>{t("cardPaymentsKpi")}</span>
                    <i className="ri-bank-card-line text-primary fs-14"></i>
                  </div>
                  <h5 className="fw-bolder font-monospace text-primary mb-0">
                    {formatCurrency(shiftStats.cardAmount, currency)}
                  </h5>
                  <span className="text-muted fs-11 mt-1">
                    {methodCounts.card} {methodCounts.card === 1 ? t("cardReceipt") : t("cardReceipts")}
                  </span>
                </div>
              </div>
              <div className="col-6 col-md-3">
                <div className="card border shadow-xs rounded-3 p-3 bg-white h-100">
                  <div className="d-flex align-items-center justify-content-between text-muted fs-11 mb-1">
                    <span>{t("bankQrKpi")}</span>
                    <i className="ri-qr-code-line text-info fs-14"></i>
                  </div>
                  <h5 className="fw-bolder font-monospace text-info mb-0">
                    {formatCurrency(shiftStats.digitalAmount, currency)}
                  </h5>
                  <span className="text-muted fs-11 mt-1">
                    {methodCounts.digital} {methodCounts.digital === 1 ? t("digitalReceipt") : t("digitalReceipts")}
                  </span>
                </div>
              </div>
            </div>

            {/* DataTable Container Card */}
            <div className="card border shadow-xs rounded-3 overflow-hidden bg-white">
              {/* DataTable Controls Bar */}
              <div className="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
                {/* Method Filter Tabs */}
                <div className="btn-group btn-group-sm" role="group" aria-label="Payment method filter">
                  <button
                    type="button"
                    className={`btn ${methodFilter === "all" ? "btn-primary" : "btn-light border text-muted"}`}
                    onClick={() => {
                      setMethodFilter("all");
                      setCurrentPage(1);
                    }}
                  >
                    {t("allCount", { count: String(methodCounts.all) })}
                  </button>
                  <button
                    type="button"
                    className={`btn ${methodFilter === "cash" ? "btn-primary" : "btn-light border text-muted"}`}
                    onClick={() => {
                      setMethodFilter("cash");
                      setCurrentPage(1);
                    }}
                  >
                    {t("cashCount", { count: String(methodCounts.cash) })}
                  </button>
                  <button
                    type="button"
                    className={`btn ${methodFilter === "card" ? "btn-primary" : "btn-light border text-muted"}`}
                    onClick={() => {
                      setMethodFilter("card");
                      setCurrentPage(1);
                    }}
                  >
                    {t("cardCount", { count: String(methodCounts.card) })}
                  </button>
                  <button
                    type="button"
                    className={`btn ${methodFilter === "digital" ? "btn-primary" : "btn-light border text-muted"}`}
                    onClick={() => {
                      setMethodFilter("digital");
                      setCurrentPage(1);
                    }}
                  >
                    {t("digitalCount", { count: String(methodCounts.digital) })}
                  </button>
                </div>

                <div className="d-flex align-items-center gap-2 flex-grow-1 justify-content-end" style={{ maxWidth: "450px" }}>
                  {/* Page Size Selector */}
                  <div className="d-flex align-items-center gap-1 text-muted fs-12 flex-shrink-0">
                    <span>{t("show")}</span>
                    <select
                      className="form-select form-select-sm py-1 px-2 fs-12"
                      style={{ width: "68px" }}
                      value={pageSize}
                      onChange={(e) => {
                        setPageSize(Number(e.target.value));
                        setCurrentPage(1);
                      }}
                    >
                      <option value={10}>10</option>
                      <option value={25}>25</option>
                      <option value={50}>50</option>
                      <option value={100}>100</option>
                    </select>
                  </div>

                  {/* Search Input */}
                  <div className="position-relative flex-grow-1">
                    <i className="ri-search-line position-absolute top-50 start-0 translate-middle-y ms-2.5 text-muted fs-13"></i>
                    <input
                      type="text"
                      className="form-control form-control-sm ps-4 pe-4 py-1 fs-12 rounded-2"
                      placeholder={t("searchInvoiceCustomer")}
                      value={search}
                      onChange={(e) => {
                        setSearch(e.target.value);
                        setCurrentPage(1);
                      }}
                    />
                    {search && (
                      <button
                        type="button"
                        className="btn btn-link text-muted position-absolute top-50 end-0 translate-middle-y me-1 p-0 text-decoration-none"
                        onClick={() => {
                          setSearch("");
                          setCurrentPage(1);
                        }}
                      >
                        <i className="ri-close-line fs-14"></i>
                      </button>
                    )}
                  </div>
                </div>
              </div>

              {/* DataTable Table */}
              <div className="table-responsive mb-0">
                <table className="table table-hover align-middle mb-0 fs-13">
                  <thead className="table-light text-muted fs-12 text-uppercase">
                    <tr>
                      <th
                        scope="col"
                        style={{ width: "220px", cursor: "pointer", userSelect: "none" }}
                        onClick={() => handleSort("id")}
                      >
                        <div className="d-flex align-items-center">
                          <span>{t("invoiceNumberCol")}</span>
                          {renderSortIcon("id")}
                        </div>
                      </th>
                      <th
                        scope="col"
                        style={{ width: "170px", cursor: "pointer", userSelect: "none" }}
                        onClick={() => handleSort("dateTime")}
                      >
                        <div className="d-flex align-items-center">
                          <span>{t("dateTimeCol")}</span>
                          {renderSortIcon("dateTime")}
                        </div>
                      </th>
                      <th
                        scope="col"
                        style={{ cursor: "pointer", userSelect: "none" }}
                        onClick={() => handleSort("customer")}
                      >
                        <div className="d-flex align-items-center">
                          <span>{t("customerCol")}</span>
                          {renderSortIcon("customer")}
                        </div>
                      </th>
                      <th
                        scope="col"
                        style={{ width: "140px", cursor: "pointer", userSelect: "none" }}
                        onClick={() => handleSort("paymentMethod")}
                      >
                        <div className="d-flex align-items-center">
                          <span>{t("methodCol")}</span>
                          {renderSortIcon("paymentMethod")}
                        </div>
                      </th>
                      <th
                        scope="col"
                        className="text-center"
                        style={{ width: "90px", cursor: "pointer", userSelect: "none" }}
                        onClick={() => handleSort("totalItemCount")}
                      >
                        <div className="d-flex align-items-center justify-content-center">
                          <span>{t("itemsCol")}</span>
                          {renderSortIcon("totalItemCount")}
                        </div>
                      </th>
                      <th
                        scope="col"
                        className="text-end"
                        style={{ width: "130px", cursor: "pointer", userSelect: "none" }}
                        onClick={() => handleSort("amount")}
                      >
                        <div className="d-flex align-items-center justify-content-end">
                          <span>{t("totalCol")}</span>
                          {renderSortIcon("amount")}
                        </div>
                      </th>
                      <th scope="col" className="text-center" style={{ width: "110px" }}>
                        {t("statusCol")}
                      </th>
                      <th scope="col" className="text-end pe-4" style={{ width: "100px" }}>
                        {t("actionCol")}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedInvoices.map((inv) => {
                      const itemCount = inv.totalItemCount ?? inv.snapshot?.cart?.length ?? 1;
                      return (
                        <tr key={inv.id} style={{ cursor: "pointer" }} onClick={() => onViewInvoice(inv)}>
                          <td>
                            <div className="d-flex align-items-center gap-1.5">
                              <span className="fw-bold text-primary font-monospace fs-13">
                                {inv.id}
                              </span>
                            </div>
                          </td>
                          <td>
                            <div className="text-dark fs-12">{inv.date}</div>
                            <small className="text-muted fs-11 font-monospace">{inv.time}</small>
                          </td>
                          <td>
                            <div className="d-flex align-items-center gap-1.5">
                              <div
                                className="rounded-circle bg-light border d-flex align-items-center justify-content-center text-muted fs-11 flex-shrink-0"
                                style={{ width: "24px", height: "24px" }}
                              >
                                <i className="ri-user-3-line"></i>
                              </div>
                              <span className="text-dark fw-medium text-truncate" style={{ maxWidth: "200px" }}>
                                {inv.customer || t("walkInCustomer")}
                              </span>
                            </div>
                          </td>
                          <td>{renderPaymentBadge(inv.paymentMethod)}</td>
                          <td className="text-center">
                            <span className="badge bg-light border text-muted px-2 py-0.5 rounded-pill fs-11">
                              {itemCount} {itemCount === 1 ? t("item") : t("items")}
                            </span>
                          </td>
                          <td className="text-end">
                            <span className="fw-bold font-monospace text-dark fs-13">
                              {formatCurrency(inv.amount, currency)}
                            </span>
                          </td>
                          <td className="text-center">
                            <span className="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill fs-11">
                              {inv.status || t("completed")}
                            </span>
                          </td>
                          <td className="text-end pe-4" onClick={(e) => e.stopPropagation()}>
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 rounded-2 py-1 px-2.5 fs-12"
                              onClick={() => onViewInvoice(inv)}
                              title={t("view")}
                            >
                              <i className="ri-file-list-3-line fs-12"></i>
                              <span>{t("view")}</span>
                            </button>
                          </td>
                        </tr>
                      );
                    })}

                    {paginatedInvoices.length === 0 && (
                      <tr>
                        <td colSpan={8} className="text-center py-5 text-muted">
                          <div className="d-flex flex-column align-items-center justify-content-center">
                            <i className="ri-inbox-line fs-32 text-muted opacity-40 mb-2"></i>
                            <h6 className="fw-semibold text-dark mb-1">{t("noInvoicesFound")}</h6>
                            <p className="text-muted fs-12 mb-2">
                              {search || methodFilter !== "all"
                                ? t("noTransactionsMatch")
                                : t("noSalesInShiftSession")}
                            </p>
                            {(search || methodFilter !== "all") && (
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-secondary rounded-2 fs-12"
                                onClick={() => {
                                  setSearch("");
                                  setMethodFilter("all");
                                  setCurrentPage(1);
                                }}
                              >
                                {t("clearFilters")}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              {/* DataTable Footer & Pagination Controls */}
              <div className="p-3 border-top bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div className="text-muted fs-12">
                  {totalEntries > 0 ? (
                    <>
                      {t("showingEntries", {
                        start: String(startIndex + 1),
                        end: String(endIndex),
                        total: String(totalEntries),
                      })}
                      {totalEntries < invoices.length && (
                        <span className="ms-1 text-muted">
                          {t("filteredFromTotal", { total: String(invoices.length) })}
                        </span>
                      )}
                    </>
                  ) : (
                    <span>{t("showingZeroEntries")}</span>
                  )}
                </div>

                {totalPages > 1 && (
                  <nav aria-label="DataTable pagination">
                    <ul className="pagination pagination-sm mb-0">
                      <li className={`page-item ${validCurrentPage === 1 ? "disabled" : ""}`}>
                        <button
                          type="button"
                          className="page-link"
                          onClick={() => setCurrentPage(1)}
                          disabled={validCurrentPage === 1}
                          title={t("firstPage")}
                        >
                          &laquo;
                        </button>
                      </li>
                      <li className={`page-item ${validCurrentPage === 1 ? "disabled" : ""}`}>
                        <button
                          type="button"
                          className="page-link"
                          onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                          disabled={validCurrentPage === 1}
                          title={t("previousPage")}
                        >
                          &lsaquo;
                        </button>
                      </li>

                      {getPaginationPages().map((page, idx) => {
                        if (typeof page === "string") {
                          return (
                            <li key={`ellipsis-${idx}`} className="page-item disabled">
                              <span className="page-link px-2">...</span>
                            </li>
                          );
                        }
                        return (
                          <li
                            key={page}
                            className={`page-item ${validCurrentPage === page ? "active" : ""}`}
                          >
                            <button
                              type="button"
                              className="page-link"
                              onClick={() => setCurrentPage(page)}
                            >
                              {page}
                            </button>
                          </li>
                        );
                      })}

                      <li className={`page-item ${validCurrentPage === totalPages ? "disabled" : ""}`}>
                        <button
                          type="button"
                          className="page-link"
                          onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                          disabled={validCurrentPage === totalPages}
                          title={t("nextPage")}
                        >
                          &rsaquo;
                        </button>
                      </li>
                      <li className={`page-item ${validCurrentPage === totalPages ? "disabled" : ""}`}>
                        <button
                          type="button"
                          className="page-link"
                          onClick={() => setCurrentPage(totalPages)}
                          disabled={validCurrentPage === totalPages}
                          title={t("lastPage")}
                        >
                          &raquo;
                        </button>
                      </li>
                    </ul>
                  </nav>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
