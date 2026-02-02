class UI {
    constructor(context) {
        const toggles = context.querySelectorAll('[data-toggle]');
        const aliases = context.querySelectorAll('[data-for]');

        toggles.forEach((toggle) => this._toggle(toggle));
        aliases.forEach((element) => this._alias(element));
    }

    _toggle(element) {
        const self = this;
        const wrap = document.querySelector(
            '[data-wrap="' + element.dataset.toggle + '"]'
        );
        if (!wrap) {
            return;
        }

        element.addEventListener('click', function (ev) {
            ev.stopPropagation();
            const action = wrap.classList.contains('open')
                ? 'closed'
                : 'open';
            self.toggle(wrap, element, action);
        });
    }

    _alias(element) {
        element.addEventListener('click', function () {
            const aliasOf = document.getElementById(element.dataset.for);
            aliasOf.dispatchEvent(new Event('click'));
        });
    }

    toggle(element, trigger, action) {
        if ('closed' === action) {
            this.close(element, trigger);
        } else {
            this.open(element, trigger);
        }
    }
    open(element, trigger) {
        const inputs = element.getElementsByClassName('ik-ui-input');
        element.classList.remove('closed');
        element.classList.add('open');
        if (trigger && trigger.classList.contains('dashicons')) {
            trigger.classList.remove('dashicons-arrow-down-alt2');
            trigger.classList.add('dashicons-arrow-up-alt2');
        }
        [...inputs].forEach(function (input) {
            input.dataset.disabled = false;
        });
    }
    close(element, trigger) {
        const inputs = element.getElementsByClassName('ik-ui-input');
        element.classList.remove('open');
        element.classList.add('closed');
        if (trigger && trigger.classList.contains('dashicons')) {
            trigger.classList.remove('dashicons-arrow-up-alt2');
            trigger.classList.add('dashicons-arrow-down-alt2');
        }
        [...inputs].forEach(function (input) {
            input.dataset.disabled = true;
        });
    }
}

const contexts = document.querySelectorAll('.ik-settings,.ik-meta-box');
if (contexts.length) {
    contexts.forEach((context) => {
        if (context) {
            // Init.
            window.addEventListener('load', new UI(context));
        }
    });
}

export default UI;