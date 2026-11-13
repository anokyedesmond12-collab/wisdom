(function () {
    "use strict";

    var body = document.body;
    var sidebar = document.querySelector(".dashboard-layout > .sidebar");
    var topBarLeft = document.querySelector(".top-bar-left");
    var menuToggle;
    var backdrop;

    if (sidebar && topBarLeft) {
        sidebar.id = "school-dashboard-navigation";
        sidebar.setAttribute("aria-label", "School administration navigation");

        menuToggle = document.createElement("button");
        menuToggle.type = "button";
        menuToggle.className = "school-menu-toggle";
        menuToggle.setAttribute("aria-controls", sidebar.id);
        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.setAttribute("aria-label", "Open navigation menu");

        var menuLines = document.createElement("span");
        menuLines.setAttribute("aria-hidden", "true");
        for (var line = 0; line < 3; line += 1) {
            menuLines.appendChild(document.createElement("i"));
        }
        menuToggle.appendChild(menuLines);
        topBarLeft.insertBefore(menuToggle, topBarLeft.firstChild);

        backdrop = document.createElement("button");
        backdrop.type = "button";
        backdrop.className = "school-menu-backdrop";
        backdrop.setAttribute("aria-label", "Close navigation menu");
        backdrop.hidden = true;
        body.appendChild(backdrop);

        function setMenuOpen(isOpen) {
            body.classList.toggle("school-menu-open", isOpen);
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
                if (window.matchMedia("(max-width: 768px)").matches) {
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
            if (!window.matchMedia("(max-width: 768px)").matches) {
                setMenuOpen(false);
            }
        });
    }

    var topBarRight = document.querySelector(".top-bar-right");
    if (topBarRight) {
        var today = document.createElement("span");
        today.className = "school-dashboard-date";
        today.textContent = new Intl.DateTimeFormat(undefined, {
            weekday: "short",
            month: "short",
            day: "numeric",
            year: "numeric"
        }).format(new Date());
        topBarRight.insertBefore(today, topBarRight.firstChild);
    }

    document.querySelectorAll(".metric-details strong").forEach(function (value) {
        var text = value.textContent.trim();
        if (!/^\d+$/.test(text) || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        var target = Number(text);
        var start = performance.now();
        var duration = 480;
        function animate(now) {
            var progress = Math.min((now - start) / duration, 1);
            value.textContent = String(Math.round(target * progress));
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }
        value.textContent = "0";
        requestAnimationFrame(animate);
    });

    var actionGrid = document.querySelector(".quick-actions-grid");
    if (actionGrid) {
        var searchWrap = document.createElement("label");
        searchWrap.className = "school-action-search";
        var search = document.createElement("input");
        search.type = "search";
        search.placeholder = "Find a management tool";
        search.setAttribute("aria-label", "Search management actions");
        searchWrap.appendChild(search);
        actionGrid.parentNode.insertBefore(searchWrap, actionGrid);

        var actions = Array.prototype.slice.call(actionGrid.querySelectorAll(".action-card"));
        var emptyMessage = document.createElement("p");
        emptyMessage.className = "school-action-empty";
        emptyMessage.textContent = "No management tools match your search.";
        emptyMessage.hidden = true;
        actionGrid.parentNode.insertBefore(emptyMessage, actionGrid.nextSibling);

        search.addEventListener("input", function () {
            var query = search.value.trim().toLocaleLowerCase();
            var visible = 0;
            actions.forEach(function (action) {
                var matches = action.textContent.toLocaleLowerCase().indexOf(query) !== -1;
                action.hidden = !matches;
                if (matches) {
                    visible += 1;
                }
            });
            emptyMessage.hidden = visible > 0;
        });
    }
})();
