const puppeteer = require('puppeteer');

(async () => {
  try {
    console.log("Launching browser...");
    const browser = await puppeteer.launch(); // Launch headless browser
    console.log("Browser launched successfully.");

    console.log("Opening a new page...");
    const page = await browser.newPage();    // Open a new tab
    console.log("New page opened.");

    console.log("Navigating to the URL...");
    await page.goto('https://www.price.com.hk/product.php?p=606102', {
      waitUntil: 'networkidle2', // Wait until the page is fully loaded
    });
    console.log("Navigation completed.");

    console.log("Retrieving page content...");
    const content = await page.content(); // Get the fully rendered HTML

    // Check if content is empty
    if (!content || content.trim() === '') {
      console.error("The page content is empty. It may be due to JavaScript rendering issues or a network problem.");
    } else {
      console.log("Page content retrieved successfully.");
      console.log(content); // Print the HTML to the console
    }

    console.log("Closing the browser...");
    await browser.close(); // Close the browser
    console.log("Browser closed successfully.");
  } catch (error) {
    console.error("An error occurred during execution:", error.message);
    console.error(error.stack); // Print the full stack trace for debugging
  }
})();