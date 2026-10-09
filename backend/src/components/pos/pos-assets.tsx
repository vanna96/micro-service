import Head from "next/head";

// Existing Laravel styles and fonts are intentionally loaded only on POS pages.
/* eslint-disable @next/next/no-css-tags, @next/next/no-page-custom-font */

export function PosAssets() {
    return (
        <Head>
            {/* Google Fonts: Plus Jakarta Sans & Inter */}
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link
                rel="preconnect"
                href="https://fonts.gstatic.com"
                crossOrigin="anonymous"
            />
            <link
                href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
                rel="stylesheet"
            />
            <link rel="stylesheet" href="/assets/admin-Bly6avC4.css" />
            <link rel="stylesheet" href="/pos/vpos-custom.css" />
            <link rel="stylesheet" href="/assets/air-datepicker-ByzRugGb.css" />
            <link
                rel="stylesheet"
                href="/assets/filepond-plugin-image-preview-BLtz_BYx.css"
            />
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
    );
}
