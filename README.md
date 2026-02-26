# Context-Aware Network Forecaster (CANF) 🌐🧠

**CANF** is an AI-driven predictive analytics pipeline designed to forecast campus network congestion based on historical bandwidth usage and exogenous contextual stressors (e.g., Finals Week, E-Sports Tournaments).

### 🏗 Architecture & Tech Stack
This project follows a decoupled, 3-tier architecture utilizing Server-Side Rendering (SSR):
* **Data Layer:** MySQL (Stores historical logs, events, and future predictions).
* **AI Engine (Background):** Python, Pandas, Scikit-Learn (Random Forest Regressor) running via asynchronous batch processing.
* **Application/UI Layer:** PHP 8+, HTML/CSS, Chart.js (Strictly SSR, no APIs/JSON).

---

### 🚀 Team Setup Instructions (Read Carefully)
To work on this repository, you must set up your local environment.

**1. Clone the repository and switch to the dev branch:**
\`\`\`bash
git clone https://github.com/Sam3305/hostel-network-forecaster.git
cd hostel-network-forecaster
git checkout dev
\`\`\`

**2. Set up your Local Database Config:**
Because we do not push passwords to GitHub, you must create a file named `db_config.php` in the root folder. **Do not commit this file.**
Add the following code to it and change the credentials to match your local XAMPP/MAMP setup:
\`\`\`php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Your local MySQL username
define('DB_PASS', '');     // Your local MySQL password (leave blank for XAMPP Windows)
define('DB_NAME', 'hostel_network');
?>
\`\`\`

**3. Database Import:**
Ask Role 1 for the SQL schema to build the `hostel_network` database on your local phpMyAdmin.