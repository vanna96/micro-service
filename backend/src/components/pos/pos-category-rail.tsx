import React from "react";
import { Category, Product } from "@/types/pos-types";
import { useTranslation } from "@/lib/i18n/i18n";

interface PosCategoryRailProps {
  categories: Category[];
  products: Product[];
  activeCategory: string;
  onSelectCategory: (id: string) => void;
  totalProductsCount?: number | null;
}

export function PosCategoryRail({
  categories,
  products,
  activeCategory,
  onSelectCategory,
  totalProductsCount,
}: PosCategoryRailProps) {
  const { t } = useTranslation();

  return (
    <aside className="pos-category-sidebar">
      {categories.map((cat) => {
        const loadedCount = products.filter(
          (p) => cat.id === "all" || p.category === cat.id
        ).length;
        const count =
          cat.id === "all" && totalProductsCount !== null && totalProductsCount !== undefined
            ? totalProductsCount
            : cat.id === activeCategory && totalProductsCount !== null && totalProductsCount !== undefined
            ? totalProductsCount
            : loadedCount;
        const isActive = activeCategory === cat.id;

        return (
          <button
            key={cat.id}
            type="button"
            className={`pos-cat-btn ${isActive ? "active" : ""}`}
            onClick={() => onSelectCategory(cat.id)}
          >
            <div className="cat-icon-wrap">
              {cat.icon ? (
                <img
                  src={cat.icon}
                  alt=""
                  style={{ width: "22px", height: "22px", objectFit: "contain" }}
                />
              ) : (
                <i
                  className={cat.id === "all" ? "ri-layout-grid-line fs-18" : "ri-price-tag-3-line fs-18"}
                  aria-hidden="true"
                ></i>
              )}
            </div>
            <span className="fs-12 fw-semibold lh-1">
              {cat.id === "all" ? t("all") : cat.name}
            </span>
            <small
              className={`fs-10 mt-1 opacity-75 ${
                isActive ? "text-white" : "text-muted"
              }`}
            >
              {count}
            </small>
          </button>
        );
      })}
    </aside>
  );
}
