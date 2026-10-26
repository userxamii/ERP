const productionApi = "/warehouse/production";

const productionEndpoints = {
    dashboard: `${productionApi}/dashboard`,
    "production-materials": `${productionApi}/materials`,
    "production-requests": `${productionApi}/requests`,
    "production-issued": `${productionApi}/issued-materials`,
    "production-batches": `${productionApi}/batches`,
};

const boundTargets = new WeakSet();

function escape(value) {
    return escapeHTML(value ?? "—");
}

function statusLabel(value) {
    const labels = {
        pending_approval: "Pending approval",
        partially_fulfilled: "Partially fulfilled",
        available: "Available",
        held: "On hold",
        rejected: "Rejected",
        damaged: "Damaged",
    };

    return labels[value] || String(value || "—").replaceAll("_", " ");
}

function renderLoading(message = "Loading production workspace…") {
    return `<section class="panel"><p class="admin-table-note" role="status">${escape(message)}</p></section>`;
}

function renderMetrics(metrics) {
    const cards = [
        ["Available materials", metrics.availableMaterials, "Active material catalog"],
        ["Need attention", metrics.materialsNeedingAttention, "At or below minimum stock"],
        ["Batches expiring soon", metrics.batchesExpiringSoon, "Eligible lots expiring within 30 days"],
        ["Pending requests", metrics.pendingRequests, "Awaiting Management approval"],
        ["Issued materials", metrics.issuedMaterials, "Your issued batch records"],
    ];

    return `<div class="production-metrics">${cards.map(([label, value, note]) => `
        <article class="panel production-metric">
            <span>${escape(label)}</span>
            <strong>${escape(value)}</strong>
            <small>${escape(note)}</small>
        </article>
    `).join("")}</div>`;
}

function renderDashboard(data) {
    const expiringRows = data.expiringBatches.map(batch => `
        <tr>
            <td>${escape(batch.materialCode)} · ${escape(batch.material)}</td>
            <td>${escape(batch.lotCode)}</td>
            <td>${escape(batch.expiresAt)}</td>
            <td>${escape(batch.daysUntilExpiry)} days</td>
            <td>${escape(batch.storageLocation)}</td>
        </tr>
    `).join("");
    const requestRows = data.pendingRequests.map(request => `
        <tr>
            <td>${escape(request.requestNumber)}</td>
            <td>${escape(request.material)}</td>
            <td>${escape(request.quantity)}</td>
            <td>${escape(statusLabel(request.status))}</td>
        </tr>
    `).join("");

    return `
        <div class="production-workspace">
            ${renderMetrics(data.metrics)}
            <div class="production-shortcuts">
                <a class="secondary-button" href="#production-materials" data-page="production-materials">Review available materials</a>
                <a class="secondary-button" href="#production-requests" data-page="production-requests">Create / track material request</a>
                <a class="secondary-button" href="#production-batches" data-page="production-batches">Review batch FEFO queue</a>
            </div>
            ${renderPanel("Batches expiring soon", renderTable(
                ["Material", "Batch / lot", "Expiration", "Remaining shelf life", "Warehouse location"],
                expiringRows,
                "No eligible batches expire within the next 30 days.",
            ))}
            ${renderPanel("Your pending material requests", renderTable(
                ["Request", "Material", "Quantity", "Status"],
                requestRows,
                "You have no requests waiting for approval.",
            ))}
        </div>
    `;
}

