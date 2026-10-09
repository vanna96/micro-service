import type { AppProps } from "next/app";
import { Provider } from "react-redux";
import { store } from "@/store";
import { AntiInspectShield } from "@/components/security/anti-inspect-shield";
import { LanguageProvider } from "@/lib/i18n/i18n";
import { PosAssets } from "@/components/pos/pos-assets";
import { ShopProvider } from "@/components/shop/shop-provider";
import { useRouter } from "next/router";
import { useEffect } from "react";
import "@/styles/shop.css";

export default function App({ Component, pageProps }: AppProps) {
    const router = useRouter();
    const pos =
        router.pathname === "/admin" || router.pathname.startsWith("/admin/");
    useEffect(() => {
        document.body.dataset.experience = pos ? "pos" : "shop";
        document.body.classList.toggle("sidebar-hidden", pos);
    }, [pos]);
    if (!pos)
        return (
            <ShopProvider
                key={pageProps.bootstrap?.store?.id || "directory"}
                initial={pageProps.bootstrap}
            >
                <Component {...pageProps} />
            </ShopProvider>
        );
    return (
        <div className="pos-app">
            <PosAssets />
            <Provider store={store}>
                <LanguageProvider>
                    <AntiInspectShield />
                    <Component {...pageProps} />
                </LanguageProvider>
            </Provider>
        </div>
    );
}
