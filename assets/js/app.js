/**
 * HabitFlow - Frontend Interactions & Dynamic Updates
 */

const HabitFlow = {
    // Show toast notification
    toast: function (message, type = 'success') {
        let container = document.getElementById('habitflow-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'habitflow-toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        const iconClass = type === 'success' ? 'bi-check-circle-fill text-success' : (type === 'danger' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-info-circle-fill text-primary');
        const bgBorder = type === 'success' ? 'border-success' : (type === 'danger' ? 'border-danger' : 'border-primary');

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center bg-white shadow-lg border-0 border-start border-4 ${bgBorder} rounded-3`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center py-2 px-3">
                    <i class="bi ${iconClass} fs-5 me-2"></i>
                    <span class="fw-semibold text-dark">${message}</span>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        container.appendChild(toastEl);
        const bsToast = new bootstrap.Toast(toastEl, { delay: 3500 });
        bsToast.show();

        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    },

    // Toggle Habit Status (AJAX)
    toggleHabit: async function (habitId, dateStr, element) {
        const item = element.closest('.habit-checklist-item');
        const checkbox = item ? item.querySelector('.habit-checkbox') : null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Temporary visual disable to prevent double clicking
        if (checkbox) checkbox.style.pointerEvents = 'none';

        try {
            const formData = new FormData();
            formData.append('habit_id', habitId);
            formData.append('date', dateStr);
            formData.append('csrf_token', csrfToken);

            const baseUrl = window.HABITFLOW_BASE_URL || '';
            const response = await fetch(`${baseUrl}/api/toggle_habit.php`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Update item visual
                if (item) {
                    if (result.is_completed) {
                        item.classList.add('is-done');
                        if (checkbox) {
                            checkbox.classList.add('checked');
                            checkbox.innerHTML = '<i class="bi bi-check-lg fw-bold"></i>';
                        }
                    } else {
                        item.classList.remove('is-done');
                        if (checkbox) {
                            checkbox.classList.remove('checked');
                            checkbox.innerHTML = '';
                        }
                    }

                    // Update completion time badge if present
                    const timeContainer = item.querySelector('.completion-time-container');
                    if (timeContainer) {
                        if (result.is_completed && result.completed_at_formatted) {
                            timeContainer.innerHTML = `<span class="badge bg-light text-secondary border"><i class="bi bi-clock me-1"></i>${result.completed_at_formatted}</span>`;
                        } else {
                            timeContainer.innerHTML = '';
                        }
                    }
                }

                // Update Dashboard Statistics if present on current page
                if (result.stats) {
                    const elCompleted = document.getElementById('stat-completed-count');
                    const elPending = document.getElementById('stat-pending-count');
                    const elRate = document.getElementById('stat-completion-rate');
                    const elProgressBar = document.getElementById('stat-progress-bar');
                    const elQuote = document.getElementById('stat-motivation-quote');

                    if (elCompleted) elCompleted.textContent = result.stats.completed_count;
                    if (elPending) elPending.textContent = result.stats.pending_count;
                    if (elRate) elRate.textContent = `${result.stats.completion_rate}%`;
                    if (elProgressBar) {
                        elProgressBar.style.width = `${result.stats.completion_rate}%`;
                        elProgressBar.setAttribute('aria-valuenow', result.stats.completion_rate);
                    }
                    if (elQuote && result.stats.quote) elQuote.textContent = result.stats.quote;
                }

                HabitFlow.toast(result.message, 'success');
            } else {
                HabitFlow.toast(result.message || 'Gagal mengubah status kebiasaan.', 'danger');
            }
        } catch (error) {
            console.error('Toggle habit error:', error);
            HabitFlow.toast('Terjadi kesalahan jaringan. Silakan coba lagi.', 'danger');
        } finally {
            if (checkbox) checkbox.style.pointerEvents = 'auto';
        }
    }
};

// DOM Ready initialization
document.addEventListener('DOMContentLoaded', () => {
    // 1. Password Visibility Toggle
    document.querySelectorAll('.btn-toggle-password').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    input.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            }
        });
    });

    // 2. Mobile Sidebar Toggle
    const sidebar = document.getElementById('appSidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('d-none');
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.add('d-none');
        });
    }

    // 3. Frequency type switcher in Habit Form (Daily vs Weekly Custom)
    const freqDaily = document.getElementById('freqDaily');
    const freqWeekly = document.getElementById('freqWeekly');
    const scheduleDaysContainer = document.getElementById('scheduleDaysContainer');

    if (freqDaily && freqWeekly && scheduleDaysContainer) {
        const updateScheduleVisibility = () => {
            if (freqWeekly.checked) {
                scheduleDaysContainer.classList.remove('d-none');
            } else {
                scheduleDaysContainer.classList.add('d-none');
            }
        };

        freqDaily.addEventListener('change', updateScheduleVisibility);
        freqWeekly.addEventListener('change', updateScheduleVisibility);
        updateScheduleVisibility();
    }
});
