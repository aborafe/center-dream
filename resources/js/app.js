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
    const shell = document.querySelector('.app-shell');
    let toggleTimer;

    if (!sidebar || !toggle || !shell) {
        return;
    }

    const setCollapsedState = (collapsed) => {
        sidebar.classList.toggle('is-collapsed', collapsed);
        shell.classList.toggle('is-sidebar-collapsed', collapsed);

        const label = collapsed ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية';
        toggle.setAttribute('aria-label', label);
        toggle.title = label;

        document.querySelectorAll('.side-nav .nav-item').forEach((item) => {
            const itemLabel = item.querySelector('.nav-label')?.textContent?.trim();

            if (itemLabel) {
                item.setAttribute('aria-label', itemLabel);
                item.title = itemLabel;
            }
        });
    };

    toggle.addEventListener('click', () => {
        if (sidebar.dataset.transitioning === 'true') {
            return;
        }

        const collapsed = !sidebar.classList.contains('is-collapsed');

        sidebar.dataset.transitioning = 'true';
        sidebar.classList.add('is-toggling');
        setCollapsedState(collapsed);
        localStorage.setItem('center-dream-sidebar-collapsed', String(collapsed));

        window.clearTimeout(toggleTimer);
        toggleTimer = window.setTimeout(() => {
            sidebar.classList.remove('is-toggling');
            delete sidebar.dataset.transitioning;
        }, 240);
    });

    setCollapsedState(localStorage.getItem('center-dream-sidebar-collapsed') === 'true');
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
            const removeButton = row.querySelector('[data-remove-subject]');
            subject.name = `subjects[${index}][subject_id]`;
            amount.name = `subjects[${index}][paid_amount]`;
            method.name = `subjects[${index}][payment_method]`;
            subject.id = `subject-${index}`;
            amount.id = `paid-amount-${index}`;
            method.id = `payment-method-${index}`;
            row.querySelector('[data-subject-label]')?.setAttribute('for', subject.id);
            row.querySelector('[data-paid-label]')?.setAttribute('for', amount.id);
            row.querySelector('[data-method-label]')?.setAttribute('for', method.id);
            removeButton.disabled = rows.querySelectorAll('[data-subject-row]').length === 1;
            removeButton.title = removeButton.disabled ? 'أضف مادة أخرى أولًا لتتمكن من الحذف' : 'حذف المادة';

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
        totalRemaining.textContent = `${money.format(Math.max(0, fees - paid))} ج.م`;
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
                    const details = document.createElement('div');
                    const title = document.createElement('strong');
                    const description = document.createElement('span');
                    const profileLink = document.createElement('a');
                    const subjects = data.student.subjects.length ? `المواد الحالية: ${data.student.subjects.join('، ')}.` : 'لا توجد مواد مسجلة حاليًا.';

                    title.textContent = `طالب مسجل: ${data.student.name}`;
                    description.textContent = `${data.student.grade} · ${data.student.academic_year}. ${subjects}`;
                    profileLink.href = data.student.profile_url;
                    profileLink.className = 'student-lookup-profile-link';
                    profileLink.textContent = `فتح ملف ${data.student.name}`;
                    profileLink.setAttribute('aria-label', `فتح ملف الطالب ${data.student.name} وإضافة مواد جديدة`);
                    details.append(title, description);
                    lookupOutput.replaceChildren(details, profileLink);
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
    const subjectSelect = document.querySelector('[data-payout-subject-select]');
    const subjectHint = document.querySelector('[data-payout-subject-hint]');
    if (!select || !preview) return;
    const update = () => {
        const option = select.selectedOptions[0];
        const teacherId = option?.value || '';

        preview.hidden = !option?.value;
        preview.querySelector('[data-payout-wallet]').textContent = option?.dataset.wallet || '';
        preview.querySelector('[data-payout-subjects]').textContent = option?.value ? `${option.dataset.subjects} مواد مرتبطة بالمدرس` : '';
        preview.querySelector('[data-payout-profile]').href = option?.dataset.profileUrl || '#';

        if (subjectSelect) {
            subjectSelect.disabled = !teacherId;
            [...subjectSelect.options].forEach((subjectOption) => {
                if (!subjectOption.dataset.teacherId) return;
                const belongsToSelectedTeacher = subjectOption.dataset.teacherId === teacherId;
                subjectOption.hidden = !belongsToSelectedTeacher;
                subjectOption.disabled = !belongsToSelectedTeacher;
            });

            if (subjectSelect.selectedOptions[0]?.dataset.teacherId && subjectSelect.selectedOptions[0].dataset.teacherId !== teacherId) {
                subjectSelect.value = '';
            }
        }

        if (subjectHint) {
            subjectHint.textContent = teacherId
                ? 'تظهر هنا المواد المسندة إلى المدرس المختار فقط.'
                : 'اختر المدرس أولًا لعرض مواده.';
        }
    };
    select.addEventListener('change', update); update();
}

