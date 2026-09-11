/**
 * Shared browser behavior for Blade screens.
 * Business mutations intentionally live in Laravel actions, not in local browser state.
 */
function normalizeEgyptianPhone(phone) {
    const digits = String(phone).replace(/\D/g, '');
    const localNumber = digits.startsWith('20') ? digits.slice(2) : digits;

    return `20${localNumber.replace(/^0+/, '')}`;
}

function setupSidebar() {
    const sidebar = document.querySelector('#app-sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    if (!sidebar || !toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        const collapsed = sidebar.classList.toggle('is-collapsed');
        const label = collapsed ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية';

        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        localStorage.setItem('center-dream-sidebar-collapsed', String(collapsed));
    });

    if (localStorage.getItem('center-dream-sidebar-collapsed') === 'true') {
        sidebar.classList.add('is-collapsed');
        toggle.setAttribute('aria-label', 'توسيع القائمة الجانبية');
        toggle.title = 'توسيع القائمة الجانبية';
    }
}

function setupWhatsAppLinks() {
    document.querySelectorAll('[data-whatsapp-phone]').forEach((link) => {
        const phone = link.dataset.whatsappPhone;

        if (!phone) {
            return;
        }

        link.href = `https://wa.me/${normalizeEgyptianPhone(phone)}`;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
    });
}

function setupSubscriptionForm() {
    const form = document.querySelector('[data-subscription-form]');

    if (!form) {
        return;
    }

    const rows = form.querySelector('[data-subject-rows]');
    const template = form.querySelector('[data-subject-template]');
    const addButton = form.querySelector('[data-add-subject]');
    const totalFee = form.querySelector('[data-total-fee]');
    const totalPaid = form.querySelector('[data-total-paid]');
    const totalRemaining = form.querySelector('[data-total-remaining]');
    const phoneInput = form.querySelector('[data-student-phone]');
    const nameInput = form.querySelector('[data-student-name]');
    const lookupOutput = form.querySelector('[data-student-lookup]');
    const money = new Intl.NumberFormat('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let lookupTimer;
    let lookupRequest;

    const updateRows = () => {
        const selectedIds = [...rows.querySelectorAll('[data-subject-select]')]
            .map((select) => select.value)
            .filter(Boolean);
        let fees = 0;
        let paid = 0;

        [...rows.querySelectorAll('[data-subject-row]')].forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = String(index + 1);
            const subject = row.querySelector('[data-subject-select]');
            const amount = row.querySelector('[data-paid-input]');
            const method = row.querySelector('[data-method-select]');
            const detail = row.querySelector('[data-subject-details]');
            subject.name = `subjects[${index}][subject_id]`;
            amount.name = `subjects[${index}][paid_amount]`;
            method.name = `subjects[${index}][payment_method]`;

            [...subject.options].forEach((option) => {
                option.disabled = Boolean(option.value && option.value !== subject.value && selectedIds.includes(option.value));
            });

            const selected = subject.selectedOptions[0];
            const fee = Number(selected?.dataset.fee || 0);
            const paidAmount = Math.max(0, Number(amount.value || 0));
            fees += fee;
            paid += paidAmount;
            detail.textContent = fee ? `${selected.dataset.teacher || 'غير محدد'} — ${money.format(fee)} ج.م` : 'اختر مادة لعرض السعر والمدرس.';
        });

        totalFee.textContent = `${money.format(fees)} ج.م`;
        totalPaid.textContent = `${money.format(paid)} ج.م`;
        totalRemaining.textContent = `الرصيد المتبقي: ${money.format(Math.max(0, fees - paid))} ج.م`;
        addButton.disabled = selectedIds.length >= rows.querySelectorAll('[data-subject-select] option[value]').length;
    };

    const bindRow = (row) => {
        row.querySelector('[data-subject-select]').addEventListener('change', updateRows);
        row.querySelector('[data-paid-input]').addEventListener('input', updateRows);
        row.querySelector('[data-remove-subject]').addEventListener('click', () => {
            if (rows.querySelectorAll('[data-subject-row]').length === 1) {
                return;
            }

            row.remove();
            updateRows();
        });
    };

    rows.querySelectorAll('[data-subject-row]').forEach(bindRow);
    addButton.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        rows.append(row);
        bindRow(row);
        updateRows();
        row.querySelector('[data-subject-select]').focus();
    });

    if (phoneInput && nameInput && lookupOutput) {
        phoneInput.addEventListener('input', () => {
            window.clearTimeout(lookupTimer);
            lookupRequest?.abort();
            lookupOutput.hidden = true;

            if (phoneInput.value.replace(/\D/g, '').length < 10) {
                return;
            }

            lookupTimer = window.setTimeout(async () => {
                lookupRequest = new AbortController();
                const url = new URL(form.dataset.studentLookupUrl, window.location.origin);
                url.searchParams.set('phone', phoneInput.value);

                try {
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: lookupRequest.signal });
                    const data = await response.json();

                    if (!data.found) {
                        lookupOutput.textContent = 'طالب جديد: أكمل الاسم ثم اختر المواد.';
                        lookupOutput.className = 'student-lookup is-new';
                        lookupOutput.hidden = false;
                        return;
                    }

                    nameInput.value = data.student.name;
                    const subjects = data.student.subjects.length ? `المواد الحالية: ${data.student.subjects.join('، ')}.` : 'لا توجد مواد مسجلة حاليًا.';
                    lookupOutput.textContent = `طالب مسجل: ${data.student.name} — ${data.student.grade} (${data.student.academic_year}). ${subjects}`;
                    lookupOutput.className = 'student-lookup is-found';
                    lookupOutput.hidden = false;
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        lookupOutput.textContent = 'تعذر البحث عن بيانات الطالب الآن. يمكنك إكمال التسجيل يدويًا.';
                        lookupOutput.className = 'student-lookup is-error';
                        lookupOutput.hidden = false;
                    }
                }
            }, 300);
        });
    }
    updateRows();
}

