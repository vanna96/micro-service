// Run with NODE_PATH pointing to a temporary Playwright installation and the
// fixture directory exported by MonitoringDashboardTest as the first argument.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const directory = process.argv[2];
    assert(directory, 'Pass the exported monitoring fixture directory.');
    const source = fs.readFileSync(path.join(directory, 'index.html'), 'utf8');
    const refreshed = JSON.parse(fs.readFileSync(path.join(directory, 'refresh.json'), 'utf8'));
    const empty = JSON.parse(fs.readFileSync(path.join(directory, 'empty-refresh.json'), 'utf8'));
    let emptyMetrics = false;
    const endpoint = source.match(/data-update-uri="([^"]+)"/)[1];
    const origin = new URL(endpoint).origin;
    const html = source.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, block =>
        /monitoring-chart-data|const componentRoot =|apexcharts.min.js|data-update-uri/.test(block) ? block : '');
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.setViewportSize({ width: 1600, height: 1000 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.clock.install();
        let live = false;
        let refreshStatus = 200;
        let preferenceStatus = 200;
        let holdRefresh;
        let releaseRefresh;
        const refreshes = [];
        await page.route('**/*', async route => {
            const request = route.request();
            const url = new URL(request.url());
            if (url.pathname === '/admin/monitoring') {
                return route.fulfill({ contentType: 'text/html', body: html.replace('data-live-enabled="false"', `data-live-enabled="${live}"`) });
            }
            if (url.pathname === new URL(endpoint).pathname) {
                const payload = request.postDataJSON();
                const calls = payload.components.flatMap(component => component.calls);
                const refreshing = calls.some(call => call.method === 'refreshDashboard');
                const saving = calls.some(call => call.method === 'setLiveEnabled');
                if (refreshing) refreshes.push(calls.find(call => call.method === 'refreshDashboard').params[0]);
                const status = saving ? preferenceStatus : (refreshStatus === 409 ? 200 : refreshStatus);
                if (status !== 200) return route.fulfill({ status, contentType: 'application/json', body: '{}' });
                const components = payload.components.map(component => {
                    const snapshot = JSON.parse(component.snapshot);
                    const effects = { returns: [] };
                    component.calls.forEach(call => {
                        if (call.method === 'setLiveEnabled') {
                            live = call.params[0];
                            effects.returns.push(null);
                        } else if (call.method === 'refreshDashboard') {
                            if (refreshStatus === 409) live = false;
                            const shouldRefresh = call.params[0] || live;
                            effects.returns.push({liveEnabled: live, refreshed: shouldRefresh, updatedAt: '12:34:56'});
                            if (shouldRefresh) effects.html = (emptyMetrics ? empty : refreshed).components[0].effects.html;
                        } else throw new Error(`Unexpected Livewire action: ${call.method}`);
                    });
                    snapshot.data.liveEnabled = live;
                    return {snapshot: JSON.stringify(snapshot), effects};
                });
                if (refreshing && holdRefresh) await holdRefresh;
                return route.fulfill({ contentType: 'application/json', body: JSON.stringify({components, assets: []}) });
            }
            if (/^\/livewire(?:-[a-zA-Z0-9]+)?\/livewire(?:\.min)?\.js$/.test(url.pathname)) {
                return route.fulfill({path: path.resolve(__dirname, '../../vendor/livewire/livewire/dist', path.basename(url.pathname))});
            }
            const file = path.resolve(__dirname, '../../public', '.' + url.pathname);
            if (file.startsWith(path.resolve(__dirname, '../../public') + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
                return route.fulfill({ path: file });
            }
            return route.fulfill({ status: 404, body: '' });
        });
        await page.goto(origin + '/admin/monitoring');
        await page.clock.runFor(16000);
        assert.equal(refreshes.length, 0, 'Paused page must not poll.');
        await page.waitForFunction(() => document.querySelectorAll('.monitor-overview-chart svg.apexcharts-svg').length === 6);
        assert.equal(await page.locator('.monitor-chart svg.apexcharts-svg').count(), 0, 'Detailed graphs stay deferred until opened.');
        assert.equal(await page.locator('#monitor-overview-memory .apexcharts-datalabel-value').textContent(), '37.5%');
        assert.equal(await page.locator('#monitor-overview-disk .apexcharts-datalabel-value').textContent(), '31.3%');
        await page.screenshot({ path: path.join(directory, 'monitoring-desktop.png'), fullPage: true });
        await page.evaluate(() => {
            window.monitoringChartElement = document.querySelector('#monitor-overview-resources');
            window.monitoringRenders = 0;
            window.monitoringDestroys = 0;
            const render = ApexCharts.prototype.render;
            const destroy = ApexCharts.prototype.destroy;
            ApexCharts.prototype.render = function (...args) { window.monitoringRenders++; return render.apply(this, args); };
            ApexCharts.prototype.destroy = function (...args) { window.monitoringDestroys++; return destroy.apply(this, args); };
        });
        await page.click('#monitor-refresh-now');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-refresh-now').disabled);
        assert.equal(refreshes.at(-1), true, 'Manual refresh uses a forced Livewire action.');
        assert.equal(await page.locator('#monitor-overview-memory .apexcharts-datalabel-value').textContent(), '50%');
        assert(await page.locator('#overview').textContent().then(text => text.includes('31.5%')), 'Livewire updates metric text.');
        assert(await page.evaluate(() => window.monitoringChartElement === document.querySelector('#monitor-overview-resources')), 'Livewire preserves the chart container.');
        assert.deepEqual(await page.evaluate(() => [window.monitoringRenders, window.monitoringDestroys]), [0, 0], 'Refresh updates charts without recreating them.');
        assert.equal(await page.textContent('#monitor-live-state'), 'Paused');
        await page.locator('#graphs > summary').click();
        await page.clock.runFor(1000);
        await page.waitForFunction(() => document.querySelectorAll('.monitor-chart svg.apexcharts-svg').length === 4);
        assert.equal(await page.locator('.monitor-chart svg.apexcharts-svg').count(), 4, 'All four graphs render.');
        assert.equal(await page.locator('.monitor-overview-chart svg.apexcharts-svg').count(), 6, 'Opening history preserves overview charts.');
        await page.locator('#graphs > summary').click();
        await page.clock.runFor(500);
        await page.waitForFunction(() => document.querySelectorAll('.monitor-overview-chart svg.apexcharts-svg').length === 6);
        assert.equal(await page.locator('.monitor-chart svg.apexcharts-svg').count(), 4, 'Closing history preserves its chart instances.');
        await page.locator('#graphs > summary').click();
        await page.clock.runFor(500);
        await page.locator('#server > summary').click();
        await page.click('#monitor-toggle-live');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-toggle-live').disabled && !document.querySelector('#monitor-refresh-now').disabled);
        assert.equal(live, true);
        assert.equal(await page.textContent('#monitor-live-state'), 'Live');
        await page.clock.runFor(16000);
        await page.waitForFunction(() => !document.querySelector('#monitor-refresh-now').disabled);
        assert(await page.locator('#graphs').evaluate(element => element.open));
        assert(await page.locator('#server').evaluate(element => element.open));
        await page.clock.runFor(250);
        assert.equal(await page.locator('.monitor-chart svg.apexcharts-svg').count(), 4, 'Refresh preserves panels without duplicate graphs.');
        assert.equal(await page.locator('.monitor-overview-chart svg.apexcharts-svg').count(), 6, 'Refresh does not duplicate overview charts.');

        holdRefresh = new Promise(resolve => { releaseRefresh = resolve; });
        await page.clock.runFor(16000);
        await page.waitForFunction(() => document.querySelector('#monitor-refresh-now').disabled);
        await page.click('#monitor-toggle-live');
        await page.clock.runFor(500);
        assert.equal(await page.textContent('#monitor-live-state'), 'Paused', 'Pause takes effect immediately while a Livewire refresh is in flight.');
        releaseRefresh();
        holdRefresh = null;
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-toggle-live').disabled && !document.querySelector('#monitor-refresh-now').disabled);
        assert.equal(await page.textContent('#monitor-live-state'), 'Paused', 'An in-flight refresh must respect Pause.');
        const countAfterPause = refreshes.length;
        await page.clock.runFor(16000);
        assert.equal(refreshes.length, countAfterPause);
        await page.reload();
        assert.equal(await page.textContent('#monitor-live-state'), 'Paused');

        refreshStatus = 409;
        await page.click('#monitor-toggle-live');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-toggle-live').disabled && !document.querySelector('#monitor-refresh-now').disabled);
        assert.equal(await page.textContent('#monitor-live-state'), 'Paused', 'Server Pause stops polling.');
        refreshStatus = 500;
        await page.click('#monitor-refresh-now');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-refresh-now').disabled);
        assert.equal(await page.textContent('#monitor-live-state'), 'Refresh unavailable');
        assert.equal(await page.locator('#containers').count(), 1, 'Failed refresh preserves the dashboard.');
        preferenceStatus = 503;
        await page.click('#monitor-toggle-live');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-toggle-live').disabled);
        assert.equal(await page.getAttribute('#monitor-toggle-live', 'aria-pressed'), 'true');
        assert.equal(await page.textContent('#monitor-live-state'), 'Refresh unavailable');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.clock.runFor(500);
        assert.deepEqual(await page.locator('#overview .monitor-card').evaluateAll(cards => cards.filter(card => {
            const bounds = card.getBoundingClientRect();
            return bounds.left < 0 || bounds.right > window.innerWidth || card.scrollWidth > card.clientWidth;
        }).map(card => ({title: card.textContent.trim().slice(0, 60), width: card.clientWidth, scroll: card.scrollWidth}))), [], 'Overview cards fit the mobile viewport.');
        assert.equal(await page.locator('#monitoring-status').evaluate(element => getComputedStyle(element).position), 'static', 'Status panel must remain in the document flow.');
        await page.screenshot({ path: path.join(directory, 'monitoring-mobile.png'), fullPage: true });
        refreshStatus = 200;
        emptyMetrics = true;
        await page.click('#monitor-refresh-now');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-refresh-now').disabled);
        await page.waitForFunction(() => document.querySelectorAll('.monitor-overview-chart svg.apexcharts-svg').length === 6);
        assert.equal(await page.locator('#monitor-overview-memory .apexcharts-text').filter({hasText: 'No capacity samples available'}).count(), 1);
        assert.equal(await page.locator('#monitor-overview-disk .apexcharts-text').filter({hasText: 'No capacity samples available'}).count(), 1);
        assert.equal(await page.locator('#monitor-overview-resources .apexcharts-text').filter({hasText: 'No historical samples yet'}).count(), 1);
        emptyMetrics = false;
        await page.click('#monitor-refresh-now');
        await page.clock.runFor(500);
        await page.waitForFunction(() => !document.querySelector('#monitor-refresh-now').disabled);
        assert.equal(await page.locator('#monitor-overview-memory .apexcharts-datalabel-value').textContent(), '50%', 'Capacity charts recover when metrics return.');
        assert.equal(await page.locator('#monitor-overview-resources .apexcharts-text').filter({hasText: 'No historical samples yet'}).count(), 0, 'Trend charts recover when metrics return.');
        assert.deepEqual(errors, [], 'Monitoring must not produce browser exceptions.');
        console.log('PASS: Livewire DOM morphing and persistent chart instances, six overview charts, capacity percentages, empty-data recovery, four detailed graphs, pause/resume, persisted pause, manual/automatic refresh, in-flight pause, server pause, failure recovery, panel preservation, mobile rendering.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
