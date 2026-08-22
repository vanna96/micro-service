import type { Metadata, Viewport } from "next";
import "@/styles/gotpos-custom.css";
import { Providers } from "./providers";

export const metadata: Metadata = {
  title: "POS System | GotPOS - Next.js App Router",
  description: "High-performance modern POS System built with Next.js App Router, React 19 & Redux Toolkit",
  icons: {
    icon: "/assets/favicon-B-3ALmIB.ico",
  },
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
  userScalable: false,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className="scroll-smooth group"
      data-layout="two-column"
      data-content-width="fluid"
      data-bs-theme="light"
      data-sidebar-colors="light"
      data-sidebar="large"
      data-nav-type="default"
      dir="ltr"
      data-colors="default"
    >
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet"
        />
        <link rel="stylesheet" href="/assets/admin-Bly6avC4.css" />
        <link rel="stylesheet" href="/assets/air-datepicker-ByzRugGb.css" />
        <link rel="stylesheet" href="/assets/filepond-plugin-image-preview-BLtz_BYx.css" />
        <link rel="stylesheet" href="/assets/virtual-select-hfXZVdeB.css" />
        <link
          rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css"
        />
        <link
          rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        />
      </head>
      <body className="sidebar-hidden">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
