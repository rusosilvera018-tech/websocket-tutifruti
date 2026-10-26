const fs = require('fs');

const file = 'resources/views/tutti/player.blade.php';
let c = fs.readFileSync(file, 'utf8');

console.log('Starting fixes...\n');

function doReplace(c, search, replace, label) {
    if (c.includes(search)) {
        c = c.replace(search, replace);
        console.log(label + ': ✓');
        return c;
    } else {
        console.log(label + ': ✗ NOT FOUND');
        return c;
    }
}

c = fs.readFileSync('resources/views/tutti/player.blade.php', 'utf8');

// ============================================================
// FIX 1: Add debouncedSaveAnswers function after goToPrevCategory
// ============================================================
const debouncedSaveAnswersFn = `
        // Debounce para envío de respuestas al servidor
        let saveDebounceTimer = null;
        function debouncedSaveAnswers() {
            if (saveDebounceTimer) clearTimeout(saveDebounceTimer);
            saveDebounceTimer = setTimeout(async () => {
                try {
                    await enviarRespuestas();
                    console.log('[DEBUG] Respuestas guardadas en servidor (debounced)');
                } catch (e) {
                    console.error('Error guardando respuestas:', e);
                }
            }, 1000);
        }
`;

if (c.includes('        }\n\n        // Navegación con teclado')) {
    c = c.replace(
        '        }\n\n        // Navegación con teclado',
        '        }\n\n' + debouncedSaveAnswersFn + '\n        // Navegación con teclado'
    );
    console.log('FIX 1: ✓ Added debouncedSaveAnswers function');
} else {
    console.log('FIX 1: ✗ Could not find insertion point');
}

// ============================================================
// FIX 2: Update goToCategory (FIRST occurrence) to call debouncedSaveAnswers
// ============================================================
const oldGoToCategory1 = '            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }';

const newGoToCategory1 = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

c = c.replace(
    "            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector('[data-index=\"${currentCategoryIndex}\"] input');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }",
    `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`
);
console.log('FIX 2:', c.includes('debouncedSaveAnswers(); // Guardar en servidor con debounce') ? '✓' : '✗');

// ============================================================
// FIX 2: Update startTimer to block inputs and send final answers
// ============================================================
const oldTimerEnd = '                if (remaining <= 0) clearInterval(currentTimerInterval);';
const newTimerEnd = `                if (remaining <= 0) {
                    clearInterval(currentTimerInterval);
                    // Tiempo agotado: bloquear inputs y enviar respuestas finales
                    carouselTrack.querySelectorAll('input[type="text"]').forEach(input => {
                        input.disabled = true;
                    });
                    btnBasta.disabled = true;
                    enviarRespuestas().then(() => {
                        console.log('[DEBUG] Respuestas finales enviadas por tiempo agotado');
                    }).catch(e => console.error('Error enviando respuestas finales:', e));
                    return;
                }`;

c = c.replace(
    '                if (remaining <= 0) clearInterval(currentTimerInterval);',
    newTimerEnd
);
console.log('FIX 3:', c.includes('Tiempo agotado') ? '✓' : '✗');

// ============================================================
// FIX 4: Update loadInitialState to restore answers from server
// ============================================================
const oldLoadInitial = `        if (data.round) {
                    const round = {
                        number: data.round.number,
                        letter: data.round.letter,
                        starts_at: data.round.starts_at,
                        ends_at: data.round.ends_at,
                    };
                    currentRound = round;
                    console.log('[DEBUG] Ronda encontrada en estado inicial, renderizando:', round);
                    renderRound(round);
                } else {
                    console.log('[DEBUG] No hay ronda activa en estado inicial');
                    renderEmptyRound();
                }
            } catch (e) {
                console.error('Error cargando estado inicial:', e);
                renderEmptyRound();
            }
        }`;

const newLoadInitial = `        if (data.round) {
                    const round = {
                        number: data.round.number,
                        letter: data.round.letter,
                        starts_at: data.round.starts_at,
                        ends_at: data.round.ends_at,
                    };
                    currentRound = round;
                    console.log('[DEBUG] Ronda encontrada en estado inicial, renderizando:', round);
                    
                    // Restaurar respuestas guardadas del servidor
                    if (data.me?.answers) {
                        myAnswers = { ...data.me.answers };
                        console.log('[DEBUG] Respuestas restauradas del servidor:', myAnswers);
                    }
                    
                    renderRound(round);
                } else {
                    console.log('[DEBUG] No hay ronda activa en estado inicial');
                    renderEmptyRound();
                }
            } catch (e) {
                console.error('Error cargando estado inicial:', e);
                renderEmptyRound();
            }
        }`;

c = c.replace(
    '        if (data.round) {\n                    const round = {\n                        number: data.round.number,\n                        letter: data.round.letter,\n                        starts_at: data.round.starts_at,\n                        ends_at: data.round.ends_at,\n                    };\n                    currentRound = round;\n                    console.log(\'[DEBUG] Ronda encontrada en estado inicial, renderizando:\', round);\n                    renderRound(round);\n                } else {\n                    console.log(\'[DEBUG] No hay ronda activa en estado inicial\');\n                    renderEmptyRound();\n                }\n            } catch (e) {\n                console.error(\'Error cargando estado inicial:\', e);\n                renderEmptyRound();\n            }\n        }',
    newLoadInitial
);
console.log('FIX 4:', c.includes('Respuestas restauradas del servidor') ? '✓' : '✗');

