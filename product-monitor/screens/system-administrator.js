const adminSections = [
    { page: "dashboard", label: "Admin Dashboard", description: "System administration overview." },
    { page: "admin-users", label: "User Management", description: "Review registered accounts and their roles." },
];

function renderSectionNavigation(activePage) {
    return `
        <nav class="admin-module-nav" aria-label="System administration">
            ${adminSections.map(section => `
                <a href="#${escapeHTML(section.page)}" data-page="${escapeHTML(section.page)}" ${section.page === activePage ? 'aria-current="page"' : ""}>
                    ${escapeHTML(section.label)}
                </a>
            `).join("")}
        </nav>
    `;
}

function renderAdminPageHeader(section) {
    return `
        <div class="admin-page-heading">
            <div>
                <p class="eyebrow">SYSTEM ADMINISTRATION</p>
                <h2>${escapeHTML(section.label)}</h2>
                <p>${escapeHTML(section.description)}</p>
            </div>
        </div>
    `;
}

function renderAdminDashboard() {
    const roleCount = Object.keys(rolePermissions).length;

    return `
        <section id="adminDashboardStatus" class="admin-table-note" role="status">Loading system overview…</section>
        <section class="stats-grid" aria-label="Administration summary">
            ${renderStatCard("Registered users", "—", "All created accounts", "blue")}
            ${renderStatCard("Active users", "—", `${roleCount} defined application roles`, "green")}
            ${renderStatCard("Successful logins", "—", "Last 24 hours", "orange")}
            ${renderStatCard("Failed login attempts", "—", "Last 24 hours", "red")}
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">SERVICE HEALTH</p>
                    <h2>API and database status</h2>
                </div>
            </div>
            <div class="admin-status-list">
                <div><span>Admin API</span><strong id="adminApiHealth" class="admin-status unavailable">Checking</strong></div>
                <div><span>Database</span><strong id="adminDatabaseHealth" class="admin-status unavailable">Checking</strong></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div><p class="eyebrow">RECENT SYSTEM ACTIVITY</p><h2>Login and account activity</h2></div>
                <a class="text-link" href="#admin-users" data-page="admin-users">Manage users <span aria-hidden="true">→</span></a>
            </div>
            <div id="adminRecentActivity" class="admin-activity-list">
                <p class="admin-table-note">Loading recent activity…</p>
            </div>
        </section>
    `;
}

function renderUserManagement() {
    return `
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">ACCOUNT DIRECTORY</p>
                    <h3>User accounts</h3>
                    <p class="admin-table-note">Set a temporary password for each new user. They must choose a new password at first sign-in.</p>
                    <p id="adminUsersStatus" class="admin-table-note" role="status">Loading accounts…</p>
                </div>
                <details class="admin-add-user">
                    <summary class="secondary-button">Add user</summary>
                    <form class="admin-user-form" data-admin-user-form="create">
                        ${renderUserFields({}, false)}
                        <button class="primary-button" type="submit">Create account</button>
                    </form>
                </details>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>User ID</th><th>Name</th><th>Email / Username</th><th>Role</th><th>Department</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody id="adminUserRows">
                        <tr><td colspan="8">Loading accounts…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    `;
}

function renderUserFields(user = {}, isEdit = true) {
    const roleOptions = Object.keys(rolePermissions).map(role => `
        <option value="${escapeHTML(role)}" ${user.role === role ? "selected" : ""}>${escapeHTML(role)}</option>
    `).join("");

    return `
        <label><span>Full name</span><input name="name" value="${escapeHTML(user.name || "")}" minlength="2" maxlength="255" required></label>
        <label><span>Email / Username</span><input name="email" type="email" value="${escapeHTML(user.email || "")}" maxlength="255" required></label>
        <label><span>Role</span><select name="role" required>${roleOptions}</select></label>
        <label><span>Department</span><input name="department" value="${escapeHTML(user.department || "")}" maxlength="100"></label>
        ${isEdit ? "" : '<label><span>Temporary password</span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>'}
    `;
}

