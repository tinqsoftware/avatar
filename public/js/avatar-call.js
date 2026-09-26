(() => {
    const shell = document.getElementById('callShell');

    if (!shell) {
        return;
    }

    const config = {
        statusUrl: shell.dataset.statusUrl,
        greetingUrl: shell.dataset.greetingUrl,
        messageUrl: shell.dataset.messageUrl,
        pollUrl: shell.dataset.pollUrl,
        riveUrl: shell.dataset.riveUrl,
        avatarName: shell.dataset.avatarName,
    };

    const ui = {
        chat: document.getElementById('chat'),
        chatPanel: document.querySelector('.chat-panel'),
        handup: document.getElementById('hangupButton'),
        hint: document.getElementById('callHint'),
        microphone: document.getElementById('microphoneButton'),
        start: document.getElementById('startConversation'),
        timer: document.getElementById('recordingTimer'),
    };

    let audioContext;
    let currentPlayback;
    let currentViseme;
    let recordingTimeout;
    let recordingInterval;
    let recognition;
    let activeRecordingSessionId;
    let nextRecordingSessionId = 0;
    let recognitionError;
    let submittedRecordingSessionId;
    let suppressRecordingFeedback = false;
    let recordingBubbleText;
    let ready = false;
    let conversationStarted = false;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
            ...options,
        });

        if (!response.ok) {
            throw new Error('La llamada no pudo completarse.');
        }

        return response.json();
    };

    const setHint = (text) => {
        ui.hint.textContent = text;
        ui.hint.classList.toggle('has-message', Boolean(text));
    };

    const addBubble = (speaker, text) => {
        const bubble = document.createElement('article');
        bubble.className = `bubble bubble--${speaker}`;
        bubble.innerHTML = `<strong>${speaker === 'user' ? 'Tú' : config.avatarName}</strong><p></p>`;
        bubble.querySelector('p').textContent = text;
        ui.chat.prepend(bubble);
        ui.chatPanel.classList.add('has-messages');

        return bubble.querySelector('p');
    };

    const revealedText = (text, elapsedMs, durationMs) => {
        const words = text.trim().split(/\s+/).filter(Boolean);
        const visibleCount = Math.min(words.length, Math.floor((elapsedMs / Math.max(durationMs, 1)) * words.length));

        return words.slice(0, visibleCount).join(' ');
    };

    const resetViseme = () => {
        if (currentViseme) {
            currentViseme.value = 0;
        }
    };

    const stopAudio = () => {
        if (!currentPlayback) {
            resetViseme();

            return;
        }

        const playback = currentPlayback;
        currentPlayback = undefined;
        playback.source.onended = null;

        try {
            playback.source.stop();
        } catch (_) {
            // The source may already have ended.
        }

        playback.disconnect();

        resetViseme();
        playback.resolve(false);
    };

    const findViseme = (visemes, seconds) => {
        const atMs = seconds * 1000;
        let value = 0;

        for (const viseme of visemes) {
            if (atMs < Number(viseme.at_ms)) {
                break;
            }

            value = Number(viseme.value);
        }

        return value;
    };

    const unlockAudio = async () => {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) {
            throw new Error('Este navegador no permite reproducir el audio de Anita.');
        }

        audioContext ||= new AudioContextClass();

        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }

        if (audioContext.state !== 'running') {
            throw new Error('No fue posible habilitar el audio de Anita.');
        }
    };

    const playAudio = async (reply, onProgress = () => {}) => {
        if (!reply.audio_url) {
            return false;
        }

        try {
            await unlockAudio();

            const audioResponse = await fetch(reply.audio_url, { credentials: 'same-origin' });

            if (!audioResponse.ok) {
                throw new Error('No se encontró el audio publicado.');
            }

            const encoded = await audioResponse.arrayBuffer();
            const buffer = await audioContext.decodeAudioData(encoded);
            stopAudio();
            const durationMs = Number(reply.duration_ms) || Math.round(buffer.duration * 1000);

            return await new Promise((resolve) => {
                const source = audioContext.createBufferSource();
                source.buffer = buffer;
                const gain = audioContext.createGain();
                const compressor = audioContext.createDynamicsCompressor();
                gain.gain.value = 1.8;
                compressor.threshold.value = -18;
                compressor.knee.value = 18;
                compressor.ratio.value = 8;
                compressor.attack.value = 0.003;
                compressor.release.value = 0.25;
                source.connect(gain);
                gain.connect(compressor);
                compressor.connect(audioContext.destination);

                const startedAt = audioContext.currentTime;
                let frame;

                const finish = (completed) => {
                    cancelAnimationFrame(frame);
                    onProgress(durationMs, durationMs);
                    source.disconnect();
                    gain.disconnect();
                    compressor.disconnect();

                    if (currentPlayback?.source === source) {
                        currentPlayback = undefined;
                    }

                    resetViseme();
                    resolve(completed);
                };

                const animate = () => {
                    if (!currentPlayback || currentPlayback.source !== source) {
                        return;
                    }

                    const seconds = Math.max(0, audioContext.currentTime - startedAt);
                    currentViseme.value = findViseme(reply.visemes, seconds);
                    onProgress(Math.min(seconds * 1000, durationMs), durationMs);
                    frame = requestAnimationFrame(animate);
                };

                source.onended = () => finish(true);
                currentPlayback = {
                    source,
                    resolve,
                    disconnect: () => {
                        source.disconnect();
                        gain.disconnect();
                        compressor.disconnect();
                    },
                };
                onProgress(0, durationMs);
                source.start();
                animate();
            });
        } catch (_) {
            resetViseme();

            return false;
        }
    };

    const playAvatarLine = async (item) => {
        const text = typeof item.text === 'string' ? item.text : '';
        const bubbleText = addBubble('avatar', '');

        if (!item.audio_url) {
            bubbleText.textContent = text;

            return false;
        }

        const completed = await playAudio(item, (elapsedMs, durationMs) => {
            bubbleText.textContent = revealedText(text, elapsedMs, durationMs);
        });
        bubbleText.textContent = text;

        return completed;
    };

    const playPlaylist = async (playlist) => {
        for (const item of playlist) {
            if (!(await playAvatarLine(item))) {
                return false;
            }
        }

        return true;
    };

    const setSpeaking = (speaking) => {
        shell.classList.toggle('is-speaking', speaking);
        shell.setAttribute('aria-busy', String(speaking));

        if (speaking) {
            stopRecording({ suppressFeedback: true });
        }

        ui.microphone.disabled = speaking || !conversationStarted || !recognition;
        ui.microphone.setAttribute('aria-label', speaking
            ? 'Micrófono inactivo mientras Anita responde'
            : 'Hablar con Anita');
    };

    const reply = async (payload) => {
        const data = await request(config.messageUrl, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        if (data.status === 'queued') {
            setHint(data.message || 'Anita está organizando tu consulta.');

            if (data.connector) {
                await playPlaylist([data.connector]);
            }

            return poll(data.ticket);
        }

        return data;
    };

    const poll = async (ticket) => {
        for (let attempt = 0; attempt < 45; attempt += 1) {
            await new Promise((resolve) => window.setTimeout(resolve, 650));
            const data = await request(config.pollUrl.replace(':ticket', encodeURIComponent(ticket)));

            if (data.status === 'ready') {
                return data.reply;
            }
        }

        throw new Error('La respuesta está tardando más de lo esperado.');
    };

    const answer = async (transcript, bubbleText = null) => {
        if (!ready || !conversationStarted) {
            return;
        }

        (bubbleText || addBubble('user', transcript)).textContent = transcript;
        setHint('Anita está preparando una respuesta.');
        setSpeaking(true);

        try {
            const response = await reply({ message: transcript });
            const playlist = Array.isArray(response.playlist) && response.playlist.length > 0
                ? response.playlist
                : [response];

            if (!(await playPlaylist(playlist))) {
                setHint('No se pudo reproducir la respuesta. Vuelve a intentarlo en unos segundos.');
            }
        } catch (_) {
            setHint('No pude completar esa respuesta. Inténtalo de nuevo.');
        } finally {
            setSpeaking(false);
        }
    };

    const clearRecordingUi = () => {
        window.clearTimeout(recordingTimeout);
        window.clearInterval(recordingInterval);
        recordingTimeout = undefined;
        recordingInterval = undefined;
        shell.classList.remove('is-recording');
        ui.microphone.classList.remove('is-recording');
        ui.timer.textContent = '';
    };

    const updateMicrophoneAvailability = () => {
        ui.microphone.disabled = !conversationStarted || shell.classList.contains('is-speaking') || !recognition;
    };

    const finishRecordingSession = (sessionId) => {
        if (!sessionId || sessionId !== activeRecordingSessionId) {
            return;
        }

        const error = recognitionError;
        const wasSubmitted = submittedRecordingSessionId === sessionId;
        const shouldShowFeedback = !wasSubmitted && !suppressRecordingFeedback;

        clearRecordingUi();
        activeRecordingSessionId = undefined;
        recognitionError = undefined;
        suppressRecordingFeedback = false;
        updateMicrophoneAvailability();

        if (!wasSubmitted && recordingBubbleText) {
            recordingBubbleText.closest('.bubble')?.remove();
            recordingBubbleText = undefined;
            if (!ui.chat.children.length) {
                ui.chatPanel.classList.remove('has-messages');
            }
        }

        if (shouldShowFeedback && error !== 'aborted') {
            setHint(microphoneErrorMessage(error || 'no-speech'));
        }
    };

    const stopRecording = ({ suppressFeedback = false } = {}) => {
        const sessionId = activeRecordingSessionId;

        if (!sessionId) {
            return;
        }

        suppressRecordingFeedback ||= suppressFeedback;
        clearRecordingUi();
        ui.microphone.disabled = true;

        if (recognition) {
            try {
                recognition.stop();
            } catch (_) {
                finishRecordingSession(sessionId);
            }
        }
    };

    const microphoneErrorMessage = (error) => {
        switch (error) {
        case 'not-allowed':
        case 'service-not-allowed':
        case 'NotAllowedError':
        case 'SecurityError':
            return 'No tengo permiso para usar el micrófono. Actívalo en la configuración del navegador y vuelve a intentarlo.';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return 'No encontré un micrófono disponible en este dispositivo.';
        case 'NotReadableError':
        case 'audio-capture':
            return 'El micrófono está siendo usado por otra aplicación. Ciérrala e inténtalo nuevamente.';
        case 'no-speech':
            return 'No llegué a escuchar una frase. Pulsa el micrófono y vuelve a hablar.';
        case 'network':
            return 'El servicio de transcripción del navegador no respondió. Revisa tu conexión e inténtalo otra vez.';
        default:
            return 'No pude transcribir lo que dijiste. Inténtalo de nuevo.';
        }
    };

    const setupRecognition = () => {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!window.isSecureContext) {
            ui.microphone.disabled = true;
            setHint('La transcripción necesita una conexión segura HTTPS.');

            return;
        }

        if (!SpeechRecognition) {
            ui.microphone.disabled = true;
            setHint('Este navegador no ofrece transcripción por voz. Prueba con un navegador compatible.');

            return;
        }

        recognition = new SpeechRecognition();
        recognition.lang = 'es-PE';
        recognition.interimResults = true;
        recognition.continuous = false;
        recognition.maxAlternatives = 1;
        recognition.onstart = () => {
            const sessionId = activeRecordingSessionId;

            if (!sessionId) {
                try {
                    recognition.stop();
                } catch (_) {
                    // The session was cancelled before Web Speech finished starting.
                }

                return;
            }

            shell.classList.add('is-recording');
            ui.microphone.classList.add('is-recording');
            ui.microphone.disabled = true;
            recordingBubbleText = addBubble('user', '');
            let remaining = 15;
            ui.timer.textContent = `${remaining}s`;
            setHint('Te escucho.');

            recordingInterval = window.setInterval(() => {
                if (sessionId !== activeRecordingSessionId) {
                    window.clearInterval(recordingInterval);

                    return;
                }

                remaining -= 1;
                ui.timer.textContent = `${Math.max(remaining, 0)}s`;
            }, 1000);
            recordingTimeout = window.setTimeout(() => {
                if (sessionId === activeRecordingSessionId) {
                    stopRecording();
                }
            }, 15000);
        };
        recognition.onresult = (event) => {
            const sessionId = activeRecordingSessionId;
            const finalTranscript = Array.from(event.results)
                .filter((result) => result.isFinal)
                .map((result) => result[0].transcript)
                .join(' ')
                .trim();
            const interimTranscript = Array.from(event.results)
                .filter((result) => !result.isFinal)
                .map((result) => result[0].transcript)
                .join(' ')
                .trim();
            const visibleTranscript = [finalTranscript, interimTranscript].filter(Boolean).join(' ');

            if (!sessionId || submittedRecordingSessionId === sessionId) {
                return;
            }

            if (recordingBubbleText && visibleTranscript) {
                recordingBubbleText.textContent = visibleTranscript;
            }

            if (!finalTranscript) {
                return;
            }

            submittedRecordingSessionId = sessionId;
            clearRecordingUi();
            const bubbleText = recordingBubbleText;
            recordingBubbleText = undefined;
            void answer(finalTranscript, bubbleText);
        };
        recognition.onerror = (event) => {
            recognitionError = event.error;
            clearRecordingUi();
        };
        recognition.onend = () => {
            finishRecordingSession(activeRecordingSessionId);
        };
    };

    const startRecording = () => {
        if (activeRecordingSessionId || !conversationStarted || !recognition || shell.classList.contains('is-speaking')) {
            return;
        }

        activeRecordingSessionId = ++nextRecordingSessionId;
        submittedRecordingSessionId = undefined;
        recognitionError = undefined;
        suppressRecordingFeedback = false;
        ui.microphone.disabled = true;
        setHint('Activando el micrófono…');

        try {
            recognition.start();
        } catch (error) {
            recognitionError = error?.name || error?.message;
            finishRecordingSession(activeRecordingSessionId);
        }
    };

    const setupRive = () => new Promise((resolve, reject) => {
        if (!window.rive) {
            reject(new Error('No se pudo cargar la animación de Anita.'));

            return;
        }

        const riveInstance = new window.rive.Rive({
            src: config.riveUrl,
            canvas: document.getElementById('avatarCanvas'),
            layout: new window.rive.Layout({
                fit: window.rive.Fit.Contain,
                alignment: window.rive.Alignment.Center,
            }),
            autoplay: true,
            stateMachines: 'AvatarStateMachine',
            autoBind: true,
            onLoad: () => {
                riveInstance.resizeDrawingSurfaceToCanvas();
                const viewModel = riveInstance.viewModelInstance;
                currentViseme = viewModel?.number('viseme');

                if (!currentViseme) {
                    reject(new Error('El archivo del avatar no expone ViewModel1.viseme.'));

                    return;
                }

                currentViseme.value = 6;

                if (Number(currentViseme.value) !== 6) {
                    reject(new Error('La propiedad viseme del avatar no acepta valores numéricos.'));

                    return;
                }

                resetViseme();
                resolve();
            },
            onLoadError: () => reject(new Error('No se pudo abrir el archivo de animación.')),
        });
    });

    const startConversation = async () => {
        if (!ready || conversationStarted) {
            return;
        }

        ui.start.disabled = true;
        setHint('Anita está iniciando la llamada.');

        try {
            await unlockAudio();
            const greeting = await request(config.greetingUrl, { method: 'POST', body: '{}' });
            setSpeaking(true);

            if (!(await playPlaylist([greeting]))) {
                throw new Error('No se pudo iniciar el saludo.');
            }

            conversationStarted = true;
            ui.start.hidden = true;
            setHint('Presiona el micrófono para hablar.');
        } catch (_) {
            setHint('No se pudo reproducir el saludo. Revisa el altavoz y vuelve a intentarlo.');
            ui.start.disabled = false;
        } finally {
            setSpeaking(false);
        }
    };

    ui.microphone.addEventListener('click', () => {
        startRecording();
    });

    ui.handup.addEventListener('click', () => {
        stopRecording();
        stopAudio();
        window.location.assign('/');
    });

    ui.start.addEventListener('click', startConversation);

    const preventZoom = (event) => {
        event.preventDefault();
    };

    document.addEventListener('gesturestart', preventZoom, { passive: false });
    document.addEventListener('gesturechange', preventZoom, { passive: false });
    document.addEventListener('gestureend', preventZoom, { passive: false });
    document.addEventListener('wheel', (event) => {
        if (event.ctrlKey || event.metaKey) {
            preventZoom(event);
        }
    }, { passive: false });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && ['+', '-', '=', '0'].includes(event.key)) {
            preventZoom(event);
        }
    });
    document.addEventListener('touchstart', (event) => {
        if (event.touches.length > 1 && !event.target.closest('#chat')) {
            preventZoom(event);
        }
    }, { passive: false });

    Promise.all([setupRive(), request(config.statusUrl)])
        .then(([_, status]) => {
            if (!status.ready) {
                throw new Error('El contenido publicado no está listo.');
            }

            ready = true;
            setHint('');
            ui.start.hidden = false;
            setupRecognition();
        })
        .catch((error) => {
            setHint(error.message || 'Anita no está disponible en este momento.');
            ui.start.hidden = true;
            ui.microphone.disabled = true;
        });
})();
