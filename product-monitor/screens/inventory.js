function renderProductName(product) {
    return `<span class="product-name"><span class="product-avatar">${escapeHTML(product.name.slice(-1))}</span><strong>${escapeHTML(product.name)}</strong></span>`;
}

function renderAgeBadge(age) {
    const label = getAgeLabel(age);
    return `<span class="age-badge ${label.toLowerCase()}"><span class="status-dot"></span>${age} days <small>${label}</small></span>`;
}

function renderStatusBadge(status) {
    return `<span class="status-badge ${getStatusClass(status)}"><span class="status-dot"></span>${escapeHTML(status)}</span>`;
}

function renderTablePage(page) {
    const tableSettings = {
        inventory: {
            title: "All inventory",
            description: `${inventory.length} batches currently tracked`,
            products: [...inventory],
            showReceived: true,
            aging: false,
            expiring: false,
        },
        aging: {
            title: "Aging inventory",
            description: "Products held for more than 30 days, prioritized by age",
            products: inventory.filter(product => (
                getInventoryAge(product) > 30
                && getDaysRemaining(product) >= 0
                && product.status !== "Expired"
            )),
            showReceived: true,
            aging: true,
            expiring: false,
        },
        expiring: {
            title: "Expiration watch",
            description: "Products expiring within 30 days and expired batches",
            products: inventory.filter(product => (
                getDaysRemaining(product) <= 30 || product.status === "Expired"
            )),
            showReceived: false,
            aging: false,
            expiring: true,
        },
    }[page];

    return `
        <section class="panel inventory-panel">
            <div class="panel-heading inventory-heading">
                <div>
                    <p class="eyebrow">${page === "aging" ? "STOCK HEALTH" : page === "expiring" ? "DATE MONITORING" : "WAREHOUSE STOCK"}</p>
                    <h2>${tableSettings.title}</h2>
                    <p class="panel-description">${tableSettings.description}</p>
                </div>
                <div class="table-toolbar">
                    <label class="search-field">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
                        <input id="productSearch" type="search" placeholder="Search products..." aria-label="Search products">
                    </label>
                    <label class="select-field">
                        <span class="visually-hidden">Filter by status</span>
                        <select id="statusFilter" aria-label="Filter by status">
                            <option value="">All statuses</option>
                            ${statusOptions.map(status => `<option value="${escapeHTML(status)}">${escapeHTML(status)}</option>`).join("")}
                        </select>
                    </label>
                    <label class="select-field sort-field">
                        <span class="visually-hidden">Sort inventory</span>
                        <select id="sortProducts" aria-label="Sort inventory">
                            <option value="name">Sort: Product name</option>
                            <option value="age-desc">Sort: Oldest received</option>
                            <option value="expiration">Sort: Expiration date</option>
                            <option value="received-desc">Sort: Recently received</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table inventory-table">
                    <thead><tr>
                        <th>Product</th>
                        <th>Batch number</th>
                        <th>Quantity</th>
                        ${tableSettings.showReceived ? "<th>Date received</th>" : ""}
                        ${tableSettings.showReceived ? "<th>Inventory age</th>" : ""}
                        <th>Expiration date</th>
                        ${tableSettings.expiring ? "<th>Days remaining</th>" : ""}
                        <th>Status</th>
                        ${tableSettings.expiring ? "<th>Priority</th>" : ""}
                        ${tableSettings.aging ? "<th>Recommended action</th>" : ""}
                        <th>Actions</th>
                    </tr></thead>
                    <tbody id="inventoryRows"></tbody>
                </table>
            </div>
            <div class="table-footer"><span id="tableResultCount"></span><span>Age and expiration are calculated from today's date.</span></div>
            <div id="tableEmptyState" class="table-empty" hidden></div>
        </section>
    `;
}

function getRecommendedAction(product) {
    const daysRemaining = getDaysRemaining(product);

    if (daysRemaining <= 7) {
        return "Prioritize distribution";
    }

    if (getInventoryAge(product) > 60) {
        return "Review for production";
    }

    return "Plan stock rotation";
}