function renderUserRows(users) {
    if (users.length === 0) {
        return '<tr><td colspan="8">No user accounts have been created.</td></tr>';
    }

    return users.map(user => {
        const detailsId = `admin-user-details-${user.id}`;
        const editId = `admin-user-edit-${user.id}`;

        return `
            <tr data-admin-user-id="${escapeHTML(user.id)}">
                <td>${escapeHTML(user.id)}</td>
                <td>
                    <button class="admin-user-toggle" type="button" data-admin-user-toggle="${escapeHTML(user.id)}" aria-expanded="false" aria-controls="${escapeHTML(detailsId)}">
                        ${escapeHTML(user.name)}
                    </button>
                </td>
                <td>${escapeHTML(user.email)}</td>
                <td><span class="admin-user-role">${escapeHTML(user.role)}</span></td>
                <td>${escapeHTML(user.department || "—")}</td>
                <td><span class="admin-status ${user.isActive ? "available" : "unavailable"}">${user.isActive ? "Active" : "Inactive"}</span></td>
                <td>${escapeHTML(formatAdminDate(user.createdAt))}</td>
                <td class="admin-user-actions">
                    <button type="button" class="table-action" data-admin-action="edit" data-user-id="${escapeHTML(user.id)}" aria-expanded="false" aria-controls="${escapeHTML(editId)}">Edit</button>
                    <button type="button" class="table-action" data-admin-action="toggle-status" data-user-id="${escapeHTML(user.id)}" data-is-active="${user.isActive ? "true" : "false"}">${user.isActive ? "Deactivate" : "Activate"}</button>
                </td>
            </tr>
            <tr id="${escapeHTML(detailsId)}" class="admin-user-details" hidden>
                <td colspan="8">
                    <dl>
                        <div><dt>User ID</dt><dd>${escapeHTML(user.id)}</dd></div>
                        <div><dt>Name</dt><dd>${escapeHTML(user.name)}</dd></div>
                        <div><dt>Email</dt><dd>${escapeHTML(user.email)}</dd></div>
                        <div><dt>Role</dt><dd>${escapeHTML(user.role)}</dd></div>
                        <div><dt>Department</dt><dd>${escapeHTML(user.department || "—")}</dd></div>
                        <div><dt>Status</dt><dd>${user.isActive ? "Active" : "Inactive"}</dd></div>
                        <div><dt>Account created</dt><dd>${escapeHTML(formatAdminDate(user.createdAt))}</dd></div>
                    </dl>
                </td>
            </tr>
            <tr id="${escapeHTML(editId)}" class="admin-user-edit-row" hidden>
                <td colspan="8">
                    <form class="admin-user-form" data-admin-user-form="edit" data-user-id="${escapeHTML(user.id)}">
                        ${renderUserFields(user)}
                        <label class="admin-active-field"><input type="checkbox" name="is_active" value="1" ${user.isActive ? "checked" : ""}> Account is active</label>
                        <div class="admin-form-actions"><button class="primary-button" type="submit">Save changes</button><button class="secondary-button" type="button" data-admin-action="cancel-edit" data-user-id="${escapeHTML(user.id)}">Cancel</button></div>
                    </form>
                </td>
            </tr>
        `;
    }).join("");
}

function formatAdminDate(date) {
    if (!date) {
        return "Unavailable";
    }

    const parsedDate = new Date(date);
    return Number.isNaN(parsedDate.getTime()) ? "Unavailable" : parsedDate.toLocaleString();
}

function getCsrfToken() {
    const cookie = document.cookie.split("; ").find(value => value.startsWith("XSRF-TOKEN="));
    return cookie ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)) : "";
}

async function administratorRequest(url, method = "GET", body = null) {
    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            ...(body ? { "Content-Type": "application/json" } : {}),
            ...(method === "GET" ? {} : { "X-XSRF-TOKEN": getCsrfToken() }),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const payload = await response.json();

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {}).flat()[0];
        throw new Error(validationMessage || payload.message || `Request failed (${response.status}).`);
    }

    return payload;
}

