(function () {
    "use strict";

    var app = document.querySelector(".superadmin-app");
    var menuToggle = document.querySelector(".admin-menu-toggle");
    var backdrop = document.querySelector(".admin-backdrop");

    function setMenuOpen(isOpen) {
        if (!app || !menuToggle || !backdrop) {
            return;
        }

        app.classList.toggle("menu-open", isOpen);
        document.body.classList.toggle("admin-menu-open", isOpen);
        menuToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        menuToggle.setAttribute("aria-label", isOpen ? "Close dashboard menu" : "Open dashboard menu");
        backdrop.hidden = !isOpen;
    }

    if (menuToggle && backdrop) {
        menuToggle.addEventListener("click", function () {
            setMenuOpen(menuToggle.getAttribute("aria-expanded") !== "true");
        });

        backdrop.addEventListener("click", function () {
            setMenuOpen(false);
        });

        document.querySelectorAll(".admin-nav a").forEach(function (link) {
            link.addEventListener("click", function () {
                setMenuOpen(false);
                document.querySelectorAll(".admin-nav a").forEach(function (item) {
                    item.classList.toggle("active", item === link);
                });
            });
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                setMenuOpen(false);
            }
        });
    }

    var dateElement = document.querySelector("[data-admin-date]");
    if (dateElement) {
        dateElement.textContent = new Intl.DateTimeFormat(undefined, {
            weekday: "short",
            month: "short",
            day: "numeric",
            year: "numeric"
        }).format(new Date());
    }

    function filterRows(rows, searchInput, statusFilter, emptyMessage, resultCount) {
        if (!rows.length) {
            return;
        }

        function applyFilters() {
            var search = searchInput ? searchInput.value.trim().toLocaleLowerCase() : "";
            var status = statusFilter ? statusFilter.value.toLowerCase() : "";
            var visibleCount = 0;

            rows.forEach(function (row) {
                var matchesSearch = !search || (row.dataset.search || "").indexOf(search) !== -1;
                var matchesStatus = !status || row.dataset.status === status;
                var isVisible = matchesSearch && matchesStatus;
                row.hidden = !isVisible;
                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (emptyMessage) {
                emptyMessage.hidden = visibleCount > 0;
            }
            if (resultCount) {
                resultCount.textContent = String(visibleCount);
            }
        }

        if (searchInput) {
            searchInput.addEventListener("input", applyFilters);
        }
        if (statusFilter) {
            statusFilter.addEventListener("change", applyFilters);
        }
    }

    filterRows(
        Array.prototype.slice.call(document.querySelectorAll("[data-school-row]")),
        document.getElementById("school-search"),
        document.getElementById("school-status-filter"),
        document.getElementById("school-filter-empty"),
        document.getElementById("school-result-count")
    );

    filterRows(
        Array.prototype.slice.call(document.querySelectorAll("[data-payment-row]")),
        document.getElementById("payment-search"),
        null,
        document.getElementById("payment-filter-empty"),
        null
    );

    document.querySelectorAll("form[data-confirm-action]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            var submitButton = event.submitter;
            var action = submitButton ? submitButton.value : form.dataset.confirmAction;
            var schoolRow = form.closest("[data-school-row]");
            var schoolName = schoolRow ? schoolRow.querySelector(".school-name").textContent.trim() : "this school";

            if (!window.confirm("Are you sure you want to " + action.toLowerCase() + " " + schoolName + "?")) {
                event.preventDefault();
            }
        });
    });
})();
