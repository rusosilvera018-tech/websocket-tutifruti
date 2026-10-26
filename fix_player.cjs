const fs = require('fs');

const file = 'resources/views/tutti/player.blade.php';
let content = fs.readFileSync(file, 'utf8');

// Helper to find and replace with proper escaping
function replaceInContent(content, searchText, replaceText) {
    if (content.includes(searchText)) {
        content = content.replace(searchText, replaceText);
        console.log('✓ Replaced');
        return true;
    } else {
        console.log('✗ NOT FOUND: ' + searchText.substring(0, 80) + '...');
        return false;
    }
}

let content = fs.readFileSync('resources/views/tutti/player.blade.php', 'utf8');

// 1. Add debouncedSaveAnswers after goToPrevCategory
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

if (content.includes('        }\n\n        // Navegación con teclado')) {
    content = content.replace(
        '        }\n\n        // Navegación con teclado',
        '        }\n\n' + debouncedSaveAnswersFn + '\n        // Navegación con teclado'
    );
    console.log('✓ Added debouncedSaveAnswers function');
} else {
    console.log('✗ Could not find insertion point for debouncedSaveAnswers');
}

// 2. Update goToCategory to call debouncedSaveAnswers
const oldGoToCategory = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
            }`;

const newGoToCategory = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

if (content.includes(oldGoToCategory)) {
    content = content.replace(oldGoToCategory, newGoToCategory);
    console.log('✓ Updated goToCategory');
} else {
    console.log('✗ Could not find goToCategory pattern');
}

// 4. Update startTimer to block inputs and send final answers when timer reaches 0
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

if (content.includes(oldTimerEnd)) {
    content = content.replace(oldTimerEnd, newTimerEnd);
    console.log('✓ Updated timer logic');
} else {
    console.log('✗ Could not find timer end pattern');
}

// 3. Update loadInitialState to restore answers from server
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

if (content.includes(oldLoadInitial)) {
    content = content.replace(oldLoadInitial, newLoadInitial);
    console.log('✓ Updated loadInitialState');
} else {
    console.log('✗ Could not find loadInitialState pattern');
}

// 2. Update goToCategory to call debouncedSaveAnswers
const oldGoToCategory = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
            }`;

const newGoToCategory = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

if (content.includes(oldGoToCategory)) {
    content = content.replace(oldGoToCategory, newGoToCategory);
    console.log('✓ Updated goToCategory');
} else {
    console.log('✗ Could not find goToCategory pattern');
}

// 4. Update startTimer to block inputs and send final answers when timer reaches 0
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

if (content.includes(oldTimerEnd)) {
    content = content.replace(oldTimerEnd, newTimerEnd);
    console.log('✓ Updated timer logic');
} else {
    console.log('✗ Could not find timer end pattern');
}

// 3. Update loadInitialState to restore answers from server
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

if (content.includes(oldLoadInitial)) {
    content = content.replace(oldLoadInitial, newLoadInitial);
    console.log('✓ Updated loadInitialState');
} else {
    console.log('✗ Could not find loadInitialState pattern');
}

// 2. Update goToCategory to call debouncedSaveAnswers
const oldGoToCategory = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
            }`;

const newGoToCategory = `            // Guardar respuesta actual antes de cambiar
            const currentInput = carouselTrack.querySelector('[data-index="${currentCategoryIndex}"] input');
            if (currentInput) {
                myAnswers[categoriesList[currentCategoryIndex].id] = currentInput.value.trim().toUpperCase();
                debouncedSaveAnswers(); // Guardar en servidor con debounce
            }`;

if (content.includes(oldGoToCategory)) {
    content = content.replace(oldGoToCategory, newGoToCategory);
    console.log('✓ Updated goToCategory');
} else {
    console.log('✗ Could not find goToCategory pattern');
}

fs.writeFileSync('resources/views/tutti/player.blade.php', content, 'utf8');
console.log('All updates applied successfully!');