export interface Category {
  id: string;
  name: string;
  icon: string;
}

export interface VariantValue {
  label: string;
  priceDelta?: number;
}

export interface ProductVariantOption {
  name: string; // e.g. "Storage", "Color", "Size"
  values: VariantValue[];
}

export interface ProductUOM {
  name: string; // e.g. "Single (1 pcs)", "Pack of 6", "Carton (24 pcs)"
  shortCode: string; // e.g. "PCS", "PK6", "CTN"
  price: number; // custom base price for this UOM
  isDefault?: boolean;
}

export interface Product {
  id: string;
  name: string;
  sku: string;
  price: number;
  category: string;
  image: string;
  stockStatus: 'instock' | 'outofstock' | 'lowstock';
  brand?: string;
  hasVariants?: boolean;
  variantOptions?: ProductVariantOption[];
  hasUOM?: boolean;
  uomList?: ProductUOM[];
  defaultUOM?: string;
}

export interface CartItem {
  id: string; // unique cart item id
  product: Product;
  quantity: number;
  selectedUOM?: ProductUOM;
  selectedVariants?: Record<string, VariantValue>;
  unitPrice: number;
}

export interface Invoice {
  id: string;
  customer: string;
  paymentMethod: 'Cash' | 'Card' | 'UPI' | 'Bank Transfer';
  date: string;
  time: string;
  amount: number;
  status: 'Completed' | 'Pending' | 'Refunded';
}

export const CATEGORIES: Category[] = [
  { id: 'all', name: 'All', icon: '/assets/img-14-Bq_mg9xG.png' },
  { id: 'clothing', name: 'Clothing', icon: '/assets/img-01-BBWp8t8E.png' },
  { id: 'footwear', name: 'Footwear', icon: '/assets/img-03-oTTY_McP.png' },
  { id: 'watches', name: 'Watches', icon: '/assets/img-02-ClVfz9I5.png' },
  { id: 'food', name: 'Food', icon: '/assets/img-22-D2w1zjf6.png' },
  { id: 'accessories', name: 'Accessories', icon: '/assets/img-19-UVF-VI0Q.png' },
  { id: 'electronics', name: 'Electronics', icon: '/assets/img-24-Q6X8xUcA.png' },
  { id: 'bags', name: 'Bags', icon: '/assets/img-08-BXmGY-HZ.png' },
  { id: 'beauty', name: 'Beauty', icon: '/assets/pr-37-CrN88d3y.png' },
  { id: 'fitness', name: 'Fitness', icon: '/assets/pr-40-pfTD79Wn.png' },
];

