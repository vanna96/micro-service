import { withSentryConfig } from "@sentry/nextjs/config";
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
    devIndicators: false,
    distDir: process.env.NEXT_BUILD_DIR || ".next",
    async redirects() {
        return [
            { source: "/pos", destination: "/admin", permanent: true },
            {
                source: "/pos/display",
                destination: "/admin/display",
                permanent: true,
            },
        ];
    },
};

export default withSentryConfig(nextConfig, {
    silent: true,
    widenClientFileUpload: true,
});
