<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registro de Marcación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f4f4f4 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        h1 {
            margin-bottom: 1.5rem;
            color: #2d3748;
            font-size: 2rem;
            letter-spacing: 1px;
        }

        .container {
            background: white;
            padding: 1.5rem 1rem;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(60, 72, 88, 0.08);
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        label {
            font-weight: 600;
            color: #374151;
        }

        select,
        button {
            width: 100%;
            padding: 0.7rem;
            margin-top: 0.3rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 1rem;
            background: #f9fafb;
            transition: border 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        select:focus,
        button:focus {
            border-color: #6366f1;
            outline: none;
            box-shadow: 0 0 0 2px #6366f133;
        }

        button {
            background-color: #6366f1;
            color: white;
            font-weight: bold;
            margin-top: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background-color: #4f46e5;
        }

        .video-container {
            position: relative;
            width: 320px;
            height: 240px;
            margin: 0 auto;
            background: #e0e7ff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(60, 72, 88, 0.10);
        }

        video,
        canvas {
            margin-top: 0;
            width: 100%;
            border-radius: 12px;
        }

        .alert {
            margin-top: 1rem;
            padding: 0.9rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-warning {
            background-color: #fef9c3;
            color: #92400e;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .time {
            margin-top: 1rem;
            font-size: 1.1rem;
            color: #6366f1;
            text-align: center;
            letter-spacing: 1px;
        }

        @media (max-width: 480px) {

            .container,
            .video-container {
                max-width: 100%;
                width: 100%;
            }

            .video-container {
                height: auto;
                min-height: 180px;
            }
        }
    </style>
</head>

<body>
    <h1>Registrar Marcación</h1>

    <div class="container">
        <!-- Nueva selección de sesión -->
        <label for="session">Sesión:</label>
        <select id="session">
            <option value="jornada">Jornada</option>
            <option value="desayuno">Desayuno</option>
            <option value="almuerzo">Almuerzo</option>
        </select>

        <!-- Selección de tipo existente -->
        <label for="type">Tipo:</label>
        <select id="type">
            <option value="entrada">Entrada 🟢</option>
            <option value="salida">Salida 🔴</option>
        </select>

        <!-- Video y canvas para la cámara -->
        <div class="video-container">
            <video id="video" width="320" height="240" autoplay muted></video>
            <canvas id="overlay" width="320" height="240" style="position: absolute; top: 0; left: 0;"></canvas>
        </div>

        <div id="messageBox" class="alert alert-warning">Inicializando...</div>
        <div class="time" id="clock"></div>
    </div>

    <audio id="successSound" src="/sounds/success.mp3" preload="auto"></audio>
    <audio id="errorSound" src="/sounds/error.mp3" preload="auto"></audio>

    <script>
        let employees = [];
        let faceMatcher;
        let currentLocation = '';
        let recognitionEnabled = false;

        // 1) Obtener ubicación obligatoria
        function requireLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    return reject('Geolocalización no soportada.');
                }
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        currentLocation = `${pos.coords.latitude},${pos.coords.longitude}`;
                        resolve(currentLocation);
                    },
                    () => reject('Debes habilitar la ubicación para continuar.')
                );
            });
        }

        // 2) Carga empleados
        async function loadEmployees() {
            const res = await fetch('/api/employees');
            employees = await res.json();
        }

        // 3) Setup cámara con permiso obligatorio
        async function setupCamera() {
            const video = document.getElementById('video');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: true
                });
                video.srcObject = stream;
                await new Promise(res => video.onloadedmetadata = res);
                video.play();
                video.style.display = 'block';
            } catch (err) {
                throw 'Debes permitir acceso a la cámara para continuar.';
            }
        }

        // 4) Carga modelos
        async function loadModels() {
            const MODEL_URL = '/models';
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        }

        // 5) Carga descriptores
        async function loadLabeledDescriptors() {
            const descriptors = [];
            for (const emp of employees) {
                if (!emp.photo) continue;
                try {
                    const img = await faceapi.fetchImage(`/storage/${emp.photo}`);
                    const det = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks()
                        .withFaceDescriptor();
                    if (det) {
                        descriptors.push(new faceapi.LabeledFaceDescriptors(`${emp.id}`, [det.descriptor]));
                    }
                } catch {}
            }
            if (descriptors.length === 0) {
                throw 'No se encontraron descriptores faciales válidos.';
            }
            faceMatcher = new faceapi.FaceMatcher(descriptors, 0.6);
        }

        // Reloj
        function updateClock() {
            document.getElementById('clock').textContent =
                `🕒 Hora actual: ${new Date().toLocaleTimeString()}`;
        }
        setInterval(updateClock, 1000);

        // 6) Reconocimiento en vivo
        async function startLiveRecognition() {
            const video = document.getElementById('video');
            const overlay = document.getElementById('overlay');
            const messageBox = document.getElementById('messageBox');
            const sessionSelect = document.getElementById('session');
            const typeSelect = document.getElementById('type');
            const successSound = document.getElementById('successSound');
            const errorSound = document.getElementById('errorSound');

            recognitionEnabled = true;

            setInterval(async () => {
                if (!recognitionEnabled) return;
                const result = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                const displaySize = {
                    width: video.width,
                    height: video.height
                };
                faceapi.matchDimensions(overlay, displaySize);
                const ctx = overlay.getContext('2d');
                ctx.clearRect(0, 0, overlay.width, overlay.height);

                if (!result) {
                    messageBox.textContent = 'Buscando rostro...';
                    messageBox.className = 'alert alert-warning';
                    return;
                }

                const resized = faceapi.resizeResults(result, displaySize);
                faceapi.draw.drawDetections(overlay, resized);
                const match = faceMatcher.findBestMatch(result.descriptor);
                if (match.label !== 'unknown') {
                    recognitionEnabled = false;
                    const emp = employees.find(e => `${e.id}` === match.label);
                    messageBox.textContent =
                        `✅ ${emp.first_name} ${emp.last_name} reconocido. Registrando...`;
                    messageBox.className = 'alert alert-success';

                    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    try {
                        const res = await fetch('/marcar', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                session: sessionSelect.value,
                                type: typeSelect.value,
                                employee_id: match.label,
                                location: currentLocation,
                            }),
                        });
                        const json = await res.json();
                        if (res.ok && json.success) {
                            messageBox.textContent = '✅ Marcación registrada.';
                            successSound.play();
                        } else {
                            throw json.message || 'Error al registrar.';
                        }
                    } catch (err) {
                        messageBox.textContent = `❌ ${err}`;
                        messageBox.className = 'alert alert-danger';
                        errorSound.play();
                    }

                    setTimeout(() => {
                        recognitionEnabled = true;
                        messageBox.textContent = 'Listo para el próximo usuario.';
                        messageBox.className = 'alert alert-warning';
                    }, 5000);
                } else {
                    messageBox.textContent = '❌ Rostro no reconocido';
                    messageBox.className = 'alert alert-danger';
                }
            }, 1500);
        }

        // Entrada principal
        window.onload = async () => {
            const messageBox = document.getElementById('messageBox');
            try {
                messageBox.textContent = 'Obteniendo ubicación…';
                messageBox.className = 'alert alert-info';
                await requireLocation();

                messageBox.textContent = 'Permitiendo cámara…';
                messageBox.className = 'alert alert-info';
                await setupCamera();

                // Habilitar selects
                document.getElementById('session').disabled = false;
                document.getElementById('type').disabled = false;

                messageBox.textContent = 'Cargando datos…';
                messageBox.className = 'alert alert-info';
                await loadEmployees();
                await loadModels();
                await loadLabeledDescriptors();

                messageBox.textContent = 'Listo para reconocimiento facial ✅';
                messageBox.className = 'alert alert-success';
                startLiveRecognition();
            } catch (err) {
                messageBox.textContent = `❌ ${err}`;
                messageBox.className = 'alert alert-danger';
                // opcional: mostramos un botón para reintentar
                const btn = document.createElement('button');
                btn.textContent = 'Reintentar';
                btn.className = 'btn btn-primary mt-2';
                btn.onclick = () => window.location.reload();
                messageBox.appendChild(btn);
            }
        };
    </script>
</body>

</html>
