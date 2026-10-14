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
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
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

export function renderInventoryScreen(page = 'inventory') {
    return renderTablePage(page);
}
