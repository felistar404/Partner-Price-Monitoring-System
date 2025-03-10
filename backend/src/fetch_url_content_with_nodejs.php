<?php
function fetch_url_content_with_nodejs($url) {
    // Ensure tmp directory exists
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    
    // Create a temporary JS file with the URL - use .mjs extension for ES modules
    $jsFile = $tmpDir . '/fetch_' . md5($url) . '.mjs';
    $jsContent = <<<EOT
    import fetch from 'node-fetch';
    
    const url = '$url';
    
    (async () => {
        try {
            const response = await fetch(url, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language': 'en-US,en;q=0.5',
                    'Connection': 'keep-alive',
                    'Upgrade-Insecure-Requests': '1',
                    'Cache-Control': 'max-age=0',
                    'Referer': 'https://www.google.com/'
                },
                timeout: 30000 // 30 seconds timeout
            });
            const text = await response.text();
            console.log(text);
        } catch (error) {
            console.error("ERROR: " + error.message);
            process.exit(1);
        }
    })();
    EOT;
    
    if (file_put_contents($jsFile, $jsContent) === false) {
        return "Error: Could not create temporary JavaScript file";
    }
    
    // Check if Node.js is available
    $nodeCheck = shell_exec("node --version");
    if (empty($nodeCheck)) {
        unlink($jsFile);
        return "Error: Node.js is not installed or not in the PATH";
    }
    
    // Execute the Node.js script with a timeout and get the response
    $response = shell_exec("node $jsFile 2>&1");
    unlink($jsFile);  // Clean up
    
    if (empty($response)) {
        return "Error: Failed to fetch content with Node.js";
    }
    
    if (strpos($response, "ERROR:") !== false) {
        return "Error: " . $response;
    }
    
    return $response;
}