"use client";

import React, { useEffect, useMemo, useState } from "react";
import { PRODUCTS, INITIAL_INVOICES } from "@/data/pos-data";
import { Product, ProductUOM, VariantValue, HeldOrder, Invoice } from "@/types/pos-types";

// Custom Hooks & Redux
import { useLiveClock } from "@/hooks/use-live-clock";
import { usePosCalculations } from "@/hooks/use-pos-calculations";
import { useAppDispatch, useAppSelector } from "@/store/hooks";
import {
  addDirectToCart,
  addConfiguredToCart,
  updateQuantity,
  removeItem,
  clearCart,
  setCart
} from "@/store/slices/cartSlice";
import {
  setDiscountPercent,
  setTaxPercent,
  setServiceFee,
  setOrderType,
  addHeldOrder,
  removeHeldOrder
} from "@/store/slices/posSlice";
import {
  setActiveCategory,
  setSearchQuery,
  setStockFilter,
  setActiveModal,
  setOpenDropdown,
  setIsFullscreen,
  setSelectedPayMethod
} from "@/store/slices/uiSlice";
import { setCustomer } from "@/store/slices/customerSlice";

// Modular POS Subcomponents
import { PosTopbar } from "./pos/pos-topbar";
import { PosCategoryRail } from "./pos/pos-category-rail";
import { PosCatalogToolbar } from "./pos/pos-catalog-toolbar";
import { PosProductCard } from "./pos/pos-product-card";
import { PosCartItem } from "./pos/pos-cart-item";
import { PosSummaryCard } from "./pos/pos-summary-card";
import { PosPaymentGrid } from "./pos/pos-payment-grid";
import { PosBottomToolbar } from "./pos/pos-bottom-toolbar";

// Modular Modal Components
import { ModalConfigProduct } from "./modals/modal-config-product";
import { ModalCashTender } from "./modals/modal-cash-tender";
import { ModalCardTerminal } from "./modals/modal-card-terminal";
import { ModalUpiQr } from "./modals/modal-upi-qr";
import { ModalBankTransfer } from "./modals/modal-bank-transfer";
import { ModalSplitTender } from "./modals/modal-split-tender";
import { ModalHeldOrders } from "./modals/modal-held-orders";
import { ModalInvoicePreview } from "./modals/modal-invoice-preview";
import { ModalPayLater } from "./modals/modal-pay-later";
import { ModalShiftHistory } from "./modals/modal-shift-history";
import { ModalPaymentSuccess } from "./modals/modal-payment-success";
import { ModalEditCustomer } from "./modals/modal-edit-customer";
import { ModalConfirmClear } from "./modals/modal-confirm-clear";

