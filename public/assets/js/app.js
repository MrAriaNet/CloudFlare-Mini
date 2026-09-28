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
        const selectedOption = roleSelect.options[roleSelect.selectedIndex];
        const rank = selectedOption ? parseInt(selectedOption.getAttribute('data-rank') || '0', 10) : 0;
        const isFullAccess = roleSelect.value === 'admin' || rank >= 100;
        accessBox.style.opacity = isFullAccess ? '0.55' : '1';
        accessBox.querySelectorAll('input').forEach((input) => {
            input.disabled = isFullAccess;
        });
        if (isFullAccess) {
            const allRadio = accessBox.querySelector('input[value="all"]');
            if (allRadio) allRadio.checked = true;
        }
        const selected = accessBox.querySelector('input[name="domain_access"]:checked');
        if (zonePicker) {
            zonePicker.style.display = (!isFullAccess && selected && selected.value === 'selected') ? 'grid' : 'none';
        }
    };

    if (roleSelect) {
        roleSelect.addEventListener('change', syncDomainAccessUi);
        accessBox.querySelectorAll('input[name="domain_access"]').forEach((input) => {
            input.addEventListener('change', syncDomainAccessUi);
        });
        syncDomainAccessUi();
    }

    const checkAll = document.getElementById('ptr-check-all');
    if (checkAll) {
        checkAll.addEventListener('change', () => {
            document.querySelectorAll('.ptr-issue-check').forEach((el) => {
                el.checked = checkAll.checked;
            });
        });
    }

    const recordsCheckAll = document.getElementById('records-check-all');
    if (recordsCheckAll) {
        recordsCheckAll.addEventListener('change', () => {
            document.querySelectorAll('.record-check').forEach((el) => {
                el.checked = recordsCheckAll.checked;
            });
        });
    }

    const bulkEditBtn = document.getElementById('records-bulk-edit-btn');
    if (bulkEditBtn) {
        bulkEditBtn.addEventListener('click', () => {
            const checked = Array.from(document.querySelectorAll('.record-check:checked'));
            if (!checked.length) {
                window.alert('Select at least one record to bulk edit.');
                return;
            }
            const zoneId = bulkEditBtn.getAttribute('data-zone-id') || '';
            const base = bulkEditBtn.getAttribute('data-base') || '';
            const params = new URLSearchParams();
            params.set('r', 'records');
            params.set('zone_id', zoneId);
            params.set('action', 'bulk_edit');
            checked.forEach((el) => {
                params.append('ids[]', el.value);
            });
            // data-base already includes ?r=records or index.php?r=records
            const joiner = base.indexOf('?') >= 0 ? '&' : '?';
            // Rebuild from scratch using path of base
            let path = base;
            const qPos = base.indexOf('?');
            if (qPos >= 0) {
                path = base.slice(0, qPos);
            }
            window.location.href = path + '?' + params.toString();
        });
    }

    document.querySelectorAll('[data-confirm-btn]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            const message = btn.getAttribute('data-confirm-btn') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
