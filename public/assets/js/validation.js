// Client-side mirror of the server's validation rules. UX-only — server is the source of truth.
(function () {
    'use strict';

    function parseRules(spec) {
        if (!spec) return [];
        return spec.split('|').map(function (token) {
            var bits = token.split(':');
            return { name: bits[0], arg: bits[1] || null };
        });
    }

    function checkRule(value, rule) {
        switch (rule.name) {
            case 'required':
                return value.trim() === '' ? 'is required' : null;
            case 'numeric':
                if (value === '') return null;
                return isNaN(Number(value)) ? 'must be numeric' : null;
            case 'integer':
                if (value === '') return null;
                return /^-?\d+$/.test(value) ? null : 'must be an integer';
            case 'min':
                if (value === '' || isNaN(Number(value))) return null;
                return Number(value) < Number(rule.arg)
                    ? 'must be at least ' + rule.arg : null;
            default:
                return null;
        }
    }

    function showError(input, message) {
        input.setAttribute('aria-invalid', 'true');
        var existing = input.parentNode.querySelector('.client-error');
        if (existing) existing.remove();
        if (!message) return;
        var span = document.createElement('span');
        span.className = 'client-error';
        span.style.color = 'var(--error-fg, #991b1b)';
        span.style.marginLeft = '0.5rem';
        span.textContent = message;
        input.parentNode.appendChild(span);
    }

    function clearError(input) {
        input.removeAttribute('aria-invalid');
        var existing = input.parentNode.querySelector('.client-error');
        if (existing) existing.remove();
    }

    function validateForm(form) {
        var ok = true;
        var inputs = form.querySelectorAll('[data-rule]');
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            var rules = parseRules(input.getAttribute('data-rule'));
            var failure = null;
            for (var j = 0; j < rules.length; j++) {
                failure = checkRule(input.value, rules[j]);
                if (failure) break;
            }
            if (failure) {
                showError(input, input.name + ' ' + failure);
                ok = false;
            } else {
                clearError(input);
            }
        }
        return ok;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-validate]')) return;
        if (!validateForm(form)) {
            event.preventDefault();
        }
    }, true);
})();
