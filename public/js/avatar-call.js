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
    };

    const ui = {
        avatarName: document.getElementById('avatarName'),
        chat: document.getElementById('chat'),
        fallback: document.getElementById('avatarFallback'),
        handup: document.getElementById('hangupButton'),
        message: document.getElementById('callMessage'),
        microphone: document.getElementById('microphoneButton'),
        speaker: document.getElementById('speakerButton'),
        start: document.getElementById('startConversation'),
        status: document.getElementById('callStatus'),
        timer: document.getElementById('recordingTimer'),
    };

    let audioContext;
    let currentPlayback;
    let currentViseme;
    let recordingTimeout;
    let recognition;
    let muted = false;
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

    const setStatus = (text, state = 'ready') => {
        ui.status.textContent = text;
        ui.status.dataset.state = state;
    };

    const setMessage = (text) => {
        ui.message.textContent = text;
    };

    const addBubble = (speaker, text) => {
        const bubble = document.createElement('article');
        bubble.className = `bubble bubble--${speaker}`;
        bubble.innerHTML = `<strong>${speaker === 'user' ? 'Tú' : 'Anita'}</strong><p></p>`;
        bubble.querySelector('p').textContent = text;
        ui.chat.prepend(bubble);
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

    const playAudio = async (reply) => {
        if (muted || !reply.audio_url || !Array.isArray(reply.visemes) || reply.visemes.length === 0) {
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

            return await new Promise((resolve) => {
                const source = audioContext.createBufferSource();
                source.buffer = buffer;
                source.connect(audioContext.destination);

                const startedAt = audioContext.currentTime;
                let frame;

                const finish = (completed) => {
                    cancelAnimationFrame(frame);

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
                    frame = requestAnimationFrame(animate);
                };

                source.onended = () => finish(true);
                currentPlayback = { source, resolve };
                source.start();
                animate();
            });
        } catch (_) {
            resetViseme();

            return false;
        }
    };

    const setSpeaking = (speaking) => {
        shell.classList.toggle('is-speaking', speaking);
    };

    const reply = async (payload) => {
        const data = await request(config.messageUrl, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        if (data.status === 'queued') {
            setMessage(data.message || 'Estoy organizando tu consulta.');

            if (data.connector) {
                await playAudio(data.connector);
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

    const answer = async (transcript) => {
        if (!ready || !conversationStarted) {
            return;
        }

        addBubble('user', transcript);
        setMessage('Anita está preparando una respuesta.');
        setSpeaking(true);

        try {
            const response = await reply({ message: transcript });
            addBubble('avatar', response.text);
            setMessage(response.text);

            if (!(await playAudio(response))) {
                setMessage('No se pudo reproducir la respuesta. Puedes reactivar el altavoz e intentarlo otra vez.');
            }
        } catch (_) {
            setMessage('No pude completar esa respuesta. Inténtalo de nuevo.');
        } finally {
            setSpeaking(false);
        }
    };

    const stopRecording = () => {
        window.clearTimeout(recordingTimeout);
        shell.classList.remove('is-recording');
        ui.microphone.classList.remove('is-recording');
        ui.timer.textContent = '';

        if (recognition) {
            recognition.stop();
        }
    };

    const setupRecognition = () => {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            ui.microphone.disabled = true;
            setMessage('Tu navegador no ofrece transcripción por micrófono.');

            return;
        }

        recognition = new SpeechRecognition();
        recognition.lang = 'es-PE';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        recognition.onresult = (event) => answer(event.results[0][0].transcript.trim());
        recognition.onerror = () => setMessage('No pude transcribir lo que dijiste. Inténtalo de nuevo.');
        recognition.onend = () => {
            window.clearTimeout(recordingTimeout);
            shell.classList.remove('is-recording');
            ui.microphone.classList.remove('is-recording');
            ui.timer.textContent = '';
        };
    };

    const startRecording = () => {
        if (!conversationStarted || !recognition || shell.classList.contains('is-recording')) {
            return;
        }

        recognition.start();
        shell.classList.add('is-recording');
        ui.microphone.classList.add('is-recording');
        let remaining = 15;
        ui.timer.textContent = `${remaining}s`;
        setMessage('Te escucho. Pulsa de nuevo para terminar antes.');
        const ticker = window.setInterval(() => {
            remaining -= 1;
            ui.timer.textContent = `${Math.max(remaining, 0)}s`;

            if (remaining <= 0) {
                window.clearInterval(ticker);
            }
        }, 1000);
        recordingTimeout = window.setTimeout(stopRecording, 15000);
    };

    const setupRive = () => new Promise((resolve, reject) => {
        if (!window.rive) {
            reject(new Error('No se pudo cargar la animación de Anita.'));

            return;
        }

        const riveInstance = new window.rive.Rive({
            src: config.riveUrl,
            canvas: document.getElementById('avatarCanvas'),
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
                ui.fallback.hidden = true;
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
        setMessage('Anita está iniciando la llamada.');

        try {
            await unlockAudio();
            const greeting = await request(config.greetingUrl, { method: 'POST', body: '{}' });
            addBubble('avatar', greeting.text);
            setMessage(greeting.text);
            setSpeaking(true);

            if (!(await playAudio(greeting))) {
                throw new Error('No se pudo iniciar el saludo.');
            }

            conversationStarted = true;
            ui.start.hidden = true;
            ui.microphone.disabled = false;
            setMessage('Pulsa el micrófono para conversar con Anita.');
        } catch (_) {
            setMessage('No se pudo reproducir el saludo. Revisa el altavoz y vuelve a intentarlo.');
            ui.start.disabled = false;
        } finally {
            setSpeaking(false);
        }
    };

    ui.microphone.addEventListener('click', () => {
        if (shell.classList.contains('is-recording')) {
            stopRecording();
        } else {
            startRecording();
        }
    });

    ui.speaker.addEventListener('click', () => {
        muted = !muted;
        ui.speaker.classList.toggle('is-muted', muted);
        ui.speaker.setAttribute('aria-pressed', String(muted));

        if (muted) {
            stopAudio();
            setMessage('Audio silenciado. Pulsa el altavoz para reactivarlo.');
        } else {
            setMessage('Audio activado.');
        }
    });

    ui.handup.addEventListener('click', () => {
        stopRecording();
        stopAudio();
        window.location.assign('/');
    });

    ui.start.addEventListener('click', startConversation);

    Promise.all([setupRive(), request(config.statusUrl)])
        .then(([_, status]) => {
            if (!status.ready) {
                throw new Error('El contenido publicado no está listo.');
            }

            ready = true;
            ui.avatarName.textContent = status.avatar.name;
            setStatus('Lista para conversar');
            setMessage('Pulsa “Iniciar conversación” para escuchar a Anita.');
            ui.start.hidden = false;
            setupRecognition();
        })
        .catch((error) => {
            setStatus('Servicio no disponible', 'error');
            setMessage(error.message || 'Anita no está disponible en este momento.');
            ui.start.hidden = true;
            ui.microphone.disabled = true;
        });
})();
