const puppeteer = require('puppeteer');

(async () => {
    const url = process.argv[2];

    if (!url) {
        console.error('Error: No URL provided.');
        process.exit(1);
    }

    try {
    // Launch Puppeteer in headless mode for server/VPS
    const browser = await puppeteer.launch({ 
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-first-run',
            '--disable-background-timer-throttling',
            '--disable-backgrounding-occluded-windows',
            '--disable-renderer-backgrounding',
            '--memory-pressure-off',
            '--disable-extensions',
            '--disable-plugins'
        ],
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined
    });

        const page = await browser.newPage();

        // Set user agent to mimic a real browser
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');

        // Increase navigation timeout to 60 seconds
        await page.setDefaultNavigationTimeout(60000); // 60 seconds

        // Navigate to the URL with shorter timeout
        await page.goto(url, { waitUntil: 'load', timeout: 60000 });

        // Extract video URL from the page
        const videoUrl = await page.evaluate(() => {
            const videoElement = document.querySelector('video source');
            return videoElement ? videoElement.src : null;
        });

        await browser.close();

        if (videoUrl) {
            console.log(videoUrl);  // Output URL to stdout
        } else {
            console.error('Error: No video URL found.');
            process.exit(1);
        }
    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
})();