export function GotPosDashboard() {
  const dispatch = useAppDispatch();

  // Redux State Selectors
  const cart = useAppSelector((state) => state.cart.items);
  const { discountPercent, taxPercent, serviceFee, orderType, heldOrders } = useAppSelector((state) => state.pos);
  const { activeCategory, searchQuery, stockFilter, activeModal, openDropdown, isFullscreen, selectedPayMethod } = useAppSelector((state) => state.ui);
  const customer = useAppSelector((state) => state.customer.profile);

  // Live Date & Time Hook
  const { currentDate, currentTime } = useLiveClock();

  // Calculations Hook
  const { totalItemCount, subTotal, discountAmount, gstAmount, totalPayable } =
    usePosCalculations(cart, discountPercent, taxPercent, serviceFee);

  // UOM & Variant Configuration Local Modal State
  const [configProduct, setConfigProduct] = useState<Product | null>(null);
  const [selectedUOM, setSelectedUOM] = useState<ProductUOM | null>(null);
  const [selectedVariants, setSelectedVariants] = useState<Record<string, VariantValue>>({});
  const [configQty, setConfigQty] = useState<number>(1);
  const [cashReceived, setCashReceived] = useState<string>("");

  // Invoices History (Static for now)
  const [invoices] = useState<Invoice[]>(INITIAL_INVOICES);

  // Keyboard Shortcuts & Click Outside Handlers
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "F12" || (e.key === "Enter" && (e.ctrlKey || e.metaKey))) {
        e.preventDefault();
        if (cart.length > 0) dispatch(setActiveModal("payment_success"));
      }
      if (e.key === "Escape") {
        dispatch(setActiveModal(null));
        setConfigProduct(null);
        dispatch(setOpenDropdown(null));
      }
    };
    const handleClickOutside = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (!target.closest(".dropdown")) dispatch(setOpenDropdown(null));
    };
    window.addEventListener("keydown", handleKeyDown);
    document.addEventListener("click", handleClickOutside);
    return () => {
      window.removeEventListener("keydown", handleKeyDown);
      document.removeEventListener("click", handleClickOutside);
    };
  }, [cart, dispatch]);

  // Fullscreen toggle
  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
      dispatch(setIsFullscreen(true));
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
        dispatch(setIsFullscreen(false));
      }
    }
  };

  // Filtered Products Memo
  const filteredProducts = useMemo(() => {
    return PRODUCTS.filter((product) => {
      const matchCategory = activeCategory === "all" || product.category === activeCategory;
      const matchSearch =
        searchQuery.trim() === "" ||
        product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        product.sku.toLowerCase().includes(searchQuery.toLowerCase());

      let matchFilter = true;
      if (stockFilter === "instock") matchFilter = product.stockStatus === "instock";
      if (stockFilter === "outofstock") matchFilter = product.stockStatus === "outofstock";
      if (stockFilter === "lowstock") matchFilter = product.stockStatus === "lowstock";

      return matchCategory && matchSearch && matchFilter;
    }).sort((a, b) => {
      if (stockFilter === "price-low") return a.price - b.price;
      if (stockFilter === "price-high") return b.price - a.price;
      return 0;
    });
  }, [activeCategory, searchQuery, stockFilter]);

  // Product Click Handler
  const handleProductClick = (product: Product) => {
    const hasConfig =
      (product.hasVariants && product.variantOptions && product.variantOptions.length > 0) ||
      (product.hasUOM && product.uomList && product.uomList.length > 0);

    if (hasConfig) {
      if (product.hasUOM && product.uomList && product.uomList.length > 0) {
        setSelectedUOM(product.uomList.find((u) => u.isDefault) || product.uomList[0]);
      } else {
        setSelectedUOM(null);
      }

      if (product.hasVariants && product.variantOptions && product.variantOptions.length > 0) {
        const initialVariants: Record<string, VariantValue> = {};
        product.variantOptions.forEach((opt) => {
          if (opt.values.length > 0) initialVariants[opt.name] = opt.values[0];
        });
        setSelectedVariants(initialVariants);
      } else {
        setSelectedVariants({});
      }

      setConfigQty(1);
      setConfigProduct(product);
    } else {
      dispatch(addDirectToCart(product));
    }
  };

  const modalUnitPrice = useMemo(() => {
    if (!configProduct) return 0;
    let price = selectedUOM ? selectedUOM.price : configProduct.price;
    Object.values(selectedVariants).forEach((v) => {
      if (v.priceDelta) price += v.priceDelta;
    });
    return price;
  }, [configProduct, selectedUOM, selectedVariants]);

  const parkCurrentCart = (ref: string, notes: string) => {
    if (cart.length === 0) return;
    const newHeld: HeldOrder = {
      id: `HOLD-${100 + heldOrders.length + 1}`,
      reference: ref.trim() || `Order #${Math.floor(1000 + Math.random() * 9000)}`,
      orderType,
      customer: { ...customer },
      cart: [...cart],
      subTotal,
      discountPercent,
      taxPercent,
      serviceFee,
      totalPayable,
      createdAt: "Just now",
      notes: notes.trim() || "Parked order",
    };
    dispatch(addHeldOrder(newHeld));
    dispatch(clearCart());
    dispatch(setActiveModal(null));
  };

  const restoreHeldOrder = (order: HeldOrder) => {
    dispatch(setCart(order.cart));
    dispatch(setOrderType(order.orderType));
    dispatch(setCustomer(order.customer));
    dispatch(setDiscountPercent(order.discountPercent));
    dispatch(setTaxPercent(order.taxPercent));
    dispatch(setServiceFee(order.serviceFee));
    dispatch(removeHeldOrder(order.id));
    dispatch(setActiveModal(null));
  };

  return (
    <div className="pos-container">
      <PosTopbar
        currentDate={currentDate}
        currentTime={currentTime}
        heldCount={heldOrders.length}
        isFullscreen={isFullscreen}
        onToggleFullscreen={toggleFullscreen}
        onOpenHoldModal={() => dispatch(setActiveModal("hold"))}
        openDropdown={openDropdown}
        onToggleDropdown={(name) =>
          dispatch(setOpenDropdown(openDropdown === name ? null : name))
        }
      />

      <div className="d-flex flex-grow-1 overflow-hidden">
        <PosCategoryRail
          activeCategory={activeCategory}
          onSelectCategory={(c) => dispatch(setActiveCategory(c))}
        />

        <main className="pos-catalog-wrapper">
          <PosCatalogToolbar
            searchQuery={searchQuery}
            onSearchChange={(q) => dispatch(setSearchQuery(q))}
            stockFilter={stockFilter}
            onStockFilterChange={(f) => dispatch(setStockFilter(f))}
            onOpenScanModal={() => dispatch(setActiveModal("upi"))}
          />

          {filteredProducts.length === 0 ? (
            <div className="bg-white rounded-3 border p-5 text-center my-4">
              <img
                src="/assets/no-order-CCjZwO4J.svg"
                alt="No items"
                style={{ width: "120px", opacity: 0.6 }}
                className="mx-auto mb-3"
              />
              <h6 className="fw-bold text-muted">No matching products found</h6>
              <p className="text-muted small mb-3">Try clearing search or filters.</p>
              <button
                type="button"
                className="btn btn-sm btn-outline-primary"
                onClick={() => {
                  dispatch(setSearchQuery(""));
                  dispatch(setActiveCategory("all"));
                  dispatch(setStockFilter("all"));
                }}
              >
                Reset Filters
              </button>
            </div>
          ) : (
            <div className="pos-product-grid">
              {filteredProducts.map((product) => (
                <PosProductCard
                  key={product.id}
                  product={product}
                  cart={cart}
                  onProductClick={handleProductClick}
                  onDirectAdd={(p) => dispatch(addDirectToCart(p))}
                  onUpdateQty={(id, delta) => dispatch(updateQuantity({ itemId: id, delta }))}
                />
              ))}
            </div>
          )}
        </main>

        <aside className="pos-cart-panel">
          <div className="p-3 border-bottom bg-white">
            <div className="d-flex align-items-center justify-content-between mb-2">
              <div className="d-flex align-items-center gap-2">
                <span className="fs-14 fw-bold font-monospace text-body">#GOT-1698</span>
                <span className="badge bg-light border text-muted fs-11">Current Sale</span>
              </div>
              <button
                type="button"
                className="btn btn-sm btn-light border text-danger size-7 rounded d-flex align-items-center justify-content-center p-0"
                onClick={() => dispatch(setActiveModal("delete"))}
              >
                <i className="ri-delete-bin-line fs-13"></i>
              </button>
            </div>

            <div className="d-flex gap-1 bg-light p-1 rounded-2">
              {(["Takeaway", "Dine-in", "Delivery"] as const).map((type) => (
                <button
                  key={type}
                  type="button"
                  className={`btn btn-sm flex-fill py-1 fs-11 fw-semibold rounded-1 border-0 ${
                    orderType === type ? "bg-white text-primary shadow-xs" : "text-muted"
                  }`}
                  onClick={() => dispatch(setOrderType(type))}
                >
                  {type}
                </button>
              ))}
            </div>

            <div className="d-flex align-items-center justify-content-between p-2 mt-2 bg-light bg-opacity-75 rounded-2 border">
              <div className="d-flex align-items-center gap-2 overflow-hidden">
                <img
                  src={customer.avatar}
                  alt="Customer"
                  className="rounded-circle"
                  style={{ width: "28px", height: "28px", objectFit: "cover" }}
                />
                <div className="overflow-hidden lh-1">
                  <span className="fs-12 fw-semibold text-truncate d-block">{customer.name}</span>
                  <small className="text-muted fs-10">{customer.points.toLocaleString()} Loyalty Pts</small>
                </div>
              </div>
              <button
                type="button"
                className="btn btn-sm btn-light border px-2 py-0.5 fs-11 rounded"
                onClick={() => dispatch(setActiveModal("edit_customer"))}
              >
                Edit
              </button>
            </div>
          </div>

          <div className="pos-cart-items-container">
            {cart.length === 0 ? (
              <div className="text-center py-5">
                <img src="/assets/no-order-CCjZwO4J.svg" alt="Empty" style={{ width: "90px", opacity: 0.5 }} className="mx-auto mb-2" />
                <p className="text-muted fs-12 mb-0">Cart is empty</p>
                <small className="text-muted fs-11">Select products from the catalog to add</small>
              </div>
            ) : (
              <div>
                <div className="d-flex align-items-center justify-content-between mb-2">
                  <span className="fs-11 fw-semibold text-muted text-uppercase">Items ({totalItemCount})</span>
                  <a href="#!" onClick={() => dispatch(setActiveModal("delete"))} className="text-danger fs-11 text-decoration-none">Remove All</a>
                </div>
                {cart.map((item) => (
                  <PosCartItem
                    key={item.id}
                    item={item}
                    onUpdateQty={(id, delta) => dispatch(updateQuantity({ itemId: id, delta }))}
                    onRemoveItem={(id) => dispatch(removeItem(id))}
                  />
                ))}
              </div>
            )}
          </div>

          {cart.length > 0 && (
            <PosSummaryCard
              subTotal={subTotal}
              discountPercent={discountPercent}
              onDiscountChange={(v) => dispatch(setDiscountPercent(v))}
              discountAmount={discountAmount}
              taxPercent={taxPercent}
              onTaxChange={(v) => dispatch(setTaxPercent(v))}
              gstAmount={gstAmount}
              serviceFee={serviceFee}
              onServiceFeeChange={(v) => dispatch(setServiceFee(v))}
              totalPayable={totalPayable}
              totalItemCount={totalItemCount}
            />
          )}

          {cart.length > 0 && (
            <PosPaymentGrid
              selectedMethod={selectedPayMethod}
              onSelectMethod={(m) => dispatch(setSelectedPayMethod(m))}
              onOpenModal={(m) => dispatch(setActiveModal(m))}
              totalPayable={totalPayable}
            />
          )}

          <PosBottomToolbar heldCount={heldOrders.length} onOpenModal={(m) => dispatch(setActiveModal(m))} />
        </aside>
      </div>

      {(activeModal || configProduct) && (
        <div className="modal-backdrop fade show" style={{ zIndex: 1055, backgroundColor: "rgba(15, 23, 42, 0.6)" }} onClick={() => { dispatch(setActiveModal(null)); setConfigProduct(null); }}></div>
      )}

      {configProduct && (
        <ModalConfigProduct
          product={configProduct}
          selectedUOM={selectedUOM}
          onSelectUOM={setSelectedUOM}
          selectedVariants={selectedVariants}
          onSelectVariant={(optName, val) => setSelectedVariants({ ...selectedVariants, [optName]: val })}
          quantity={configQty}
          onUpdateQuantity={(delta) => setConfigQty(Math.max(1, configQty + delta))}
          modalUnitPrice={modalUnitPrice}
          onAddToCart={() => {
            dispatch(addConfiguredToCart({ product: configProduct, quantity: configQty, selectedUOM, selectedVariants }));
            setConfigProduct(null);
          }}
          onClose={() => setConfigProduct(null)}
        />
      )}

      {activeModal === "cash" && (
        <ModalCashTender
          totalPayable={totalPayable}
          cashReceived={cashReceived}
          onCashChange={setCashReceived}
          cashChange={Math.max(0, (parseFloat(cashReceived) || 0) - totalPayable)}
          onConfirm={() => dispatch(setActiveModal("payment_success"))}
          onClose={() => dispatch(setActiveModal(null))}
        />
      )}

      {activeModal === "card" && (
        <ModalCardTerminal totalPayable={totalPayable} onConfirm={() => dispatch(setActiveModal("payment_success"))} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "upi" && (
        <ModalUpiQr totalPayable={totalPayable} onConfirm={() => dispatch(setActiveModal("payment_success"))} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "bank" && (
        <ModalBankTransfer totalPayable={totalPayable} onConfirm={() => dispatch(setActiveModal("payment_success"))} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "split" && (
        <ModalSplitTender totalPayable={totalPayable} onConfirm={() => dispatch(setActiveModal("payment_success"))} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "hold" && (
        <ModalHeldOrders
          heldOrders={heldOrders}
          cartCount={totalItemCount}
          totalPayable={totalPayable}
          onRestoreOrder={restoreHeldOrder}
          onDeleteOrder={(id) => dispatch(removeHeldOrder(id))}
          onParkCurrentCart={parkCurrentCart}
          onClose={() => dispatch(setActiveModal(null))}
        />
      )}
      {activeModal === "invoice" && (
        <ModalInvoicePreview cart={cart} totalPayable={totalPayable} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "pay_later" && (
        <ModalPayLater customerName={customer.name} onConfirm={() => { alert("Saved!"); dispatch(setActiveModal(null)); }} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "history" && (
        <ModalShiftHistory invoices={invoices} onViewInvoice={() => dispatch(setActiveModal("invoice"))} onClose={() => dispatch(setActiveModal(null))} />
      )}
      {activeModal === "payment_success" && (
        <ModalPaymentSuccess
          totalPayable={totalPayable}
          selectedPayMethod={selectedPayMethod}
          currentDate={currentDate}
          onPrint={() => window.print()}
          onNextSale={() => { dispatch(clearCart()); dispatch(setActiveModal(null)); }}
          onClose={() => dispatch(setActiveModal(null))}
        />
      )}
      {activeModal === "edit_customer" && (
        <ModalEditCustomer
          customer={customer}
          onSaveCustomer={(updated) => { dispatch(setCustomer(updated)); dispatch(setActiveModal(null)); }}
          onClose={() => dispatch(setActiveModal(null))}
        />
      )}
      {activeModal === "delete" && (
        <ModalConfirmClear
          onConfirm={() => { dispatch(clearCart()); dispatch(setActiveModal(null)); }}
          onClose={() => dispatch(setActiveModal(null))}
        />
      )}
    </div>
  );
}
