"use client";

import React, { createContext, useContext } from "react";
import { CurrencyInfo } from "@/types/pos-types";

const CurrencyContext = createContext<CurrencyInfo | null>(null);

export function CurrencyProvider({ currency, children }: { currency?: CurrencyInfo; children: React.ReactNode }) {
  return (
    <CurrencyContext.Provider value={currency || null}>
      {children}
    </CurrencyContext.Provider>
  );
}

export function useCurrency(): CurrencyInfo | null {
  return useContext(CurrencyContext);
}

export function formatCurrency(amount: number, currency?: CurrencyInfo | null): string {
  const resolved = currency;
  if (!resolved) return String(Number(amount || 0));
  const separator = resolved.symbol.length > 1 ? " " : "";
  const num = Number(amount || 0);
  const isNegative = num < 0;
  const absNum = Math.abs(num);
  const formatted = absNum.toLocaleString("en-US", {
    minimumFractionDigits: resolved.decimalPlaces,
    maximumFractionDigits: resolved.decimalPlaces,
  });
  return `${isNegative ? "-" : ""}${resolved.symbol}${separator}${formatted}`;
}