function renderAdminActivity(activities) {
    if (activities.length === 0) {
        return '<p class="admin-table-note">No sign-ins or system changes have been recorded yet.</p>';
    }

    return `
        <div class="admin-activity-list">
            ${activities.map(activity => `
                <article class="admin-activity-item">
                    <span class="admin-activity-mark ${activity.eventType === "login_failed" || activity.eventType === "login_locked" ? "failed" : ""}" aria-hidden="true"></span>
                    <div>
                        <strong>${escapeHTML(activity.description)}</strong>
                        <p>${escapeHTML(activity.email || activity.user || "Unknown account")}${activity.actor ? ` · by ${escapeHTML(activity.actor)}` : ""}</p>
                        <small>${escapeHTML(formatAdminDate(activity.createdAt))}${activity.ipAddress ? ` · ${escapeHTML(activity.ipAddress)}` : ""}</small>
                    </div>
                </article>
            `).join("")}
        </div>
    `;
}

export async function loadSystemAdministratorDashboard(target) {
    try {
        const payload = await administratorRequest("/warehouse/admin/dashboard");
        const status = target.querySelector("#adminDashboardStatus");

        if (!status) {
            return;
        }

        const cards = target.querySelectorAll(".stats-grid .stat-card");
        const metricValues = [
            payload.metrics.registeredUsers,
            payload.metrics.activeUsers,
            payload.metrics.successfulLogins,
            payload.metrics.failedLogins,
        ];
        cards.forEach((card, index) => {
            const value = card.querySelector(".stat-copy strong");
            if (value) {
                value.textContent = String(metricValues[index] ?? 0);
            }
        });
        status.textContent = "Updated just now. Login counts cover the last 24 hours.";

        for (const [id, state] of [["adminApiHealth", payload.health.api], ["adminDatabaseHealth", payload.health.database]]) {
            const indicator = target.querySelector(`#${id}`);
            if (indicator) {
                indicator.textContent = state;
                indicator.classList.toggle("available", state === "Operational");
                indicator.classList.toggle("unavailable", state !== "Operational");
            }
        }

        const activity = target.querySelector("#adminRecentActivity");
        if (activity) {
            activity.innerHTML = renderAdminActivity(payload.recentActivity || []);
        }
    } catch (error) {
        const status = target.querySelector("#adminDashboardStatus");
        if (status) {
            status.textContent = error instanceof Error ? error.message : "Unable to load the system overview.";
            status.classList.add("admin-error");
        }
    }
}

