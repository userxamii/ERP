function renderAuthMessage(message, className = "auth-message") {
    return message ? `<p class="${className}" role="status">${escapeHTML(message)}</p>` : "";
}

function renderBrandLogo() {
    return '<div class="login-brand"><img class="login-brand-logo" src="/product-monitor/brand-logo.png" alt="Universal Robina Corporation" width="554" height="554" fetchpriority="high"></div>';
}

function renderLoginPage(message = "", email = "") {
    return `
        <div class="login-shell">
            <section class="login-card" aria-labelledby="authTitle">
                <div class="login-header">
                    ${renderBrandLogo()}
                    <p class="eyebrow">WAREHOUSE MANAGEMENT</p>
                    <h2 id="authTitle">Welcome back</h2>
                    <p>Sign in to continue to your assigned role workspace.</p>
                </div>
                ${renderAuthMessage(message)}
                <p id="authError" class="auth-error" role="alert" hidden></p>
                <form id="loginForm" class="login-form">
                    <label>
                        <span>Email address</span>
                        <input name="email" type="email" value="${escapeHTML(email)}" autocomplete="email" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <button type="submit" class="primary-button login-button">Sign in</button>
                </form>
                <p class="auth-switch">Accounts are issued by your system administrator. Contact them to request access.</p>
                <p class="auth-storage-note">Sign in with the temporary password provided by your administrator.</p>
            </section>
        </div>
    `;
}

function renderChangePasswordPage(message = "") {
    return `
        <div class="login-shell">
            <section class="login-card" aria-labelledby="authTitle">
                <div class="login-header">
                    ${renderBrandLogo()}
                    <p class="eyebrow">SECURE YOUR ACCOUNT</p>
                    <h2 id="authTitle">Change your temporary password</h2>
                    <p>Choose a private password only you know. You must change the temporary password before continuing.</p>
                </div>
                ${renderAuthMessage(message)}
                <p id="authError" class="auth-error" role="alert" hidden></p>
                <form id="changePasswordForm" class="login-form">
                    <label>
                        <span>Temporary password</span>
                        <input name="current_password" type="password" autocomplete="current-password" required>
                    </label>
                    <label>
                        <span>New password</span>
                        <input name="password" type="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <label>
                        <span>Confirm new password</span>
                        <input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <button type="submit" class="primary-button login-button">Change password</button>
                    <button type="button" class="secondary-button" data-auth-action="logout">Sign out</button>
                </form>
                <p class="auth-storage-note">Your administrator cannot see the new password.</p>
            </section>
        </div>
    `;
}

export function renderLoginScreen(message = '', email = '') {
    return renderLoginPage(message, email);
}

export function renderChangePasswordScreen(message = '') {
    return renderChangePasswordPage(message);
}
