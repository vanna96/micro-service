import * as Sentry from "@sentry/nextjs";
import type { NextApiRequest, NextApiResponse } from "next";

export default function handler(_req: NextApiRequest, res: NextApiResponse) {
  try {
    throw new Error("Sentry Next.js Test Exception - Integration verified successfully!");
  } catch (error) {
    Sentry.captureException(error);
    return res.status(200).json({
      status: "ok",
      message: "Sentry test exception triggered and captured.",
      dsn_configured: Boolean(process.env.NEXT_PUBLIC_SENTRY_DSN || process.env.SENTRY_DSN),
    });
  }
}
