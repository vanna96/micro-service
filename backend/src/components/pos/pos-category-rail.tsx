import React from "react";
import { Category, Product } from "@/types/pos-types";
import { CATEGORIES, PRODUCTS } from "@/data/pos-data";

interface PosCategoryRailProps {
  activeCategory: string;
  onSelectCategory: (id: string) => void;
}

export function PosCategoryRail({
  activeCategory,
  onSelectCategory,
}: PosCategoryRailProps) {
  return (
    <aside className="pos-category-sidebar">
      {CATEGORIES.map((cat) => {
        const count = PRODUCTS.filter(
          (p) => cat.id === "all" || p.category === cat.id
        ).length;
        const isActive = activeCategory === cat.id;

        return (
          <button
            key={cat.id}
            type="button"
            className={`pos-cat-btn ${isActive ? "active" : ""}`}
            onClick={() => onSelectCategory(cat.id)}
          >
            <div className="cat-icon-wrap">
              <img
                src={cat.icon}
                alt={cat.name}
                style={{ width: "22px", height: "22px", objectFit: "contain" }}
              />
            </div>
            <span className="fs-12 fw-semibold lh-1">{cat.name}</span>
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
