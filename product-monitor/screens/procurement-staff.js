const procurementApi = "/warehouse/procurement";

const screenEndpoints = {
    dashboard: `${procurementApi}/dashboard`,
    "procurement-materials": `${procurementApi}/materials`,
    "procurement-requests": `${procurementApi}/requests`,
    "procurement-suppliers": `${procurementApi}/suppliers`,
    "procurement-deliveries": `${procurementApi}/deliveries`,
    "procurement-reports": `${procurementApi}/reports`,
};

function escape(value) {
    return escapeHTML(value ?? "—");
}

function displayDate(value) {
    if (!value) {
        return "—";
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? escape(value)
        : escape(date.toLocaleDateString());
}

function displayStatus(value) {
    const labels = {
        submitted: "Pending approval",
        partially_received: "Partially received",
        out_of_stock: "Out of stock",
        low_stock: "Low stock",
        running_low: "Running low",
    };

    return labels[value] || String(value || "—").replaceAll("_", " ");
}

function renderTable(headers, rows, emptyMessage) {
    return `
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr>${headers.map(header => `<th>${escape(header)}</th>`).join("")}</tr></thead>
                <tbody>${rows || `<tr><td colspan="${headers.length}">${escape(emptyMessage)}</td></tr>`}</tbody>
            </table>
        </div>
    `;
}

function renderPanel(title, eyebrow, content, action = "") {
    return `
        <section class="panel procurement-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">${escape(eyebrow)}</p><h2>${escape(title)}</h2></div>
                ${action}
            </div>
            ${content}
        </section>
    `;
}

function renderLoading(message = "Loading procurement data…") {
    return `<section class="panel"><p class="admin-table-note" role="status">${escape(message)}</p></section>`;
}

function renderRequestForm(data) {
    const materials = data.materials.map(material => `
        <option value="${escape(material.id)}">${escape(material.code)} · ${escape(material.name)} (${escape(material.unit)})</option>
    `).join("");
    const materialPlaceholder = data.materials.length
        ? "Select material"
        : "No active materials available";

    return `
        <details class="procurement-form-details">
            <summary class="secondary-button">New purchase request</summary>
            <form class="procurement-form" data-procurement-form="create-request">
                <label><span>Request number</span><input name="request_number" maxlength="64" required></label>
                <label><span>Request date</span><input name="request_date" type="date" value="${new Date().toISOString().slice(0, 10)}" required></label>
                <label><span>Expected need date</span><input name="required_date" type="date"></label>
                <label><span>Material</span><select name="material_id" required ${data.materials.length ? "" : "disabled"}><option value="">${materialPlaceholder}</option>${materials}</select></label>
                <label><span>Quantity</span><input name="requested_qty" type="number" min="0.001" step="0.001" required></label>
                <label class="procurement-form-wide"><span>Purpose / justification</span><textarea name="purpose" maxlength="10000" required></textarea></label>
                <button class="primary-button" type="submit" ${data.materials.length ? "" : "disabled"}>Save draft request</button>
            </form>
        </details>
    `;
}

function renderOrderForm(data) {
    const suppliers = data.suppliers.map(supplier => `
        <option value="${escape(supplier.id)}">${escape(supplier.name)}</option>
    `).join("");
    const approvedItems = data.approvedRequestItems.map(item => `
        <option value="${escape(item.id)}" data-material-id="${escape(item.materialId)}">${escape(item.requestNumber)} · ${escape(item.code)} ${escape(item.material)} — ${escape(item.remainingQuantity)} ${escape(item.unit)} remaining</option>
    `).join("");
    const supplierTerms = data.supplierMaterials.map(item => `
        <option value="${escape(item.id)}" data-supplier-id="${escape(item.supplierId)}" data-material-id="${escape(item.materialId)}">${escape(item.supplier)} · ${escape(item.material)} (min ${escape(item.minimumOrderQuantity)})</option>
    `).join("");
    const currencies = data.currencies.map(currency => `<option value="${escape(currency)}">${escape(currency)}</option>`).join("");

    return `
        <details class="procurement-form-details">
            <summary class="secondary-button">Create purchase order</summary>
            <form class="procurement-form" data-procurement-form="create-order">
                <label><span>Order number</span><input name="order_number" maxlength="64" required></label>
                <label><span>Supplier</span><select name="supplier_id" required><option value="">Select supplier</option>${suppliers}</select></label>
                <label><span>Order date</span><input name="order_date" type="date" value="${new Date().toISOString().slice(0, 10)}" required></label>
                <label><span>Expected delivery</span><input name="expected_delivery_date" type="date"></label>
                <label><span>Currency</span><select name="currency_code" required>${currencies}</select></label>
                <label><span>Approved request item</span><select name="purchase_request_item_id" required><option value="">Select approved demand</option>${approvedItems}</select></label>
                <label><span>Supplier material terms</span><select name="supplier_material_id"><option value="">No specific terms</option>${supplierTerms}</select></label>
                <label><span>Order quantity</span><input name="ordered_qty" type="number" min="0.001" step="0.001" required></label>
                <label><span>Unit price</span><input name="unit_price" type="number" min="0" step="0.0001" value="0" required></label>
                <button class="primary-button" type="submit">Save draft order</button>
            </form>
        </details>
    `;
}

function renderSupplierForm() {
    return `
        <details class="procurement-form-details">
            <summary class="secondary-button">Add supplier</summary>
            <form class="procurement-form" data-procurement-form="create-supplier">
                <label><span>Supplier code</span><input name="supplier_code" maxlength="64" required></label>
                <label><span>Supplier name</span><input name="name" maxlength="255" required></label>
                <label><span>Contact person</span><input name="contact_person" maxlength="255"></label>
                <label><span>Contact email</span><input name="contact_email" type="email" maxlength="255"></label>
                <label><span>Contact phone</span><input name="contact_phone" maxlength="50"></label>
                <button class="primary-button" type="submit">Save supplier</button>
            </form>
        </details>
    `;
}

function renderDeliveryForm(data) {
    const availableOrders = data.orderedOrders.filter(order =>
        order.items.some(item => Number(item.remainingDispatchQuantity) > 0));
    const orderOptions = availableOrders.map(order => `
        <option value="${escape(order.id)}">${escape(order.number)} · ${escape(order.supplier)} · expected ${displayDate(order.expectedDate)}</option>
    `).join("");
    const orderItems = availableOrders.flatMap(order => order.items
        .filter(item => Number(item.remainingDispatchQuantity) > 0)
        .map(item => `
        <option value="${escape(item.id)}" data-order-id="${escape(order.id)}">${escape(order.number)} · ${escape(item.code)} ${escape(item.material)} — ${escape(item.remainingDispatchQuantity)} ${escape(item.unit)} available</option>
    `)).join("");

    return `
        <details class="procurement-form-details">
            <summary class="secondary-button">Record expected delivery</summary>
            <form class="procurement-form" data-procurement-form="create-delivery">
                <label><span>Delivery number</span><input name="delivery_number" maxlength="64" required></label>
                <label><span>Purchase order</span><select name="purchase_order_id" required><option value="">Select ordered PO</option>${orderOptions}</select></label>
                <label><span>Order item</span><select name="purchase_order_item_id" required><option value="">Select order item</option>${orderItems}</select></label>
                <label><span>Dispatched quantity</span><input name="dispatched_qty" type="number" min="0.001" step="0.001" required></label>
                <label><span>Expected arrival</span><input name="expected_arrival_date" type="date"></label>
                <label><span>Tracking number</span><input name="tracking_number" maxlength="100"></label>
                <button class="primary-button" type="submit">Save and dispatch delivery</button>
            </form>
        </details>
    `;
}

function renderReceiptForm(data) {
    const deliveryOptions = data.receiptDeliveries.map(delivery => `
        <option value="${escape(delivery.id)}">${escape(delivery.number)} · ${escape(delivery.supplier)} · ${escape(delivery.purchaseOrder)}</option>
    `).join("");
    const itemOptions = data.receiptDeliveries.flatMap(delivery => delivery.items.map(item => `
        <option value="${escape(item.id)}" data-delivery-id="${escape(delivery.id)}">${escape(delivery.number)} · ${escape(item.material)} · ${escape(item.remainingQuantity)} remaining</option>
    `)).join("");

    return `
        <details class="procurement-form-details">
            <summary class="secondary-button">Record goods receipt</summary>
            <form class="procurement-form" data-procurement-form="create-receipt">
                <label><span>Receipt number</span><input name="receipt_number" maxlength="64" required></label>
                <label><span>Delivery</span><select name="delivery_id" required><option value="">Select active delivery</option>${deliveryOptions}</select></label>
                <label><span>Delivery item</span><select name="delivery_item_id" required><option value="">Select material line</option>${itemOptions}</select></label>
                <label><span>Accepted quantity</span><input name="accepted_qty" type="number" min="0" step="0.001" value="0" required></label>
                <label><span>Rejected quantity</span><input name="rejected_qty" type="number" min="0" step="0.001" value="0" required></label>
                <label><span>Batch / lot number</span><input name="lot_code" maxlength="100"></label>
                <label><span>Expiry date</span><input name="expires_at" type="date"></label>
                <label><span>Actual received at</span><input name="actual_received_at" type="datetime-local"></label>
                <label class="procurement-form-wide"><span>Discrepancy notes (required when any quantity is rejected)</span><textarea name="discrepancy_notes" maxlength="10000"></textarea></label>
                <button class="primary-button" type="submit">Post goods receipt</button>
            </form>
        </details>
    `;
}

function renderDashboard(data) {
    const metrics = [
        ["Pending material requests", data.metrics.pendingMaterialRequests, "Awaiting approval"],
        ["Pending purchase requests", data.metrics.pendingPurchaseRequests, "Approved demand awaiting an order"],
        ["Potential shortages", data.metrics.potentialShortages, "Low stock or uncovered approved demand"],
        ["Expected deliveries", data.metrics.expectedDeliveries, "Open inbound records"],
        ["Supplier risks", data.metrics.supplierRisks, "Suppliers with overdue deliveries"],
    ];
    const lowStockRows = data.lowStockMaterials.map(material => `
        <tr><td>${escape(material.code)}</td><td>${escape(material.name)}</td><td>${escape(material.available)} ${escape(material.unit)}</td><td>${escape(material.minimum)} ${escape(material.unit)}</td><td><span class="admin-status unavailable">${escape(displayStatus("low_stock"))}</span></td></tr>
    `).join("");
    const deliveryRows = data.expectedDeliveries.map(delivery => `
        <tr><td>${escape(delivery.number)}</td><td>${escape(delivery.supplier)}</td><td>${escape(delivery.purchaseOrder)}</td><td>${displayDate(delivery.expectedDate)}</td><td>${escape(displayStatus(delivery.status))}</td></tr>
    `).join("");
    const activity = data.recentActivity.map(item => `
        <div class="activity-item"><span class="activity-dot"></span><span>${escape(item.description)}${item.actor ? ` · ${escape(item.actor)}` : ""}</span><small>${displayDate(item.occurredAt)}</small></div>
    `).join("");

    return `
        <section class="procurement-briefing">
            <div><p class="eyebrow">PROCUREMENT WORKSPACE</p><h2>Procurement priorities</h2><p>Monitor material requirements, approval queues, inbound deliveries, and supplier risks.</p></div>
            <div class="procurement-briefing-meta"><span>Data as of</span><strong>${displayDate(data.asOf)}</strong><small>Live procurement records</small></div>
        </section>
        <section class="procurement-metric-grid" aria-label="Procurement summary">
            ${metrics.map(([label, value, detail]) => `<article class="procurement-metric-card"><div class="procurement-metric-copy"><small>${escape(label)}</small><strong>${escape(value)}</strong><span>${escape(detail)}</span></div></article>`).join("")}
        </section>
        ${renderPanel("Material shortages", "MATERIAL REQUIREMENTS", renderTable(["Code", "Material", "Available", "Minimum", "Risk"], lowStockRows, "No materials are currently at or below minimum stock."), '<a class="text-link" href="#procurement-materials" data-page="procurement-materials">View materials →</a>')}
        ${renderPanel("Expected deliveries", "INBOUND MONITORING", renderTable(["Delivery", "Supplier", "Purchase order", "Expected", "Status"], deliveryRows, "No open deliveries are expected."), '<a class="text-link" href="#procurement-deliveries" data-page="procurement-deliveries">View deliveries →</a>')}
        ${renderPanel("Recent activity", "PROCUREMENT HISTORY", activity ? `<div class="activity-feed">${activity}</div>` : '<p class="admin-table-note">No procurement activity recorded yet.</p>')}
    `;
}

function renderMaterials(data) {
    const rows = data.materials.map(material => `
        <tr>
            <td>${escape(material.code)}</td><td>${escape(material.name)}</td><td>${escape(material.category)}</td>
            <td>${escape(material.available)} ${escape(material.unit)}</td><td>${escape(material.reserved)} ${escape(material.unit)}</td>
            <td>${escape(material.minimum)} ${escape(material.unit)}</td><td>${escape(material.dailyUsage)} ${escape(material.unit)}</td>
            <td>${escape(material.inbound)} ${escape(material.unit)}</td><td><strong>${escape(material.suggestedReplenishment)} ${escape(material.unit)}</strong></td>
            <td>${material.coverDays === null ? "—" : `${escape(material.coverDays)} days`}</td><td><span class="admin-status ${material.risk === "sufficient" ? "available" : "unavailable"}">${escape(displayStatus(material.risk))}</span></td>
        </tr>
    `).join("");

    return renderPanel(
        "Material replenishment requirements",
        "LIVE MATERIAL POSITION",
        `${renderTable(["Code", "Material", "Category", "Available stock", "Reserved", "Minimum level", "Est. daily usage", "Inbound", "Suggested replenishment", "Stock cover", "Risk"], rows, "No active material records are available.")}<p class="procurement-source-note">Daily usage is calculated from the last 30 days of stock-out movements. Suggested replenishment considers current available stock, reorder target, and open inbound quantities.</p>`,
        '<a class="text-link" href="#procurement-requests" data-page="procurement-requests">Create a purchase request →</a>',
    );
}

function renderRequestsAndOrders(data) {
    const isProcurementStaff = window.authState?.role === "Procurement Staff";
    const isApprover = ["Management", "System Administrator"].includes(window.authState?.role);
    const requestRows = data.requests.map(purchaseRequest => {
        const actions = purchaseRequest.status === "draft" && isProcurementStaff
            ? `<button class="table-action" type="button" data-procurement-action="submit-request" data-id="${escape(purchaseRequest.id)}">Submit for approval</button>`
            : purchaseRequest.status === "submitted" && isApprover
                ? `<button class="table-action" type="button" data-procurement-action="approve-request" data-id="${escape(purchaseRequest.id)}">Approve</button> <button class="table-action" type="button" data-procurement-action="reject-request" data-id="${escape(purchaseRequest.id)}">Reject</button>`
                : "";
        const items = purchaseRequest.items.map(item => `${escape(item.code)} ${escape(item.material)} · ${escape(item.quantity)} ${escape(item.unit)}`).join("<br>");
        const supplier = purchaseRequest.approvals.map(approval => `${escape(displayStatus(approval.decision))}${approval.approver ? ` by ${escape(approval.approver)}` : ""}`).join("<br>");

        return `<tr><td>${escape(purchaseRequest.number)}</td><td>${displayDate(purchaseRequest.requestDate)}</td><td>${displayDate(purchaseRequest.requiredDate)}</td><td>${items || "—"}</td><td>${escape(purchaseRequest.requester)}</td><td><span class="admin-status ${purchaseRequest.status === "approved" ? "available" : "unavailable"}">${escape(displayStatus(purchaseRequest.status))}</span></td><td>${supplier || "—"}</td><td>${actions}</td></tr>`;
    }).join("");
    const orderRows = data.orders.map(order => {
        const actions = order.status === "draft" && isProcurementStaff
            ? `<button class="table-action" type="button" data-procurement-action="place-order" data-id="${escape(order.id)}">Place order</button>`
            : "";
        const items = order.items.map(item => `${escape(item.code)} ${escape(item.material)} · ${escape(item.quantity)} ${escape(item.unit)}`).join("<br>");
        return `<tr><td>${escape(order.number)}</td><td>${escape(order.supplier)}</td><td>${displayDate(order.orderDate)}</td><td>${displayDate(order.expectedDate)}</td><td>${items || "—"}</td><td>${escape(order.currency)} ${escape(Number(order.total || 0).toFixed(2))}</td><td><span class="admin-status ${order.status === "ordered" ? "available" : "unavailable"}">${escape(displayStatus(order.status))}</span></td><td>${actions}</td></tr>`;
    }).join("");
    const canManage = isProcurementStaff;

    return `
        ${canManage ? renderRequestForm(data) : ""}
        ${isProcurementStaff && data.approvedRequestItems.length > 0 ? renderOrderForm(data) : ""}
        ${renderPanel("Purchase requests", "APPROVAL WORKFLOW", renderTable(["Request number", "Request date", "Expected need", "Material & quantity", "Requested by", "Status", "Decision", "Actions"], requestRows, "No purchase requests have been created."))}
        ${renderPanel("Purchase orders", "SUPPLIER ORDERS", renderTable(["Order number", "Supplier", "Order date", "Expected delivery", "Items", "Total", "Status", "Actions"], orderRows, "No purchase orders have been created."))}
        ${isProcurementStaff && data.approvedRequestItems.length === 0 ? '<p class="admin-table-note">Approved request items will be available for purchase orders after Management or a System Administrator approves them.</p>' : ""}
        <p data-procurement-status class="admin-table-note" role="status"></p>
    `;
}

function renderSuppliers(data) {
    const rows = data.suppliers.map(supplier => {
        const materials = supplier.materials.map(item => `${escape(item.name)}${item.preferred ? " · preferred" : ""}`).join("<br>") || "—";
        const deliveryHistory = supplier.recentDeliveries.map(delivery => `${escape(delivery.number)} · ${escape(displayStatus(delivery.status))} · expected ${displayDate(delivery.expectedDate)} · actual ${displayDate(delivery.actualDate)}`).join("<br>") || "No deliveries";
        return `<tr><td>${escape(supplier.code)}</td><td><strong>${escape(supplier.name)}</strong><br>${escape(supplier.contactPerson)}</td><td>${escape(supplier.email)}<br>${escape(supplier.phone)}</td><td>${materials}</td><td>${escape(supplier.purchaseOrderCount)} orders · ${escape(supplier.deliveryCount)} deliveries</td><td>${deliveryHistory}</td><td><span class="admin-status ${supplier.active ? "available" : "unavailable"}">${supplier.active ? "Active" : "Inactive"}</span></td></tr>`;
    }).join("");

    return `
        ${window.authState?.role === "Procurement Staff" ? renderSupplierForm() : ""}
        ${renderPanel("Supplier directory and performance", "SUPPLIER MANAGEMENT", renderTable(["Code", "Supplier", "Contact", "Materials", "History", "Recent delivery records", "Status"], rows, "No suppliers have been added."))}
    `;
}

function renderDeliveries(data) {
    const rows = data.deliveries.map(delivery => {
        const lines = delivery.items.map(item => `
            <div>${escape(item.material)} · ${escape(item.receivedQuantity)} / ${escape(item.dispatchedQuantity)} received
            ${item.lots.length ? `<br>Lot: ${item.lots.map(escape).join(", ")}` : ""}
            ${item.discrepancies.length ? `<br><span class="admin-status unavailable">${item.discrepancies.map(issue => `${escape(issue.rejectedQuantity)} rejected${issue.notes ? ` — ${escape(issue.notes)}` : ""}`).join("; ")}</span>` : ""}</div>
        `).join("");
        return `<tr><td>${escape(delivery.number)}</td><td>${escape(delivery.supplier)}</td><td>${escape(delivery.purchaseOrder)}</td><td>${displayDate(delivery.expectedDate)}</td><td>${displayDate(delivery.actualDate)}</td><td>${lines || "—"}</td><td>${escape(delivery.trackingNumber)}</td><td><span class="admin-status ${delivery.status === "received" ? "available" : "unavailable"}">${escape(displayStatus(delivery.status))}</span></td></tr>`;
    }).join("");
    const isProcurementStaff = window.authState?.role === "Procurement Staff";

    return `
        ${isProcurementStaff && data.orderedOrders.length ? renderDeliveryForm(data) : ""}
        ${isProcurementStaff && data.receiptDeliveries.length ? renderReceiptForm(data) : ""}
        ${renderPanel("Supplier delivery records", "INBOUND TRACKING", renderTable(["Delivery", "Supplier", "Purchase order", "Expected", "Actual", "Received quantity, lots & discrepancies", "Tracking", "Status"], rows, "No delivery records have been created."))}
    `;
}

function renderReports(data) {
    const activityRows = data.replenishmentHistory.map(item => `<tr><td>${escape(item.description)}</td><td>${escape(item.actor)}</td><td>${displayDate(item.date)}</td></tr>`).join("");
    const frequentRows = data.frequentlyRequestedMaterials.map(item => `<tr><td>${escape(item.code)}</td><td>${escape(item.material)}</td><td>${escape(item.requestCount)}</td><td>${escape(item.quantity)}</td></tr>`).join("");
    const delayedRows = data.delayedDeliveries.map(item => `<tr><td>${escape(item.number)}</td><td>${escape(item.supplier)}</td><td>${escape(item.purchaseOrder)}</td><td>${displayDate(item.expectedDate)}</td><td>${escape(item.daysLate)}</td></tr>`).join("");
    const overstockRows = data.potentialOverstock.map(item => `<tr><td>${escape(item.name)}</td><td>${escape(item.available)} ${escape(item.unit)}</td><td>${escape(item.reorderTo)} ${escape(item.unit)}</td></tr>`).join("");
    const riskRows = data.stockoutRisks.map(item => `<tr><td>${escape(item.code)}</td><td>${escape(item.name)}</td><td>${escape(item.available)} ${escape(item.unit)}</td><td>${escape(item.dailyUsage)} ${escape(item.unit)}</td><td>${escape(displayStatus(item.risk))}</td></tr>`).join("");

    return `
        ${renderPanel("Replenishment history", "RECENT PROCUREMENT ACTIVITY", renderTable(["Activity", "Recorded by", "Date"], activityRows, "No replenishment activity is recorded."))}
        ${renderPanel("Frequently requested materials", "90-DAY DEMAND", renderTable(["Code", "Material", "Request count", "Requested quantity"], frequentRows, "No material requests have been recorded in the last 90 days."))}
        ${renderPanel("Delayed deliveries", "SUPPLIER PERFORMANCE", renderTable(["Delivery", "Supplier", "Order", "Expected date", "Days late"], delayedRows, "No deliveries are overdue."))}
        ${renderPanel("Potential overstock", "STOCK ABOVE REORDER TARGET", renderTable(["Material", "Available", "Reorder target"], overstockRows, "No materials are above their reorder target."))}
        ${renderPanel("Materials at risk of running out", "STOCKOUT WATCH", renderTable(["Code", "Material", "Available", "Estimated daily usage", "Risk"], riskRows, "No material stockout risks are currently flagged."))}
    `;
}

function bindMatchingOptions(parentSelect, childSelect, matchesOption) {
    if (!parentSelect || !childSelect) {
        return;
    }

    const updateOptions = () => {
        for (const option of childSelect.options) {
            const matches = !option.value || matchesOption(option, parentSelect.value);
            option.hidden = !matches;
            option.disabled = !matches;

            if (!matches && option.selected) {
                childSelect.value = "";
            }
        }
    };

    parentSelect.addEventListener("change", updateOptions);
    updateOptions();
}

function bindFormOptionFilters(target, page) {
    if (page === "procurement-requests") {
        const form = target.querySelector('[data-procurement-form="create-order"]');
        if (form) {
            const supplier = form.querySelector('[name="supplier_id"]');
            const requestItem = form.querySelector('[name="purchase_request_item_id"]');
            const supplierMaterial = form.querySelector('[name="supplier_material_id"]');
            const updateTerms = () => {
                const supplierId = supplier.value;
                const materialId = requestItem.selectedOptions[0]?.dataset.materialId;
                for (const option of supplierMaterial.options) {
                    const matches = !option.value
                        || (option.dataset.supplierId === supplierId && option.dataset.materialId === materialId);
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (!matches && option.selected) {
                        supplierMaterial.value = "";
                    }
                }
            };
            supplier.addEventListener("change", updateTerms);
            requestItem.addEventListener("change", updateTerms);
            updateTerms();
        }
    }

    if (page === "procurement-deliveries") {
        const deliveryForm = target.querySelector('[data-procurement-form="create-delivery"]');
        if (deliveryForm) {
            bindMatchingOptions(
                deliveryForm.querySelector('[name="purchase_order_id"]'),
                deliveryForm.querySelector('[name="purchase_order_item_id"]'),
                (option, orderId) => option.dataset.orderId === orderId,
            );
        }

        const receiptForm = target.querySelector('[data-procurement-form="create-receipt"]');
        if (receiptForm) {
            bindMatchingOptions(
                receiptForm.querySelector('[name="delivery_id"]'),
                receiptForm.querySelector('[name="delivery_item_id"]'),
                (option, deliveryId) => option.dataset.deliveryId === deliveryId,
            );
        }
    }
}

const screenRenderers = {
    dashboard: renderDashboard,
    "procurement-materials": renderMaterials,
    "procurement-requests": renderRequestsAndOrders,
    "procurement-suppliers": renderSuppliers,
    "procurement-deliveries": renderDeliveries,
    "procurement-reports": renderReports,
};

const procurementStaff = {
    renderDashboard() {
        return renderLoading("Loading procurement dashboard…");
    },
    renderScreen() {
        return renderLoading();
    },
    async loadScreen(page, target = document.getElementById("appView")) {
        if (!target || !screenEndpoints[page]) {
            return;
        }

        target.innerHTML = renderLoading();

        try {
            const response = await fetch(screenEndpoints[page], {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `The request failed (${response.status}).`);
            }

            target.innerHTML = screenRenderers[page](data);
            target.dataset.procurementPage = page;
            bindFormOptionFilters(target, page);
        } catch (error) {
            target.innerHTML = `<section class="panel"><p class="error-message" role="alert">${escape(error.message || "Could not load procurement data.")}</p></section>`;
        }
    },
};

async function submitProcurementForm(event) {
    const form = event.target.closest("[data-procurement-form]");
    if (!form) {
        return;
    }

    event.preventDefault();
    const formData = new FormData(form);
    const value = name => String(formData.get(name) || "").trim();
    const type = form.dataset.procurementForm;
    let endpoint = "";
    let payload = {};

    if (type === "create-request") {
        endpoint = `${procurementApi}/requests`;
        payload = {
            request_number: value("request_number"),
            request_date: value("request_date"),
            required_date: value("required_date") || null,
            purpose: value("purpose"),
            material_id: value("material_id"),
            requested_qty: value("requested_qty"),
            justification: value("purpose"),
        };
    } else if (type === "create-order") {
        endpoint = `${procurementApi}/orders`;
        payload = {
            order_number: value("order_number"),
            supplier_id: value("supplier_id"),
            order_date: value("order_date"),
            expected_delivery_date: value("expected_delivery_date") || null,
            currency_code: value("currency_code"),
            items: [{
                purchase_request_item_id: value("purchase_request_item_id"),
                supplier_material_id: value("supplier_material_id") || null,
                ordered_qty: value("ordered_qty"),
                unit_price: value("unit_price"),
            }],
        };
    } else if (type === "create-supplier") {
        endpoint = `${procurementApi}/suppliers`;
        payload = {
            supplier_code: value("supplier_code"),
            name: value("name"),
            contact_person: value("contact_person") || null,
            contact_email: value("contact_email") || null,
            contact_phone: value("contact_phone") || null,
            is_active: true,
        };
    } else if (type === "create-delivery") {
        endpoint = `${procurementApi}/deliveries`;
        payload = {
            delivery_number: value("delivery_number"),
            purchase_order_id: value("purchase_order_id"),
            dispatch_date: new Date().toISOString().slice(0, 10),
            expected_arrival_date: value("expected_arrival_date") || null,
            tracking_number: value("tracking_number") || null,
            items: [{
                purchase_order_item_id: value("purchase_order_item_id"),
                dispatched_qty: value("dispatched_qty"),
            }],
        };
    } else if (type === "create-receipt") {
        const deliveryId = value("delivery_id");
        endpoint = `${procurementApi}/deliveries/${encodeURIComponent(deliveryId)}/receipts`;
        payload = {
            receipt_number: value("receipt_number"),
            delivery_id: deliveryId,
            actual_received_at: value("actual_received_at") || null,
            items: [{
                delivery_item_id: value("delivery_item_id"),
                accepted_qty: value("accepted_qty"),
                rejected_qty: value("rejected_qty"),
                lot_code: value("lot_code") || null,
                expires_at: value("expires_at") || null,
                discrepancy_notes: value("discrepancy_notes") || null,
            }],
        };
    }

    try {
        const result = await window.sendWorkspaceRequest(endpoint, payload);
        window.showToast(result.message || "Procurement record saved.");
        await procurementStaff.loadScreen(document.getElementById("appView")?.dataset.procurementPage || "dashboard");
    } catch (error) {
        window.showToast(error.message || "Could not save the procurement record.");
    }
}

async function handleProcurementAction(event) {
    const button = event.target.closest("[data-procurement-action]");
    if (!button) {
        return;
    }

    const action = button.dataset.procurementAction;
    const id = encodeURIComponent(button.dataset.id);
    let endpoint = "";
    let payload = {};

    if (action === "submit-request") {
        endpoint = `${procurementApi}/requests/${id}/submit`;
    } else if (action === "approve-request" || action === "reject-request") {
        endpoint = `${procurementApi}/requests/${id}/decision`;
        payload = {
            purchase_request_id: button.dataset.id,
            decision: action === "approve-request" ? "approved" : "rejected",
            comments: "",
        };
    } else if (action === "place-order") {
        endpoint = `${procurementApi}/orders/${id}/place`;
    }

    if (!endpoint) {
        return;
    }

    button.disabled = true;

    try {
        const result = await window.sendWorkspaceRequest(endpoint, payload);
        window.showToast(result.message || "Procurement workflow updated.");
        await procurementStaff.loadScreen("procurement-requests");
    } catch (error) {
        button.disabled = false;
        window.showToast(error.message || "Could not update the procurement workflow.");
    }
}

document.addEventListener("submit", submitProcurementForm);
document.addEventListener("click", handleProcurementAction);

export { procurementStaff };
