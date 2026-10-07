function showExpiring() {
    alert(
        "Expiration Alert\n\n" +
        "3 products are nearing expiration.\n" +
        "Please prioritize them for distribution."
    );
}

function updateProduct(productName) {
    const newStatus = prompt(
        "Update status for " + productName + ":\n\n" +
        "Example: For Distribution, Distributed, On Hold"
    );

    if (newStatus) {
        alert(
            productName +
            " has been updated to:\n" +
            newStatus
        );
    }
}

function filterProducts() {
    const search = prompt(
        "Enter product name or batch number:"
    );

    if (!search) {
        return;
    }

    const rows = document.querySelectorAll("#productTable tr");

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();

        if (text.includes(search.toLowerCase())) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}