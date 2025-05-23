<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registro de Marcación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        h1 {
            margin-bottom: 1rem;
            color: #333;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        label {
            display: block;
            margin-top: 1rem;
            font-weight: 600;
        }

        select,
        button {
            width: 100%;
            padding: 0.6rem;
            margin-top: 0.5rem;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }

        button {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }

        button:hover {
            background-color: #45a049;
        }

        video,
        canvas {
            margin-top: 1rem;
            width: 100%;
            border-radius: 10px;
        }

        .alert {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: 5px;
            text-align: center;
            font-weight: 600;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .time {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #555;
        }
    </style>
</head>

<body>
    <h1>Registro de Marcación</h1>

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

        <div style="position: relative; width: 320px; height: 240px;">
            <video id="video" width="320" height="240" autoplay muted></video>
            <canvas id="overlay" width="320" height="240" style="position: absolute; top: 0; left: 0;"></canvas>
        </div>

        <div id="messageBox" class="alert alert-warning">Cargando modelos...</div>
        <div class="time" id="clock"></div>
    </div>

    <audio id="successSound" src="/sounds/success.mp3" preload="auto"></audio>
    <audio id="errorSound" src="/sounds/error.mp3" preload="auto"></audio>

    <script>
        let employees = [];
        let faceMatcher;
        let currentLocation = '';
        let recognitionEnabled = true;

        // 1) Carga lista de empleados
        async function loadEmployees() {
            const res = await fetch('/api/employees');
            employees = await res.json();
            console.log(`🔍 Empleados cargados: ${employees.length}`);
        }

        // 2) Prepara la cámara y espera a que el vídeo arranque
        async function setupCamera() {
            const video = document.getElementById('video');
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true
            });
            video.srcObject = stream;
            await new Promise(resolve => {
                video.onloadedmetadata = () => {
                    video.play();
                    console.log('📹 Cámara lista');
                    resolve();
                };
            });
        }

        // 3) Carga los modelos desde public/models
        async function loadModels() {
            const MODEL_URL = '/models';
            console.log('🚧 Cargando modelos desde', MODEL_URL);
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            console.log('✅ Modelos cargados');
        }

        // 4) Construye los descriptores etiquetados y crea el FaceMatcher
        async function loadLabeledDescriptors() {
            const descriptors = [];

            for (const emp of employees) {
                if (!emp.photo) continue;
                try {
                    const imgUrl = `/storage/${emp.photo}`;
                    const img = await faceapi.fetchImage(imgUrl);
                    const det = await faceapi
                        .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks()
                        .withFaceDescriptor();

                    if (det) {
                        descriptors.push(
                            new faceapi.LabeledFaceDescriptors(
                                `${emp.id}`,
                                [det.descriptor]
                            )
                        );
                        console.log(`👍 Descriptor cargado para empleado ${emp.id}`);
                    } else {
                        console.warn(`⚠️ No se detectó rostro en la foto de ${emp.id}`);
                    }
                } catch (err) {
                    console.error(`❌ Error cargando foto de ${emp.id}:`, err);
                }
            }

            if (descriptors.length === 0) {
                throw new Error('No se encontró ningún descriptor válido. Revisa tus fotos/modelos.');
            }

            faceMatcher = new faceapi.FaceMatcher(descriptors, 0.6);
            console.log(`🤖 FaceMatcher listo con ${descriptors.length} etiquetas`);
        }

        // utilitarios
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => currentLocation = `${pos.coords.latitude},${pos.coords.longitude}`,
                    () => console.warn('No se pudo obtener ubicación.')
                );
            }
        }

        function updateClock() {
            document.getElementById('clock').textContent =
                `🕒 Hora actual: ${new Date().toLocaleTimeString()}`;
        }
        setInterval(updateClock, 1000);

        // 5) Inicia el bucle de reconocimiento
        async function startLiveRecognition() {
            const video = document.getElementById('video');
            const overlay = document.getElementById('overlay');
            const messageBox = document.getElementById('messageBox');
            const typeSelect = document.getElementById('type');
            const successSound = document.getElementById('successSound');
            const errorSound = document.getElementById('errorSound');

            setInterval(async () => {
                if (!recognitionEnabled) return;

                const result = await faceapi
                    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
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
                    const emp = employees.find(e => `${e.id}` === match.label);
                    recognitionEnabled = false;
                    messageBox.textContent =
                        `✅ ${emp.first_name} ${emp.last_name} reconocido. Registrando...`;
                    messageBox.className = 'alert alert-success';

                    try {
                        const res = await fetch('/marcar', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                type: typeSelect.value,
                                 session: document.getElementById('session').value,
                                employee_id: match.label,
                                location: currentLocation
                            })
                        });
                        const json = await res.json();
                        if (res.ok && json.success) {
                            messageBox.textContent = '✅ Marcación registrada.';
                            successSound.play();
                        } else {
                            throw new Error(json.message || 'Error al registrar.');
                        }
                    } catch (err) {
                        console.error('Error al marcar:', err);
                        messageBox.textContent = `❌ ${err.message}`;
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

        // 6) Punto de entrada con manejo de errores
        window.onload = async () => {
            const messageBox = document.getElementById('messageBox');
            try {
                getLocation();
                await loadEmployees();
                await loadModels();
                await setupCamera();
                await loadLabeledDescriptors();
                messageBox.textContent = 'Listo para reconocimiento facial ✅';
                messageBox.className = 'alert alert-success';
                startLiveRecognition();
            } catch (err) {
                console.error('Error de inicialización:', err);
                messageBox.textContent = `❌ ${err.message}`;
                messageBox.className = 'alert alert-danger';
            }
        };
    </script>
</body>

</html>
