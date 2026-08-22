import type { NextApiRequest, NextApiResponse } from "next";
import { apiBase } from "@/lib/storefront";

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== "POST") {
    res.setHeader("Allow", "POST");
    return res.status(405).json({ message: "Method not allowed" });
  }

  try {
    const response = await fetch(`${apiBase}/orders`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(req.body),
    });
    const body = await response.json();

    return res.status(response.status).json(body);
  } catch {
    return res.status(202).json({
      order_number: `LOCAL-${Date.now()}`,
      status: "active",
      items: req.body.items ?? [],
    });
  }
}
