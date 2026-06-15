const { chromium } = require('playwright');

const SCROLL_ROUNDS = parseInt(process.env.YANDEX_PARSER_SCROLL_ROUNDS || '60', 10);
const SCROLL_DELAY_MS = parseInt(process.env.YANDEX_PARSER_SCROLL_DELAY_MS || '500', 10);
const SCROLL_SETTLE_MS = parseInt(process.env.YANDEX_PARSER_SCROLL_SETTLE_MS || '800', 10);
const STALE_LIMIT = parseInt(process.env.YANDEX_PARSER_STALE_LIMIT || '10', 10);
const ASPECT_LIMIT = parseInt(process.env.YANDEX_PARSER_ASPECT_LIMIT || '15', 10);
const COLLECT_ASPECTS = process.env.YANDEX_PARSER_ASPECTS === '1';

function parseReviewsFromJson(html) {
    const idx = html.indexOf('"reviews":[{');
    if (idx === -1) return [];

    const start = idx + '"reviews":'.length;
    let depth = 0;
    let arrayStr = '';

    for (let i = start; i < html.length; i++) {
        const char = html[i];
        arrayStr += char;
        if (char === '[') depth++;
        else if (char === ']') {
            depth--;
            if (depth === 0) break;
        }
    }

    try {
        return JSON.parse(arrayStr);
    } catch {
        return [];
    }
}

function normalizeReview(r) {
    return {
        review_id: r.reviewId || r.review_id || `review-${Math.random().toString(36).slice(2)}`,
        author: typeof r.author === 'object' ? (r.author?.name || '') : (r.author || ''),
        text: r.text || '',
        rating: Number(r.rating) || 0,
        date: r.updatedTime || r.date || null,
    };
}

function extractCompanyInfo(html) {
    const company = { name: '', rating: 0, reviewCount: 0, ratingCount: 0 };

    const ratingMatch = html.match(/"ratingValue"\s*:\s*([\d.]+)/);
    const reviewCountMatch = html.match(/"reviewCount"\s*:\s*(\d+)/);
    const ratingCountMatch = html.match(/"ratingCount"\s*:\s*(\d+)/);

    if (ratingMatch) company.rating = parseFloat(ratingMatch[1]);
    if (reviewCountMatch) company.reviewCount = parseInt(reviewCountMatch[1], 10);
    if (ratingCountMatch) company.ratingCount = parseInt(ratingCountMatch[1], 10);

    const shortTitleMatch = html.match(/"shortTitle"\s*:\s*"([^"]+)"/);
    if (shortTitleMatch) company.name = shortTitleMatch[1];

    return company;
}

function addReviews(allReviews, reviews) {
    for (const review of reviews) {
        const normalized = normalizeReview(review);
        allReviews.set(normalized.review_id, normalized);
    }
}

function attachReviewCollector(page, allReviews, aspectNames) {
    page.on('response', async (response) => {
        const responseUrl = response.url();
        if (!responseUrl.includes('fetchReviews')) {
            return;
        }

        try {
            const data = await response.json();
            if (data.error) {
                return;
            }

            addReviews(allReviews, data.data?.reviews || data.reviews || []);

            const aspects = data.data?.aspects;
            if (aspects?.length) {
                aspectNames.splice(0, aspectNames.length, ...aspects.map((aspect) => aspect.text).filter(Boolean));
            }
        } catch {
            // ignore non-JSON or parse errors
        }
    });
}

async function scrollForMore(page, allReviews) {
    let prevCount = allReviews.size;
    let staleRounds = 0;

    for (let round = 0; round < SCROLL_ROUNDS; round++) {
        await page.mouse.wheel(0, 5000);
        await page.waitForTimeout(SCROLL_DELAY_MS);

        await page.evaluate(() => {
            document.querySelectorAll('.scroll__container, .business-reviews-card-view__reviews').forEach((el) => {
                el.scrollTop = el.scrollHeight;
            });
        });

        await page.waitForTimeout(SCROLL_SETTLE_MS);

        const currentCount = allReviews.size;
        if (currentCount === prevCount) {
            staleRounds++;
            if (staleRounds >= STALE_LIMIT) {
                break;
            }
        } else {
            staleRounds = 0;
        }
        prevCount = currentCount;
    }
}

async function resetReviewsView(page) {
    try {
        const tab = page.getByRole('tab', { name: /Отзывы/i }).first();
        if (await tab.count()) {
            await tab.click({ force: true });
            await page.waitForTimeout(2000);
        }
    } catch {
        // already on reviews tab or overlay blocks the click
    }
}

async function clickAspectFilter(page, aspectName) {
    const escaped = aspectName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const chip = page.getByText(new RegExp(`${escaped}\\s*•`)).first();
    if (!(await chip.count())) {
        return false;
    }

    await chip.click();
    await page.waitForTimeout(2500);
    return true;
}

async function scrape(url) {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox'],
    });

    const page = await browser.newPage({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        locale: 'ru-RU',
        viewport: { width: 1280, height: 900 },
    });

    const allReviews = new Map();
    const aspectNames = [];
    attachReviewCollector(page, allReviews, aspectNames);

    let reviewsUrl = url.replace(/\/$/, '');
    if (!reviewsUrl.includes('/reviews')) {
        reviewsUrl += '/reviews';
    }
    reviewsUrl += '/';

    await page.goto(reviewsUrl, { waitUntil: 'networkidle', timeout: 120000 });
    await page.waitForTimeout(2000);

    const pageHtml = await page.content();
    const company = extractCompanyInfo(pageHtml);
    addReviews(allReviews, parseReviewsFromJson(pageHtml));

    await scrollForMore(page, allReviews);

    if (COLLECT_ASPECTS && aspectNames.length > 0) {
        for (const aspectName of aspectNames.slice(0, ASPECT_LIMIT)) {
            try {
                await resetReviewsView(page);

                if (!(await clickAspectFilter(page, aspectName))) {
                    continue;
                }

                await scrollForMore(page, allReviews);
            } catch {
                // skip failed aspect filter and continue with the next one
            }
        }
    }

    await browser.close();

    const reviews = Array.from(allReviews.values());

    if (!company.name && reviews.length > 0) {
        company.name = 'Организация';
    }

    return {
        company,
        reviews,
        total_fetched: reviews.length,
    };
}

const url = process.argv[2];
if (!url) {
    console.error(JSON.stringify({ error: 'URL argument is required' }));
    process.exit(1);
}

scrape(url)
    .then((result) => {
        process.stdout.write(JSON.stringify(result));
    })
    .catch((err) => {
        console.error(JSON.stringify({ error: err.message }));
        process.exit(1);
    });
