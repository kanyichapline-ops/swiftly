# Swiftly. ⚡️

An ultra-modern, privacy-focused URL shortener with visual data analytics, built with PHP and a modern frontend stack.

Swiftly is a professional-grade URL management tool. It combines high-end aesthetics with powerful data visualization, using Client-Side Identity Tracking to ensure users enjoy a personalized, private dashboard experience without the friction of a login system.

## ✨ Core Features
*   **Modern Landing Page**: High-end glassmorphism UI with interactive GSAP animations and mouse-tracking spotlight effects.
*   **Zero-Login UX**: Anonymous yet persistent dashboard access via secure browser cookies.
*   **Full Link Management**: Create, edit, and delete links directly from the dashboard.
*   **Custom Aliases**: Personalize your links for branding and better CTR.
*   **Link Expiry**: Set links to automatically deactivate after a certain date.
*   **QR Code Generation**: Automatically generate a shareable QR code for every link.
*   **Advanced Dashboard**:
    *   Dynamic, time-based greetings.
    *   Interactive charts for trend analysis and traffic distribution.
    *   Ultra-modern table with search, pagination, and empty-state handling.
    *   Shareable public-facing analytics page.
    *   CSV data export.

## 🛡️ Security & Abuse Prevention
*   **Rate-Limiting**: Built-in protection against spamming by limiting the number of links a user (identified by their browser cookie) can create within a specific time window.
*   **Blacklist Filtering**: Prevents shortening of URLs from known malicious or unwanted domains via `includes/blacklist.txt`.
*   **PDO Prepared Statements**: Full protection against SQL Injection attacks to ensure database integrity.

## 🛠️ Tech Stack
*   **Frontend**: Tailwind CSS, GSAP (Animations), Chart.js (Data Viz), Lucide Icons.
*   **Backend**: PHP 8.x (PDO) & MySQL.
*   **Identity**: Persistent Browser Cookies for history management.
*   **Server**: Apache (`.htaccess`) for Clean URLs.

## 🚀 Installation & Setup
1.  **Clone the Repository**
    ```bash
    git clone https://github.com/CHAPLINE055/Swiftly-URL-Shortener.git
    ```
2.  **Database Setup**
    *   Import `swiftly_db.sql` into your MySQL server. This will create the `swiftly_db` database and all necessary tables (`links`, `analytics`, `rate_limits`).

3.  **Configuration**
    *   **Database**: Edit `includes/db.php` with your MySQL credentials.
    *   **Base URL**: Edit `includes/config.php` to set your application's `BASE_URL`. This is crucial for generating correct short links.

4.  **Blacklist (Optional)**
    *   Add any domains you wish to block to `includes/blacklist.txt`, one per line.

5.  **Web Server**
    *   **Apache**: Ensure `mod_rewrite` is enabled. The included `.htaccess` file should handle clean URL redirection automatically.
    *   **Nginx**: You will need to configure your server block to handle rewrites, similar to the logic in the `.htaccess` file.

## 👥 Development Team

| Contributor       | Registration Number | Technical Ownership & Scope                                                                                                      |
| ----------------- | ------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| 🛡️ Jacinta Mwende | BSCCS/2025/59872    | **Abuse Prevention & Gatekeeper**: Implemented multi-layer security protocols, including IP rate-limiting and malicious domain filtering. |
| 📊 Joan Mulei     | BSCCS/2025/59368    | **Advanced Analytics & Top-K Heaps**: Developed high-performance ranking algorithms and real-time click-stream data visualization.   |
| 🔀 Kanyi Chapline  | BSCCS/2025/45195    | **Horizontal Data Partitioning**: Architected the multi-node scaling logic and distributed request routing between Shard Alpha and Beta. |
| 🏷️ Michelle Mutheu | BSCCS/2025/58367    | **Custom Aliases & TTL Scheduler**: Engineered the personalized routing system and the background logic for automated link expiration. |
| ⚙️ Yvonne Mureithi | BSCCS/2025/59424    | **Core Shorten & Redirect**: Built the central hashing engine, unique ID generation, and collision-resistant URL mapping system.     |