export async function loadSystemAdministratorUsers(target, newlyCreatedUserId = null) {
    const status = target.querySelector("#adminUsersStatus");
    const rows = target.querySelector("#adminUserRows");

    if (!status || !rows) {
        throw new Error("The user account list could not be displayed.");
    }

    if (!target.dataset.adminUserToggleBound) {
        target.addEventListener("click", event => {
            const toggle = event.target.closest("[data-admin-user-toggle]");

            if (!toggle || !target.contains(toggle)) {
                return;
            }

            const details = target.querySelector(`#admin-user-details-${CSS.escape(toggle.dataset.adminUserToggle)}`);

            if (details) {
                const isExpanded = toggle.getAttribute("aria-expanded") === "true";
                toggle.setAttribute("aria-expanded", String(!isExpanded));
                details.hidden = isExpanded;
            }
        });
        target.dataset.adminUserToggleBound = "true";
    }

    if (!target.dataset.adminUserActionsBound) {
        target.addEventListener("submit", async event => {
            const form = event.target.closest("[data-admin-user-form]");
            if (!form || !target.contains(form)) {
                return;
            }

            event.preventDefault();
            const isEdit = form.dataset.adminUserForm === "edit";
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            if (isEdit) {
                data.is_active = formData.has("is_active");
            }

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                const userId = form.dataset.userId;
                const payload = await administratorRequest(
                    isEdit ? `/warehouse/admin/users/${encodeURIComponent(userId)}` : "/warehouse/admin/users",
                    isEdit ? "PATCH" : "POST",
                    data,
                );
                await loadSystemAdministratorUsers(target, isEdit ? null : payload.user?.id ?? null);
                target.querySelector("#adminUsersStatus").textContent = payload.message || "Account saved.";

                if (!isEdit) {
                    form.reset();
                    form.closest("details")?.removeAttribute("open");
                    const successToast = target.querySelector(".admin-user-success-toast");

                    if (successToast) {
                        successToast.textContent = "User added!";
                        successToast.hidden = false;
                        successToast.classList.remove("visible");
                        successToast.addEventListener("animationend", () => {
                            successToast.hidden = true;
                            successToast.classList.remove("visible");
                        }, { once: true });
                        void successToast.offsetWidth;
                        successToast.classList.add("visible");
                    }
                }
            } catch (error) {
                target.querySelector("#adminUsersStatus").textContent = error instanceof Error ? error.message : "Unable to save the account.";
                target.querySelector("#adminUsersStatus").classList.add("admin-error");
            } finally {
                submitButton.disabled = false;
            }
        });

        target.addEventListener("click", async event => {
            const actionButton = event.target.closest("[data-admin-action]");
            if (!actionButton || !target.contains(actionButton)) {
                return;
            }

            const { adminAction, userId } = actionButton.dataset;
            if (adminAction === "edit" || adminAction === "cancel-edit") {
                const editRow = target.querySelector(`#admin-user-edit-${CSS.escape(userId)}`);
                const editButton = target.querySelector(`[data-admin-action="edit"][data-user-id="${CSS.escape(userId)}"]`);
                if (editRow) {
                    editRow.hidden = adminAction === "cancel-edit" || !editRow.hidden;
                }
                if (editButton) {
                    editButton.setAttribute("aria-expanded", String(!editRow.hidden));
                }
                return;
            }

            actionButton.disabled = true;
            try {
                if (adminAction === "toggle-status") {
                    const isActive = actionButton.dataset.isActive === "true";
                    await administratorRequest(`/warehouse/admin/users/${encodeURIComponent(userId)}`, "PATCH", {
                        is_active: !isActive,
                    });
                    await loadSystemAdministratorUsers(target);
                    target.querySelector("#adminUsersStatus").textContent = `Account ${!isActive ? "activated" : "deactivated"}.`;
                }
            } catch (error) {
                target.querySelector("#adminUsersStatus").textContent = error instanceof Error ? error.message : "Unable to update the account.";
                target.querySelector("#adminUsersStatus").classList.add("admin-error");
            } finally {
                actionButton.disabled = false;
            }
        });
        target.dataset.adminUserActionsBound = "true";
    }

    try {
        const payload = await administratorRequest("/warehouse/admin/users");

        if (!Array.isArray(payload.users)) {
            throw new Error("The user account service returned invalid data.");
        }

        rows.innerHTML = renderUserRows(payload.users);
        if (newlyCreatedUserId !== null) {
            const createdUserRow = rows.querySelector(`[data-admin-user-id="${CSS.escape(String(newlyCreatedUserId))}"]`);
            createdUserRow?.classList.add("admin-user-added");
        }
        status.textContent = `${payload.users.length} account${payload.users.length === 1 ? "" : "s"} found.`;
    } catch (error) {
        const message = error instanceof Error ? error.message : "An unexpected error occurred.";

        rows.innerHTML = '<tr><td colspan="8">Unable to load user accounts.</td></tr>';
        status.textContent = message;
        status.classList.add("admin-error");
    }
}

const adminPageRenderers = {
    dashboard: renderAdminDashboard,
    "admin-users": renderUserManagement,
};

export function renderSystemAdministratorScreen(page = "dashboard") {
    const section = adminSections.find(item => item.page === page) || adminSections[0];
    const renderPage = adminPageRenderers[section.page] || adminPageRenderers.dashboard;

    return `
        <div class="admin-workspace">
            <p class="admin-user-success-toast" role="status" aria-live="polite" hidden></p>
            ${renderSectionNavigation(section.page)}
            ${renderAdminPageHeader(section)}
            ${renderPage()}
        </div>
    `;
}

export const systemAdministrator = {
    renderDashboard: () => renderSystemAdministratorScreen(),
    renderScreen: renderSystemAdministratorScreen,
    loadDashboard: loadSystemAdministratorDashboard,
    loadUsers: loadSystemAdministratorUsers,
};