export const PRODUCTS: Product[] = [
  // 1. PRODUCTS WITH UOM (Unit of Measure)
  {
    id: 'pr-25',
    name: 'Full Cream Fresh Milk',
    sku: 'FD-DRY-005',
    price: 2.49,
    category: 'food',
    image: '/assets/pr-25-2HQ0Uvnq.png',
    stockStatus: 'instock',
    brand: 'FarmFresh',
    hasUOM: true,
    defaultUOM: 'Bottle (1L)',
    uomList: [
      { name: 'Bottle (1L)', shortCode: '1L', price: 2.49, isDefault: true },
      { name: 'Pack of 6 (6L)', shortCode: 'PK6', price: 13.99 },
      { name: 'Carton (12L)', shortCode: 'CTN', price: 26.50 },
    ],
  },
  {
    id: 'pr-24',
    name: 'Cold Pressed Orange Juice',
    sku: 'FD-BEV-004',
    price: 3.99,
    category: 'food',
    image: '/assets/pr-24-CaCL_grq.png',
    stockStatus: 'instock',
    brand: 'FreshJuice',
    hasUOM: true,
    defaultUOM: 'Bottle (500ml)',
    uomList: [
      { name: 'Bottle (500ml)', shortCode: '500ML', price: 3.99, isDefault: true },
      { name: 'Family Jug (2L)', shortCode: '2L', price: 11.99 },
      { name: 'Crate (6 Bottles)', shortCode: 'CRT', price: 21.50 },
    ],
  },
  {
    id: 'pr-22',
    name: 'Premium Roasted Almonds',
    sku: 'FD-DRY-002',
    price: 8.99,
    category: 'food',
    image: '/assets/pr-22-AujqxRgd.png',
    stockStatus: 'instock',
    brand: 'NutriBite',
    hasUOM: true,
    defaultUOM: 'Pouch (200g)',
    uomList: [
      { name: 'Pouch (200g)', shortCode: '200G', price: 8.99, isDefault: true },
      { name: 'Container (500g)', shortCode: '500G', price: 19.99 },
      { name: 'Bulk Bag (1kg)', shortCode: '1KG', price: 36.00 },
    ],
  },
  {
    id: 'pr-21',
    name: 'Classic Salted Potato Chips',
    sku: 'FD-SNK-001',
    price: 2.99,
    category: 'food',
    image: '/assets/pr-21-Dip6sWIz.png',
    stockStatus: 'instock',
    brand: 'CrunchBite',
    hasUOM: true,
    defaultUOM: 'Regular Bag (150g)',
    uomList: [
      { name: 'Regular Bag (150g)', shortCode: 'BAG', price: 2.99, isDefault: true },
      { name: 'Party Size (400g)', shortCode: 'PTY', price: 6.49 },
      { name: 'Box (10 Bags)', shortCode: 'BX10', price: 25.00 },
    ],
  },
  {
    id: 'pr-23',
    name: 'Instant Espresso Coffee Roast',
    sku: 'FD-BEV-003',
    price: 6.99,
    category: 'food',
    image: '/assets/pr-23-DKNsLIkt.png',
    stockStatus: 'instock',
    brand: 'AromaRoast',
    hasUOM: true,
    defaultUOM: 'Jar (100g)',
    uomList: [
      { name: 'Jar (100g)', shortCode: '100G', price: 6.99, isDefault: true },
      { name: 'Economy Refill (500g)', shortCode: '500G', price: 27.99 },
      { name: 'Case (6 Jars)', shortCode: 'CS6', price: 38.50 },
    ],
  },
  {
    id: 'pr-26',
    name: 'Instant Masala Noodles Pack',
    sku: 'FD-SNK-006',
    price: 1.99,
    category: 'food',
    image: '/assets/pr-26-CbwXBE4S.png',
    stockStatus: 'instock',
    brand: 'QuickMeal',
    hasUOM: true,
    defaultUOM: 'Single Pack (70g)',
    uomList: [
      { name: 'Single Pack (70g)', shortCode: '1PC', price: 1.99, isDefault: true },
      { name: 'Multi-Pack (4x70g)', shortCode: 'PK4', price: 6.99 },
      { name: 'Master Box (24 Packs)', shortCode: 'BX24', price: 38.00 },
    ],
  },

  // 2. PRODUCTS WITH VARIATIONS (RAM, Memory, Color, Size)
  {
    id: 'pr-20',
    name: 'Smart Fitness Watch Pro',
    sku: 'WT-SMT-002',
    price: 99.99,
    category: 'watches',
    image: '/assets/pr-20-DLcbFUnr.png',
    stockStatus: 'instock',
    brand: 'TechPulse',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Case Size & Memory',
        values: [
          { label: '40mm (64GB)', priceDelta: 0 },
          { label: '44mm (128GB)', priceDelta: 30 },
          { label: '46mm (256GB Cellular)', priceDelta: 70 },
        ],
      },
      {
        name: 'Color',
        values: [
          { label: 'Midnight Black', priceDelta: 0 },
          { label: 'Titanium Gray', priceDelta: 0 },
          { label: 'Starlight Silver', priceDelta: 0 },
        ],
      },
    ],
  },
  {
    id: 'pr-27',
    name: 'USB-C Fast Charger & Cable',
    sku: 'EL-CHR-001',
    price: 19.99,
    category: 'accessories',
    image: '/assets/pr-27-2sMQbU1b.png',
    stockStatus: 'instock',
    brand: 'TechPulse',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Output Power',
        values: [
          { label: '30W Compact', priceDelta: 0 },
          { label: '65W Dual Port GaN', priceDelta: 15 },
          { label: '100W Pro 4-Port', priceDelta: 35 },
        ],
      },
      {
        name: 'Cable Included',
        values: [
          { label: '1.2m Braided Type-C', priceDelta: 0 },
          { label: '2.0m Heavy Duty Cable', priceDelta: 5 },
        ],
      },
    ],
  },
  {
    id: 'pr-3',
    name: 'Polka Dot Skater Dress',
    sku: 'DR-WMN-002',
    price: 32.99,
    category: 'clothing',
    image: '/assets/pr-3-BzZJ2azf.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Size',
        values: [
          { label: 'S (UK 8)', priceDelta: 0 },
          { label: 'M (UK 10)', priceDelta: 0 },
          { label: 'L (UK 12)', priceDelta: 0 },
          { label: 'XL (UK 14)', priceDelta: 3 },
        ],
      },
      {
        name: 'Color',
        values: [
          { label: 'Navy Blue Dot', priceDelta: 0 },
          { label: 'Classic Crimson Dot', priceDelta: 0 },
        ],
      },
    ],
  },
  {
    id: 'pr-16',
    name: 'Men’s Running Sports Shoes',
    sku: 'FT-MEN-002',
    price: 59.99,
    category: 'footwear',
    image: '/assets/pr-16-qIivuYeB.png',
    stockStatus: 'instock',
    brand: 'PulseFit',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Shoe Size',
        values: [
          { label: 'US 8 / EU 41', priceDelta: 0 },
          { label: 'US 9 / EU 42', priceDelta: 0 },
          { label: 'US 10 / EU 43', priceDelta: 0 },
          { label: 'US 11 / EU 44', priceDelta: 0 },
        ],
      },
      {
        name: 'Colorway',
        values: [
          { label: 'Shadow Black', priceDelta: 0 },
          { label: 'Volt Green', priceDelta: 0 },
          { label: 'Pure White', priceDelta: 0 },
        ],
      },
    ],
  },
  {
    id: 'pr-5',
    name: 'Men’s Light Blue Ripped Jeans',
    sku: 'JN-MEN-006',
    price: 44.99,
    category: 'clothing',
    image: '/assets/pr-6-B0ESum4b.png',
    stockStatus: 'lowstock',
    brand: 'UrbanDenim',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Waist Size',
        values: [
          { label: '30W x 32L', priceDelta: 0 },
          { label: '32W x 32L', priceDelta: 0 },
          { label: '34W x 32L', priceDelta: 0 },
          { label: '36W x 34L', priceDelta: 0 },
        ],
      },
    ],
  },
  {
    id: 'pr-39',
    name: 'Matte Velvet Lipstick',
    sku: 'BT-MKP-023',
    price: 19.99,
    category: 'beauty',
    image: '/assets/pr-39-Bgva8Ct-.png',
    stockStatus: 'instock',
    brand: 'GlowGoddess',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Shade',
        values: [
          { label: 'Ruby Wine #01', priceDelta: 0 },
          { label: 'Nude Peach #04', priceDelta: 0 },
          { label: 'Rosewood Berry #09', priceDelta: 0 },
        ],
      },
    ],
  },
  {
    id: 'pr-40',
    name: 'Adjustable Dumbbell Set',
    sku: 'FT-DBL-031',
    price: 59.99,
    category: 'fitness',
    image: '/assets/pr-40-pfTD79Wn.png',
    stockStatus: 'instock',
    brand: 'IronPeak',
    hasVariants: true,
    variantOptions: [
      {
        name: 'Weight Specification',
        values: [
          { label: 'Pair 20kg (2x10kg)', priceDelta: 0 },
          { label: 'Pair 32kg (2x16kg)', priceDelta: 40 },
          { label: 'Pair 40kg (2x20kg)', priceDelta: 75 },
        ],
      },
    ],
  },

  // 3. GENERAL ITEMS (1-tap add without modal)
  {
    id: 'pr-35',
    name: 'Women’s Elegant Handbag',
    sku: 'BG-HBG-011',
    price: 89.99,
    category: 'bags',
    image: '/assets/pr-35-B2Unq0hU.png',
    stockStatus: 'instock',
    brand: 'NovaWear',
    hasVariants: false,
  },
  {
    id: 'pr-36',
    name: 'Designer Leather Shoulder Bag',
    sku: 'BG-HBG-012',
    price: 99.99,
    category: 'bags',
    image: '/assets/pr-36-COs7RIFT.png',
    stockStatus: 'instock',
    brand: 'NovaWear',
    hasVariants: false,
  },
  {
    id: 'pr-2',
    name: 'Women’s Red Camisole Top',
    sku: 'TP-WMN-003',
    price: 17.99,
    category: 'clothing',
    image: '/assets/pr-2-BB_J2W-Z.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: false,
  },
  {
    id: 'pr-4',
    name: 'Women’s Formal Pink Shirt',
    sku: 'SH-WMN-004',
    price: 29.99,
    category: 'clothing',
    image: '/assets/pr-4-JPhm9DaC.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: false,
  },
  {
    id: 'pr-6',
    name: 'Women’s Navy Mini Skirt',
    sku: 'SK-WMN-005',
    price: 22.99,
    category: 'clothing',
    image: '/assets/pr-5-CDf-ezTw.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: false,
  },
  {
    id: 'pr-7',
    name: 'Men’s Beige Casual Jacket',
    sku: 'JK-MEN-007',
    price: 64.99,
    category: 'clothing',
    image: '/assets/pr-7-DOHIoQVB.png',
    stockStatus: 'instock',
    brand: 'UrbanDenim',
    hasVariants: false,
  },
  {
    id: 'pr-8',
    name: 'Men’s Olive Green Hoodie',
    sku: 'HD-MEN-008',
    price: 42.99,
    category: 'clothing',
    image: '/assets/pr-8-D5PGAWyz.png',
    stockStatus: 'instock',
    brand: 'UrbanDenim',
    hasVariants: false,
  },
  {
    id: 'pr-9',
    name: 'Men’s Blue Formal Shirt',
    sku: 'SH-MEN-009',
    price: 34.99,
    category: 'clothing',
    image: '/assets/pr-9-BDpG0GmU.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: false,
  },
  {
    id: 'pr-11',
    name: 'Women’s Beige Palazzo Pants',
    sku: 'PT-WMN-011',
    price: 28.99,
    category: 'clothing',
    image: '/assets/pr-11-eQyjF3vr.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: false,
  },
  {
    id: 'pr-12',
    name: 'Girls Floral Party Dress',
    sku: 'DR-KID-012',
    price: 31.99,
    category: 'clothing',
    image: '/assets/pr-12-C9E8vuC8.png',
    stockStatus: 'instock',
    brand: 'TinyTrend',
    hasVariants: false,
  },
  {
    id: 'pr-13',
    name: 'Men’s Checked Casual Shirt',
    sku: 'SH-MEN-013',
    price: 33.99,
    category: 'clothing',
    image: '/assets/pr-13-DYerNIVt.png',
    stockStatus: 'instock',
    brand: 'UrbanDenim',
    hasVariants: false,
  },
  {
    id: 'pr-19',
    name: 'Stainless Steel Analog Watch',
    sku: 'WT-MEN-001',
    price: 129.99,
    category: 'watches',
    image: '/assets/pr-19-DZ7YoUrR.png',
    stockStatus: 'instock',
    brand: 'Chronos',
    hasVariants: false,
  },
  {
    id: 'pr-14',
    name: 'Women’s Wrap Style Top',
    sku: 'TP-WMN-014',
    price: 24.99,
    category: 'clothing',
    image: '/assets/pr-14-CCZuqt_h.png',
    stockStatus: 'instock',
    brand: 'StyleHub',
    hasVariants: false,
  },
  {
    id: 'pr-1',
    name: 'Men’s Black Hoodie',
    sku: 'HD-MEN-001',
    price: 39.99,
    category: 'clothing',
    image: '/assets/pr-1-DpkbRlV7.png',
    stockStatus: 'instock',
    brand: 'UrbanDenim',
    hasVariants: false,
  },
  {
    id: 'pr-15',
    name: 'Men’s Leather Ankle Boots',
    sku: 'FT-MEN-001',
    price: 79.99,
    category: 'footwear',
    image: '/assets/pr-15-6fjzOYEd.png',
    stockStatus: 'instock',
    brand: 'PulseFit',
    hasVariants: false,
  },
  {
    id: 'pr-17',
    name: 'Women’s Glitter High Heels',
    sku: 'FT-WMN-003',
    price: 49.99,
    category: 'footwear',
    image: '/assets/pr-17-DRBVlZ_i.png',
    stockStatus: 'instock',
    brand: 'GlamourWalk',
    hasVariants: false,
  },
  {
    id: 'pr-18',
    name: 'Men’s Casual Sneakers',
    sku: 'FT-MEN-004',
    price: 54.99,
    category: 'footwear',
    image: '/assets/pr-18-7yzb-3ya.png',
    stockStatus: 'instock',
    brand: 'PulseFit',
    hasVariants: false,
  },
  {
    id: 'pr-28',
    name: 'Men’s Genuine Leather Wallet',
    sku: 'AC-WLT-002',
    price: 29.99,
    category: 'accessories',
    image: '/assets/pr-28-DCx9aLKb.png',
    stockStatus: 'instock',
    brand: 'NovaWear',
    hasVariants: false,
  },
  {
    id: 'pr-29',
    name: 'Men’s Classic Leather Belt',
    sku: 'AC-BLT-003',
    price: 19.99,
    category: 'accessories',
    image: '/assets/pr-29-BJ3rc4Q5.png',
    stockStatus: 'instock',
    brand: 'NovaWear',
    hasVariants: false,
  },
  {
    id: 'pr-37',
    name: 'Liquid Foundation Makeup',
    sku: 'BT-MKP-021',
    price: 24.99,
    category: 'beauty',
    image: '/assets/pr-37-CrN88d3y.png',
    stockStatus: 'instock',
    brand: 'GlowGoddess',
    hasVariants: false,
  },
  {
    id: 'pr-38',
    name: 'Herbal Skincare Essentials Set',
    sku: 'BT-SKN-022',
    price: 49.99,
    category: 'beauty',
    image: '/assets/pr-38-C9wZYe_k.png',
    stockStatus: 'instock',
    brand: 'GlowGoddess',
    hasVariants: false,
  },
  {
    id: 'pr-41',
    name: 'Indoor Exercise Spin Bike',
    sku: 'FT-CRD-032',
    price: 349.99,
    category: 'fitness',
    image: '/assets/pr-41-BqS_jmfx.png',
    stockStatus: 'instock',
    brand: 'IronPeak',
    hasVariants: false,
  },
  {
    id: 'pr-42',
    name: 'Multi-Function Bench Press Set',
    sku: 'FT-BCH-033',
    price: 499.99,
    category: 'fitness',
    image: '/assets/pr-42-J9Sh46nX.png',
    stockStatus: 'instock',
    brand: 'IronPeak',
    hasVariants: false,
  },
];

