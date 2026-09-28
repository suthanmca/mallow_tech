import './bootstrap';

document.querySelectorAll('[data-width]').forEach((element) => {
	const width = Number(element.dataset.width);
	element.style.width = `${Math.min(100, Math.max(0, width))}%`;
});

document.addEventListener('click', async (event) => {
	const passwordToggle = event.target.closest('[data-password-toggle]');
	if (passwordToggle) {
		const input = document.getElementById(passwordToggle.dataset.passwordToggle);
		const visible = input.type === 'password';
		input.type = visible ? 'text' : 'password';
		passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
		return;
	}

	const copyButton = event.target.closest('[data-copy]');
	if (copyButton) {
		const value = document.getElementById(copyButton.dataset.copy)?.textContent.trim();
		if (value) {
			await navigator.clipboard.writeText(value);
			copyButton.setAttribute('aria-label', 'API key copied');
			copyButton.setAttribute('title', 'Copied');
		}
		return;
	}

	const dismissButton = event.target.closest('[data-dismiss]');
	if (dismissButton) {
		document.getElementById(dismissButton.dataset.dismiss)?.remove();
	}
});
