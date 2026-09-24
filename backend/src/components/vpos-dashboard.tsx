"use client";

import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  completeCashPosSale,
  completePaymentPosSale,
  createPosSaleId,
  deleteHeldPosSale,
  fetchPosBranches,
  fetchPosCategories,
  fetchPosProducts,
  fetchPosCatalog,
  fetchPosCustomers,
  fetchPosProduct,
  fetchPosSales,
  getOrCreatePosClientToken,
  holdPosSale,
  pricePosCart,
  restoreHeldPosSale,
  saveCurrentPosSale,
  syncPosDisplay,
} from "@/lib/pos-api";
import { getEcho } from "@/lib/echo";
import { AppliedPromotion, CartItem, CashTenderInput, Category, CurrencyInfo, Customer, CustomerPriceList, DiscountType, Product, ProductUOM, ProductVariant, VariantValue, HeldOrder, Invoice, PosBranch, PosCartPricing, PosCompanyInfo, PosSaleSnapshot } from "@/types/pos-types";

// Custom Hooks & Redux
import { useLiveClock } from "@/hooks/use-live-clock";
import { usePosCalculations } from "@/hooks/use-pos-calculations";
import { useAppDispatch, useAppSelector } from "@/store/hooks";
import {
  addDirectToCart,
  addPromotionReward,
  addConfiguredToCart,
  updateQuantity,
  removeItem,
  clearCart,
  setCart
} from "@/store/slices/cartSlice";
import {
  setDiscount,
  setDiscountValue,
  setTaxPercent,
  setServiceFee,
  setOrderType,
  addHeldOrder,
  removeHeldOrder,
  setHeldOrders
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
import { emptyCustomer, setCustomer } from "@/store/slices/customerSlice";

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
import { ModalHeldOrders } from "./modals/modal-held-orders";
import { ModalInvoicePreview } from "./modals/modal-invoice-preview";
import { ModalPayLater } from "./modals/modal-pay-later";
import { ModalShiftHistory } from "./modals/modal-shift-history";
import { ModalPaymentSuccess } from "./modals/modal-payment-success";
import { ModalEditCustomer } from "./modals/modal-edit-customer";
import { ModalConfirmClear } from "./modals/modal-confirm-clear";
import { ModalDisplayPair } from "./modals/modal-display-pair";
import { CurrencyProvider } from "@/lib/currency";
import { useTranslation } from "@/lib/i18n/i18n";

const selectedBranchStorageKey = "vpos.selected-branch-id";

function generateInvoiceNumber(date = new Date()): string {
  const pad = (value: number, length = 2) => String(value).padStart(length, "0");
  const datePart = [
    String(date.getFullYear()).slice(-2),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
  ].join("");
  const timePart = [pad(date.getHours()), pad(date.getMinutes()), pad(date.getSeconds())].join("");

  return `VP-${datePart}-${timePart}-${pad(date.getMilliseconds(), 3)}`;
}

export function VPosDashboard({ centralUrl }: { centralUrl?: string } = {}) {
  const dispatch = useAppDispatch();
  const { t } = useTranslation();

  // Redux State Selectors
  const cart = useAppSelector((state) => state.cart.items);
  const { discountType, discountValue, taxPercent, serviceFee, orderType, heldOrders } = useAppSelector((state) => state.pos);
  const { activeCategory, searchQuery, stockFilter, activeModal, openDropdown, isFullscreen, selectedPayMethod } = useAppSelector((state) => state.ui);
  const customer = useAppSelector((state) => state.customer.profile) || emptyCustomer;
  const [currency, setCurrency] = useState<CurrencyInfo | null>(null);
  const [currencies, setCurrencies] = useState<CurrencyInfo[]>([]);
  const [company, setCompany] = useState<PosCompanyInfo | null>(null);

  const [currencyRates, setCurrencyRates] = useState<Record<string, number>>({});
  const [promotionPricingResult, setPromotionPricingResult] = useState<{
    cartKey: string;
    pricing: PosCartPricing;
  } | null>(null);
  const [promotionPricingErrorResult, setPromotionPricingErrorResult] = useState<{
    cartKey: string;
    message: string;
  } | null>(null);
  const cartPricingKey = useMemo(() => JSON.stringify(cart.map((item) => ({
    id: item.product.id,
    variantId: item.selectedVariant?.id || null,
    uomId: item.selectedUOM?.id || null,
    quantity: item.quantity,
  }))), [cart]);
  const promotionPricing = promotionPricingResult?.cartKey === cartPricingKey
    ? promotionPricingResult.pricing
    : null;
  const promotionPricingError = promotionPricingErrorResult?.cartKey === cartPricingKey
    ? promotionPricingErrorResult.message
    : null;
  const isPromotionPricing = cart.length > 0 && !promotionPricing && !promotionPricingError;
  const promotionDiscountAmount = useMemo(() => {
    if (!promotionPricing?.applied_promotion) return 0;

    return Number(Number(promotionPricing.discount_total || 0).toFixed(currency?.decimalPlaces ?? 2));
  }, [currency, promotionPricing]);
  const appliedPromotion = useMemo<AppliedPromotion | null>(() => (
    promotionPricing?.applied_promotion
      ? { ...promotionPricing.applied_promotion, savings: promotionDiscountAmount }
      : null
  ), [promotionDiscountAmount, promotionPricing]);

  // Live Date & Time Hook
  const { currentDate, currentTime } = useLiveClock();

  // Calculations Hook
  const calculatedTotals = usePosCalculations(
    cart,
    discountType,
    discountValue,
    taxPercent,
    serviceFee,
    currency,
    currencyRates
  );
  const totalItemCount = calculatedTotals.totalItemCount;
  const subTotal = promotionPricing
    ? Number(promotionPricing.subtotal || 0)
    : calculatedTotals.subTotal;
  const manualDiscountAmount = subTotal > 0
    ? Math.min(
      subTotal,
      discountType === "percentage"
        ? subTotal * Math.min(100, Math.max(0, discountValue || 0)) / 100
        : Math.max(0, discountValue || 0)
    )
    : 0;
  const discountAmount = Math.min(subTotal, manualDiscountAmount + promotionDiscountAmount);
  const gstAmount = subTotal > 0
    ? (subTotal - discountAmount) * ((taxPercent || 0) / 100)
    : 0;
  const totalPayable = Math.max(0, subTotal - discountAmount + gstAmount + (serviceFee || 0));

  // UOM & Variant Configuration Local Modal State
  const [configProduct, setConfigProduct] = useState<Product | null>(null);
  const [selectedUOM, setSelectedUOM] = useState<ProductUOM | null>(null);
  const [selectedVariants, setSelectedVariants] = useState<Record<string, VariantValue>>({});
  const [configQty, setConfigQty] = useState<number>(1);
  const [categories, setCategories] = useState<Category[]>([
    { id: "all", name: "All" },
  ]);
  const [branches, setBranches] = useState<PosBranch[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState("");
  const [isBranchLoading, setIsBranchLoading] = useState(true);
  const [products, setProducts] = useState<Product[]>([]);
  const [isCatalogLoading, setIsCatalogLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [hasMoreProducts, setHasMoreProducts] = useState(true);
  const [totalProductsCount, setTotalProductsCount] = useState<number | null>(null);
  const [catalogError, setCatalogError] = useState<string | null>(null);
  const [catalogVersion, setCatalogVersion] = useState(0);

  const prevCatalogFilterRef = useRef({
    activeCategory,
    searchQuery,
    stockFilter,
    selectedBranchId,
  });

  // Optimistically deduct stock in local React state immediately on successful sale (0ms feedback)
  const deductStockOptimistically = useCallback((soldItems: CartItem[]) => {
    if (!soldItems || soldItems.length === 0) return;

    setProducts((prevProducts) => {
      return prevProducts.map((product) => {
        // Find all cart lines that belong to this product
        const relevantLines = soldItems.filter((item) => {
          const lineProdId = String(item.product?.id ?? item.id);
          const prodId = String(product.id);
          const lineSku = String(item.product?.sku ?? "");
          const prodSku = String(product.sku ?? "");

          if (lineProdId === prodId) return true;
          if (lineSku && prodSku && lineSku.toLowerCase() === prodSku.toLowerCase()) return true;

          if (
            item.selectedVariant &&
            product.variants?.some(
              (v) =>
                String(v.id) === String(item.selectedVariant?.id) ||
                (v.sku && item.selectedVariant?.sku && v.sku.toLowerCase() === item.selectedVariant.sku.toLowerCase())
            )
          ) {
            return true;
          }

          return false;
        });

        if (relevantLines.length === 0) return product;

        // 1. Update variant stocks if product has variants
        let updatedVariants = product.variants;
        if (product.variants && product.variants.length > 0) {
          updatedVariants = product.variants.map((variant) => {
            const variantLines = relevantLines.filter((line) => {
              if (!line.selectedVariant) return false;
              return (
                String(line.selectedVariant.id) === String(variant.id) ||
                (line.selectedVariant.sku && variant.sku && line.selectedVariant.sku.toLowerCase() === variant.sku.toLowerCase())
              );
            });

            if (variantLines.length === 0) return variant;

            const soldQty = variantLines.reduce((acc, l) => acc + (Number(l.quantity) || 0), 0);
            const newVarStock = Math.max(0, (variant.stock ?? 0) - soldQty);

            return {
              ...variant,
              stock: newVarStock,
            };
          });
        }

        // 2. Update base product stock factoring UOM conversion factors
        let totalBaseDeducted = 0;
        for (const line of relevantLines) {
          const factor = Number(line.selectedUOM?.conversionFactorToBase) || 1;
          totalBaseDeducted += (Number(line.quantity) || 0) * factor;
        }

        const currentStock = typeof product.stock === "number" ? product.stock : 0;
        const newParentStock = Math.max(0, currentStock - totalBaseDeducted);

        // 3. Update stockStatus
        let newStockStatus = product.stockStatus;
        if (product.stockControl !== false) {
          if (newParentStock <= 0) {
            newStockStatus = "outofstock";
          } else if (newParentStock <= 10) {
            newStockStatus = "lowstock";
          } else {
            newStockStatus = "instock";
          }
        }

        return {
          ...product,
          stock: newParentStock,
          stockStatus: newStockStatus,
          variants: updatedVariants,
        };
      });
    });
  }, []);

  const catalogWrapperRef = useRef<HTMLElement | null>(null);
  const sentinelRef = useRef<HTMLDivElement | null>(null);

  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [viewedInvoice, setViewedInvoice] = useState<Invoice | null>(null);
  const [isPaidInvoicePreview, setIsPaidInvoicePreview] = useState(false);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [priceLists, setPriceLists] = useState<CustomerPriceList[]>([]);
  const [isCustomerLoading, setIsCustomerLoading] = useState(true);
  const [customerError, setCustomerError] = useState<string | null>(null);
  const [invoiceNumber, setInvoiceNumber] = useState("");
  const [saleId, setSaleId] = useState("");
  const [posClientToken, setPosClientToken] = useState("");
  const [isSaleHydrated, setIsSaleHydrated] = useState(false);
  const [salePersistenceError, setSalePersistenceError] = useState<string | null>(null);
  const [isDisplayModalOpen, setIsDisplayModalOpen] = useState(false);
  const [liveTender, setLiveTender] = useState<{ tendered: number; change: number } | null>(null);
  const handleTenderChange = useCallback((summary: { tendered: number; change: number }) => {
    setLiveTender((prev) => {
      if (prev && prev.tendered === summary.tendered && prev.change === summary.change) {
        return prev;
      }
      return summary;
    });
  }, []);
  const [lastPaymentDetails, setLastPaymentDetails] = useState<{
    method: "cash" | "card" | "qr" | "bank" | "split" | "later";
    amount: number;
    tendered?: number;
    change?: number;
    provider?: string;
    reference?: string;
    invoiceNumber?: string;
  } | null>(null);
  const currencyError = useMemo(() => {
    if (cart.length === 0) return null;
    if (!currency) return "Loading tenant currency settings...";

    const missingRate = cart.some((item) => {
      const itemCode = item.product.currency?.code || currency.code;
      return itemCode !== currency.code && !(Number(currencyRates[itemCode]) > 0);
    });

    return missingRate
      ? "An exchange rate is missing for one or more item currencies. Configure it in Exchange Rates before payment."
      : null;
  }, [cart, currency, currencyRates]);
  // Shared sale totals use the tenant base currency. Individual products and
  // cart lines keep their own currency metadata.
  const displayCurrency = currency;

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      const generatedNumber = generateInvoiceNumber();
      setInvoiceNumber(generatedNumber);
      setSaleId(createPosSaleId());
    }, 0);

    return () => window.clearTimeout(timeoutId);
  }, []);

  const startNewInvoice = () => {
    setInvoiceNumber(generateInvoiceNumber());
    setSaleId(createPosSaleId());
  };

  useEffect(() => {
    let cancelled = false;
    const clientToken = getOrCreatePosClientToken();
    setPosClientToken(clientToken);

    fetchPosSales(clientToken)
      .then(({ current, heldOrders: savedHeldOrders, invoices: savedInvoices }) => {
        if (cancelled) return;

        dispatch(setHeldOrders(savedHeldOrders));
        setInvoices(savedInvoices);
        if (!current) return;

        setSaleId(current.saleId);
        setInvoiceNumber(current.invoiceNumber || generateInvoiceNumber());
        dispatch(setCart(Array.isArray(current.cart) ? current.cart : []));
        dispatch(setOrderType(current.orderType || "Takeaway"));
        dispatch(setCustomer(current.customer || emptyCustomer));
        dispatch(setDiscount({
          type: current.discountType || "percentage",
          value: Number(current.discountValue || 0),
        }));
        dispatch(setTaxPercent(Number(current.taxPercent || 0)));
        dispatch(setServiceFee(Number(current.serviceFee || 0)));
      })
      .catch((error: unknown) => {
        if (cancelled) return;
        setSalePersistenceError(
          error instanceof Error ? error.message : "Unable to restore saved POS data"
        );
      })
      .finally(() => {
        if (!cancelled) {
          setPosClientToken(clientToken);
          setIsSaleHydrated(true);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [dispatch]);

  const currentSaleSnapshot = useMemo<PosSaleSnapshot>(() => ({
    saleId,
    invoiceNumber,
    orderType,
    customer,
    cart,
    discountType,
    discountValue,
    taxPercent,
    serviceFee,
    appliedPromotion,
    promotionDiscountAmount,
  }), [
    appliedPromotion,
    cart,
    customer,
    discountType,
    discountValue,
    invoiceNumber,
    orderType,
    promotionDiscountAmount,
    saleId,
    serviceFee,
    taxPercent,
  ]);

  useEffect(() => {
    if (!isSaleHydrated || !posClientToken || !saleId || !invoiceNumber) return;

    const timeoutId = window.setTimeout(() => {
      saveCurrentPosSale(posClientToken, currentSaleSnapshot)
        .then(() => setSalePersistenceError(null))
        .catch((error: unknown) => {
          setSalePersistenceError(
            error instanceof Error ? error.message : "Unable to save the current sale"
          );
        });
    }, 600);

    return () => window.clearTimeout(timeoutId);
  }, [currentSaleSnapshot, invoiceNumber, isSaleHydrated, posClientToken, saleId]);

  // Real-time synchronization to Customer-Facing Display over Soketi WebSocket
  useEffect(() => {
    // Do not publish the Redux defaults while the saved sale is still loading.
    // Otherwise a POS page refresh briefly broadcasts an empty cart and replaces
    // the last good customer-display state with `idle`.
    if (!isSaleHydrated || !posClientToken) return;

    const timer = setTimeout(() => {
      let action: "cart_updated" | "payment_pending" | "payment_success" | "idle" = "cart_updated";
      let paymentData: any = null;

      if (activeModal === "upi") {
        action = "payment_pending";
        paymentData = {
          method: "qr",
          amount: totalPayable,
          qrCodeUrl: "/assets/qr-CvtWFzmv.png",
        };
      } else if (activeModal === "bank") {
        action = "payment_pending";
        paymentData = {
          method: "bank",
          amount: totalPayable,
          bankName: "Bank Transfer",
        };
      } else if (activeModal === "card") {
        action = "payment_pending";
        paymentData = {
          method: "card",
          amount: totalPayable,
          provider: selectedPayMethod || "Card Terminal",
        };
      } else if (activeModal === "cash") {
        action = "payment_pending";
        paymentData = {
          method: "cash",
          amount: totalPayable,
          tendered: liveTender?.tendered || 0,
          change: liveTender?.change || 0,
        };
      } else if (activeModal === "pay_later") {
        action = "payment_pending";
        paymentData = {
          method: "later",
          amount: totalPayable,
        };
      } else if (activeModal === "payment_success") {
        action = "payment_success";
        paymentData = lastPaymentDetails || {
          method: (selectedPayMethod?.toLowerCase() === "card"
            ? "card"
            : selectedPayMethod?.toLowerCase() === "upi"
              ? "qr"
              : selectedPayMethod?.toLowerCase() === "bank"
                ? "bank"
                : "cash") as any,
          amount: totalPayable,
          change: liveTender?.change || 0,
          tendered: liveTender?.tendered || totalPayable,
          invoiceNumber,
        };
      } else if (cart.length === 0) {
        action = "idle";
      }

      syncPosDisplay({
        token: posClientToken,
        action,
        cart,
        totals: {
          subTotal,
          discountAmount,
          gstAmount,
          totalPayable: totalPayable > 0 ? totalPayable : (lastPaymentDetails?.amount ?? totalPayable),
          totalItemCount,
          currencySymbol: currency?.symbol || "$",
          currencyCode: currency?.code || "USD",
        },
        payment: paymentData,
        customer: customer ? { id: customer.id, name: customer.name, phone: customer.phone } : null,
        branch: {
          id: selectedBranchId,
          name: branches.find((b) => b.id === selectedBranchId)?.name || "Main Branch",
        },
      });
    }, 120);

    return () => clearTimeout(timer);
  }, [
    isSaleHydrated,
    posClientToken,
    cart,
    subTotal,
    discountAmount,
    gstAmount,
    totalPayable,
    totalItemCount,
    activeModal,
    customer,
    selectedBranchId,
    branches,
    currency,
    liveTender,
    lastPaymentDetails,
    selectedPayMethod,
    invoiceNumber,
  ]);

  // Fetch Categories once (or on catalog reload)
  useEffect(() => {
    let cancelled = false;

    fetchPosCategories()
      .then((cats) => {
        if (!cancelled) setCategories(cats);
      })
      .catch((error: unknown) => {
        console.error("Failed to load categories:", error);
      });

    return () => {
      cancelled = true;
    };
  }, [catalogVersion]);

  // Load active branches and restore the cashier's last selection.
  useEffect(() => {
    let cancelled = false;

    fetchPosBranches()
      .then((availableBranches) => {
        if (cancelled) return;

        setBranches(availableBranches);
        const savedBranchId = window.localStorage.getItem(selectedBranchStorageKey);
        const nextBranchId = savedBranchId === "all"
          || availableBranches.some((branch) => branch.id === savedBranchId)
          ? savedBranchId || "all"
          : "all";

        setSelectedBranchId(nextBranchId);
        window.localStorage.setItem(selectedBranchStorageKey, nextBranchId);
      })
      .catch((error: unknown) => {
        console.error("Failed to load branches:", error);
        if (!cancelled) {
          setBranches([]);
          setSelectedBranchId("all");
        }
      })
      .finally(() => {
        if (!cancelled) setIsBranchLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  // Fetch Products (Initial 20 items per request)
  useEffect(() => {
    if (isBranchLoading) return;

    let cancelled = false;

    const filterChanged =
      prevCatalogFilterRef.current.activeCategory !== activeCategory ||
      prevCatalogFilterRef.current.searchQuery !== searchQuery ||
      prevCatalogFilterRef.current.stockFilter !== stockFilter ||
      prevCatalogFilterRef.current.selectedBranchId !== selectedBranchId;

    prevCatalogFilterRef.current = { activeCategory, searchQuery, stockFilter, selectedBranchId };

    // Only set full-screen loading spinner if filters changed or initial load (no products yet)
    if (filterChanged || products.length === 0) {
      setIsCatalogLoading(true);
    }
    setCatalogError(null);
    setCurrentPage(1);

    const sort =
      stockFilter === "price-low"
        ? "price_asc"
        : stockFilter === "price-high"
          ? "price_desc"
          : "name_asc";

    const isSearching = Boolean(searchQuery && searchQuery.trim());
    // When searching, search all products across all categories (not restricted to current category)
    const categoryId = isSearching
      ? undefined
      : activeCategory === "all"
        ? undefined
        : activeCategory;

    fetchPosProducts({
      page: 1,
      perPage: 20,
      categoryId,
      branchId: selectedBranchId && selectedBranchId !== "all" ? selectedBranchId : undefined,
      search: isSearching ? searchQuery.trim() : undefined,
      sort,
    })
      .then((result) => {
        if (cancelled) return;
        setProducts(result.products);
        setCurrentPage(1);
        setHasMoreProducts(result.hasMore);
        setTotalProductsCount(result.total);
      })
      .catch((error: unknown) => {
        if (cancelled) return;
        setCatalogError(
          error instanceof Error ? error.message : "Unable to load the product catalog"
        );
      })
      .finally(() => {
        if (!cancelled) setIsCatalogLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [activeCategory, searchQuery, stockFilter, catalogVersion, isBranchLoading, selectedBranchId]);

  // Real-time stock synchronization via WebSocket (Echo / Soketi)
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    const handleStockUpdated = () => {
      // Background re-fetch catalog without resetting isCatalogLoading to true
      setCatalogVersion((version) => version + 1);
    };

    const stockChannel = echo.channel("pos-stock");
    stockChannel.listen(".PosStockUpdated", handleStockUpdated);

    let tokenChannel: ReturnType<typeof echo.channel> | null = null;
    if (posClientToken) {
      tokenChannel = echo.channel(`pos-display.${posClientToken}`);
      tokenChannel.listen(".PosStockUpdated", handleStockUpdated);
    }

    return () => {
      stockChannel.stopListening(".PosStockUpdated", handleStockUpdated);
      if (tokenChannel) {
        tokenChannel.stopListening(".PosStockUpdated", handleStockUpdated);
      }
    };
  }, [posClientToken]);

  // Re-sync catalog when returning to tab/window
  useEffect(() => {
    let lastSyncTime = Date.now();
    const handleVisibilityOrFocus = () => {
      if (typeof document !== "undefined" && document.visibilityState === "visible") {
        if (Date.now() - lastSyncTime > 15000) {
          lastSyncTime = Date.now();
          setCatalogVersion((v) => v + 1);
        }
      }
    };

    window.addEventListener("focus", handleVisibilityOrFocus);
    document.addEventListener("visibilitychange", handleVisibilityOrFocus);

    return () => {
      window.removeEventListener("focus", handleVisibilityOrFocus);
      document.removeEventListener("visibilitychange", handleVisibilityOrFocus);
    };
  }, []);

  // Load More Products (20 items per request on scroll)
  const loadMoreProducts = useCallback(() => {
    if (isLoadingMore || isCatalogLoading || !hasMoreProducts) return;

    setIsLoadingMore(true);
    const nextPage = currentPage + 1;
    const sort =
      stockFilter === "price-low"
        ? "price_asc"
        : stockFilter === "price-high"
          ? "price_desc"
          : "name_asc";

    const isSearching = Boolean(searchQuery && searchQuery.trim());
    const categoryId = isSearching
      ? undefined
      : activeCategory === "all"
        ? undefined
        : activeCategory;

    fetchPosProducts({
      page: nextPage,
      perPage: 20,
      categoryId,
      branchId: selectedBranchId && selectedBranchId !== "all" ? selectedBranchId : undefined,
      search: isSearching ? searchQuery.trim() : undefined,
      sort,
    })
      .then((result) => {
        setProducts((prev) => {
          const existingIds = new Set(prev.map((p) => p.id));
          const newItems = result.products.filter((p) => !existingIds.has(p.id));
          return [...prev, ...newItems];
        });
        setCurrentPage(result.page);
        setHasMoreProducts(result.hasMore);
        setTotalProductsCount(result.total);
      })
      .catch((error: unknown) => {
        console.error("Failed to load more products:", error);
      })
      .finally(() => {
        setIsLoadingMore(false);
      });
  }, [
    isLoadingMore,
    isCatalogLoading,
    hasMoreProducts,
    currentPage,
    stockFilter,
    activeCategory,
    searchQuery,
    selectedBranchId,
  ]);

  // IntersectionObserver to automatically load more on scroll
  useEffect(() => {
    const sentinel = sentinelRef.current;
    const container = catalogWrapperRef.current;
    if (!sentinel || !hasMoreProducts || isCatalogLoading || isLoadingMore) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          loadMoreProducts();
        }
      },
      {
        root: container,
        rootMargin: "250px",
        threshold: 0.1,
      }
    );

    observer.observe(sentinel);

    return () => {
      observer.disconnect();
    };
  }, [hasMoreProducts, isCatalogLoading, isLoadingMore, loadMoreProducts]);

  const handleCatalogScroll = (e: React.UIEvent<HTMLElement>) => {
    const { scrollTop, scrollHeight, clientHeight } = e.currentTarget;
    if (scrollHeight - scrollTop - clientHeight < 250) {
      if (!isLoadingMore && !isCatalogLoading && hasMoreProducts) {
        loadMoreProducts();
      }
    }
  };

  // Saved carts may contain an older product snapshot. Refresh its currency
  // metadata from the live catalog without changing the saved sale price.
  useEffect(() => {
    if (!isSaleHydrated || products.length === 0 || cart.length === 0) return;

    const productsById = new Map(products.map((product) => [product.id, product]));
    let changed = false;
    const synchronizedCart = cart.map((item) => {
      const catalogProduct = productsById.get(item.product.id);
      if (!catalogProduct) return item;

      const currentCurrency = item.product.currency;
      const catalogCurrency = catalogProduct.currency;
      const currencyChanged = catalogCurrency
        ? currentCurrency?.code !== catalogCurrency.code ||
        currentCurrency.symbol !== catalogCurrency.symbol ||
        currentCurrency.decimalPlaces !== catalogCurrency.decimalPlaces
        : false;
      const catalogUom = item.selectedUOM
        ? catalogProduct.uomList?.find((uom) =>
          (item.selectedUOM?.id && uom.id === item.selectedUOM.id) ||
          (item.selectedUOM?.code && uom.code === item.selectedUOM.code) ||
          uom.name === item.selectedUOM?.name
        )
        : undefined;
      const uomChanged = Boolean(catalogUom) && (
        item.selectedUOM?.id !== catalogUom?.id ||
        item.selectedUOM?.code !== catalogUom?.code
      );

      const catalogVariant = item.selectedVariant && catalogProduct.variants
        ? catalogProduct.variants.find((v) =>
          v.id === item.selectedVariant?.id ||
          (item.selectedVariant?.sku && v.sku === item.selectedVariant.sku) ||
          (
            v.optionValueIds.length === item.selectedVariant?.optionValueIds?.length &&
            item.selectedVariant?.optionValueIds?.every((id) => v.optionValueIds.includes(id))
          )
        )
        : undefined;
      const variantChanged = Boolean(catalogVariant) && (
        item.selectedVariant?.id !== catalogVariant?.id ||
        item.selectedVariant?.stock !== catalogVariant?.stock ||
        item.selectedVariant?.sku !== catalogVariant?.sku
      );
      const catalogUnitPrice = catalogVariant?.price
        ?? catalogUom?.price
        ?? catalogProduct.price;
      const priceChanged = item.unitPrice !== catalogUnitPrice;
      const productChanged =
        item.product.stock !== catalogProduct.stock ||
        item.product.stockControl !== catalogProduct.stockControl ||
        currencyChanged;

      if (!productChanged && !uomChanged && !variantChanged && !priceChanged) return item;

      changed = true;
      let updatedQuantity = item.quantity;
      if (catalogProduct.stockControl !== false) {
        if (catalogVariant) {
          updatedQuantity = Math.min(updatedQuantity, Math.max(0, catalogVariant.stock));
        } else if (typeof catalogProduct.stock === "number") {
          const factor = Number(catalogUom?.conversionFactorToBase ?? item.selectedUOM?.conversionFactorToBase ?? 1) || 1;
          const maxAllowed = Math.max(0, Math.floor(catalogProduct.stock / factor));
          updatedQuantity = Math.min(updatedQuantity, maxAllowed);
        }
      }

      return {
        ...item,
        quantity: updatedQuantity > 0 ? updatedQuantity : item.quantity,
        product: {
          ...item.product,
          stock: catalogProduct.stock,
          stockControl: catalogProduct.stockControl,
          currency: catalogProduct.currency || item.product.currency,
        },
        selectedUOM: catalogUom || item.selectedUOM,
        selectedVariant: catalogVariant || item.selectedVariant,
        unitPrice: catalogUnitPrice,
      };
    });

    if (changed) dispatch(setCart(synchronizedCart));
  }, [cart, dispatch, isSaleHydrated, products]);

  useEffect(() => {
    let cancelled = false;

    fetchPosCustomers()
      .then((data) => {
        if (cancelled) return;
        setCustomers(data.customers);
        setPriceLists(data.priceLists);
        setCurrency(data.currency);
        setCurrencies(data.currencies);
        setCurrencyRates(data.currencyRates);
        setCompany(data.company);
      })
      .catch((error: unknown) => {
        if (cancelled) return;
        setCustomerError(
          error instanceof Error ? error.message : "Unable to load customers"
        );
      })
      .finally(() => {
        if (!cancelled) setIsCustomerLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (cart.length === 0) return;

    const controller = new AbortController();
    const resetTimeoutId = window.setTimeout(() => {
      setPromotionPricingResult((current) =>
        current?.cartKey === cartPricingKey ? null : current
      );
      setPromotionPricingErrorResult((current) =>
        current?.cartKey === cartPricingKey ? null : current
      );
    }, 0);

    const timeoutId = window.setTimeout(() => {
      pricePosCart(cart, controller.signal)
        .then((pricing) => {
          if (controller.signal.aborted) return;
          setPromotionPricingResult({ cartKey: cartPricingKey, pricing });
          setPromotionPricingErrorResult(null);
        })
        .catch((error: unknown) => {
          if (controller.signal.aborted) return;
          setPromotionPricingResult(null);
          setPromotionPricingErrorResult({
            cartKey: cartPricingKey,
            message: error instanceof Error ? error.message : "Unable to check promotions",
          });
        });
    }, 250);

    return () => {
      window.clearTimeout(resetTimeoutId);
      window.clearTimeout(timeoutId);
      controller.abort();
    };
  }, [cart, cartPricingKey]);

  useEffect(() => {
    const reward = promotionPricing?.auto_add_items?.[0];

    if (!reward) return;

    let cancelled = false;

    const addReward = async () => {
      try {
        const product = products.find((candidate) => Number(candidate.id) === reward.item_id)
          || await fetchPosProduct(reward.item_id);

        if (cancelled) return;

        dispatch(addPromotionReward({
          product,
          quantity: reward.quantity,
          promotionId: reward.promotion_id,
          promotionName: reward.promotion_name,
          uomId: reward.uom_id,
        }));
      } catch (error: unknown) {
        if (cancelled) return;
        setPromotionPricingErrorResult({
          cartKey: cartPricingKey,
          message: error instanceof Error ? error.message : "Unable to add the free promotion item",
        });
      }
    };

    void addReward();

    return () => {
      cancelled = true;
    };
  }, [cartPricingKey, dispatch, products, promotionPricing]);

  useEffect(() => {
    if (!promotionPricing) return;

    const activeBogoPromotionId = promotionPricing.applied_promotion?.type === "bogo"
      ? promotionPricing.applied_promotion.id
      : null;

    cart
      .filter((item) => item.promotionReward?.promotionId !== activeBogoPromotionId)
      .forEach((item) => {
        if (item.promotionReward) dispatch(removeItem(item.id));
      });
  }, [cart, dispatch, promotionPricing]);

  // Keyboard Shortcuts & Click Outside Handlers
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "F12" || (e.key === "Enter" && (e.ctrlKey || e.metaKey))) {
        e.preventDefault();
        const paymentModal = {
          Cash: "cash",
          Card: "card",
          UPI: "upi",
          Bank: "bank",
          Split: "split",
        }[selectedPayMethod] || "cash";
        if (cart.length > 0 && paymentModal && !isPromotionPricing) {
          dispatch(setActiveModal(paymentModal));
        }
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
  }, [cart, dispatch, isPromotionPricing, selectedPayMethod]);

  // Fullscreen toggle
  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => { });
      dispatch(setIsFullscreen(true));
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen().catch(() => { });
        dispatch(setIsFullscreen(false));
      }
    }
  };

  // Filtered Products Memo (Stock status filtering on loaded items)
  const filteredProducts = useMemo(() => {
    return products.filter((product) => {
      let matchFilter = true;
      if (stockFilter === "instock") matchFilter = product.stockStatus === "instock";
      if (stockFilter === "outofstock") matchFilter = product.stockControl !== false && product.stockStatus === "outofstock";
      if (stockFilter === "lowstock") matchFilter = product.stockControl !== false && product.stockStatus === "lowstock";
      if (stockFilter === "featured") matchFilter = Boolean(product.isFeatured);
      if (stockFilter === "premium") matchFilter = Boolean(product.isPremium);
      if (stockFilter === "new-arrival") matchFilter = Boolean(product.isNewArrival);

      return matchFilter;
    });
  }, [products, stockFilter]);

  // Product Click Handler
  const handleProductClick = (product: Product) => {
    const isStockControlled = product.stockControl !== false;
    const hasConfig =
      (product.hasVariants && product.variantOptions && product.variantOptions.length > 0) ||
      (product.hasUOM && product.uomList && product.uomList.length > 0);

    if (hasConfig) {
      if (product.hasUOM && product.uomList && product.uomList.length > 0) {
        const baseInCart = cart
          .filter((i) => i.product.id === product.id)
          .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
        const remainingBase = typeof product.stock === "number" ? Math.max(0, product.stock - baseInCart) : null;

        const uomHasStock = (u: ProductUOM) => {
          if (!isStockControlled || remainingBase === null) return true;
          const factor = Number(u.conversionFactorToBase ?? 1) || 1;
          return Math.floor(remainingBase / factor) > 0;
        };

        const initialUom =
          product.uomList.find((u) => u.isDefault && uomHasStock(u)) ||
          product.uomList.find((u) => uomHasStock(u)) ||
          product.uomList.find((u) => u.isDefault) ||
          product.uomList[0];

        setSelectedUOM(initialUom);
      } else {
        setSelectedUOM(null);
      }

      if (product.hasVariants && product.variantOptions && product.variantOptions.length > 0) {
        const initialVariants: Record<string, VariantValue> = {};
        const initialVariant =
          product.variants?.find((variant) => variant.isDefault && (!isStockControlled || variant.stock > 0)) ||
          product.variants?.find((variant) => !isStockControlled || variant.stock > 0);

        product.variantOptions.forEach((opt) => {
          const selectedValue = opt.values.find((value) =>
            initialVariant?.optionValueIds.includes(value.id)
          );
          if (selectedValue) initialVariants[opt.name] = selectedValue;
        });
        setSelectedVariants(initialVariants);
      } else {
        setSelectedVariants({});
      }

      setConfigQty(1);
      setConfigProduct(product);
    } else {
      if (isStockControlled && typeof product.stock === "number" && product.stock <= 0) {
        return;
      }
      dispatch(addDirectToCart(product));
    }
  };

  const selectedVariant = useMemo<ProductVariant | null>(() => {
    if (!configProduct?.hasVariants || !configProduct.variantOptions) return null;

    const selectedValueIds = configProduct.variantOptions
      .map((group) => selectedVariants[group.name]?.id)
      .filter((id): id is number => typeof id === "number");

    if (selectedValueIds.length !== configProduct.variantOptions.length) return null;

    return configProduct.variants?.find(
      (variant) =>
        variant.optionValueIds.length === selectedValueIds.length &&
        selectedValueIds.every((id) => variant.optionValueIds.includes(id))
    ) || null;
  }, [configProduct, selectedVariants]);

  const modalMaxQuantity = useMemo<number | null>(() => {
    if (!configProduct) return null;
    const isStockControlled = configProduct.stockControl !== false;
    if (!isStockControlled) return null;

    const baseInCart = cart
      .filter((i) => i.product.id === configProduct.id)
      .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
    const remainingBase = typeof configProduct.stock === "number"
      ? Math.max(0, configProduct.stock - baseInCart)
      : null;

    if (configProduct.hasVariants) {
      if (!selectedVariant) return 0;
      const variantInCart = cart
        .filter((i) => i.selectedVariant?.id === selectedVariant.id)
        .reduce((sum, i) => sum + i.quantity, 0);
      const remainingVariant = Math.max(0, selectedVariant.stock - variantInCart);
      return remainingBase !== null ? Math.min(remainingVariant, remainingBase) : remainingVariant;
    }

    if (remainingBase !== null) {
      const factor = Number(selectedUOM?.conversionFactorToBase ?? 1) || 1;
      return Math.max(0, Math.floor(remainingBase / factor));
    }

    return null;
  }, [configProduct, cart, selectedUOM, selectedVariant]);

  const handleSelectUOM = (uom: ProductUOM) => {
    setSelectedUOM(uom);
    if (configProduct && configProduct.stockControl !== false && typeof configProduct.stock === "number") {
      const baseInCart = cart
        .filter((i) => i.product.id === configProduct.id)
        .reduce((sum, i) => sum + i.quantity * (Number(i.selectedUOM?.conversionFactorToBase ?? 1) || 1), 0);
      const factor = Number(uom.conversionFactorToBase ?? 1) || 1;
      const maxForUom = Math.max(0, Math.floor(Math.max(0, configProduct.stock - baseInCart) / factor));
      if (maxForUom > 0) {
        setConfigQty((prev) => Math.max(1, Math.min(prev, maxForUom)));
      }
    }
  };

  const isVariantValueDisabled = (groupIndex: number, value: VariantValue) => {
    if (!configProduct?.variants || !configProduct.variantOptions) return true;

    const requiredValueIds = configProduct.variantOptions
      .slice(0, groupIndex)
      .map((group) => selectedVariants[group.name]?.id)
      .filter((id): id is number => typeof id === "number");
    requiredValueIds.push(value.id);

    const isStockControlled = configProduct.stockControl !== false;
    return !configProduct.variants.some(
      (variant) =>
        (!isStockControlled || variant.stock > 0) &&
        requiredValueIds.every((id) => variant.optionValueIds.includes(id))
    );
  };

  const handleVariantSelection = (groupName: string, value: VariantValue) => {
    if (!configProduct?.variants || !configProduct.variantOptions) return;

    const groupIndex = configProduct.variantOptions.findIndex(
      (group) => group.name === groupName
    );
    if (groupIndex < 0 || isVariantValueDisabled(groupIndex, value)) return;

    const requiredValueIds = configProduct.variantOptions
      .slice(0, groupIndex)
      .map((group) => selectedVariants[group.name]?.id)
      .filter((id): id is number => typeof id === "number");
    requiredValueIds.push(value.id);

    const isStockControlled = configProduct.stockControl !== false;
    const matchingVariant = configProduct.variants.find(
      (variant) =>
        (!isStockControlled || variant.stock > 0) &&
        requiredValueIds.every((id) => variant.optionValueIds.includes(id))
    );
    if (!matchingVariant) return;

    const nextSelections: Record<string, VariantValue> = {};
    configProduct.variantOptions.forEach((group) => {
      const matchingValue = group.values.find((optionValue) =>
        matchingVariant.optionValueIds.includes(optionValue.id)
      );
      if (matchingValue) nextSelections[group.name] = matchingValue;
    });
    setSelectedVariants(nextSelections);
    setConfigQty((quantity) => Math.min(quantity, matchingVariant.stock));
  };

  const modalUnitPrice = useMemo(() => {
    if (!configProduct) return 0;
    if (selectedVariant) return selectedVariant.price;
    let price = selectedUOM ? selectedUOM.price : configProduct.price;
    Object.values(selectedVariants).forEach((v) => {
      if (v.priceDelta) price += v.priceDelta;
    });
    return price;
  }, [configProduct, selectedUOM, selectedVariant, selectedVariants]);

  const handleDiscountTypeChange = (nextType: DiscountType) => {
    if (nextType === discountType) return;

    const nextValue = nextType === "fixed"
      ? discountAmount
      : subTotal > 0
        ? (discountAmount / subTotal) * 100
        : 0;

    dispatch(setDiscount({ type: nextType, value: Number(nextValue.toFixed(2)) }));
  };

  const priceListDiscount = (selectedCustomer: Customer) => ({
    type: selectedCustomer.priceList?.pricingMethod === "fixed"
      ? "fixed" as const
      : "percentage" as const,
    value: selectedCustomer.priceList?.pricingMethod === "fixed"
      ? selectedCustomer.priceList.fixedAmount || 0
      : selectedCustomer.priceList?.pricingMethod === "discount"
        ? selectedCustomer.priceList.discountPercent
        : 0,
  });

  const parkCurrentCart = async (ref: string, notes: string) => {
    if (cart.length === 0 || currencyError || isPromotionPricing || !posClientToken || !saleId) return;
    const newHeld: HeldOrder = {
      id: "HOLD-SAVING",
      databaseId: 0,
      saleId,
      invoiceNumber,
      reference: ref.trim() || `Order #${Math.floor(1000 + Math.random() * 9000)}`,
      orderType,
      customer: { ...customer },
      cart: [...cart],
      subTotal,
      discountType,
      discountValue,
      taxPercent,
      serviceFee,
      appliedPromotion,
      promotionDiscountAmount,
      totalPayable,
      createdAt: new Date().toLocaleString(),
      notes: notes.trim() || "Parked order",
    };

    try {
      const savedOrder = await holdPosSale(posClientToken, newHeld);
      dispatch(addHeldOrder(savedOrder));
      dispatch(clearCart());
      startNewInvoice();
      setSalePersistenceError(null);
      dispatch(setActiveModal(null));
    } catch (error: unknown) {
      setSalePersistenceError(
        error instanceof Error ? error.message : "Unable to hold the current sale"
      );
    }
  };

  const restoreHeldOrder = async (order: HeldOrder) => {
    if (!posClientToken) return;

    try {
      const restored = await restoreHeldPosSale(posClientToken, order);
      dispatch(setCart(restored.cart));
      dispatch(setOrderType(restored.orderType));
      dispatch(setCustomer(restored.customer));
      dispatch(setDiscount({ type: restored.discountType, value: restored.discountValue }));
      dispatch(setTaxPercent(restored.taxPercent));
      dispatch(setServiceFee(restored.serviceFee));
      setSaleId(restored.saleId);
      setInvoiceNumber(restored.invoiceNumber);
      dispatch(removeHeldOrder(order.id));
      setSalePersistenceError(null);
      dispatch(setActiveModal(null));
    } catch (error: unknown) {
      setSalePersistenceError(
        error instanceof Error ? error.message : "Unable to restore the held sale"
      );
    }
  };

  const completeCashTender = async (
    tenders: CashTenderInput[],
    tenderSummary?: { tendered: number; change: number }
  ) => {
    if (!posClientToken) {
      throw new Error("The POS session is not ready. Please try again.");
    }
    if (isPromotionPricing) {
      throw new Error("Promotions are still being calculated. Please try again.");
    }

    try {
      await completeCashPosSale(
        posClientToken,
        currentSaleSnapshot,
        {
          subTotal,
          discountAmount,
          taxAmount: gstAmount,
          serviceFee,
          totalPayable,
        },
        tenders
      );

      // Optimistically deduct stock immediately in local state for 0ms lag
      deductStockOptimistically(currentSaleSnapshot.cart);

      // Trigger background catalog sync once queue worker has processed
      setTimeout(() => {
        setCatalogVersion((version) => version + 1);
      }, 1200);

      setLastPaymentDetails({
        method: "cash",
        amount: totalPayable,
        tendered: tenderSummary?.tendered ?? totalPayable,
        change: tenderSummary?.change ?? 0,
        invoiceNumber,
      });
      fetchPosSales(posClientToken).then((data) => setInvoices(data.invoices)).catch(() => undefined);
      setSalePersistenceError(null);
      dispatch(setSelectedPayMethod("Cash"));
      dispatch(setActiveModal("payment_success"));
    } catch (error: unknown) {
      setSalePersistenceError(
        error instanceof Error ? error.message : "Unable to save the cash payment"
      );
      throw error;
    }
  };

  const completeNonCashPayment = async (
    method: "Card" | "Bank",
    details: { provider: string; reference: string; metadata?: Record<string, string> }
  ) => {
    if (!posClientToken || !currency) {
      throw new Error("The POS session is not ready. Please try again.");
    }
    if (isPromotionPricing) {
      throw new Error("Promotions are still being calculated. Please try again.");
    }

    try {
      await completePaymentPosSale(
        posClientToken,
        currentSaleSnapshot,
        { subTotal, discountAmount, taxAmount: gstAmount, serviceFee, totalPayable },
        method,
        [{
          currencyCode: currency.code,
          amount: totalPayable,
          provider: details.provider,
          reference: details.reference,
          metadata: details.metadata,
        }]
      );

      // Optimistically deduct stock immediately in local state for 0ms lag
      deductStockOptimistically(currentSaleSnapshot.cart);

      // Trigger background catalog sync once queue worker has processed
      setTimeout(() => {
        setCatalogVersion((version) => version + 1);
      }, 1200);

      setLastPaymentDetails({
        method: method.toLowerCase() === "card" ? "card" : "bank",
        amount: totalPayable,
        provider: details.provider,
        reference: details.reference,
        invoiceNumber,
      });
      fetchPosSales(posClientToken).then((data) => setInvoices(data.invoices)).catch(() => undefined);
      setSalePersistenceError(null);
      dispatch(setActiveModal("payment_success"));
    } catch (error: unknown) {
      setSalePersistenceError(error instanceof Error ? error.message : `Unable to save the ${method.toLowerCase()} payment`);
      throw error;
    }
  };

  const deleteHeldOrder = async (order: HeldOrder) => {
    if (!posClientToken) return;

    try {
      await deleteHeldPosSale(posClientToken, order);
      dispatch(removeHeldOrder(order.id));
      setSalePersistenceError(null);
    } catch (error: unknown) {
      setSalePersistenceError(
        error instanceof Error ? error.message : "Unable to delete the held sale"
      );
    }
  };

  const handleSearchChange = useCallback(
    (query: string) => {
      dispatch(setSearchQuery(query));
      if (query.trim()) {
        dispatch(setActiveCategory("all"));
        if (stockFilter !== "all") {
          dispatch(setStockFilter("all"));
        }
      }
    },
    [dispatch, stockFilter]
  );

  const handleSelectCategory = useCallback(
    (categoryId: string) => {
      if (searchQuery) {
        dispatch(setSearchQuery(""));
      }
      dispatch(setActiveCategory(categoryId));
    },
    [dispatch, searchQuery]
  );

  const handleBranchChange = useCallback(
    (branchId: string) => {
      if (!branchId || branchId === selectedBranchId) return;

      if (cart.length > 0 && !window.confirm(t("switchBranchClearCart"))) {
        return;
      }

      if (cart.length > 0) {
        dispatch(clearCart());
      }
      dispatch(setActiveCategory("all"));
      setSelectedBranchId(branchId);
      window.localStorage.setItem(selectedBranchStorageKey, branchId);
    },
    [cart.length, dispatch, selectedBranchId]
  );

  return (
    <CurrencyProvider currency={displayCurrency || undefined}>
      <div className="d-flex flex-column vh-100 bg-light text-dark pos-dashboard">

        <PosTopbar
          branches={branches}
          selectedBranchId={selectedBranchId}
          isBranchLoading={isBranchLoading}
          onBranchChange={handleBranchChange}
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
          switchTenantUrl={centralUrl}
          onOpenDisplayModal={() => setIsDisplayModalOpen(true)}
          customerDisplayUrl={posClientToken ? `/pos/display?token=${encodeURIComponent(posClientToken)}` : undefined}
        />

        <div className="d-flex flex-column flex-lg-row flex-grow-1 overflow-hidden">
          <PosCategoryRail
            categories={categories}
            products={products}
            activeCategory={activeCategory}
            onSelectCategory={handleSelectCategory}
            totalProductsCount={totalProductsCount}
          />

          <main
            ref={catalogWrapperRef}
            className="pos-catalog-wrapper"
            onScroll={handleCatalogScroll}
          >
            <PosCatalogToolbar
              searchQuery={searchQuery}
              onSearchChange={handleSearchChange}
              stockFilter={stockFilter}
              onStockFilterChange={(f) => dispatch(setStockFilter(f))}
              onOpenScanModal={() => dispatch(setActiveModal("upi"))}
            />

            {isCatalogLoading && products.length === 0 ? (
              <div className="d-flex flex-column align-items-center justify-content-center flex-grow-1 py-5 text-muted">
                <div className="spinner-border spinner-border-sm text-primary mb-3" role="status">
                  <span className="visually-hidden">{t("loadingProducts")}</span>
                </div>
                <span className="fs-12">{t("loadingCatalog")}</span>
              </div>
            ) : catalogError && products.length === 0 ? (
              <div className="bg-white rounded-3 border p-5 text-center my-4">
                <i className="ri-cloud-off-line fs-1 text-danger d-block mb-2"></i>
                <h6 className="fw-bold">{t("couldNotLoadCatalog")}</h6>
                <p className="text-muted small mb-3">{catalogError}</p>
                <button
                  type="button"
                  className="btn btn-sm btn-primary"
                  onClick={() => {
                    setIsCatalogLoading(true);
                    setCatalogError(null);
                    setCatalogVersion((version) => version + 1);
                  }}
                >
                  <i className="ri-refresh-line me-1"></i>
                  {t("tryAgain")}
                </button>
              </div>
            ) : filteredProducts.length === 0 ? (
              <div className="bg-white rounded-3 border p-5 text-center my-4">
                <img
                  src="/assets/no-order-CCjZwO4J.svg"
                  alt="No items"
                  style={{ width: "120px", opacity: 0.6 }}
                  className="mx-auto mb-3"
                />
                <h6 className="fw-bold text-muted">{t("noMatchingProducts")}</h6>
                <p className="text-muted small mb-3">{t("tryClearingFilters")}</p>
                <button
                  type="button"
                  className="btn btn-sm btn-outline-primary"
                  onClick={() => {
                    dispatch(setSearchQuery(""));
                    dispatch(setActiveCategory("all"));
                    dispatch(setStockFilter("all"));
                  }}
                >
                  {t("resetFilters")}
                </button>
              </div>
            ) : (
              <>
                {searchQuery.trim() && (
                  <div className="d-flex align-items-center justify-content-between mb-2 px-1">
                    <span className="fs-12 text-muted">
                      {t("showingMatchingProducts")} &ldquo;<strong>{searchQuery}</strong>&rdquo; ({filteredProducts.length} {t("found")})
                    </span>
                    <button
                      type="button"
                      className="btn btn-sm btn-link text-decoration-none p-0 fs-12 text-primary"
                      onClick={() => dispatch(setSearchQuery(""))}
                    >
                      {t("clearSearch")}
                    </button>
                  </div>
                )}

                <div className="pos-product-grid">
                  {filteredProducts.map((product) => (
                    <PosProductCard
                      key={product.id}
                      product={product}
                      cart={cart}
                      onProductClick={handleProductClick}
                      onDirectAdd={handleProductClick}
                      onUpdateQty={(id, delta) => dispatch(updateQuantity({ itemId: id, delta }))}
                    />
                  ))}
                </div>

                {/* Scroll to Load More Sentinel & Indicator */}
                {hasMoreProducts && (
                  <div ref={sentinelRef} className="pos-load-more-container py-3 text-center w-100">
                    {isLoadingMore ? (
                      <div className="d-flex align-items-center justify-content-center gap-2 text-muted fs-12 py-2">
                        <div className="spinner-border spinner-border-sm text-primary" role="status">
                          <span className="visually-hidden">{t("loadingMoreItems")}</span>
                        </div>
                        <span>{t("loadingMoreItemsEllipsis")}</span>
                      </div>
                    ) : (
                      <button
                        type="button"
                        className="btn btn-sm btn-light border text-muted fs-12 rounded-pill px-3 py-1 shadow-xs"
                        onClick={loadMoreProducts}
                      >
                        <i className="ri-arrow-down-line me-1"></i>
                        {t("scrollToLoadMore")} ({filteredProducts.length} {t("of")} {totalProductsCount ?? "..."})
                      </button>
                    )}
                  </div>
                )}

                {!hasMoreProducts && filteredProducts.length > 0 && (
                  <div className="text-center py-3 text-muted fs-11 opacity-75">
                    {t("showingAll")} {filteredProducts.length} {filteredProducts.length === 1 ? t("item") : t("items")}
                  </div>
                )}
              </>
            )}
          </main>

          <aside className="pos-cart-panel">
            <div className="p-3 border-bottom bg-white">
              <div className="mb-2">
                <div className="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                  <span className="fs-14 fw-bold text-body">{t("currentSale")}</span>
                  <div className="pos-order-type-switcher" role="group" aria-label="Order type">
                    {(["Takeaway", "Dine-in", "Delivery"] as const).map((type) => (
                      <button
                        key={type}
                        type="button"
                        className={orderType === type ? "active" : ""}
                        onClick={() => dispatch(setOrderType(type))}
                      >
                        {type === "Takeaway" ? t("takeaway") : type === "Dine-in" ? t("dineIn") : t("delivery")}
                      </button>
                    ))}
                  </div>
                </div>
                <div className="pos-sale-meta-row mt-1">
                  <input
                    type="text"
                    className="form-control form-control-sm font-monospace text-primary fw-semibold"
                    style={{ minWidth: 0, flex: "1 1 auto" }}
                    aria-label="Invoice number"
                    value={invoiceNumber}
                    placeholder={t("generatingInvoice")}
                    maxLength={40}
                    onChange={(event) => setInvoiceNumber(event.target.value)}
                    onBlur={(event) =>
                      setInvoiceNumber(event.target.value.trim().replace(/\s+/g, "-"))
                    }
                  />
                  <button
                    type="button"
                    className="pos-customer-inline"
                    onClick={() => dispatch(setActiveModal("edit_customer"))}
                    title={t("customer")}
                  >
                    <i className="ri-user-line" aria-hidden="true"></i>
                    <span>{customer.name || customer.code || t("customer")}</span>
                  </button>
                </div>
                {salePersistenceError && (
                  <div className="text-danger fs-10 mt-1" role="alert">
                    <i className="ri-error-warning-line me-1"></i>
                    {salePersistenceError}
                  </div>
                )}
              </div>

            </div>

            <div className="pos-cart-items-container">
              {currencyError && (
                <div className="alert alert-warning mx-3 mt-3 mb-0 py-2 fs-11" role="alert">
                  <i className="ri-error-warning-line me-1"></i>
                  {currencyError}
                </div>
              )}
              {cart.length === 0 ? (
                <div className="text-center py-5">
                  <img src="/assets/no-order-CCjZwO4J.svg" alt="Empty" style={{ width: "90px", opacity: 0.5 }} className="mx-auto mb-2" />
                  <p className="text-muted fs-12 mb-0">{t("cartIsEmpty")}</p>
                  <small className="text-muted fs-11">{t("selectProductsToAdd")}</small>
                </div>
              ) : (
                <div>
                  <div className="pos-cart-items-header d-flex align-items-center justify-content-between mb-2">
                    <span className="fs-11 fw-semibold text-muted text-uppercase">{t("items")} ({totalItemCount})</span>
                    <a href="#!" onClick={() => dispatch(setActiveModal("delete"))} className="text-danger fs-11 text-decoration-none">{t("removeAll")}</a>
                  </div>
                  {cart.map((item) => (
                    <PosCartItem
                      key={item.id}
                      item={item}
                      onUpdateQty={(id, delta) => dispatch(updateQuantity({ itemId: id, delta }))}
                      onAddPaidItem={(rewardItem) => {
                        if (rewardItem.selectedUOM || rewardItem.selectedVariant || rewardItem.selectedVariants) {
                          dispatch(addConfiguredToCart({
                            product: rewardItem.product,
                            quantity: 1,
                            selectedUOM: rewardItem.selectedUOM || null,
                            selectedVariants: rewardItem.selectedVariants || {},
                            selectedVariant: rewardItem.selectedVariant || null,
                          }));
                          return;
                        }

                        dispatch(addDirectToCart(rewardItem.product));
                      }}
                      onRemoveItem={(id) => dispatch(removeItem(id))}
                    />
                  ))}
                </div>
              )}
            </div>

            {cart.length > 0 && currency && !currencyError && (
              <PosSummaryCard
                subTotal={subTotal}
                discountType={discountType}
                discountValue={discountValue}
                onDiscountTypeChange={handleDiscountTypeChange}
                onDiscountValueChange={(value) => dispatch(setDiscountValue(value))}
                isDiscountLocked={Boolean(customer.priceList)}
                manualDiscountAmount={manualDiscountAmount}
                appliedPromotion={appliedPromotion}
                promotionDiscountAmount={promotionDiscountAmount}
                isPromotionPricing={isPromotionPricing}
                isPromotionChecked={Boolean(promotionPricing)}
                promotionPricingError={promotionPricingError}
                taxPercent={taxPercent}
                onTaxChange={(v) => dispatch(setTaxPercent(v))}
                gstAmount={gstAmount}
                serviceFee={serviceFee}
                onServiceFeeChange={(v) => dispatch(setServiceFee(v))}
                totalPayable={totalPayable}
                totalItemCount={totalItemCount}
              />
            )}

            {cart.length > 0 && currency && !currencyError && !isPromotionPricing && (
              <PosPaymentGrid
                selectedMethod={selectedPayMethod}
                onSelectMethod={(m) => dispatch(setSelectedPayMethod(m))}
                onOpenModal={(m) => dispatch(setActiveModal(m))}
                totalPayable={totalPayable}
              />
            )}

            <PosBottomToolbar
              heldCount={heldOrders.length}
              onOpenModal={(m) => {
                if (m === "invoice") {
                  setViewedInvoice(null);
                  setIsPaidInvoicePreview(false);
                }
                dispatch(setActiveModal(m));
              }}
            />
          </aside>
        </div>

        {(activeModal || configProduct) && (
          <div className="modal-backdrop fade show" style={{ zIndex: 1055, backgroundColor: "rgba(15, 23, 42, 0.6)" }} onClick={() => { dispatch(setActiveModal(null)); setConfigProduct(null); }}></div>
        )}

        {configProduct && (
          <ModalConfigProduct
            product={configProduct}
            selectedUOM={selectedUOM}
            onSelectUOM={handleSelectUOM}
            selectedVariants={selectedVariants}
            onSelectVariant={handleVariantSelection}
            isVariantValueDisabled={isVariantValueDisabled}
            hasValidVariant={
              !configProduct.hasVariants ||
              (selectedVariant !== null && selectedVariant.stock > 0)
            }
            quantity={configQty}
            onUpdateQuantity={(delta) =>
              setConfigQty(
                Math.max(1, Math.min(configQty + delta, modalMaxQuantity ?? Infinity))
              )
            }
            maxQuantity={modalMaxQuantity}
            modalUnitPrice={modalUnitPrice}
            onAddToCart={() => {
              const isStockControlled = configProduct.stockControl !== false;
              if (
                configProduct.hasVariants &&
                (!selectedVariant || (isStockControlled && (selectedVariant.stock <= 0 || configQty > selectedVariant.stock)))
              ) return;
              if (isStockControlled && modalMaxQuantity !== null && (modalMaxQuantity <= 0 || configQty > modalMaxQuantity)) {
                return;
              }
              dispatch(addConfiguredToCart({ product: configProduct, quantity: configQty, selectedUOM, selectedVariants, selectedVariant }));
              setConfigProduct(null);
            }}
            onClose={() => setConfigProduct(null)}
          />
        )}

        {activeModal === "cash" && (
          <ModalCashTender
            totalPayable={totalPayable}
            currencies={currencies}
            currencyRates={currencyRates}
            onTenderChange={handleTenderChange}
            onConfirm={completeCashTender}
            onClose={() => {
              setLiveTender(null);
              dispatch(setActiveModal(null));
            }}
          />
        )}

        {activeModal === "card" && (
          <ModalCardTerminal
            totalPayable={totalPayable}
            onConfirm={(details) => completeNonCashPayment("Card", {
              provider: details.provider,
              reference: details.reference,
              metadata: { last_four: details.lastFour },
            })}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "upi" && (
          <ModalUpiQr
            totalPayable={totalPayable}
            onConfirm={async () => {
              const qrRef = `QR-${Date.now().toString().slice(-6)}`;
              try {
                if (posClientToken && currency && !isPromotionPricing) {
                  await completePaymentPosSale(
                    posClientToken,
                    currentSaleSnapshot,
                    { subTotal, discountAmount, taxAmount: gstAmount, serviceFee, totalPayable },
                    "Bank",
                    [{
                      currencyCode: currency.code,
                      amount: totalPayable,
                      provider: "KHQR / UPI",
                      reference: qrRef,
                    }]
                  );

                  // Optimistically deduct stock immediately in local state for 0ms lag
                  deductStockOptimistically(currentSaleSnapshot.cart);

                  // Trigger background catalog sync once queue worker has processed
                  setTimeout(() => {
                    setCatalogVersion((version) => version + 1);
                  }, 1200);

                  fetchPosSales(posClientToken).then((data) => setInvoices(data.invoices)).catch(() => undefined);
                }
              } catch (err) {
                console.warn("Could not persist UPI sale:", err);
              }
              setLastPaymentDetails({
                method: "qr",
                amount: totalPayable,
                provider: "KHQR / UPI",
                reference: qrRef,
                invoiceNumber,
              });
              dispatch(setActiveModal("payment_success"));
            }}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "bank" && (
          <ModalBankTransfer
            totalPayable={totalPayable}
            onConfirm={(details) => completeNonCashPayment("Bank", { provider: details.bankName, reference: details.reference })}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "hold" && (
          <ModalHeldOrders
            heldOrders={heldOrders}
            cartCount={totalItemCount}
            totalPayable={totalPayable}
            onRestoreOrder={restoreHeldOrder}
            onDeleteOrder={deleteHeldOrder}
            onParkCurrentCart={parkCurrentCart}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "invoice" && (
          <ModalInvoicePreview
            invoiceNumber={viewedInvoice?.id || invoiceNumber}
            currentDate={viewedInvoice?.date || currentDate}
            currentTime={viewedInvoice?.time || currentTime}
            cart={viewedInvoice?.snapshot.cart || cart}
            currencyRates={currencyRates}
            company={company}
            customer={viewedInvoice?.snapshot.customer || customer}
            orderType={viewedInvoice?.snapshot.orderType || orderType}
            paymentMethod={viewedInvoice?.paymentMethod || (isPaidInvoicePreview ? selectedPayMethod : "Not yet")}
            discountType={viewedInvoice?.snapshot.discountType || discountType}
            discountValue={viewedInvoice?.snapshot.discountValue ?? discountValue}
            subTotal={viewedInvoice?.subTotal ?? subTotal}
            discountAmount={viewedInvoice?.discountAmount ?? discountAmount}
            appliedPromotion={viewedInvoice?.snapshot.appliedPromotion || appliedPromotion}
            promotionDiscountAmount={viewedInvoice?.snapshot.promotionDiscountAmount ?? promotionDiscountAmount}
            taxPercent={viewedInvoice?.snapshot.taxPercent ?? taxPercent}
            taxAmount={viewedInvoice?.taxAmount ?? gstAmount}
            serviceFee={viewedInvoice?.serviceFee ?? serviceFee}
            totalPayable={viewedInvoice?.amount ?? totalPayable}
            totalItemCount={viewedInvoice?.totalItemCount ?? totalItemCount}
            onClose={() => { setViewedInvoice(null); setIsPaidInvoicePreview(false); dispatch(setActiveModal(null)); }}
          />
        )}
        {activeModal === "pay_later" && (
          <ModalPayLater
            customerName={customer.name}
            onConfirm={() => {
              setLastPaymentDetails({
                method: "later",
                amount: totalPayable,
                invoiceNumber,
              });
              dispatch(setActiveModal("payment_success"));
            }}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "history" && (
          <ModalShiftHistory
            invoices={invoices}
            onViewInvoice={(invoice) => { setViewedInvoice(invoice); setIsPaidInvoicePreview(true); dispatch(setActiveModal("invoice")); }}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "payment_success" && (
          <ModalPaymentSuccess
            invoiceNumber={lastPaymentDetails?.invoiceNumber || invoiceNumber}
            totalPayable={lastPaymentDetails?.amount || totalPayable}
            selectedPayMethod={lastPaymentDetails?.method ? lastPaymentDetails.method.toUpperCase() : selectedPayMethod}
            currentDate={currentDate}
            onNextSaleAndInvoice={() => {
              // 1. Prepare completed invoice snapshot for ModalInvoicePreview
              const paidInvoice: Invoice = {
                databaseId: 0,
                saleId: saleId,
                id: lastPaymentDetails?.invoiceNumber || invoiceNumber,
                customer: customer.name || "Walk-in Customer",
                paymentMethod: lastPaymentDetails?.method ? lastPaymentDetails.method.toUpperCase() : selectedPayMethod,
                date: currentDate,
                time: currentTime,
                amount: lastPaymentDetails?.amount || totalPayable,
                status: "Completed",
                snapshot: { ...currentSaleSnapshot, cart: [...cart] },
                subTotal,
                discountAmount,
                taxAmount: gstAmount,
                serviceFee,
                totalItemCount,
              };

              setViewedInvoice(paidInvoice);
              setIsPaidInvoicePreview(true);

              // 2. Reset active cart, customer, discount, and start new invoice for next sale
              dispatch(clearCart());
              dispatch(setCustomer(emptyCustomer));
              dispatch(setDiscount({ type: "percentage", value: 0 }));
              startNewInvoice();
              setLiveTender(null);
              setLastPaymentDetails(null);
              setCatalogVersion((version) => version + 1);

              // 3. Open invoice preview dialog directly
              dispatch(setActiveModal("invoice"));
            }}
            onClose={() => {
              dispatch(clearCart());
              dispatch(setCustomer(emptyCustomer));
              dispatch(setDiscount({ type: "percentage", value: 0 }));
              startNewInvoice();
              setLiveTender(null);
              setLastPaymentDetails(null);
              setCatalogVersion((version) => version + 1);
              dispatch(setActiveModal(null));
            }}
          />
        )}
        {activeModal === "edit_customer" && (
          <ModalEditCustomer
            customer={customer}
            customers={customers}
            priceLists={priceLists}
            isLoading={isCustomerLoading}
            error={customerError}
            onSaveCustomer={(updated) => {
              dispatch(setCustomer(updated));
              dispatch(setDiscount(priceListDiscount(updated)));
              dispatch(setActiveModal(null));
            }}
            onClearCustomer={() => {
              dispatch(setCustomer(emptyCustomer));
              dispatch(setDiscount({ type: "percentage", value: 0 }));
              dispatch(setActiveModal(null));
            }}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {activeModal === "delete" && (
          <ModalConfirmClear
            onConfirm={() => {
              dispatch(clearCart());
              startNewInvoice();
              dispatch(setActiveModal(null));
            }}
            onClose={() => dispatch(setActiveModal(null))}
          />
        )}
        {isDisplayModalOpen && (
          <ModalDisplayPair
            clientToken={posClientToken}
            branchName={branches.find((b) => b.id === selectedBranchId)?.name}
            activeCartCount={totalItemCount}
            onClose={() => setIsDisplayModalOpen(false)}
          />
        )}
      </div>
    </CurrencyProvider>
  );
}