function setupFlashMessages() {
    document.querySelectorAll('[data-flash-message]').forEach((message) => {
        const close = message.querySelector('[data-dismiss-flash]');
        let dismissTimer;

        const dismiss = () => {
            message.classList.add('is-dismissing');
            window.setTimeout(() => message.remove(), 220);
        };

        const scheduleDismiss = () => {
            window.clearTimeout(dismissTimer);
            dismissTimer = window.setTimeout(dismiss, 6500);
        };

        close?.addEventListener('click', dismiss);
        message.addEventListener('mouseenter', () => window.clearTimeout(dismissTimer));
        message.addEventListener('mouseleave', scheduleDismiss);
        message.addEventListener('focusin', () => window.clearTimeout(dismissTimer));
        message.addEventListener('focusout', scheduleDismiss);
        scheduleDismiss();
    });
}

function setupSubmissionLoading() {
    const loader = document.querySelector('[data-page-loader]');

    if (!loader) {
        return;
    }

    const loaderText = loader.querySelector('span:last-child');
    const showPageLoader = (text) => {
        if (loaderText) {
            loaderText.textContent = text;
        }

        document.body.classList.add('is-page-loading');
    };

    const showDialogLoader = (form) => {
        const dialog = form.closest('dialog');

        if (!dialog) {
            return;
        }

        dialog.classList.add('is-submitting');
        dialog.setAttribute('aria-busy', 'true');

        if (dialog.querySelector('[data-dialog-submit-loader]')) {
            return;
        }

        const dialogLoader = document.createElement('div');
        const mark = document.createElement('span');
        const text = document.createElement('span');

        dialogLoader.className = 'dialog-submit-loader';
        dialogLoader.dataset.dialogSubmitLoader = 'true';
        dialogLoader.setAttribute('role', 'status');
        dialogLoader.setAttribute('aria-live', 'polite');
        mark.className = 'dialog-submit-loader-mark';
        mark.setAttribute('aria-hidden', 'true');
        mark.append(document.createElement('i'), document.createElement('i'), document.createElement('i'));
        text.textContent = 'جاري حفظ التعديل…';
        dialogLoader.append(mark, text);
        dialog.append(dialogLoader);
    };

    window.requestAnimationFrame(() => document.body.classList.remove('is-page-loading'));

    document.querySelectorAll('form[method="POST"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || !form.checkValidity() || form.dataset.submitting === 'true') {
                event.preventDefault();

                return;
            }

            form.dataset.submitting = 'true';
            form.classList.add('is-submitting');
            showDialogLoader(form);
            document.body.classList.add('is-submitting');
            showPageLoader('جاري تنفيذ العملية…');

            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
            });
        });
    });

    window.addEventListener('pageshow', () => {
        document.body.classList.remove('is-page-loading');
        document.body.classList.remove('is-submitting');
        document.querySelectorAll('form.is-submitting').forEach((form) => {
            delete form.dataset.submitting;
            form.classList.remove('is-submitting');
            form.querySelectorAll('button.is-loading').forEach((button) => {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.removeAttribute('aria-busy');
            });
        });
        document.querySelectorAll('dialog.is-submitting').forEach((dialog) => {
            dialog.classList.remove('is-submitting');
            dialog.removeAttribute('aria-busy');
            dialog.querySelector('[data-dialog-submit-loader]')?.remove();
        });
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) {
            return;
        }

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) {
            return;
        }

        const destination = new URL(link.href, window.location.href);
        if (destination.origin === window.location.origin) {
            showPageLoader('جاري تحميل الصفحة…');
        }
    });
}

