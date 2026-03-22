<div id="whodash-landing">

    <!-- Hero / Branding Panel -->
    <div class="wd-hero">
        <div class="wd-eyebrow">World of Warcraft Analytics</div>
        <h1 class="wd-logo">Who<span>DASH</span></h1>
        <p class="wd-tagline">Your characters. Your data. Every adventure — tracked, visualised, and waiting for you.
        </p>

        <div class="wd-features">
            <div class="wd-feature">
                <div class="wd-feature-icon">⚔️</div>
                <div class="wd-feature-text">
                    <strong>Combat &amp; Progression</strong>
                    <span>Deep dives into kills, deaths, roles, and advancement milestones.</span>
                </div>
            </div>
            <div class="wd-feature">
                <div class="wd-feature-icon">🏦</div>
                <div class="wd-feature-text">
                    <strong>Economy &amp; Bazaar</strong>
                    <span>Track currencies, bank alts, trades, and marketplace trends over time.</span>
                </div>
            </div>
            <div class="wd-feature">
                <div class="wd-feature-icon">🏰</div>
                <div class="wd-feature-text">
                    <strong>Guild &amp; Social</strong>
                    <span>Guild hall analytics, treasury logs, and social footprint across realms.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Vertical Divider -->
    <div class="wd-divider"></div>

    <!-- Auth Panel -->
    <div class="wd-auth-panel">

        <!-- Login Card -->
        <div id="loginCard" class="wd-auth-card">
            <h2 class="wd-auth-title">Welcome back</h2>
            <p class="wd-auth-subtitle">Sign in to access your dashboard</p>
            <form id="loginForm">
                <input type="text" name="username" required placeholder="Username" />
                <input type="password" name="password" required placeholder="Password" />
                <div class="wd-remember">
                    <label class="wd-remember-label">
                        <input type="checkbox" name="remember_me" value="1" class="wd-remember-checkbox" />
                        <span class="wd-remember-box"></span>
                        Remember me for 30 days
                    </label>
                </div>
                <button type="submit" class="wd-btn-primary">Enter the Dashboard</button>
            </form>
            <div id="loginError" class="muted"></div>
            <div class="wd-auth-divider">or</div>
            <button type="button" class="wd-btn-secondary" data-action="show-register">Create an Account</button>
        </div>

        <!-- Register Card (hidden by default) -->
        <div id="registerCard" class="wd-auth-card hidden">
            <h2 class="wd-auth-title">Join WhoDASH</h2>
            <p class="wd-auth-subtitle">Create your free account to get started</p>
            <form id="registerForm">
                <input type="text" name="username" required placeholder="Choose a username" />
                <input type="password" name="password" required placeholder="Password" />
                <input type="password" name="confirm_password" required placeholder="Confirm Password" />
                <button type="submit" class="wd-btn-primary">Create Account</button>
            </form>
            <div id="registerError" class="muted"></div>
            <div class="wd-auth-divider">already have an account?</div>
            <button type="button" class="wd-btn-secondary" data-action="show-login">Back to Login</button>
        </div>

    </div>
</div>