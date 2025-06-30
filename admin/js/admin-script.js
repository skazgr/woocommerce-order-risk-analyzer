document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
		new bootstrap.Popover(el);
	});

	const accordionId = 'wcOrderRiskAccordion';
	const buttons = document.querySelectorAll(`#${accordionId} .accordion-button`);

	buttons.forEach((btn, idx) => {
		btn.addEventListener('click', () => {
			localStorage.setItem('wcOrderRiskLastTab', idx);
		});
	});

	const saved = localStorage.getItem('wcOrderRiskLastTab');
	if (saved !== null && buttons[saved]) {
		// Collapse all
		document.querySelectorAll(`#${accordionId} .accordion-collapse`).forEach(el => el.classList.remove('show'));
		document.querySelectorAll(`#${accordionId} .accordion-button`).forEach(el => el.classList.add('collapsed'));

		// Expand saved
		const targetId = buttons[saved].getAttribute('data-bs-target');
		if (targetId) {
			const targetEl = document.querySelector(targetId);
			if (targetEl) {
				targetEl.classList.add('show');
				buttons[saved].classList.remove('collapsed');
			}
		}
	}

	if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('#high_risk_payment_methods_select').select2({
            width: '100%',
            placeholder: wcOrderRiskData.selectPlaceholder,
            allowClear: true
        });
	}
});