export const INITIAL_CART: CartItem[] = [
  {
    id: 'pr-25-PK6',
    product: PRODUCTS.find((p) => p.id === 'pr-25') || PRODUCTS[0],
    quantity: 1,
    selectedUOM: { name: 'Pack of 6 (6L)', shortCode: 'PK6', price: 13.99 },
    unitPrice: 13.99,
  },
  {
    id: 'pr-20-44mm-Titanium',
    product: PRODUCTS.find((p) => p.id === 'pr-20') || PRODUCTS[0],
    quantity: 1,
    selectedVariants: {
      'Case Size & Memory': { label: '44mm (128GB)', priceDelta: 30 },
      Color: { label: 'Titanium Gray', priceDelta: 0 },
    },
    unitPrice: 129.99,
  },
  {
    id: 'pr-35',
    product: PRODUCTS.find((p) => p.id === 'pr-35') || PRODUCTS[0],
    quantity: 1,
    unitPrice: 89.99,
  },
];

export const INITIAL_INVOICES: Invoice[] = [
  { id: '#INV-1023', customer: 'Walk-in', paymentMethod: 'Cash', date: '18 Aug 2026', time: '10:45 AM', amount: 1250, status: 'Completed' },
  { id: '#INV-1024', customer: 'Rahul Patel', paymentMethod: 'UPI', date: '18 Aug 2026', time: '11:10 AM', amount: 3420, status: 'Completed' },
  { id: '#INV-1025', customer: 'Neha Sharma', paymentMethod: 'Card', date: '18 Aug 2026', time: '12:05 PM', amount: 2180, status: 'Completed' },
  { id: '#INV-1026', customer: 'Walk-in', paymentMethod: 'Cash', date: '18 Aug 2026', time: '01:20 PM', amount: 980, status: 'Completed' },
  { id: '#INV-1027', customer: 'Amit Verma', paymentMethod: 'UPI', date: '18 Aug 2026', time: '02:05 PM', amount: 4760, status: 'Completed' },
  { id: '#INV-1028', customer: 'Pooja Shah', paymentMethod: 'Card', date: '18 Aug 2026', time: '02:40 PM', amount: 1890, status: 'Completed' },
  { id: '#INV-1029', customer: 'Walk-in', paymentMethod: 'Cash', date: '18 Aug 2026', time: '03:15 PM', amount: 640, status: 'Completed' },
];
