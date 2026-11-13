(() => {
    "use strict";

    const app = document.querySelector(".teacher-app");
    const menuToggle = document.querySelector(".teacher-menu-toggle");
    const menuBackdrop = document.querySelector(".teacher-sidebar-backdrop");
    const sidebar = document.querySelector(".teacher-sidebar");

    if (app && menuToggle && menuBackdrop && sidebar) {
        const setMenuOpen = (isOpen) => {
            app.classList.toggle("menu-open", isOpen);
            document.body.classList.toggle("teacher-menu-open", isOpen);
            menuToggle.setAttribute("aria-expanded", String(isOpen));
            menuToggle.setAttribute("aria-label", isOpen ? "Close teacher menu" : "Open teacher menu");
            menuBackdrop.hidden = !isOpen;
        };

        menuToggle.addEventListener("click", () => {
            setMenuOpen(menuToggle.getAttribute("aria-expanded") !== "true");
        });
        menuBackdrop.addEventListener("click", () => setMenuOpen(false));
        sidebar.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                if (window.matchMedia("(max-width: 760px)").matches) {
                    setMenuOpen(false);
                }
            });
        });
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && menuToggle.getAttribute("aria-expanded") === "true") {
                setMenuOpen(false);
                menuToggle.focus();
            }
        });
        window.addEventListener("resize", () => {
            if (!window.matchMedia("(max-width: 760px)").matches) {
                setMenuOpen(false);
            }
        });
    }

    const dateNode = document.querySelector("[data-current-date]");
    if (dateNode) {
        dateNode.textContent = new Intl.DateTimeFormat(undefined, {
            weekday: "long",
            month: "short",
            day: "numeric"
        }).format(new Date());
    }

    const greeting = document.querySelector("[data-greeting]");
    if (greeting) {
        const hour = new Date().getHours();
        const salutation = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
        greeting.textContent = `${salutation}, ${greeting.dataset.name || "Teacher"}`;
    }

    const counters = document.querySelectorAll("[data-count]");
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    counters.forEach((counter) => {
        const target = Number(counter.dataset.count);
        if (!Number.isFinite(target)) {
            return;
        }
        if (reduceMotion || target === 0) {
            counter.textContent = String(target);
            return;
        }

        const duration = 550;
        const start = performance.now();
        const animate = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            counter.textContent = String(Math.round(target * progress));
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        requestAnimationFrame(animate);
    });

    const search = document.getElementById("assignment-search");
    const assignmentCards = Array.from(document.querySelectorAll("[data-assignment-card]"));
    const emptySearch = document.getElementById("assignment-empty-filter");
    if (search && assignmentCards.length) {
        const filterAssignments = () => {
            const query = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            assignmentCards.forEach((card) => {
                const matches = (card.dataset.search || "").toLocaleLowerCase().includes(query);
                card.hidden = !matches;
                if (matches) {
                    visible += 1;
                }
            });
            if (emptySearch) {
                emptySearch.hidden = visible > 0;
            }
        };
        search.addEventListener("input", filterAssignments);
    }
})();
