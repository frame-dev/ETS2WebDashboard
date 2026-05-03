(() => {
    const tabStorageKey = "ets2-dashboard-settings-tab";
    const tabs = Array.from(document.querySelectorAll("[data-settings-tab]"));
    const panels = Array.from(document.querySelectorAll("[data-settings-panel]"));

    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    const activateTab = (tabName) => {
        let matched = false;

        tabs.forEach((tab) => {
            const isActive = tab.dataset.settingsTab === tabName;
            tab.classList.toggle("is-active", isActive);
            tab.setAttribute("aria-selected", isActive ? "true" : "false");
            if (isActive) {
                matched = true;
            }
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.settingsPanel === tabName;
            panel.classList.toggle("is-active", isActive);
            panel.hidden = !isActive;
        });

        if (!matched) {
            return;
        }

        try {
            window.localStorage.setItem(tabStorageKey, tabName);
        } catch (error) {
        }

        if (window.history && typeof window.history.replaceState === "function") {
            window.history.replaceState(null, "", `#${tabName}`);
        } else {
            window.location.hash = tabName;
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            activateTab(tab.dataset.settingsTab || "");
        });
    });

    document.addEventListener("click", (event) => {
        const action = event.target instanceof Element
            ? event.target.closest("[data-confirm]")
            : null;
        if (!(action instanceof HTMLElement)) {
            return;
        }

        const message = action.dataset.confirm || "Continue with this action?";
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    let initialTab = window.location.hash.replace(/^#/, "");

    if (!tabs.some((tab) => tab.dataset.settingsTab === initialTab)) {
        try {
            initialTab = window.localStorage.getItem(tabStorageKey) || "";
        } catch (error) {
            initialTab = "";
        }
    }

    if (!tabs.some((tab) => tab.dataset.settingsTab === initialTab)) {
        initialTab = tabs[0]?.dataset.settingsTab || "general";
    }

    activateTab(initialTab);
})();
