import Head from "next/head";
import { GotPosDashboard } from "@/components/gotpos-dashboard";

export default function Home() {
  return (
    <>
      <Head>
        <title>POS System | GotPOS - Bootstrap Admin Dashboard Template</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
        <meta name="description" content="GotPOS - Modern POS System" />
        <link rel="shortcut icon" href="/assets/favicon-B-3ALmIB.ico" />
      </Head>
      <GotPosDashboard />
    </>
  );
}