function setupUserForm() {
    const roleSelect = document.querySelector('#role_id');
    const permissionGrid = document.querySelector('[data-permission-grid]');

    if (!roleSelect || !permissionGrid) {
        return;
    }

    const updatePermissions = () => {
        const role = roleSelect.selectedOptions[0]?.dataset.roleSlug;
        const disabled = role === 'admin' || role === 'teacher';

        permissionGrid.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.disabled = disabled;
            if (disabled) {
                checkbox.checked = false;
            }
        });
        permissionGrid.setAttribute('aria-disabled', String(disabled));
    };

    roleSelect.addEventListener('change', updatePermissions);
    updatePermissions();
}

function setupDialogsAndRows() {
    document.querySelectorAll('[data-open-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal()));
    document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelectorAll('[data-auto-dialog]').forEach((dialog) => dialog.showModal());
    document.querySelectorAll('.clickable-row[data-href]').forEach((row) => {
        row.addEventListener('click', (event) => { if (!event.target.closest('a,button,form,input')) window.location.assign(row.dataset.href); });
        row.addEventListener('keydown', (event) => { if (event.key === 'Enter') window.location.assign(row.dataset.href); });
    });
    document.querySelectorAll('[data-delete-student]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!window.confirm(`سيُحذف الطالب ${form.dataset.studentName} وكل اشتراكاته ومدفوعاته. هل تريد المتابعة؟`)) event.preventDefault();
    }));
}

function setupTeacherPayoutPreview() {
    const select = document.querySelector('[data-teacher-select]');
    const preview = document.querySelector('[data-teacher-payout-preview]');
    if (!select || !preview) return;
    const update = () => {
        const option = select.selectedOptions[0];
        preview.hidden = !option?.value;
        preview.querySelector('[data-payout-wallet]').textContent = option?.dataset.wallet || '';
        preview.querySelector('[data-payout-subjects]').textContent = option?.value ? `${option.dataset.subjects} مواد مرتبطة بالمدرس` : '';
        preview.querySelector('[data-payout-profile]').href = option?.dataset.profileUrl || '#';
    };
    select.addEventListener('change', update); update();
}

document.addEventListener('DOMContentLoaded', () => {
    setupSidebar();
    setupWhatsAppLinks();
    setupSubscriptionForm();
    setupUserForm();
    setupDialogsAndRows();
    setupTeacherPayoutPreview();
});
