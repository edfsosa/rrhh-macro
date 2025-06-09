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
            font-size: 0.8rem;
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
    <h1>Registro Biométrico</h1>

    <div class="container">
        <!-- Selección de sucursal -->
        <label for="branch">Sucursal:</label>
        <select id="branch">
            <option value="" disabled selected>Seleccione una sucursal</option>
        </select>

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
    // Variables globales
    let employees = [];
    let faceMatcher;
    let currentLocation = '';
    let recognitionEnabled = false;
    let recognitionStarted = false; // Bandera para evitar múltiples inicios

    // 0) Cargar sucursales en el <select>
    async function loadBranches() {
        const branchSelect = document.getElementById('branch');
        try {
            const response = await fetch('/api/branches');
            if (!response.ok) {
                throw new Error(`Error al cargar sucursales: ${response.statusText}`);
            }
            const branches = await response.json();
            branches.forEach(branch => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error al cargar sucursales:', error);
            alert('No se pudieron cargar las sucursales. Intente nuevamente.');
        }
    }

    // 1) Obtener ubicación
    async function requireLocation() {
        if (!navigator.geolocation) {
            throw new Error('Geolocalización no soportada.');
        }

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                ({ coords: { latitude, longitude } }) => {
                    currentLocation = `${latitude},${longitude}`;
                    resolve(currentLocation);
                },
                () => reject(new Error('Debes permitir el acceso a la ubicación para continuar.'))
            );
        });
    }

    // 2) Carga empleados
    async function loadEmployees(branchId) {
        try {
            const response = await fetch(`/api/employees?branch_id=${branchId}`);
            if (!response.ok) {
                throw new Error(`Error al cargar empleados: ${response.statusText}`);
            }
            employees = await response.json();
        } catch (error) {
            console.error('Error al cargar empleados:', error);
            throw new Error('No se pudieron cargar los empleados. Intente nuevamente.');
        }
    }

    // Manejar selección de sucursal
    document.getElementById('branch').addEventListener('change', async (event) => {
        const branchId = event.target.value;
        if (!branchId) return;

        const messageBox = document.getElementById('messageBox');
        messageBox.textContent = 'Cargando empleados…';
        messageBox.className = 'alert alert-info';

        try {
            // Pausar reconocimiento si estaba activo
            recognitionEnabled = false;
            
            await loadEmployees(branchId);
            
            // Cargar modelos si es la primera vez
            if (!recognitionStarted) {
                messageBox.textContent = 'Cargando modelos de reconocimiento…';
                await loadModels();
            }

            messageBox.textContent = 'Cargando descriptores faciales…';
            await loadLabeledDescriptors();

            // Iniciar reconocimiento solo si es la primera vez
            if (!recognitionStarted) {
                startLiveRecognition();
                recognitionStarted = true;
            }

            recognitionEnabled = true;
            messageBox.textContent = 'Sistema listo para reconocimiento ✅';
            messageBox.className = 'alert alert-success';
        } catch (error) {
            console.error('Error en selección de sucursal:', error);
            messageBox.textContent = `❌ ${error.message}`;
            messageBox.className = 'alert alert-danger';
        }
    });

    // 3) Setup cámara con permiso obligatorio
    async function setupCamera() {
        const video = document.getElementById('video');

        if (!video) {
            throw new Error('Elemento de video no encontrado.');
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;

            await new Promise(resolve => {
                video.onloadedmetadata = resolve;
            });

            video.play();
            video.style.display = 'block';
        } catch (error) {
            console.error('Error al acceder a la cámara:', error);
            throw new Error('Debes permitir el acceso a la cámara para continuar.');
        }
    }

    // 4) Carga modelos
    async function loadModels() {
        const MODEL_URL = '/models';
        const models = [
            faceapi.nets.tinyFaceDetector,
            faceapi.nets.faceLandmark68Net,
            faceapi.nets.faceRecognitionNet
        ];

        try {
            await Promise.all(models.map(net => net.loadFromUri(MODEL_URL)));
            console.log('Modelos cargados correctamente.');
        } catch (error) {
            console.error('Error al cargar los modelos:', error);
            throw new Error('No se pudieron cargar los modelos. Verifique la ruta o la conexión.');
        }
    }

    // 5) Carga descriptores
    async function loadLabeledDescriptors() {
        const descriptors = [];

        try {
            for (const emp of employees) {
                if (!emp.photo) {
                    console.warn(`Empleado ${emp.id} sin foto registrada`);
                    continue;
                }

                const img = await faceapi.fetchImage(`/storage/${emp.photo}`);
                const detection = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    descriptors.push(new faceapi.LabeledFaceDescriptors(`${emp.id}`, [detection.descriptor]));
                } else {
                    console.warn(`No se detectó rostro en foto de empleado ID: ${emp.id}`);
                }
            }

            if (descriptors.length === 0) {
                throw new Error('No se encontraron rostros válidos en los registros');
            }

            faceMatcher = new faceapi.FaceMatcher(descriptors, 0.5);
            console.log('Descriptores faciales cargados:', descriptors.length);
        } catch (error) {
            console.error('Error en carga de descriptores:', error);
            throw new Error('Error procesando rostros: ' + error.message);
        }
    }

    // Reloj
    function updateClock() {
        const clockElement = document.getElementById('clock');
        if (!clockElement) return;
        
        const now = new Date();
        clockElement.textContent = `${now.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })} | ${now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            })}`;
    }

    // 6) Reconocimiento en vivo
    async function startLiveRecognition() {
        const elements = {
            video: document.getElementById('video'),
            overlay: document.getElementById('overlay'),
            messageBox: document.getElementById('messageBox'),
            sessionSelect: document.getElementById('session'),
            typeSelect: document.getElementById('type'),
            successSound: document.getElementById('successSound'),
            errorSound: document.getElementById('errorSound'),
        };

        recognitionEnabled = true;

        const updateOverlay = (overlay, video) => {
            const displaySize = { width: video.width, height: video.height };
            faceapi.matchDimensions(overlay, displaySize);
            return displaySize;
        };

        const handleRecognitionSuccess = async (match, elements) => {
            recognitionEnabled = false;
            const emp = employees.find(e => `${e.id}` === match.label);
            
            if (!emp) {
                elements.messageBox.textContent = '❌ Empleado no registrado';
                elements.messageBox.className = 'alert alert-danger';
                elements.errorSound.play();
                setTimeout(() => recognitionEnabled = true, 3000);
                return;
            }

            elements.messageBox.textContent = `✅ ${emp.first_name} ${emp.last_name} reconocido. Registrando...`;
            elements.messageBox.className = 'alert alert-success';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const res = await fetch('/marcar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        session: elements.sessionSelect.value,
                        type: elements.typeSelect.value,
                        employee_id: match.label,
                        location: currentLocation,
                    }),
                });

                const json = await res.json();
                if (res.ok && json.success) {
                    elements.messageBox.textContent = '✅ Marcación registrada.';
                    elements.successSound.play();
                } else {
                    throw new Error(json.message || 'Error en servidor');
                }
            } catch (err) {
                elements.messageBox.textContent = `❌ ${err.message}`;
                elements.messageBox.className = 'alert alert-danger';
                elements.errorSound.play();
            }

            setTimeout(() => {
                recognitionEnabled = true;
                elements.messageBox.textContent = 'Listo para reconocimiento';
                elements.messageBox.className = 'alert alert-info';
            }, 5000);
        };

        const handleRecognitionFailure = (elements) => {
            elements.messageBox.textContent = '❌ Rostro no reconocido';
            elements.messageBox.className = 'alert alert-danger';
        };

        setInterval(async () => {
            if (!recognitionEnabled || !faceMatcher) return;

            const result = await faceapi.detectSingleFace(
                elements.video, 
                new faceapi.TinyFaceDetectorOptions()
            )
            .withFaceLandmarks()
            .withFaceDescriptor();

            const displaySize = updateOverlay(elements.overlay, elements.video);
            const ctx = elements.overlay.getContext('2d');
            ctx.clearRect(0, 0, elements.overlay.width, elements.overlay.height);

            if (!result) {
                elements.messageBox.textContent = 'Buscando rostro...';
                elements.messageBox.className = 'alert alert-warning';
                return;
            }

            const resized = faceapi.resizeResults(result, displaySize);
            faceapi.draw.drawDetections(elements.overlay, resized);

            const match = faceMatcher.findBestMatch(result.descriptor);
            if (match.label !== 'unknown') {
                await handleRecognitionSuccess(match, elements);
            } else {
                handleRecognitionFailure(elements);
            }
        }, 1500);
    }

    // Entrada principal
    window.onload = async () => {
        const messageBox = document.getElementById('messageBox');
        if (!messageBox) return;

        const updateMessageBox = (message, className) => {
            messageBox.textContent = message;
            messageBox.className = `alert ${className}`;
        };

        const enableSelects = () => {
            document.getElementById('session').disabled = false;
            document.getElementById('type').disabled = false;
        };

        const showRetryButton = () => {
            const btn = document.createElement('button');
            btn.textContent = 'Reintentar';
            btn.className = 'btn btn-primary mt-2';
            btn.onclick = () => window.location.reload();
            messageBox.appendChild(btn);
        };

        try {
            // Iniciar reloj
            updateClock();
            setInterval(updateClock, 1000);

            updateMessageBox('Obteniendo ubicación…', 'alert-info');
            await requireLocation();

            updateMessageBox('Configurando cámara…', 'alert-info');
            await setupCamera();

            updateMessageBox('Cargando sucursales…', 'alert-info');
            await loadBranches();

            updateMessageBox('Seleccione una sucursal para comenzar', 'alert-success');
            enableSelects();

        } catch (err) {
            console.error('Error en inicialización:', err);
            updateMessageBox(`❌ ${err.message}`, 'alert-danger');
            showRetryButton();
        }
    };
</script>
</body>

</html>
