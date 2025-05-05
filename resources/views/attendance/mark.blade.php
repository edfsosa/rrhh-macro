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

        async function loadEmployees() {
            const res = await fetch('/api/employees');
            employees = await res.json();
        }

        async function setupCamera() {
            const video = document.getElementById('video');
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true
            });
            video.srcObject = stream;
        }

        async function loadModels() {
            await faceapi.nets.tinyFaceDetector.loadFromUri('/models');
            await faceapi.nets.faceLandmark68Net.loadFromUri('/models');
            await faceapi.nets.faceRecognitionNet.loadFromUri('/models');
        }

        async function loadLabeledDescriptors() {
            const labeledDescriptors = await Promise.all(
                employees.filter(e => e.photo).map(async employee => {
                    const img = await faceapi.fetchImage(`/storage/${employee.photo}`);
                    const detection = await faceapi
                        .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks()
                        .withFaceDescriptor();
                    return detection ?
                        new faceapi.LabeledFaceDescriptors(`${employee.id}`, [detection.descriptor]) :
                        null;
                })
            );
            faceMatcher = new faceapi.FaceMatcher(labeledDescriptors.filter(d => d), 0.6);
        }

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => currentLocation = `${position.coords.latitude},${position.coords.longitude}`,
                    error => console.warn('No se pudo obtener ubicación.')
                );
            }
        }

        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = `🕒 Hora actual: ${now.toLocaleTimeString()}`;
        }
        setInterval(updateClock, 1000);

        async function startLiveRecognition() {
            const video = document.getElementById('video');
            const overlay = document.getElementById('overlay');
            const messageBox = document.getElementById('messageBox');
            const typeSelect = document.getElementById('type');
            const successSound = document.getElementById('successSound');
            const errorSound = document.getElementById('errorSound');

            setInterval(async () => {
                if (!recognitionEnabled) return;

                const detection = await faceapi
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

                if (!detection) {
                    messageBox.textContent = 'Buscando rostro...';
                    messageBox.className = 'alert alert-warning';
                    return;
                }

                const resizedDetections = faceapi.resizeResults(detection, displaySize);
                faceapi.draw.drawDetections(overlay, resizedDetections);

                const match = faceMatcher.findBestMatch(detection.descriptor);
                if (match.label !== 'unknown') {
                    const employee = employees.find(e => e.id == match.label);
                    recognitionEnabled = false;
                    messageBox.textContent =
                        `✅ ${employee.first_name} ${employee.last_name} (${employee.ci}) reconocido. Registrando...`;
                    messageBox.className = 'alert alert-success';

                    try {
                        const response = await fetch('/marcar', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                type: typeSelect.value,
                                employee_id: match.label,
                                location: currentLocation
                            })
                        });
                        const result = await response.json();

                        if (response.ok && result.success) {
                            messageBox.textContent = '✅ Marcación registrada. Espera 5 segundos...';
                            successSound.play();
                        } else {
                            messageBox.textContent = `⚠️ ${result.message || 'Error al registrar.'}`;
                            messageBox.className = 'alert alert-warning';
                            errorSound.play();
                        }
                    } catch (error) {
                        console.error('Error al marcar:', error);
                        messageBox.textContent = '❌ Error de conexión con el servidor.';
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

        window.onload = async () => {
            getLocation();
            await loadEmployees();
            await loadModels();
            await setupCamera();
            await loadLabeledDescriptors();
            document.getElementById('messageBox').textContent = 'Listo para reconocimiento facial ✅';
            document.getElementById('messageBox').className = 'alert alert-success';
            updateClock();
            startLiveRecognition();
        };
    </script>
</body>

</html>
