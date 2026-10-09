import type { Metadata, Viewport } from "next";
import "../../public/pos/vpos-custom.css";
import { Providers } from "./providers";
import { AntiInspectShield } from "@/components/security/anti-inspect-shield";

export const metadata: Metadata = {
  title: "V-POS | Point of Sale",
  description: "V-POS modern point-of-sale system",
  icons: {
    icon: "/branding/v-pos-mark.svg",
    apple: "/branding/v-pos-mark.png",
  },
  appleWebApp: {
    capable: true,
    title: "V-POS",
    statusBarStyle: "black-translucent",
  },
  manifest: "/manifest.json",
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
        <link rel="stylesheet" href="/fonts/nextjs-fonts/fonts.css" />
        <link rel="stylesheet" href="/assets/admin-Bly6avC4.css" />
        <link rel="stylesheet" href="/assets/air-datepicker-ByzRugGb.css" />
        <link rel="stylesheet" href="/assets/filepond-plugin-image-preview-BLtz_BYx.css" />
        <link rel="stylesheet" href="/assets/virtual-select-hfXZVdeB.css" />
        <link rel="stylesheet" href="/assets/vendor/remixicon/remixicon.css" />
        <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" />
      </head>
      <body className="sidebar-hidden">
        <Providers>
          <AntiInspectShield />
          {children}
        </Providers>
      </body>
    </html>
  );
}