// ============================================================
// FIX 5: Update goToCategory (SECOND occurrence) to call debouncedSaveAnswers
// ============================================================
const oldGoToCategory2 = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
            }`;

const newGoToCategory2 = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

c = c.replace(
    '            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }',
    newGoToCategory2
);
console.log('FIX 5:', c.includes('debouncedSaveAnswers(); // Guardar en servidor con debounce') ? '✓ (2nd)' : '✗ (2nd)');

// ============================================================
// FIX 6: Update timer to send final answers (second occurrence)
// ============================================================
const oldTimerEnd2 = '                if (remaining <= 0) clearInterval(currentTimerInterval);';
const newTimerEnd2 = `                if (remaining <= 0) {
                    clearInterval(currentTimerInterval);
                    // Tiempo agotado: bloquear inputs y enviar respuestas finales
                    carouselTrack.querySelectorAll('input[type="text"]').forEach(input => {
                        input.disabled = true;
                    });
                    btnBasta.disabled = true;
                    enviarRespuestas().then(() => {
                        console.log('[DEBUG] Respuestas finales enviadas por tiempo agotado');
                    }).catch(e => console.error('Error enviando respuestas finales:', e));
                    return;
                }`;

c = c.replace(
    '                if (remaining <= 0) clearInterval(currentTimerInterval);',
    newTimerEnd2
);
console.log('FIX 6:', c.includes('Tiempo agotado') ? '✓ (2nd)' : '✗');

// ============================================================
// FIX 7: Add debouncedSaveAnswers function (re-check)
// ============================================================
const debouncedSaveAnswersFn2 = `
        // Debounce para envío de respuestas al servidor
        let saveDebounceTimer = null;
        function debouncedSaveAnswers() {
            if (saveDebounceTimer) clearTimeout(saveDebounceTimer);
            saveDebounceTimer = setTimeout(async () => {
                try {
                    await enviarRespuestas();
                    console.log('[DEBUG] Respuestas guardadas en servidor (debounced)');
                } catch (e) {
                    console.error('Error guardando respuestas:', e);
                }
            }, 1000);
        }
`;

if (c.includes('        }\n\n        // Navegación con teclado')) {
    c = c.replace(
        '        }\n\n        // Navegación con teclado',
        '        }\n\n' + '        // Debounce para envío de respuestas al servidor\n        let saveDebounceTimer = null;\n        function debouncedSaveAnswers() {\n            if (saveDebounceTimer) clearTimeout(saveDebounceTimer);\n            saveDebounceTimer = setTimeout(async () => {\n                try {\n                    await enviarRespuestas();\n                    console.log(\'[DEBUG] Respuestas guardadas en servidor (debounced)\');\n                } catch (e) {\n                    console.error(\'Error guardando respuestas:\', e);\n                }\n            }, 1000);\n        }\n        // Navegación con teclado'
    );
    console.log('FIX 7: ✓ Added debouncedSaveAnswers function (re-check)');
} else {
    console.log('FIX 7: Already present');
}

// ============================================================
// FIX 8: Update goToCategory (first occurrence) to call debouncedSaveAnswers
// ============================================================
const oldGoToCategory1_v2 = '            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }';

const newGoToCategory1_v2 = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

if (c.includes('            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }')) {
    c = c.replace(
        '            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }',
        `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`
    );
    console.log('FIX 8: ✓ Updated goToCategory (1st - re-apply)');
} else {
    console.log('FIX 8: Already updated');
}

// ============================================================
// FIX 9: Update goToCategory (second occurrence) to call debouncedSaveAnswers
// ============================================================
const oldGoToCategory2_v2 = '            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }';

const newGoToCategory2_v2 = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

c = c.replace(
    '            // Guardar respuesta actual antes de cambiar\n            const currentInput = carouselTrack.querySelector(\'[data-index="${currentCategoryIndex}"] input\');\n            if (currentInput) {\n                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();\n            }',
    newGoToCategory2_v2
);
console.log('FIX 9:', c.includes('debouncedSaveAnswers(); // Guardar en servidor con debounce') ? '✓ (3rd)' : '✗ (3rd)');

// ============================================================
// FIX 10: Update timer to send final answers (second occurrence)
// ============================================================
const oldTimerEnd2 = '                if (remaining <= 0) clearInterval(currentTimerInterval);';
const newTimerEnd2 = `                if (remaining <= 0) {
                    clearInterval(currentTimerInterval);
                    // Tiempo agotado: bloquear inputs y enviar respuestas finales
                    carouselTrack.querySelectorAll('input[type="text"]').forEach(input => {
                        input.disabled = true;
                    });
                    btnBasta.disabled = true;
                    enviarRespuestas().then(() => {
                        console.log('[DEBUG] Respuestas finales enviadas por tiempo agotado');
                    }).catch(e => console.error('Error enviando respuestas finales:', e));
                    return;
                }`;

c = c.replace(
    '                if (remaining <= 0) clearInterval(currentTimerInterval);',
    newTimerEnd2
);
console.log('FIX 10:', c.includes('Tiempo agotado') ? '✓ (2nd timer)' : '✗ (2nd timer)');

// Write the final result
fs.writeFileSync('resources/views/tutti/player.blade.php', c, 'utf8');
console.log('\n✅ All fixes applied successfully!');