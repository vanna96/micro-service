#!/usr/bin/env node

'use strict';

const http = require('http');
const https = require('https');

const stages = (process.argv[2] || '10').split(',').map(Number);
const seconds = Number(process.argv[3] || 20);
const domain = process.argv[4];
const targetHost = process.env.TARGET_HOST || '127.0.0.1';
const targetPort = Number(process.env.TARGET_PORT || 80);
const useTls = process.env.TARGET_TLS === '1';
const transport = useTls ? https : http;
const ipPoolSize = Math.max(16, Math.min(4096, Number(process.env.IP_POOL_SIZE || 512)));
let ipCounter = Number(process.env.IP_OFFSET || 0);

if (!domain) {
    console.error('Usage: node mixed-mobile-api.js <concurrency[,concurrency...]> <seconds> <tenant-domain>');
    process.exit(2);
}

function clientIp() {
    // Reuse a realistic client pool. Generating a brand-new address per request
    // measures security-cache churn instead of catalog throughput.
    const number = (ipCounter++ % ipPoolSize) + 1;

    return `198.${18 + ((number >> 16) & 1)}.${(number >> 8) & 255}.${number & 255}`;
}

function percentile(values, quantile) {
    if (!values.length) {
        return 0;
    }

    const sorted = [...values].sort((left, right) => left - right);

    return sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * quantile))];
}

function request(agent, method, path, body, captureBody = false) {
    return new Promise((resolve) => {
        const payload = body ? JSON.stringify(body) : null;
        const started = process.hrtime.bigint();
        const ip = clientIp();
        const req = transport.request({
            host: targetHost,
            port: targetPort,
            method,
            path,
            agent,
            timeout: 5000,
            headers: {
                Host: domain,
                Accept: 'application/json',
                'X-Forwarded-For': ip,
                'X-Real-IP': ip,
                ...(payload ? {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(payload),
                } : {}),
            },
            ...(useTls ? {
                servername: domain,
                rejectUnauthorized: process.env.TARGET_TLS_VERIFY !== '0',
            } : {}),
        }, (response) => {
            let bytes = 0;
            const chunks = [];

            response.on('data', (chunk) => {
                bytes += chunk.length;
                if (captureBody) {
                    chunks.push(chunk);
                }
            });
            response.on('end', () => resolve({
                status: response.statusCode,
                milliseconds: Number(process.hrtime.bigint() - started) / 1e6,
                bytes,
                body: captureBody ? Buffer.concat(chunks).toString() : '',
            }));
        });

        req.on('timeout', () => req.destroy(new Error('timeout')));
        req.on('error', (error) => resolve({
            error: error.message,
            milliseconds: Number(process.hrtime.bigint() - started) / 1e6,
            bytes: 0,
            body: '',
        }));

        if (payload) {
            req.write(payload);
        }
        req.end();
    });
}

async function main() {
    const setupAgent = new transport.Agent({ keepAlive: true, maxSockets: 2 });
    const probe = await request(setupAgent, 'GET', '/v1/api/mobile/products?per_page=20', null, true);
    setupAgent.destroy();

    if (probe.status !== 200) {
        throw new Error(`Product probe failed with HTTP ${probe.status}: ${probe.body.slice(0, 200)}`);
    }

    const products = JSON.parse(probe.body).data || [];
    if (!products.length) {
        throw new Error('No active products are available for the mixed API benchmark.');
    }

    const itemIds = products.map((product) => product.id);
    const searchTerms = products
        .map((product) => String(product.name || '').split(/\s+/)[0])
        .filter(Boolean);
    const specifications = [
        { name: 'bootstrap', weight: 5, make: () => ['GET', '/v1/api/mobile/bootstrap'] },
        { name: 'products', weight: 25, make: () => ['GET', `/v1/api/mobile/products?per_page=20&page=${1 + Math.floor(Math.random() * 2)}`] },
        { name: 'search', weight: 15, make: () => ['GET', `/v1/api/mobile/products?per_page=20&search=${encodeURIComponent(searchTerms[Math.floor(Math.random() * searchTerms.length)] || 'a')}`] },
        { name: 'detail', weight: 15, make: () => ['GET', `/v1/api/mobile/products/${itemIds[Math.floor(Math.random() * itemIds.length)]}`] },
        { name: 'categories', weight: 10, make: () => ['GET', '/v1/api/mobile/categories?per_page=20'] },
        { name: 'branches', weight: 5, make: () => ['GET', '/v1/api/mobile/branches'] },
        { name: 'banners', weight: 5, make: () => ['GET', '/v1/api/mobile/banners?type=mobile'] },
        { name: 'currencies', weight: 5, make: () => ['GET', '/v1/api/mobile/currencies'] },
        {
            name: 'cart_price',
            weight: 15,
            make: () => ['POST', '/v1/api/mobile/cart/price', {
                items: [{
                    item_id: itemIds[Math.floor(Math.random() * itemIds.length)],
                    quantity: 1 + Math.floor(Math.random() * 3),
                }],
                currency_mode: 'native',
            }],
        },
    ];
    const weighted = specifications.flatMap((specification) => Array(specification.weight).fill(specification));

    for (const concurrency of stages) {
        const agent = new transport.Agent({ keepAlive: true, maxSockets: concurrency + 2 });
        const started = Date.now();
        const deadline = started + (seconds * 1000);
        const allLatencies = [];
        const statuses = {};
        const byEndpoint = Object.fromEntries(specifications.map((specification) => [
            specification.name,
            { requests: 0, successes: 0, latencies: [] },
        ]));
        let successes = 0;
        let failures = 0;
        let bytes = 0;

        async function worker() {
            while (Date.now() < deadline) {
                const specification = weighted[Math.floor(Math.random() * weighted.length)];
                const [method, path, body] = specification.make();
                const result = await request(agent, method, path, body);
                const endpoint = byEndpoint[specification.name];

                endpoint.requests++;
                endpoint.latencies.push(result.milliseconds);
                allLatencies.push(result.milliseconds);
                bytes += result.bytes || 0;

                const status = result.error || String(result.status);
                statuses[status] = (statuses[status] || 0) + 1;

                if (!result.error && result.status >= 200 && result.status < 300) {
                    successes++;
                    endpoint.successes++;
                } else {
                    failures++;
                }
            }
        }

        await Promise.all(Array.from({ length: concurrency }, worker));
        agent.destroy();

        const elapsed = (Date.now() - started) / 1000;
        const endpoints = {};
        for (const [name, endpoint] of Object.entries(byEndpoint)) {
            endpoints[name] = {
                requests: endpoint.requests,
                successes: endpoint.successes,
                p50_ms: Number(percentile(endpoint.latencies, 0.5).toFixed(1)),
                p95_ms: Number(percentile(endpoint.latencies, 0.95).toFixed(1)),
            };
        }

        console.log(JSON.stringify({
            concurrency,
            target_seconds: seconds,
            elapsed_seconds: Number(elapsed.toFixed(2)),
            requests: successes + failures,
            successes,
            failures,
            statuses,
            requests_per_second: Number((successes / elapsed).toFixed(1)),
            p50_ms: Number(percentile(allLatencies, 0.5).toFixed(1)),
            p95_ms: Number(percentile(allLatencies, 0.95).toFixed(1)),
            p99_ms: Number(percentile(allLatencies, 0.99).toFixed(1)),
            throughput_mbps: Number(((bytes * 8 / elapsed) / 1e6).toFixed(2)),
            endpoints,
        }));
    }
}

main().catch((error) => {
    console.error(error.stack);
    process.exit(1);
});
