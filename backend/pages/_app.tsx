import type { AppProps } from "next/app";
import "@/styles/vpos-custom.css";
import { Provider } from "react-redux";
import { store } from "@/store";
import { AntiInspectShield } from "@/components/security/anti-inspect-shield";
import { LanguageProvider } from "@/lib/i18n/i18n";

export default function App({ Component, pageProps }: AppProps) {
  return (
    <Provider store={store}>
      <LanguageProvider>
        <AntiInspectShield />
        <Component {...pageProps} />
      </LanguageProvider>
    </Provider>
  );
}
