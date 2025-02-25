# Project Description (Partner Price Monitoring System)

This project is a PHP-based system designed for internal use by a company that creates high-tech equipment (e.g., NAS devices). The system monitors and evaluates product pricing from partnered merchants listed on third-party platforms (e.g., price.com.hk). It ensures pricing compliance by comparing merchant prices with the company's standard reference prices and notifying partners of any discrepancies.

---

## Features

- **Web Scraping**: Automatically fetches product prices from third-party platforms (e.g., price.com.hk) using PHP's `cURL`.
- **Price Comparison**: Compares scraped data with company-defined reference prices to detect overpricing, underpricing, or missing prices.
- **Notifications**: Sends email notifications to partners or internal staff when discrepancies are detected (e.g., missing prices, overpricing, or underpricing).
- **Merchant Categorization**: Merchants are categorized into three groups:
  - **Overcharge**: Prices higher than reference values.
  - **Undercharge**: Prices lower than reference values.
  - **Satisfaction**: Prices within acceptable limits.
- **Cooldown and Hard Refresh**:
  - Implements a **24-hour cooldown** to prevent excessive refreshes and minimize detection as a bot by third-party platforms.
  - Provides a **hard refresh** feature to override the cooldown for immediate updates, accessible via a web interface.

---

## Installation

### Prerequisites

- **PHP 8.0+**: Ensure PHP is installed on your system.
- **Composer**: For dependency management.
- **MySQL**: Used as the database.

### Steps


---

## Contributing

This project is a web-based curling system designed for educational purposes. Pull requests are currently disabled, and the project will no longer be updated after it is completed.

Thank you for your interest and understanding.

## License

[MIT](https://choosealicense.com/licenses/mit/)