function setupTableFilters() {
    const tables = [...document.querySelectorAll('.main-content .table-wrap > table:not([data-disable-table-filters])')];

    tables.forEach((table, tableIndex) => {
        const columns = [...table.querySelectorAll('thead th')]
            .map((header, index) => ({ label: header.textContent.trim(), index }))
            .filter((columnDefinition) => columnDefinition.label && columnDefinition.label !== 'إجراء' && columnDefinition.label !== 'إجراءات');
        const rows = [...table.querySelectorAll('tbody tr')]
            .filter((row) => row.cells.length > 0 && !row.querySelector('td[colspan]'));

        if (!columns.length || !rows.length) {
            return;
        }

        const queryKey = `table-${tableIndex}-q`;
        const columnKey = `table-${tableIndex}-column`;
        const valueKey = `table-${tableIndex}-value`;
        const queryParams = new URLSearchParams(window.location.search);
        const toolbar = document.createElement('form');
        const search = document.createElement('input');
        const column = document.createElement('select');
        const value = document.createElement('select');
        const reset = document.createElement('button');
        const count = document.createElement('output');

        toolbar.className = 'table-filter-bar';
        toolbar.setAttribute('role', 'search');
        toolbar.setAttribute('aria-label', 'تصفية بيانات الجدول');

        search.type = 'search';
        search.name = queryKey;
        search.placeholder = 'ابحث في الجدول…';
        search.autocomplete = 'off';
        search.setAttribute('aria-label', 'بحث في كل بيانات الجدول');
        search.value = queryParams.get(queryKey) || '';

        column.name = columnKey;
        column.setAttribute('aria-label', 'اختر عمود التصفية');
        column.append(new Option('كل الأعمدة', ''));
        columns.forEach((columnDefinition) => column.append(new Option(columnDefinition.label, String(columnDefinition.index))));
        column.value = queryParams.get(columnKey) || '';

        value.name = valueKey;
        value.setAttribute('aria-label', 'اختر قيمة التصفية');
        value.disabled = !column.value;

        reset.type = 'button';
        reset.className = 'outline-button table-filter-reset';
        reset.textContent = 'إعادة الضبط';
        reset.hidden = true;

        count.className = 'table-filter-count';
        count.setAttribute('aria-live', 'polite');

        toolbar.append(search, column, value, reset, count);
        table.closest('.table-wrap').before(toolbar);

        const cellValue = (row, index) => row.cells[index]?.textContent.trim().replace(/\s+/g, ' ') || '';
        const updateValueOptions = () => {
            const selectedColumn = Number(column.value);
            const currentValue = value.value || queryParams.get(valueKey) || '';

            value.replaceChildren(new Option('كل القيم', ''));
            value.disabled = column.value === '';

            if (column.value === '') {
                return;
            }

            [...new Set(rows.map((row) => cellValue(row, selectedColumn)).filter(Boolean))]
                .sort((first, second) => first.localeCompare(second, 'ar'))
                .forEach((optionValue) => value.append(new Option(optionValue, optionValue)));
            value.value = [...value.options].some((option) => option.value === currentValue) ? currentValue : '';
        };

        const syncUrl = () => {
            const nextUrl = new URL(window.location.href);
            [[queryKey, search.value], [columnKey, column.value], [valueKey, value.value]].forEach(([key, selectedValue]) => {
                if (selectedValue) {
                    nextUrl.searchParams.set(key, selectedValue);
                } else {
                    nextUrl.searchParams.delete(key);
                }
            });
            window.history.replaceState({}, '', nextUrl);
        };

        const apply = () => {
            const normalizedQuery = search.value.trim().toLocaleLowerCase('ar');
            const selectedColumn = column.value === '' ? null : Number(column.value);
            const selectedValue = value.value;
            let visibleCount = 0;

            rows.forEach((row) => {
                const values = [...row.cells].map((cell) => cell.textContent.trim().replace(/\s+/g, ' '));
                const matchesQuery = !normalizedQuery || values.some((cell) => cell.toLocaleLowerCase('ar').includes(normalizedQuery));
                const matchesValue = selectedColumn === null || !selectedValue || values[selectedColumn] === selectedValue;
                const visible = matchesQuery && matchesValue;

                row.hidden = !visible;
                visibleCount += Number(visible);
            });

            count.textContent = `عرض ${visibleCount} من ${rows.length}`;
            reset.hidden = !search.value && !column.value && !value.value;
            syncUrl();
        };

        column.addEventListener('change', () => {
            queryParams.delete(valueKey);
            updateValueOptions();
            apply();
        });
        value.addEventListener('change', apply);
        search.addEventListener('input', apply);
        reset.addEventListener('click', () => {
            search.value = '';
            column.value = '';
            updateValueOptions();
            apply();
            search.focus();
        });
        toolbar.addEventListener('submit', (event) => event.preventDefault());

        updateValueOptions();
        apply();
    });
}