function getExpiryPriority(product) {
    const daysRemaining = getDaysRemaining(product);

    if (daysRemaining < 0 || product.status === "Expired") {
        return "Critical";
    }

    if (daysRemaining <= 7) {
        return "High";
    }

    return "Medium";
}

function renderInventoryRows(page) {
    const rowsElement = document.getElementById("inventoryRows");

    if (!rowsElement) {
        return;
    }

    const searchTerm = document.getElementById("productSearch").value.trim().toLocaleLowerCase();
    const selectedStatus = document.getElementById("statusFilter").value;
    const sortBy = document.getElementById("sortProducts").value;
    const tableSettings = {
        inventory: { showReceived: true, aging: false, expiring: false },
        aging: { showReceived: true, aging: true, expiring: false },
        expiring: { showReceived: false, aging: false, expiring: true },
    }[page];

    const filteredProducts = [...getProductsForPage(page)]
        .filter(product => {
            const matchesSearch = [
                product.name,
                product.batch,
                product.status,
                getAgeLabel(getInventoryAge(product)),
            ].join(" ").toLocaleLowerCase().includes(searchTerm);
            const matchesStatus = !selectedStatus || product.status === selectedStatus;

            return matchesSearch && matchesStatus;
        })
        .sort((first, second) => {
            if (sortBy === "age-desc") {
                return getInventoryAge(second) - getInventoryAge(first);
            }

            if (sortBy === "expiration") {
                return first.expiration.localeCompare(second.expiration);
            }

            if (sortBy === "received-desc") {
                return second.received.localeCompare(first.received);
            }

            return first.name.localeCompare(second.name);
        });

    rowsElement.innerHTML = filteredProducts.map(product => {
        const daysRemaining = getDaysRemaining(product);

        return `
            <tr>
                <td>${renderProductName(product)}</td>
                <td class="mono">${escapeHTML(product.batch)}</td>
                <td><strong class="quantity">${product.quantity}</strong><span class="unit-label"> units</span></td>
                ${tableSettings.showReceived ? `<td>${getDateLabel(product.received)}</td>` : ""}
                ${tableSettings.showReceived ? `<td>${renderAgeBadge(getInventoryAge(product))}</td>` : ""}
                <td>${getDateLabel(product.expiration)}</td>
                ${tableSettings.expiring ? `<td><span class="expiry-badge ${daysRemaining < 0 || product.status === "Expired" ? "expired" : daysRemaining <= 7 ? "urgent" : "soon"}">${escapeHTML(getExpiryLabel(daysRemaining))}</span></td>` : ""}
                <td>${renderStatusBadge(product.status)}</td>
                ${tableSettings.expiring ? `<td><span class="priority-tag ${getPriorityClass(getExpiryPriority(product))}">${getExpiryPriority(product)}</span></td>` : ""}
                ${tableSettings.aging ? `<td><span class="recommended-action">${escapeHTML(getRecommendedAction(product))}</span></td>` : ""}
                <td>
                    <div class="row-actions">
                        <button class="table-action" type="button" data-action="details" data-product-id="${escapeHTML(product.id)}">Details</button>
                        <button class="table-action primary-action" type="button" data-action="update-status" data-product-id="${escapeHTML(product.id)}">Update status</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    const emptyState = document.getElementById("tableEmptyState");
    const isEmpty = filteredProducts.length === 0;
    emptyState.hidden = !isEmpty;
    emptyState.textContent = searchTerm || selectedStatus
        ? "No inventory matches these search and filter options."
        : "No inventory items to display.";
    document.getElementById("tableResultCount").textContent = `Showing ${filteredProducts.length} of ${getProductsForPage(page).length} batches`;
}

function getProductsForPage(page) {
    if (page === "aging") {
        return inventory.filter(product => (
            getInventoryAge(product) > 30
            && getDaysRemaining(product) >= 0
            && product.status !== "Expired"
        ));
    }

    if (page === "expiring") {
        return inventory.filter(product => getDaysRemaining(product) <= 30 || product.status === "Expired");
    }

    return inventory;
}

export function renderInventoryScreen(page = 'inventory') {
    return renderTablePage(page);
}
