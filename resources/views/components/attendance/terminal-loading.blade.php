<section id="loadingScreen" class="terminal-screen hidden" role="region" aria-label="Cargando sistema">
    <div class="screen-body">
        <div class="loading-card">
            <div class="loading-spinner">
                <div class="spinner-ring"></div>
                <div class="spinner-ring"></div>
                <div class="spinner-ring"></div>
            </div>

            <h1 class="loading-title">Cargando sistema</h1>
            <p class="loading-subtitle" id="loadingMessage">Iniciando reconocimiento facial...</p>

            <div class="loading-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="loadingProgress"></div>
                </div>
                <div class="progress-text" id="loadingPercentage">0%</div>
            </div>

            <div class="loading-steps" id="loadingSteps" aria-live="polite" aria-atomic="true">
                <div class="loading-step" id="step1">
                    <span class="step-indicator" aria-hidden="true"></span>
                    <span class="step-text">Verificando compatibilidad del navegador</span>
                </div>
                <div class="loading-step" id="step2">
                    <span class="step-indicator" aria-hidden="true"></span>
                    <span class="step-text">Cargando modelos de reconocimiento facial</span>
                </div>
                <div class="loading-step" id="step3">
                    <span class="step-indicator" aria-hidden="true"></span>
                    <span class="step-text">Preparando sistema de marcación</span>
                </div>
            </div>
        </div>
    </div>
</section>
