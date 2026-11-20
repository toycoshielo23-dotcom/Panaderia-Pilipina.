document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const confirmation = form.dataset.confirm;
            if (confirmation && !window.confirm(confirmation)) {
                event.preventDefault();
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }
        });
    });

    const tenantSearch = document.querySelector('#tenant-search');
    const tenantStatus = document.querySelector('#tenant-status-filter');
    if (tenantSearch && tenantStatus) {
        const tenantRows = [...document.querySelectorAll('.tenant-row')];
        const tenantGroups = [...document.querySelectorAll('.room-tenant-card')];
        const filterTenants = () => {
            const query = tenantSearch.value.trim().toLowerCase();
            const status = tenantStatus.value;

            tenantRows.forEach((row) => {
                const matches = row.dataset.search.includes(query)
                    && (!status || row.dataset.status === status);
                row.hidden = !matches;
            });

            tenantGroups.forEach((group) => {
                const rows = [...group.querySelectorAll('.tenant-row')];
                const hasVisibleTenant = rows.some((row) => !row.hidden);
                const emptyState = group.querySelector('.tenant-empty-state');
                if (emptyState) emptyState.hidden = rows.length === 0 || hasVisibleTenant;
            });
        };

        tenantSearch.addEventListener('input', filterTenants);
        tenantStatus.addEventListener('change', filterTenants);
    }
});
