document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const roleSelect = document.getElementById('operator-role');
    const accessBox = document.getElementById('domain-access-box');
    const zonePicker = document.getElementById('zone-picker');

    const syncDomainAccessUi = () => {
        if (!roleSelect || !accessBox) return;
        const isAdmin = roleSelect.value === 'admin';
        accessBox.style.opacity = isAdmin ? '0.55' : '1';
        accessBox.querySelectorAll('input').forEach((input) => {
            input.disabled = isAdmin;
        });
        if (isAdmin) {
            const allRadio = accessBox.querySelector('input[value="all"]');
            if (allRadio) allRadio.checked = true;
        }
        const selected = accessBox.querySelector('input[name="domain_access"]:checked');
        if (zonePicker) {
            zonePicker.style.display = (!isAdmin && selected && selected.value === 'selected') ? 'grid' : 'none';
        }
    };

    if (roleSelect) {
        roleSelect.addEventListener('change', syncDomainAccessUi);
        accessBox?.querySelectorAll('input[name="domain_access"]').forEach((input) => {
            input.addEventListener('change', syncDomainAccessUi);
        });
        syncDomainAccessUi();
    }
});
