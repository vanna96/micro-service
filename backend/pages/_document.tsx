import { Html, Head, Main, NextScript } from "next/document";

export default function Document() {
    return (
        <Html lang="en">
            <Head>
                <link
                    rel="icon"
                    href="/branding/v-pos-mark.svg"
                    type="image/svg+xml"
                />
                <link
                    rel="icon"
                    href="/branding/v-pos-mark-32.png"
                    type="image/png"
                    sizes="32x32"
                />
                <link
                    rel="shortcut icon"
                    href="/branding/v-pos-mark.svg"
                    type="image/svg+xml"
                />
                <link rel="apple-touch-icon" href="/branding/v-pos-mark.png" />
                <link rel="stylesheet" href="/fonts/nextjs-fonts/fonts.css" />
            </Head>
            <body>
                <Main />
                <NextScript />
            </body>
        </Html>
    );
}
