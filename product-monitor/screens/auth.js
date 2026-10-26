<<<<<<< HEAD
function renderAccountOptions() {
    return Object.keys(rolePermissions)
        .filter(role => role !== "System Administrator")
        .map(role => `<option value="${escapeHTML(role)}">${escapeHTML(role)}</option>`)
        .join("");
}

=======
>>>>>>> 28aff9e (Integrate connected ERP workflows and sample data)
function renderAuthMessage(message, className = "auth-message") {
    return message ? `<p class="${className}" role="status">${escapeHTML(message)}</p>` : "";
}

function renderLoginPage(message = "", email = "") {
    return `
        <div class="login-shell">
            <section class="login-card" aria-labelledby="authTitle">
                <div class="login-header">
                    <div class="auth-icon-badge"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg></div>
                    <p class="eyebrow">SECURE ACCESS</p>
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
                    <div class="auth-icon-badge"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2 4 5v6c0 5.25 3.4 9.1 8 11 4.6-1.9 8-5.75 8-11V5l-8-3z"/><path d="m9 12 2 2 4-4"/></svg></div>
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
