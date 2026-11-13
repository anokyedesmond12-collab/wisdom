(function () {
    "use strict";

    var body = document.body;
    var header = document.querySelector(".topnav");
    var sidebar = document.querySelector(".sidebar");
    var menuToggle;
    var backdrop;

    if (header && sidebar) {
        sidebar.id = "family-portal-navigation";
        sidebar.setAttribute("aria-label", "Portal navigation");

        menuToggle = document.createElement("button");
        menuToggle.type = "button";
        menuToggle.className = "portal-menu-toggle";
        menuToggle.setAttribute("aria-controls", sidebar.id);
        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.setAttribute("aria-label", "Open navigation menu");

        var menuLines = document.createElement("span");
        menuLines.setAttribute("aria-hidden", "true");
        for (var line = 0; line < 3; line += 1) {
            menuLines.appendChild(document.createElement("i"));
        }
        menuToggle.appendChild(menuLines);
        header.insertBefore(menuToggle, header.firstChild);

        backdrop = document.createElement("button");
        backdrop.type = "button";
        backdrop.className = "portal-sidebar-backdrop";
        backdrop.setAttribute("aria-label", "Close navigation menu");
        backdrop.hidden = true;
        body.appendChild(backdrop);

        function setMenuOpen(isOpen) {
            body.classList.toggle("portal-menu-open", isOpen);
            menuToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            menuToggle.setAttribute("aria-label", isOpen ? "Close navigation menu" : "Open navigation menu");
            backdrop.hidden = !isOpen;
        }

        menuToggle.addEventListener("click", function () {
            setMenuOpen(menuToggle.getAttribute("aria-expanded") !== "true");
        });
        backdrop.addEventListener("click", function () {
            setMenuOpen(false);
        });
        sidebar.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                if (window.matchMedia("(max-width: 760px)").matches) {
                    setMenuOpen(false);
                }
            });
        });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && menuToggle.getAttribute("aria-expanded") === "true") {
                setMenuOpen(false);
                menuToggle.focus();
            }
        });
        window.addEventListener("resize", function () {
            if (!window.matchMedia("(max-width: 760px)").matches) {
                setMenuOpen(false);
            }
        });
    }

    var welcomeCard = document.querySelector(".welcome-card");
    if (welcomeCard) {
        var date = document.createElement("span");
        date.className = "portal-welcome-date";
        date.textContent = new Intl.DateTimeFormat(undefined, {
            weekday: "long",
            month: "short",
            day: "numeric",
            year: "numeric"
        }).format(new Date());
        welcomeCard.appendChild(date);
    }

    document.querySelectorAll(".info-card .val").forEach(function (value) {
        var match = value.textContent.trim().match(/^(\d+(?:\.\d+)?)(.*)$/);
        if (!match || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        var target = Number(match[1]);
        var suffix = match[2];
        var decimalPlaces = (match[1].split(".")[1] || "").length;
        var start = performance.now();
        var duration = 480;

        function animate(now) {
            var progress = Math.min((now - start) / duration, 1);
            value.textContent = (target * progress).toFixed(decimalPlaces) + suffix;
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }

        value.textContent = (0).toFixed(decimalPlaces) + suffix;
        requestAnimationFrame(animate);
    });

    document.querySelectorAll(".section-card table").forEach(function (table) {
        var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr")).filter(function (row) {
            return !row.querySelector(".empty-note");
        });
        if (!rows.length) {
            return;
        }

        var tools = document.createElement("div");
        tools.className = "portal-table-tools";
        var search = document.createElement("input");
        search.type = "search";
        search.className = "portal-table-search";
        search.placeholder = "Filter these records";
        search.setAttribute("aria-label", "Filter records in this table");
        var count = document.createElement("span");
        count.className = "portal-row-count";
        var noMatches = document.createElement("p");
        noMatches.className = "portal-filter-empty";
        noMatches.textContent = "No records match your search.";
        noMatches.hidden = true;
        tools.appendChild(search);
        tools.appendChild(count);
        table.parentNode.insertBefore(tools, table);
        table.parentNode.insertBefore(noMatches, table.nextSibling);

        function filterRows() {
            var query = search.value.trim().toLocaleLowerCase();
            var visible = 0;
            rows.forEach(function (row) {
                var matches = row.textContent.toLocaleLowerCase().indexOf(query) !== -1;
                row.hidden = !matches;
                if (matches) {
                    visible += 1;
                }
            });
            count.textContent = visible + (visible === 1 ? " record" : " records");
            noMatches.hidden = visible > 0;
        }

        search.addEventListener("input", filterRows);
        filterRows();
    });

    var attendance = document.getElementById("attendance");
    if (attendance) {
        attendance.querySelectorAll("tbody tr").forEach(function (row) {
            var cell = row.querySelector("td:last-child");
            if (!cell || cell.querySelector(".empty-note")) {
                return;
            }

            var status = cell.textContent.trim();
            var badge = document.createElement("span");
            badge.className = "portal-status is-" + status.toLowerCase().replace(/[^a-z]+/g, "-");
            badge.textContent = status;
            cell.textContent = "";
            cell.appendChild(badge);
        });
    }

    document.querySelectorAll(".sidebar a[href^='#']").forEach(function (link) {
        link.addEventListener("click", function () {
            document.querySelectorAll(".sidebar a[href^='#']").forEach(function (item) {
                item.classList.toggle("active", item === link);
            });
        });
    });
})();