function renderPanel(title, body) {
    return `<section class="panel production-panel"><div class="panel-heading"><h2>${escape(title)}</h2></div>${body}</section>`;
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

function renderMaterials(data) {
    const rows = data.materials.map(material => `
        <tr>
            <td><strong>${escape(material.code)}</strong></td>
            <td>${escape(material.name)}</td>
            <td>${escape(material.category)}</td>
            <td>${escape(material.availableQuantity)} ${escape(material.unit)}</td>
            <td>${escape(material.reservedQuantity)} ${escape(material.unit)}</td>
            <td>${escape(material.minimumQuantity)} ${escape(material.unit)}</td>
            <td>${escape(material.usableBatchCount)} / ${escape(material.batchCount)}</td>
        </tr>
    `).join("");

    return `<div class="production-workspace">${renderPanel("Available materials", renderTable(
        ["Material code", "Material", "Category", "Usable quantity", "Reserved", "Minimum", "Usable / total batches"],
        rows,
        "No active materials are available.",
    ))}<p class="production-note">Usable quantities exclude expired and QA-held batches and active stock reservations. Select only materials and lots compatible with your production order.</p></div>`;
}

function renderRequestForm(data) {
    const materialOptions = data.materials.map(material => `
        <option value="${escape(material.id)}">${escape(material.code)} · ${escape(material.name)} (${escape(material.unit)})</option>
    `).join("");
    const hasMaterialChoices = materialOptions.length > 0;

    return `
        <details class="procurement-form-details" open>
            <summary class="secondary-button">New material request</summary>
            <form class="procurement-form" data-production-form="create-request">
                <label><span>Request number</span><input name="request_number" maxlength="64" required></label>
                <label><span>Material</span><select name="material_id" required ${hasMaterialChoices ? "" : "disabled"}><option value="">${hasMaterialChoices ? "Select a usable material" : "No usable materials available"}</option>${materialOptions}</select></label>
                <label><span>Requested quantity</span><input name="requested_qty" type="number" min="0.001" step="0.001" required></label>
                <label><span>Production order / reference</span><input name="production_order" maxlength="120" placeholder="Optional production order"></label>
                <label><span>Required date</span><input name="required_date" type="date" min="${new Date().toISOString().slice(0, 10)}"></label>
                <label class="procurement-form-wide"><span>Purpose</span><textarea name="purpose" maxlength="2000" required></textarea></label>
                <button class="primary-button" type="submit" ${hasMaterialChoices ? "" : "disabled"}>Submit for approval</button>
            </form>
        </details>
    `;
}

function renderRequests(data) {
    const rows = data.requests.map(request => {
        const actions = [];
        if (data.canApprove && request.status === "pending_approval") {
            actions.push(`<input class="production-decision-notes" aria-label="Decision comments for ${escape(request.requestNumber)}" placeholder="Decision comments (optional)" data-comments-for="${escape(request.id)}">`);
            actions.push(`<button class="table-action" type="button" data-production-action="approve" data-id="${escape(request.id)}">Approve</button>`);
            actions.push(`<button class="table-action" type="button" data-production-action="reject" data-id="${escape(request.id)}">Reject</button>`);
        }
        const batches = data.availableBatches.filter(batch => String(batch.materialId) === String(request.materialId));
        if (data.canFulfill && ["approved", "partially_fulfilled"].includes(request.status)) {
            const options = batches.map(batch => `
                <option value="${escape(batch.id)}" data-quantity="${escape(batch.quantityOnHand)}">
                    ${batch.recommended ? "FEFO · " : ""}${escape(batch.lotCode)} · ${escape(batch.quantityOnHand)} ${escape(batch.unit)} · expires ${escape(batch.expiresAt || "not set")}
                </option>
            `).join("");
            actions.push(`
                <form class="production-fulfillment-form" data-production-form="fulfill-request" data-id="${escape(request.id)}" data-remaining-quantity="${escape(request.remainingQuantity)}">
                    <select name="inventory_batch_id" required ${batches.length ? "" : "disabled"}>
                        <option value="">${batches.length ? "Select eligible batch" : "No eligible batch available"}</option>${options}
                    </select>
                    <input name="quantity" type="number" min="0.001" step="0.001" max="${escape(request.remainingQuantity)}" placeholder="Qty (${escape(request.unit)})" required>
                    <input name="destination_location" maxlength="120" placeholder="Production line / destination" required>
                    <button class="table-action" type="submit" ${batches.length ? "" : "disabled"}>Issue materials</button>
                </form>
            `);
        }
        const fulfillments = request.fulfillments.map(issue => `
            ${escape(issue.batch)} · ${escape(issue.quantity)} ${escape(request.unit)} by ${escape(issue.issuedBy)} (${formatDate(issue.issuedAt)})
        `).join("<br>") || "Not yet issued";

        return `<tr>
            <td><strong>${escape(request.requestNumber)}</strong><br><small>${escape(formatDate(request.createdAt))}</small></td>
            <td>${escape(request.materialCode)} · ${escape(request.material)}</td>
            <td>${escape(request.requestedQuantity)} ${escape(request.unit)}<br><small>${escape(request.fulfilledQuantity)} fulfilled · ${escape(request.remainingQuantity)} remaining</small></td>
            <td>${escape(request.productionOrder || "—")}<br>${escape(request.purpose)}</td>
            <td>${escape(request.requiredDate || "—")}</td>
            <td><span class="admin-status ${request.status === "fulfilled" || request.status === "approved" ? "available" : "unavailable"}">${escape(statusLabel(request.status))}</span><br><small>${request.approver ? `Decision by ${escape(request.approver)}` : ""}${request.decisionNotes ? ` · ${escape(request.decisionNotes)}` : ""}</small></td>
            <td>${escape(request.requester)}</td>
            <td>${fulfillments}</td>
            <td>${actions.join("<br>") || "—"}</td>
        </tr>`;
    }).join("");
    const form = data.canCreate ? renderRequestForm(data) : "";

    return `<div class="production-workspace">${form}${renderPanel("Material requests", renderTable(
        ["Request / date", "Material", "Requested / fulfilled", "Production order / purpose", "Required date", "Status / approval", "Requested by", "Warehouse fulfillment", "Actions"],
        rows,
        "No material requests are available.",
    ))}<p class="production-action-status" role="status" aria-live="polite"></p></div>`;
}

function renderIssued(data) {
    const rows = data.issues.map(issue => `
        <tr>
            <td>${escape(issue.requestNumber)}</td>
            <td>${escape(issue.materialCode)} · ${escape(issue.material)}</td>
            <td>${escape(issue.batch)}</td>
            <td>${escape(issue.quantity)} ${escape(issue.unit)}</td>
            <td>${escape(issue.productionOrder)}<br><small>${escape(issue.destination)}</small></td>
            <td>${escape(issue.issuedBy)}</td>
            <td>${formatDate(issue.issuedAt)}</td>
            <td>${escape(issue.remaining)} ${escape(issue.unit)}</td>
        </tr>
    `).join("");

    return `<div class="production-workspace">${renderPanel("Issued materials", renderTable(
        ["Request", "Material", "Batch / lot", "Issued quantity", "Production reference / destination", "Issuing staff", "Issued at", "Batch remaining"],
        rows,
        "No materials have been issued against production requests.",
    ))}</div>`;
}

function renderBatches(data) {
    const rows = data.batches.map(batch => {
        const shelfLife = batch.daysUntilExpiry === null
            ? "No expiry date"
            : batch.daysUntilExpiry < 0
                ? `Expired ${Math.abs(batch.daysUntilExpiry)} days ago`
                : `${batch.daysUntilExpiry} days`;
        const recommendation = batch.recommended
            ? '<span class="admin-status available">FEFO recommended</span>'
            : batch.eligible
                ? "Eligible"
                : "Not eligible for issue";

        return `<tr>
            <td>${escape(batch.materialCode)} · ${escape(batch.material)}</td>
            <td>${escape(batch.lotCode)}</td>
            <td>${escape(batch.quantityOnHand)} ${escape(batch.unit)}</td>
            <td><span class="admin-status ${batch.qualityStatus === "available" ? "available" : "unavailable"}">${escape(statusLabel(batch.qualityStatus))}</span></td>
            <td>${escape(batch.expiresAt || "—")}</td>
            <td>${escape(shelfLife)}</td>
            <td>${escape(batch.storageLocation)}</td>
            <td>${recommendation}</td>
        </tr>`;
    }).join("");

    return `<div class="production-workspace"><p class="production-note">FEFO recommendations prioritize the earliest-expiring eligible batch. Expired, held, rejected, damaged, and empty batches are never recommended; QA status and production suitability take priority over expiry order.</p>${renderPanel(
        "Batch availability & expiration",
        renderTable(
            ["Material", "Batch / lot", "On hand", "QA status", "Expiration", "Remaining shelf life", "Warehouse location", "Issue recommendation"],
            rows,
            "No inventory batches are available.",
        ),
    )}</div>`;
}

function formatDate(value) {
    if (!value) {
        return "—";
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? escape(value) : escape(date.toLocaleString());
}

const screenRenderers = {
    dashboard: renderDashboard,
    "production-materials": renderMaterials,
    "production-requests": renderRequests,
    "production-issued": renderIssued,
    "production-batches": renderBatches,
};

async function submitProductionForm(event) {
    const form = event.target.closest("[data-production-form]");
    if (!form) {
        return;
    }

    event.preventDefault();
    const formData = new FormData(form);
    const value = name => String(formData.get(name) || "").trim();

    try {
        if (form.dataset.productionForm === "create-request") {
            await window.sendWorkspaceRequest(`${productionApi}/requests`, {
                request_number: value("request_number"),
                material_id: value("material_id"),
                requested_qty: value("requested_qty"),
                production_order: value("production_order") || null,
                required_date: value("required_date") || null,
                purpose: value("purpose"),
            });
        } else if (form.dataset.productionForm === "fulfill-request") {
            await window.sendWorkspaceRequest(`${productionApi}/requests/${encodeURIComponent(form.dataset.id)}/fulfill`, {
                inventory_batch_id: value("inventory_batch_id"),
                quantity: value("quantity"),
                destination_location: value("destination_location"),
            });
        } else {
            return;
        }

        await productionStaff.loadScreen(form.closest("[data-production-page]")?.dataset.productionPage || "production-requests");
        window.showToast("Production material workflow updated.");
    } catch (error) {
        showActionError(form, error);
    }
}

function updateFulfillmentQuantityLimit(event) {
    const batchSelect = event.target.closest('select[name="inventory_batch_id"]');
    if (!batchSelect) {
        return;
    }

    const quantityInput = batchSelect.form?.querySelector('[name="quantity"]');
    const batchQuantity = Number(batchSelect.selectedOptions[0]?.dataset.quantity);
    if (!quantityInput || !Number.isFinite(batchQuantity) || batchQuantity <= 0) {
        return;
    }

    const remainingQuantity = Number(batchSelect.form.dataset.remainingQuantity);
    quantityInput.max = String(Math.min(batchQuantity, remainingQuantity));
    if (Number(quantityInput.value) > Number(quantityInput.max)) {
        quantityInput.value = quantityInput.max;
    }
}

async function handleProductionAction(event) {
    const button = event.target.closest("[data-production-action]");
    if (!button) {
        return;
    }

    const requestRow = button.closest("tr");
    const commentInput = requestRow?.querySelector(`[data-comments-for="${CSS.escape(button.dataset.id)}"]`);

    try {
        await window.sendWorkspaceRequest(`${productionApi}/requests/${encodeURIComponent(button.dataset.id)}/decision`, {
            decision: button.dataset.productionAction,
            comments: commentInput?.value.trim() || null,
        });
        await productionStaff.loadScreen("production-requests");
        window.showToast(`Material request ${button.dataset.productionAction === "approve" ? "approved" : "rejected"}.`);
    } catch (error) {
        showActionError(button, error);
    }
}

function showActionError(element, error) {
    const target = element.closest("[data-production-page]")?.querySelector(".production-action-status");
    if (target) {
        target.textContent = error.message || "The production request could not be updated.";
        target.classList.add("error-message");
    } else {
        window.showToast(error.message || "The production request could not be updated.");
    }
}

const productionStaff = {
    renderDashboard() {
        return renderLoading("Loading production dashboard…");
    },
    renderScreen() {
        return renderLoading();
    },
    async loadScreen(page, target = document.getElementById("appView")) {
        if (!target || !productionEndpoints[page]) {
            return;
        }

        if (!boundTargets.has(target)) {
            target.addEventListener("submit", submitProductionForm);
            target.addEventListener("click", handleProductionAction);
            target.addEventListener("change", updateFulfillmentQuantityLimit);
            boundTargets.add(target);
        }

        target.innerHTML = renderLoading();

        try {
            const response = await fetch(productionEndpoints[page], {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `The request failed (${response.status}).`);
            }

            target.innerHTML = screenRenderers[page](data);
            target.dataset.productionPage = page;
        } catch (error) {
            target.innerHTML = `<section class="panel"><p class="error-message" role="alert">${escape(error.message || "Could not load production data.")}</p></section>`;
        }
    },
};

export { productionStaff };
