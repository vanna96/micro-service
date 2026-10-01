"use client";

import StatusPage from "@/components/status-page";

export default function AppError({ reset }: { error: Error; reset: () => void }) {
  return <StatusPage statusCode={500} onRetry={reset} />;
}

