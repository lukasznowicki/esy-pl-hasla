(function () {
    const configNode = document.getElementById('pg-config');
    if (!configNode || !configNode.textContent) {
        return;
    }

    const config = JSON.parse(configNode.textContent);
    const i18n = config.i18n;
    let customState = Object.assign({}, config.custom);
    let preset = config.preset;
    let saveTimer = 0;

    const options = document.getElementById('options');
    const quantumSummary = document.getElementById('quantumSummary');
    const quantumNote = document.getElementById('quantumNote');
    const strengthFill = document.getElementById('psScroll');
    const strengthLabel = document.getElementById('psValue');
    const strengthHint = document.getElementById('strengthHint');
    const lengthOutput = document.getElementById('goLengthValue');
    const countOutput = document.getElementById('goCountValue');
    const resultBox = document.getElementById('generatedPasswords');
    const passwordList = document.getElementById('listPasswords');
    const copyStatus = document.getElementById('copyStatus');
    const formError = document.getElementById('formError');
    const checkResult = document.getElementById('checkResult');
    const checkInput = document.getElementById('checkPassword');
    const checkToggle = document.getElementById('checkToggle');
    const banner = document.getElementById('cookie');

    if (window.location.hash === '#cookie' && banner && !banner.hidden) {
        window.requestAnimationFrame(function () {
            banner.scrollIntoView({ block: 'start' });
        });
    }

    function text(key, bits) {
        const template = i18n[key] || key;
        if (bits === undefined) {
            return template;
        }
        return template.replace('{bits}', bits);
    }

    function log2(size) {
        if (size <= 0) {
            return 0;
        }
        if ((size & (size - 1)) === 0) {
            let exponent = 0;
            let rest = size;
            while (rest > 1) {
                rest >>= 1;
                exponent += 1;
            }
            return exponent;
        }
        return Math.log(size) / Math.LN2;
    }

    function formatBits(bits) {
        const rounded = Math.round(bits * 10) / 10;
        if (Math.abs(rounded - Math.round(rounded)) < 1e-6) {
            return String(Math.round(rounded));
        }
        return rounded.toFixed(1);
    }

    function verdictFor(bits) {
        if (bits < 40) {
            return 'very_weak';
        }
        if (bits < 64) {
            return 'weak';
        }
        if (bits < 80) {
            return 'moderate';
        }
        if (bits < 128) {
            return 'strong';
        }
        if (bits < 256) {
            return 'very_strong';
        }
        return 'quantum';
    }

    function strip(alphabet, similar) {
        const skip = new Set(similar);
        let out = '';
        for (const character of alphabet) {
            if (!skip.has(character)) {
                out += character;
            }
        }
        return out;
    }

    function bitsFor(state) {
        let middle = '';
        if (state.lower) {
            middle += config.alphabets.lower;
        }
        if (state.upper) {
            middle += config.alphabets.upper;
        }
        if (state.digits) {
            middle += config.alphabets.digits;
        }
        if (state.specialBasic) {
            middle += config.alphabets.specialBasic;
        }
        if (state.specialExtended) {
            middle += config.alphabets.specialExtended;
        }
        if (state.similar) {
            middle = strip(middle, config.alphabets.similar);
        }
        if (middle.length === 0) {
            return 0;
        }
        if (state.edges) {
            let edge = config.alphabets.lower + config.alphabets.upper;
            if (state.similar) {
                edge = strip(edge, config.alphabets.similar);
            }
            if (edge.length === 0 || state.length < 2) {
                return 0;
            }
            return (2 * log2(edge.length)) + ((state.length - 2) * log2(middle.length));
        }
        return state.length * log2(middle.length);
    }

    function showStrength(bits, verdict, display) {
        const width = Math.max(0, Math.min(100, (bits / 256) * 100));
        strengthFill.style.width = width + '%';
        const shown = display || formatBits(bits);
        strengthLabel.textContent = text('strength.bits', shown) + ' · ' + text('verdict.' + verdict);
    }

    function readDom() {
        return {
            lower: document.getElementById('peLowerCase').checked,
            upper: document.getElementById('peUpperCase').checked,
            digits: document.getElementById('peDigits').checked,
            specialBasic: document.getElementById('peSpecial1').checked,
            specialExtended: document.getElementById('peSpecial2').checked,
            similar: document.getElementById('peSimilar').checked,
            edges: document.getElementById('peEdges').checked,
            length: Number(document.getElementById('goLength').value),
            count: Number(document.getElementById('goCount').value)
        };
    }

    function writeDom(state) {
        document.getElementById('peLowerCase').checked = state.lower;
        document.getElementById('peUpperCase').checked = state.upper;
        document.getElementById('peDigits').checked = state.digits;
        document.getElementById('peSpecial1').checked = state.specialBasic;
        document.getElementById('peSpecial2').checked = state.specialExtended;
        document.getElementById('peSimilar').checked = state.similar;
        document.getElementById('peEdges').checked = state.edges;
        document.getElementById('goLength').value = String(state.length);
        document.getElementById('goCount').value = String(state.count);
        lengthOutput.textContent = String(state.length);
        countOutput.textContent = String(state.count);
        passwordList.rows = Math.max(1, state.count);
        document.getElementById('doGenerate').textContent = text(state.count > 1 ? 'generate.many' : 'generate.one');
    }

    function presetDisplay(name) {
        if (name === 'custom') {
            return customState;
        }
        const info = config.presets[name];
        return {
            lower: true,
            upper: true,
            digits: true,
            specialBasic: true,
            specialExtended: true,
            similar: false,
            edges: false,
            length: info.length,
            count: customState.count
        };
    }

    function selectedPreset() {
        const chosen = document.querySelector('input[name="preset"]:checked');
        return chosen ? chosen.value : preset;
    }

    function applyUi(name, persist) {
        preset = name;
        const quantum = name === 'quantum';
        options.hidden = quantum;
        options.disabled = name !== 'custom';
        document.getElementById('goCount').disabled = name !== 'custom' && !config.consent;
        quantumSummary.hidden = !quantum;
        quantumNote.hidden = !quantum;
        writeDom(presetDisplay(name));
        if (name === 'custom') {
            const bits = bitsFor(customState);
            showStrength(bits, verdictFor(bits));
        } else {
            const info = config.presets[name];
            showStrength(info.bits, info.verdict, info.bitsDisplay);
        }
        strengthHint.textContent = text('strength.exact');
        if (persist) {
            scheduleSave();
        }
    }

    function scheduleSave() {
        if (!config.consent) {
            return;
        }
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(function () {
            post({
                action: 'settings',
                lang: config.lang,
                preset: preset,
                options: customState
            }).catch(function () {
                return null;
            });
        }, 300);
    }

    function post(body) {
        return fetch(config.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrf
            },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    }

    function showError(target, message) {
        target.textContent = message || '';
    }

    document.querySelectorAll('input[name="preset"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.disabled) {
                return;
            }
            formError.textContent = '';
            applyUi(input.value, true);
        });
    });

    options.addEventListener('input', function () {
        if (preset !== 'custom' || options.disabled) {
            return;
        }
        customState = readDom();
        lengthOutput.textContent = String(customState.length);
        countOutput.textContent = String(customState.count);
        passwordList.rows = Math.max(1, customState.count);
        document.getElementById('doGenerate').textContent = text(customState.count > 1 ? 'generate.many' : 'generate.one');
        const bits = bitsFor(customState);
        showStrength(bits, verdictFor(bits));
        scheduleSave();
    });

    document.getElementById('goCount').addEventListener('input', function () {
        if (this.disabled) {
            return;
        }
        customState.count = Number(this.value);
        countOutput.textContent = String(customState.count);
        passwordList.rows = Math.max(1, customState.count);
        document.getElementById('doGenerate').textContent = text(customState.count > 1 ? 'generate.many' : 'generate.one');
        scheduleSave();
    });

    document.getElementById('doGenerate').addEventListener('click', function () {
        formError.textContent = '';
        const active = selectedPreset();
        if (active === 'custom' && bitsFor(customState) === 0) {
            showError(formError, text('error.empty_alphabet'));
            return;
        }
        post({
            action: 'generate',
            lang: config.lang,
            preset: active,
            options: customState
        }).then(function (result) {
            if (!result.data || !result.data.ok) {
                showError(formError, result.data && result.data.message ? result.data.message : text('error.generic'));
                return;
            }
            passwordList.value = result.data.passwords.join('\n');
            resultBox.hidden = false;
            passwordList.rows = Math.max(1, result.data.passwords.length);
            showStrength(result.data.bits, result.data.verdict, result.data.bitsDisplay);
            strengthHint.textContent = result.data.message || text('strength.exact');
        }).catch(function () {
            showError(formError, text('error.generic'));
        });
    });

    document.getElementById('copyList').addEventListener('click', function () {
        const value = passwordList.value.trim();
        const done = function () {
            copyStatus.textContent = text('copy.done');
            window.setTimeout(function () {
                copyStatus.textContent = '';
            }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function () {
                fallbackCopy(value);
                done();
            });
            return;
        }
        fallbackCopy(value);
        done();
    });

    function fallbackCopy(value) {
        const field = document.createElement('textarea');
        field.value = value;
        field.setAttribute('readonly', '');
        field.style.position = 'absolute';
        field.style.left = '-9999px';
        document.body.appendChild(field);
        field.select();
        document.execCommand('copy');
        field.remove();
    }

    document.getElementById('checker').addEventListener('submit', function (event) {
        event.preventDefault();
        checkResult.replaceChildren();
        if (checkInput.value === '') {
            const empty = document.createElement('p');
            empty.textContent = text('error.empty_password');
            checkResult.appendChild(empty);
            return;
        }
        post({
            action: 'check',
            lang: config.lang,
            password: checkInput.value
        }).then(function (result) {
            checkResult.replaceChildren();
            if (!result.data || !result.data.ok) {
                const failure = document.createElement('p');
                failure.textContent = result.data && result.data.message ? result.data.message : text('error.generic');
                checkResult.appendChild(failure);
                return;
            }
            renderCheck(result.data);
        }).catch(function () {
            checkResult.replaceChildren();
            const failure = document.createElement('p');
            failure.textContent = text('error.generic');
            checkResult.appendChild(failure);
        });
    });

    function renderCheck(data) {
        const summary = document.createElement('p');
        summary.textContent = text('strength.bits', data.bitsDisplay) + ' · ' + text('verdict.' + data.verdict);
        const estimate = document.createElement('p');
        estimate.className = 'hint';
        estimate.textContent = data.message || text('strength.estimate');
        checkResult.append(summary, estimate);

        const used = [];
        Object.keys(data.classes || {}).forEach(function (name) {
            if (data.classes[name]) {
                used.push(text('class.' + name));
            }
        });
        if (used.length > 0) {
            const classes = document.createElement('p');
            classes.textContent = text('check.classes') + ': ' + used.join(', ');
            checkResult.appendChild(classes);
        }

        (data.findings || []).forEach(function (finding) {
            const item = document.createElement('p');
            item.textContent = text('finding.' + finding.code) + ': ';
            const match = document.createElement('code');
            match.textContent = finding.match;
            item.appendChild(match);
            checkResult.appendChild(item);
        });
    }

    checkToggle.addEventListener('click', function () {
        const hidden = checkInput.type === 'password';
        checkInput.type = hidden ? 'text' : 'password';
        checkToggle.textContent = text(hidden ? 'check.hide' : 'check.show');
    });

    document.getElementById('cookieAccept').addEventListener('click', function () {
        chooseConsent('accept');
    });
    document.getElementById('cookieDecline').addEventListener('click', function () {
        chooseConsent('decline');
    });
    const cookiesLink = document.getElementById('cookiesLink');
    if (cookiesLink) {
        cookiesLink.addEventListener('click', function () {
            chooseConsent('reset');
        });
    }

    function chooseConsent(value) {
        post({
            action: 'consent',
            lang: config.lang,
            value: value
        }).then(function (result) {
            if (result.data && result.data.ok) {
                if (value === 'reset') {
                    if ('scrollRestoration' in window.history) {
                        window.history.scrollRestoration = 'manual';
                    }
                    window.history.replaceState(null, '', window.location.pathname + window.location.search + '#cookie');
                }
                window.location.reload();
                return;
            }
            showError(formError, result.data && result.data.message ? result.data.message : text('error.generic'));
        }).catch(function () {
            showError(formError, text('error.generic'));
        });
    }

    applyUi(selectedPreset(), false);
})();