function setupDateRangePicker() {
    const form = document.querySelector('[data-date-range-picker]');

    if (!form) {
        return;
    }

    const dialog = document.querySelector('[data-date-range-dialog]');
    const fromInput = form.querySelector('[data-date-range-from]');
    const toInput = form.querySelector('[data-date-range-to]');
    const trigger = form.querySelector('[data-date-range-open]');
    const value = form.querySelector('[data-date-range-value]');
    const calendar = dialog?.querySelector('[data-date-range-calendar]');
    const month = dialog?.querySelector('[data-date-range-month]');
    const selection = dialog?.querySelector('[data-date-range-selection]');

    if (!dialog || !fromInput || !toInput || !trigger || !value || !calendar || !month || !selection) {
        return;
    }

    const format = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { day: 'numeric', month: 'short', year: 'numeric' });
    const monthFormat = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { month: 'long', year: 'numeric' });
    const parseDate = (dateValue) => dateValue ? new Date(`${dateValue}T12:00:00`) : null;
    const toIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    let draftFrom = '';
    let draftTo = '';
    let cursor = new Date();

    const updateTrigger = () => {
        const start = parseDate(fromInput.value);
        const end = parseDate(toInput.value);

        value.textContent = start && end
            ? (fromInput.value === toInput.value ? format.format(start) : `${format.format(start)} — ${format.format(end)}`)
            : 'اختر فترة الدفتر…';
    };

    const render = () => {
        const year = cursor.getFullYear();
        const monthIndex = cursor.getMonth();
        const firstDay = new Date(year, monthIndex, 1);
        const gridStart = new Date(year, monthIndex, 1 - firstDay.getDay());
        const draftStartDate = parseDate(draftFrom);
        const draftEndDate = parseDate(draftTo);

        month.textContent = monthFormat.format(firstDay);
        calendar.replaceChildren();

        for (let index = 0; index < 42; index += 1) {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            const iso = toIso(date);
            const isCurrentMonth = date.getMonth() === monthIndex;
            const isStart = iso === draftFrom;
            const isEnd = iso === draftTo;
            const isInRange = Boolean(draftFrom && draftTo && iso > draftFrom && iso < draftTo);
            const day = document.createElement('button');

            day.type = 'button';
            day.className = 'date-range-day';
            day.textContent = String(date.getDate());
            day.disabled = !isCurrentMonth;
            day.setAttribute('role', 'gridcell');
            day.setAttribute('aria-label', format.format(date));
            day.setAttribute('aria-selected', String(isStart || isEnd));
            day.classList.toggle('is-outside-month', !isCurrentMonth);
            day.classList.toggle('is-range-start', isStart);
            day.classList.toggle('is-range-end', isEnd);
            day.classList.toggle('is-in-range', isInRange);
            day.addEventListener('click', () => {
                if (!draftFrom || draftTo) {
                    draftFrom = iso;
                    draftTo = '';
                } else if (iso < draftFrom) {
                    draftFrom = iso;
                } else {
                    draftTo = iso;
                }

                render();
            });
            calendar.append(day);
        }

        if (!draftStartDate) {
            selection.textContent = 'اختر تاريخ البداية.';
        } else if (!draftEndDate) {
            selection.textContent = `البداية: ${format.format(draftStartDate)}. اختر تاريخ النهاية.`;
        } else {
            selection.textContent = `الفترة: ${format.format(draftStartDate)} إلى ${format.format(draftEndDate)}.`;
        }
    };

    trigger.addEventListener('click', () => {
        draftFrom = fromInput.value;
        draftTo = toInput.value;
        cursor = parseDate(draftFrom) || new Date();
        render();
        dialog.showModal();
    });
    dialog.querySelector('[data-date-range-close]')?.addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-date-range-prev]')?.addEventListener('click', () => {
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
        render();
    });
    dialog.querySelector('[data-date-range-next]')?.addEventListener('click', () => {
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
        render();
    });
    dialog.querySelector('[data-date-range-clear]')?.addEventListener('click', () => {
        fromInput.value = '';
        toInput.value = '';
        updateTrigger();
        dialog.close();
        form.requestSubmit();
    });
    dialog.querySelector('[data-date-range-apply]')?.addEventListener('click', () => {
        if (!draftFrom) {
            return;
        }

        fromInput.value = draftFrom;
        toInput.value = draftTo || draftFrom;
        updateTrigger();
        dialog.close();
        form.requestSubmit();
    });

    updateTrigger();
}

document.addEventListener('DOMContentLoaded', () => {
    setupSidebar();
    setupWhatsAppLinks();
    setupSubscriptionForm();
    setupUserForm();
    setupDialogsAndRows();
    setupTeacherPayoutPreview();
    setupFlashMessages();
    setupSubmissionLoading();
    setupTableFilters();
    setupDateRangePicker();
});
