<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semaphore Lock Demo</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Стили для адаптивной высоты без скролла */
        .screen-height-fit {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }

        .flex-grow-1 {
            flex: 1 1 0%;
            min-height: 0;
        }

        .flex-shrink-0 {
            flex-shrink: 0;
        }

        /* Маленькие квадратики */
        .task {
            width: 16px !important;
            height: 16px !important;
            font-size: 0.5rem !important;
            border-radius: 2px !important;
        }

        /* Уменьшенный шрифт в логах */
        .log-entry {
            font-size: 0.7rem !important;
            line-height: 1.2 !important;
        }

        .log-time {
            font-size: 0.65rem !important;
        }

        /* Уменьшенные легенды */
        .legend-color {
            width: 12px !important;
            height: 12px !important;
        }

        /* Стили для слотов семафора */
        .semaphore-slots-container {
            margin-top: 10px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .semaphore-slots-label {
            color: #4a5568;
            font-size: 0.8rem;
            margin-bottom: 6px;
            text-align: center;
        }

        .semaphore-slots {
            display: flex;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .semaphore-slot {
            width: 24px;
            height: 8px;
            border-radius: 2px;
            background: #e2e8f0;
            border: 1px solid #cbd5e0;
            transition: all 0.3s ease;
        }

        .semaphore-slot.active {
            background: #48bb78;
            border-color: #38a169;
            box-shadow: 0 0 4px rgba(72, 187, 120, 0.5);
        }

        .semaphore-slot.available {
            background: #90cdf4;
            border-color: #63b3ed;
        }

        /* Стили для слайдера */
        .semaphore-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .semaphore-value {
            min-width: 40px;
            text-align: center;
            font-weight: bold;
            color: #2d3748;
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        input[type="range"] {
            flex-grow: 1;
            height: 4px;
            -webkit-appearance: none;
            background: #cbd5e0;
            border-radius: 2px;
            outline: none;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: #4299e1;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        input[type="range"]::-moz-range-thumb {
            width: 18px;
            height: 18px;
            background: #4299e1;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-900 to-teal-400 screen-height-fit flex flex-col">
    <div class="flex-grow-1 overflow-hidden p-3">
        <div class="max-w-7xl mx-auto h-full flex flex-col">
            <!-- Header -->
            <div class="bg-white/95 rounded-xl shadow-xl p-4 md:p-6 mb-3 border border-gray-200 flex-shrink-0">
                <div class="text-center">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                        <span class="inline-block mr-2">🔒</span>
                        Semaphore Lock Demonstration
                    </h1>
                    <p class="text-gray-600">
                        Real-time visualization of concurrent task processing with Redis semaphores
                    </p>
                </div>
            </div>

            <!-- Основной контейнер -->
            <div class="flex-grow-1 flex flex-col min-h-0">
                <!-- Pipeline и легенда -->
                <div class="flex-grow-1 flex flex-col min-h-0">
                    <!-- Pipeline Container -->
                    <div class="bg-white/95 rounded-xl shadow-xl p-3 md:p-4 mb-3 border border-gray-200 flex-grow-1 min-h-0" style="height: 30vh;">
                        <div class="pipeline-container bg-gray-50 border border-gray-300 rounded-lg p-2 md:p-3 relative h-full" id="pipeline">
                            <!-- Section backgrounds -->
                            <div class="absolute inset-0 flex">
                                <div class="w-1/5 bg-blue-100/30"></div>
                                <div class="w-1/5 bg-orange-100/30"></div>
                                <div class="w-1/5 bg-yellow-100/30"></div>
                                <div class="w-1/5 bg-green-100/30"></div>
                                <div class="w-1/5 bg-emerald-100/30"></div>
                            </div>

                            <!-- Section dividers -->
                            <div class="section-divider left-1/5"></div>
                            <div class="section-divider left-2/5"></div>
                            <div class="section-divider left-3/5 bg-red-500"></div>
                            <div class="section-divider left-4/5"></div>

                            <!-- Main pipeline line -->
                            <div class="pipeline-line"></div>
                            <div class="pipeline-mid"></div>

                            <!-- Labels (центры секций) -->
                            <div class="pipeline-mid-label absolute top-2 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold z-15">
                                LOCK CHECK
                            </div>

                            <!-- Заголовки в центрах секций -->
                            <div class="stage-label left-[10%]">QUEUE</div>
                            <div class="stage-label left-[30%]">PROCESS</div>
                            <div class="stage-label left-[70%]">IN PROGRESS</div>
                            <div class="stage-label left-[90%]">COMPLETE</div>

                            <!-- Слоты семафора -->
                            <div class="semaphore-slots-pipeline" id="semaphoreSlotsPipeline"></div>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="bg-white/95 rounded-xl shadow-xl p-3 md:p-4 mb-3 border border-gray-200 flex-shrink-0">
                        <div class="flex flex-wrap justify-center gap-2">
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-blue"></div>
                                <span class="text-xs">Queued</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-orange"></div>
                                <span class="text-xs">Processing</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-yellow"></div>
                                <span class="text-xs">Checking Lock</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-green"></div>
                                <span class="text-xs">Lock Acquired</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="legend-color bg-semaphore-red"></div>
                                <span class="text-xs">Lock Failed</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Нижняя часть: Stats, Controls, Logs -->
                <div class="flex-grow-1 flex flex-col min-h-0">
                    <!-- Stats и Controls в одной строке -->
                    <div class="flex flex-col md:flex-row gap-3 mb-3 flex-shrink-0">
                        <!-- Stats -->
                        <div class="grid grid-cols-4 gap-2 md:gap-3 md:w-2/3">
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">ACTIVE TASKS</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="activeTasks">0</div>
                                </div>
                            </div>
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">ACTIVE LOCKS</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="activeLocks">0</div>
                                </div>
                            </div>
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">COMPLETED</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="completedTasks">0</div>
                                </div>
                            </div>
                            <div class="stat-card rounded-xl shadow-lg p-3 md:p-4">
                                <div class="text-center">
                                    <div class="text-gray-300 text-sm mb-1">MAX CONCURRENT</div>
                                    <div class="stat-value text-white text-lg md:text-xl" id="maxConcurrentValue">2</div>
                                </div>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="bg-white/95 rounded-xl shadow-xl p-3 md:p-4 border border-gray-200 md:w-1/3">
                            <!-- Управление семафором -->
                            <div class="semaphore-controls">
                                <span class="text-sm text-gray-600 whitespace-nowrap">Max Tasks:</span>
                                <input type="range" id="maxConcurrentSlider" min="1" max="10" value="2">
                                <div class="semaphore-value" id="maxConcurrentDisplay">2</div>
                            </div>

                            <!-- Визуализация слотов -->
                            <div class="semaphore-slots-container">
                                <div class="semaphore-slots-label">Semaphore Slots</div>
                                <div class="semaphore-slots" id="semaphoreSlots">
                                    <!-- Слоты будут генерироваться JS -->
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 mt-2">
                                <div class="flex gap-2">
                                    <button class="flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow hover:shadow-lg text-sm flex-1" id="createSingleBtn">
                                        <span>+</span>
                                        <span>Single Task</span>
                                    </button>
                                    <button class="flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow hover:shadow-lg text-sm flex-1" id="createThreeBtn">
                                        <span>🚀</span>
                                        <span>3 Tasks</span>
                                    </button>
                                    <button class="flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow hover:shadow-lg text-sm flex-1" id="createFiveBtn">
                                        <span>🔥</span>
                                        <span>5 Tasks</span>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <div class="mb-1 flex justify-between text-sm text-gray-600">
                                        <span>Queue Load</span>
                                        <span id="queueLoad">0%</span>
                                    </div>
                                    <div class="progress-thin bg-gray-200 rounded-full overflow-hidden h-1.5">
                                        <div class="progress-fill h-full bg-gradient-to-r from-blue-500 to-green-500 transition-all duration-300" id="queueProgress" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logs - 1/5 экрана -->
                    <div class="flex-grow-1 min-h-0" style="height: 20vh;">
                        <div class="bg-white/95 rounded-xl shadow-xl border border-gray-200 overflow-hidden h-full">
                            <div class="bg-gray-800 text-white px-4 py-2 border-b border-gray-700">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-medium">
                                        <span class="inline-block mr-2">📋</span>
                                        Event Log
                                    </h3>
                                    <span class="text-gray-400 text-sm">Live WebSocket Events</span>
                                </div>
                            </div>
                            <div class="log-panel bg-black text-green-400 p-2 overflow-y-auto h-full" id="logPanel">
                                <div class="log-entry border-b border-gray-800 pb-1 mb-1 text-xs">
                                    <span class="log-time text-blue-400">[00:00:00]</span>
                                    <span>System initialized. Waiting for tasks...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Глобальные переменные
            let tasks = {};
            let activeTasks = 0;
            let completedCount = 0;
            let pusher = null;
            let channel = null;
            const csrfToken = '{{ csrf_token() }}';

            // Настройки семафора
            let maxConcurrent = 2;

            // Проверяем что Pusher загружен
            if (typeof window.Pusher === 'undefined') {
                console.error('Pusher not loaded');
                addLog('SYSTEM', 'error', 1, 'WebSocket library not loaded');
                return;
            }

            // Инициализируем Pusher
            try {
                pusher = new window.Pusher('app-key', {
                    wsHost: window.location.hostname,
                    wsPort: 6001,
                    wssPort: 6001,
                    forceTLS: false,
                    encrypted: false,
                    disableStats: true,
                    cluster: 'mt1',
                });

                // Подписываемся на канал
                channel = pusher.subscribe('tasks');

                channel.bind('task.status.changed', function(data) {
                    handleTaskUpdate(data);
                });

            } catch (error) {
                console.error('Pusher error:', error);
                addLog('SYSTEM', 'error', 1, 'WebSocket initialization failed');
            }

            // Инициализация UI семафора
            initializeSemaphoreUI();

            // Обработчики кнопок
            document.getElementById('createSingleBtn').addEventListener('click', () => createTask(1));
            document.getElementById('createThreeBtn').addEventListener('click', () => createTask(3));
            document.getElementById('createFiveBtn').addEventListener('click', () => createTask(5));

            // Слайдер для выбора max concurrent
            const maxConcurrentSlider = document.getElementById('maxConcurrentSlider');
            const maxConcurrentDisplay = document.getElementById('maxConcurrentDisplay');
            const maxConcurrentValue = document.getElementById('maxConcurrentValue');

            maxConcurrentSlider.addEventListener('input', function() {
                maxConcurrent = parseInt(this.value);
                maxConcurrentDisplay.textContent = maxConcurrent;
                maxConcurrentValue.textContent = maxConcurrent;
                updateSemaphoreSlots();
                updateStats();
                addLog('SYSTEM', 'config', 1, `Max concurrent tasks changed to ${maxConcurrent}`);
            });

            // Функция инициализации UI семафора
            function initializeSemaphoreUI() {
                updateSemaphoreSlots();

                // Создаем слоты на пайплайне
                const slotsContainer = document.getElementById('semaphoreSlotsPipeline');

                // Очищаем контейнер
                slotsContainer.innerHTML = '';

                // Создаем линию слотов
                for (let i = 0; i < maxConcurrent; i++) {
                    const slot = document.createElement('div');
                    slot.className = 'semaphore-slot-pipeline';
                    slot.style.position = 'absolute';
                    slot.style.width = '12px';
                    slot.style.height = '12px';
                    slot.style.borderRadius = '2px';
                    slot.style.backgroundColor = '#e2e8f0';
                    slot.style.border = '1px solid #cbd5e0';
                    slot.style.bottom = '10px';
                    slot.style.left = `${50 + (i - maxConcurrent/2 + 0.5) * 3}%`;
                    slot.style.transform = 'translateX(-50%)';
                    slot.style.transition = 'all 0.3s ease';
                    slot.style.zIndex = '5';
                    slot.dataset.slotIndex = i;
                    slotsContainer.appendChild(slot);
                }

                updatePipelineSemaphoreSlots();
            }

            // Обновление слотов семафора
            function updateSemaphoreSlots() {
                const slotsContainer = document.getElementById('semaphoreSlots');
                slotsContainer.innerHTML = '';

                // Рассчитываем активные слоты
                let occupiedSlots = 0;
                for (const taskId in tasks) {
                    const task = tasks[taskId];
                    if (task.status === 'lock_acquired' || task.status === 'processing_progress') {
                        occupiedSlots++;
                    }
                }

                // Создаем слоты
                for (let i = 0; i < maxConcurrent; i++) {
                    const slot = document.createElement('div');
                    slot.className = 'semaphore-slot';

                    if (i < occupiedSlots) {
                        slot.classList.add('active');
                    } else {
                        slot.classList.add('available');
                    }

                    slotsContainer.appendChild(slot);
                }

                // Обновляем слоты на пайплайне
                updatePipelineSemaphoreSlots();
            }

            // Обновление слотов на пайплайне
            function updatePipelineSemaphoreSlots() {
                const slots = document.querySelectorAll('.semaphore-slot-pipeline');

                // Рассчитываем активные слоты
                let occupiedSlots = 0;
                for (const taskId in tasks) {
                    const task = tasks[taskId];
                    if (task.status === 'lock_acquired' || task.status === 'processing_progress') {
                        occupiedSlots++;
                    }
                }

                // Обновляем каждый слот
                slots.forEach((slot, index) => {
                    if (index < maxConcurrent) {
                        slot.style.display = 'block';
                        if (index < occupiedSlots) {
                            slot.style.backgroundColor = '#48bb78';
                            slot.style.borderColor = '#38a169';
                            slot.style.boxShadow = '0 0 4px rgba(72, 187, 120, 0.5)';
                        } else {
                            slot.style.backgroundColor = '#90cdf4';
                            slot.style.borderColor = '#63b3ed';
                            slot.style.boxShadow = 'none';
                        }
                    } else {
                        slot.style.display = 'none';
                    }
                });

                // Добавляем или удаляем слоты при изменении maxConcurrent
                const pipeline = document.getElementById('pipeline');
                const slotsContainer = document.getElementById('semaphoreSlotsPipeline');
                const currentSlots = slotsContainer.children.length;

                if (currentSlots < maxConcurrent) {
                    // Добавляем недостающие слоты
                    for (let i = currentSlots; i < maxConcurrent; i++) {
                        const slot = document.createElement('div');
                        slot.className = 'semaphore-slot-pipeline';
                        slot.style.position = 'absolute';
                        slot.style.width = '12px';
                        slot.style.height = '12px';
                        slot.style.borderRadius = '2px';
                        slot.style.backgroundColor = '#90cdf4';
                        slot.style.border = '1px solid #63b3ed';
                        slot.style.bottom = '10px';
                        slot.style.left = `${50 + (i - maxConcurrent/2 + 0.5) * 3}%`;
                        slot.style.transform = 'translateX(-50%)';
                        slot.style.transition = 'all 0.3s ease';
                        slot.style.zIndex = '5';
                        slot.dataset.slotIndex = i;
                        slotsContainer.appendChild(slot);
                    }
                } else if (currentSlots > maxConcurrent) {
                    // Удаляем лишние слоты
                    for (let i = currentSlots - 1; i >= maxConcurrent; i--) {
                        if (slotsContainer.children[i]) {
                            slotsContainer.children[i].remove();
                        }
                    }
                }
            }

            // Функция обработки обновления задачи
            function handleTaskUpdate(data) {
                const taskId = data.taskId || 'unknown-' + Date.now();
                const status = data.status || 'unknown';
                const attempt = data.attempt || 1;
                const message = data.data?.message || '';
                const progress = data.data?.progress || 0; // получаем прогресс

                addLog(taskId, status, attempt, message);

                if (!tasks[taskId]) {
                    createTaskElement(taskId, status, attempt);
                }

                updateTaskElement(taskId, status, attempt, {
                    ...data.data || {},
                    progress: progress // Передаем прогресс
                });
                updateStats();
                updateSemaphoreSlots(); // Обновляем слоты при изменении статуса
            }

            // Функция создания элемента задачи
            function createTaskElement(taskId, status = 'queued', attempt = 1) {
                const pipeline = document.getElementById('pipeline');
                if (!pipeline) return;

                const oldElement = document.getElementById(`task-${taskId}`);
                if (oldElement) oldElement.remove();

                const task = document.createElement('div');
                task.className = `task task-pulse task-${getStatusClass(status)}`;
                task.id = `task-${taskId}`;
                task.dataset.taskId = taskId;
                task.textContent = attempt; // По умолчанию номер попытки

                // Получаем секцию для текущего статуса
                const section = getSectionForStatus(status);

                // Случайная фиксированная вертикальная позиция (15-85%)
                const topPos = 15 + Math.random() * 70; // 15% - 85%

                // Фиксированная горизонтальная позиция в центре секции
                const sectionCenter = section.center;
                const horizontalJitter = Math.random() * 4 - 2; // ±2% случайное смещение
                const leftPos = sectionCenter + horizontalJitter;

                task.style.left = `${leftPos}%`;
                task.style.top = `${topPos}%`;
                task.style.transform = 'translateY(-50%)';

                pipeline.appendChild(task);

                // Сохраняем в памяти
                tasks[taskId] = {
                    element: task,
                    status: status,
                    attempt: attempt,
                    position: leftPos,
                    topPosition: topPos, // Сохраняем фиксированную позицию
                    targetPosition: section.center,
                    verticalPosition: topPos, // Фиксированная вертикальная позиция
                    horizontalPosition: leftPos, // Фиксированная горизонтальная позиция
                    currentSection: section.sectionIndex, // Текущая секция
                    progress: 0 // Начальный прогресс
                };

                activeTasks++;
            }

            // Получаем секцию для статуса
            function getSectionForStatus(status) {
                const sections = {
                    'queued': {
                        sectionIndex: 0,
                        center: 10
                    },
                    'processing': {
                        sectionIndex: 1,
                        center: 30
                    },
                    'checking_lock': {
                        sectionIndex: 2,
                        center: 50
                    },
                    'lock_acquired': {
                        sectionIndex: 3,
                        center: 70
                    },
                    'processing_progress': {
                        sectionIndex: 3,
                        center: 70
                    },
                    'lock_failed': {
                        sectionIndex: 0,
                        center: 10
                    },
                    'completed': {
                        sectionIndex: 4,
                        center: 90
                    },
                    'failed': {
                        sectionIndex: 0,
                        center: 10
                    }
                };

                return sections[status] || sections.queued;
            }

            // Получаем класс статуса
            function getStatusClass(status) {
                switch (status) {
                    case 'queued':
                        return 'queued';
                    case 'processing':
                        return 'processing';
                    case 'checking_lock':
                        return 'checking';
                    case 'lock_acquired':
                        return 'lock';
                    case 'lock_failed':
                        return 'failed';
                    case 'processing_progress':
                        return 'progress';
                    case 'completed':
                        return 'completed';
                    default:
                        return 'queued';
                }
            }

            // Обновление элемента задачи (обновлено - отображение процента)
            function updateTaskElement(taskId, status, attempt, extraData = {}) {
                const task = tasks[taskId];
                if (!task) return;

                const element = task.element;
                if (!element) return;

                const oldStatus = task.status;
                task.status = status;
                task.attempt = attempt;

                // Обновляем прогресс если есть
                if (extraData.progress !== undefined) {
                    task.progress = extraData.progress;
                }

                // Очищаем классы и добавляем новые
                element.className = 'task task-pulse';
                element.classList.add(`task-${getStatusClass(status)}`);

                // Обновляем текст в зависимости от статуса
                if (status === 'processing_progress') {
                    // Показываем процент выполнения для processing_progress
                    const progress = task.progress || 0;
                    element.textContent = `${progress}%`;
                    element.title = `Прогресс: ${progress}% (попытка ${attempt})`;
                    // Можно добавить стиль для прогресса
                    element.style.fontSize = '0.45rem'; // Чуть меньше для процентов
                } else if (status === 'lock_acquired') {
                    // Для lock_acquired показываем начало выполнения
                    element.textContent = '▶';
                    element.title = `Начало выполнения (попытка ${attempt})`;
                    element.style.fontSize = '0.5rem'; // Восстанавливаем размер
                } else if (status === 'completed') {
                    // Для завершенных задач можно показать галочку
                    element.textContent = '✓';
                    element.title = `Завершено (попытка ${attempt})`;
                    element.style.fontSize = '0.5rem';
                } else if (status === 'failed') {
                    // Для неудачных задач можно показать крестик
                    element.textContent = '✗';
                    element.title = `Ошибка (попытка ${attempt})`;
                    element.style.fontSize = '0.5rem';
                } else {
                    // Для остальных статусов показываем номер попытки
                    element.textContent = attempt;
                    element.title = `Попытка ${attempt}`;
                    element.style.fontSize = '0.5rem'; // Восстанавливаем размер
                }

                const section = getSectionForStatus(status);
                const oldSection = task.currentSection;
                task.currentSection = section.sectionIndex;

                // Фиксируем позиции задачи
                const currentTopPos = task.topPosition;
                const currentLeftPos = task.position;

                // Если задача уже в секции In Progress (lock_acquired или processing_progress)
                // И переходит в ту же секцию - не двигаем!
                const isInProgressSection = (status === 'lock_acquired' || status === 'processing_progress');
                const wasInProgressSection = (oldStatus === 'lock_acquired' || oldStatus === 'processing_progress');

                if (isInProgressSection && wasInProgressSection) {
                    // Остаемся на месте в In Progress
                    // Просто обновляем статус без движения
                    task.position = currentLeftPos; // Оставляем ту же позицию
                } else if (status === 'completed' || status === 'failed') {
                    // Для завершенных задач двигаем в конец
                    const targetPos = section.center;
                    animateHorizontalTask(element, currentLeftPos, targetPos, currentTopPos);
                    task.position = targetPos;

                    setTimeout(() => {
                        if (element.parentNode) {
                            element.parentNode.removeChild(element);
                            delete tasks[taskId];
                            activeTasks--;
                            if (status === 'completed') completedCount++;
                            updateStats();
                            updateSemaphoreSlots();
                        }
                    }, 800);
                } else if (oldSection !== section.sectionIndex) {
                    // Переход между секциями - двигаем в центр новой секции
                    const targetPos = section.center;
                    const horizontalJitter = Math.random() * 4 - 2; // ±2% случайное смещение
                    const newPos = targetPos + horizontalJitter;

                    animateHorizontalTask(element, currentLeftPos, newPos, currentTopPos);
                    task.position = newPos;
                } else {
                    // Остаемся в той же секции, но статус изменился
                    // Например: checking_lock -> lock_acquired в одной секции
                    // В этом случае немного смещаем для визуального эффекта
                    const jitter = Math.random() * 3 - 1.5; // ±1.5% случайное смещение
                    const newPos = currentLeftPos + jitter;

                    animateHorizontalTask(element, currentLeftPos, newPos, currentTopPos);
                    task.position = newPos;
                }

                setTimeout(() => {
                    element.classList.remove('task-pulse');
                }, 300);
            }

            // Анимация только горизонтального движения
            function animateHorizontalTask(element, fromLeft, toLeft, topPosition) {
                const duration = 600;
                const startTime = performance.now();

                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);

                    const easeProgress = progress < 0.5 ?
                        2 * progress * progress :
                        1 - Math.pow(-2 * progress + 2, 2) / 2;

                    const currentPos = fromLeft + (toLeft - fromLeft) * easeProgress;
                    element.style.left = `${currentPos}%`;
                    // Вертикальная позиция не меняется
                    element.style.top = `${topPosition}%`;
                    element.style.transform = 'translateY(-50%)';

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                }

                requestAnimationFrame(step);
            }

            // Обновление статистики
            function updateStats() {
                document.getElementById('activeTasks').textContent = activeTasks;
                document.getElementById('completedTasks').textContent = completedCount;

                let activeLocks = 0;
                for (const taskId in tasks) {
                    const task = tasks[taskId];
                    if (task.status === 'lock_acquired' || task.status === 'processing_progress') {
                        activeLocks++;
                    }
                }
                document.getElementById('activeLocks').textContent = activeLocks;

                const queueLoad = Math.min((activeTasks / maxConcurrent) * 100, 100);
                document.getElementById('queueProgress').style.width = `${queueLoad}%`;
                document.getElementById('queueLoad').textContent = `${Math.round(queueLoad)}%`;
            }

            // Добавление лога
            function addLog(taskId, status, attempt, message = '') {
                const logPanel = document.getElementById('logPanel');
                const time = new Date().toLocaleTimeString();
                const shortTaskId = taskId ? taskId.substring(0, 6) : 'SYS';

                const logEntry = document.createElement('div');
                logEntry.className = 'log-entry border-b border-gray-800 pb-1 mb-1 text-xs';
                logEntry.innerHTML = `
            <span class="log-time text-blue-400">[${time}]</span>
            <span class="text-yellow-300 ml-1">${shortTaskId}</span>
            <span class="text-green-400 ml-1">${status.toUpperCase()}</span>
            ${message ? `<span class="ml-1">- ${message}</span>` : ''}
        `;

                logPanel.appendChild(logEntry);
                logPanel.scrollTop = logPanel.scrollHeight;

                if (logPanel.children.length > 40) {
                    logPanel.removeChild(logPanel.firstChild);
                }
            }

            // Создание задач
            function createTask(count = 1) {
                if (!csrfToken) {
                    addLog('SYSTEM', 'error', 1, 'CSRF token not found');
                    return;
                }

                addLog('SYSTEM', 'info', 1, `Creating ${count} task(s) with max concurrent = ${maxConcurrent}`);

                fetch('/api/tasks/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            count: count,
                            max_concurrent: maxConcurrent
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        addLog('SYSTEM', 'success', 1, `${count} task(s) created successfully`);
                    })
                    .catch(error => {
                        addLog('SYSTEM', 'error', 1, 'Failed to create task');
                        console.error('Error creating task:', error);
                    });
            }

            // Инициализация
            updateStats();
            updateSemaphoreSlots();

            // Периодическое обновление статистики
            setInterval(updateStats, 2000);

            // Начальное сообщение
            setTimeout(() => {
                addLog('SYSTEM', 'info', 1, 'Ready to create tasks');
                addLog('SYSTEM', 'info', 1, `Current max concurrent tasks: ${maxConcurrent}`);
            }, 500);
        });
    </script>
</body>

</html>