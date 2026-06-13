const { chromium } = require('playwright');

const SCROLL_ROUNDS = 40;
const SCROLL_DELAY_MS = 1200;
const STALE_LIMIT = 4;

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

async function scrape(url) {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        locale: 'ru-RU',
        viewport: { width: 1280, height: 900 },
    });

    const page = await context.newPage();
    const allReviews = new Map();

    page.on('response', async (response) => {
        const responseUrl = response.url();
        if (!responseUrl.includes('fetchReviews') && !responseUrl.includes('getReviews')) {
            return;
        }

        try {
            const contentType = response.headers()['content-type'] || '';
            if (!contentType.includes('json')) return;

            const data = await response.json();
            const reviews = data.reviews || data.data?.reviews || [];
            for (const review of reviews) {
                const normalized = normalizeReview(review);
                allReviews.set(normalized.review_id, normalized);
            }
        } catch {
            // ignore non-JSON or parse errors
        }
    });

    let reviewsUrl = url.replace(/\/$/, '');
    if (!reviewsUrl.includes('/reviews')) {
        reviewsUrl += '/reviews';
    }
    reviewsUrl += '/';

    await page.goto(reviewsUrl, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(3000);

    const pageHtml = await page.content();
    const company = extractCompanyInfo(pageHtml);

    for (const review of parseReviewsFromJson(pageHtml)) {
        const normalized = normalizeReview(review);
        allReviews.set(normalized.review_id, normalized);
    }

    const domReviews = await page.evaluate(() => {
        const results = [];
        document.querySelectorAll('.business-review-view__info').forEach((el, index) => {
            const author = el.querySelector('[itemprop="name"]')?.textContent?.trim() || '';
            const dateEl = el.querySelector('.business-review-view__date');
            const date = dateEl?.getAttribute('datetime') || dateEl?.textContent?.trim() || '';
            const text = el.querySelector('.business-review-view__body')?.textContent?.trim() || '';
            const ratingEl = el.querySelector('[itemprop="ratingValue"]');
            const rating = ratingEl
                ? parseInt(ratingEl.getAttribute('content') || ratingEl.textContent || '0', 10)
                : 0;

            if (author || text) {
                results.push({
                    review_id: `dom-${index}`,
                    author,
                    text,
                    rating,
                    date,
                });
            }
        });
        return results;
    });

    for (const review of domReviews) {
        if (!allReviews.has(review.review_id)) {
            allReviews.set(review.review_id, review);
        }
    }

    let prevCount = allReviews.size;
    let staleRounds = 0;

    for (let round = 0; round < SCROLL_ROUNDS; round++) {
        await page.evaluate(() => {
            const selectors = [
                '.scroll__container',
                '.business-reviews-card-view__reviews',
                '.business-card-view__main',
                '.scroll-container',
            ];

            for (const selector of selectors) {
                const el = document.querySelector(selector);
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            }

            window.scrollTo(0, document.body.scrollHeight);
        });

        await page.waitForTimeout(SCROLL_DELAY_MS);

        try {
            const showMore = page.locator(
                'button:has-text("Показать ещё"), button:has-text("ещё"), span:has-text("Показать ещё")',
            ).first();

            if (await showMore.isVisible({ timeout: 300 })) {
                await showMore.click();
                await page.waitForTimeout(2000);
            }
        } catch {
            // no show more button
        }

        const currentCount = allReviews.size;
        if (currentCount === prevCount) {
            staleRounds++;
            if (staleRounds >= STALE_LIMIT) break;
        } else {
            staleRounds = 0;
        }
        prevCount = currentCount;
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
