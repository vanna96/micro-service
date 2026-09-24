import { Html, Head, Main, NextScript } from "next/document";

export default function Document() {
  return (
    <Html
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
      <Head>
        <link rel="icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="icon" href="/branding/v-pos-mark-32.png" type="image/png" sizes="32x32" />
        <link rel="shortcut icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="apple-touch-icon" href="/branding/v-pos-mark.png" />
        {/* Google Fonts: Plus Jakarta Sans & Inter */}
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
      </Head>
      <body className="sidebar-hidden">
        <Main />
        <NextScript />
      </body>
    </Html>
  );
}
