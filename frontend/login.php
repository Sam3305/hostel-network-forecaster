<?php
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
        exit;
    } else {
        header("Location: user.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Predictor AI - Login</title>
    <link rel="stylesheet" href="style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap"
        rel="stylesheet">
</head>

<body class="centered-layout">
    <nav class="navbar glass-panel-nav">
        <div class="logo">
            <h1>NET<span class="gradient-text">PREDICT</span></h1>
        </div>
        <div class="nav-links">
            <a href="index.html" class="nav-link">Public View</a>
            <a href="login.php" class="nav-link active">Login</a>
        </div>
    </nav>

    <div class="login-wrapper">
        <div class="glass-panel login-panel">
            <h2 class="section-title text-center">SYSTEM AUTHENTICATION</h2>
            <form id="login-form">
                <div class="input-group">
                    <label>ACCESS ID (Role)</label>
                    <input type="text" id="username" placeholder="Enter 'admin' or 'user'" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>DECRYPTION KEY</label>
                    <input type="password" id="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="neon-btn full-width">INITIALIZE SESSION</button>
                <div id="login-error" style="color: var(--neon-red); text-align: center; margin-top: 1rem; font-size: 0.85rem;"></div>
            </form>
            <div class="login-hint">Hint: Login as "user" or "admin".</div>
        </div>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorEl = document.getElementById('login-error');
            
            errorEl.innerText = "Authenticating...";
            errorEl.style.color = "var(--text-muted)";

            try {
                const res = await fetch('../api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', username, password })
                });
                
                const data = await res.json();
                
                if (data.status === 'success') {
                    if (data.role === 'admin') {
                        window.location.href = 'admin.php';
                    } else {
                        window.location.href = 'user.php';
                    }
                } else {
                    errorEl.style.color = "var(--neon-red)";
                    errorEl.innerText = data.message || "Invalid credentials.";
                }
            } catch (err) {
                errorEl.style.color = "var(--neon-red)";
                errorEl.innerText = "System error during authentication.";
            }
        });
    </script>
</body>

</html>