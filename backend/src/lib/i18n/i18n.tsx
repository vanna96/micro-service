"use client";

import React, { createContext, useCallback, useContext, useEffect, useState } from "react";
import en, { type TranslationKeys } from "./en";
import kh from "./kh";

export type Locale = "en" | "kh";

const STORAGE_KEY = "vpos.language";

const dictionaries: Record<Locale, Record<TranslationKeys, string>> = { en, kh };

interface I18nContextValue {
  locale: Locale;
  setLocale: (locale: Locale) => void;
  language: Locale;
  setLanguage: (locale: Locale) => void;
  t: (key: TranslationKeys, replacements?: Record<string, string>) => string;
}

const I18nContext = createContext<I18nContextValue>({
  locale: "en",
  setLocale: () => {},
  language: "en",
  setLanguage: () => {},
  t: (key) => en[key] ?? key,
});

function getInitialLocale(): Locale {
  if (typeof window === "undefined") return "en";

  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored === "kh" || stored === "en") return stored;
  if (stored === "km") {
    try {
      localStorage.setItem(STORAGE_KEY, "kh");
    } catch {
      // Ignore storage errors
    }
    return "kh";
  }
  return "en";
}

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>("en");

  // Read from localStorage on mount (client-only)
  useEffect(() => {
    const initial = getInitialLocale();
    setLocaleState(initial);
    if (typeof document !== "undefined") {
      document.documentElement.lang = initial;
    }
  }, []);

  const setLocale = useCallback((next: Locale) => {
    setLocaleState(next);
    try {
      localStorage.setItem(STORAGE_KEY, next);
      document.cookie = `locale=${next}; path=/; max-age=31536000; SameSite=Lax`;
    } catch {
      // Ignore storage errors (e.g. private browsing)
    }
    // Update <html lang> for accessibility
    document.documentElement.lang = next;
  }, []);

  const t = useCallback(
    (key: TranslationKeys, replacements?: Record<string, string>): string => {
      let value = dictionaries[locale]?.[key] ?? en[key] ?? key;

      if (replacements) {
        for (const [token, replacement] of Object.entries(replacements)) {
          value = value.replace(`{${token}}`, replacement);
        }
      }

      return value;
    },
    [locale]
  );

  return (
    <I18nContext.Provider value={{ locale, setLocale, language: locale, setLanguage: setLocale, t }}>
      {children}
    </I18nContext.Provider>
  );
}

/**
 * Hook to access translation function and locale control.
 *
 * Usage:
 * ```tsx
 * const { t, locale, setLocale } = useTranslation();
 * <span>{t("subtotal")}</span>
 * ```
 */
export function useTranslation() {
  return useContext(I18nContext);
}
