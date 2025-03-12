<?php
function fetch_url_content_with_puppeteer($url) {
    // Ensure tmp directory exists
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    
    // Create a unique output file
    $outputFile = $tmpDir . '/output_' . md5($url . time()) . '.html';
    
    // Create a temporary JS file with the URL
    $jsFile = $tmpDir . '/puppeteer_' . md5($url) . '.js';
    $jsContent = <<<EOT
const puppeteer = require('puppeteer');

(async () => {
    // Launch browser with specific settings
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu',
            '--window-size=1920,1080',
        ]
    });

    try {
        const page = await browser.newPage();
        
        // Set a realistic viewport
        await page.setViewport({ width: 1920, height: 1080 });
        
        // Set realistic user agent
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        // Allow all cookies
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'en-US,en;q=0.9',
            'Referer': 'https://www.google.com/'
        });
        
        // Enable JavaScript (enabled by default in Puppeteer)
        await page.setJavaScriptEnabled(true);
        
        // Navigate to URL with timeout
        await page.goto('$url', { 
            waitUntil: 'networkidle2',
            timeout: 60000 // 60 second timeout
        });
        
        // Wait additional time for dynamic content to load
        await page.waitForTimeout(5000);
        
        // Perform some random scrolling to mimic human behavior
        await page.evaluate(() => {
            window.scrollBy(0, Math.random() * 500);
            return new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 1000));
        });
        
        // Wait for any potential overlay or popup that might appear
        try {
            await page.waitForTimeout(2000);
            
            // Find and click any "accept cookies" or similar buttons
            const buttonSelectors = [
                'button[id*="accept"]', 
                'button[id*="cookie"]',
                'button[class*="accept"]', 
                'button[class*="cookie"]',
                'a[id*="accept"]',
                'a[class*="accept"]'
            ];
            
            for (const selector of buttonSelectors) {
                const buttons = await page.$$(selector);
                if (buttons.length > 0) {
                    await buttons[0].click();
                    await page.waitForTimeout(2000);
                    break;
                }
            }
        } catch (e) {
            // Ignore errors from this section, it's just best effort
            console.error("Non-fatal error while handling potential overlays:", e.message);
        }
        
        // Get page content after JavaScript execution
        const content = await page.content();
        require('fs').writeFileSync('$outputFile', content);
    } catch (error) {
        console.error("ERROR: " + error.message);
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
EOT;
    
    if (file_put_contents($jsFile, $jsContent) === false) {
        return "Error: Could not create temporary JavaScript file";
    }
    
    // Execute the Puppeteer script
    $output = shell_exec("node $jsFile 2>&1");
    
    // Check for errors in the output
    if (strpos($output, "ERROR:") !== false) {
        unlink($jsFile);
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
        return "Error running Puppeteer: " . $output;
    }
    
    // Read the HTML content from the output file
    if (file_exists($outputFile)) {
        $html = file_get_contents($outputFile);
        unlink($outputFile); // Clean up
    } else {
        $html = "Error: Output file not created";
    }
    
    // unlink($jsFile); // Clean up
    
    return $html;
}